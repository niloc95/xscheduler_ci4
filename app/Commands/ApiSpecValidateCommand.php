<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Router\DefinedRouteCollector;
use Config\Services;

/**
 * =============================================================================
 * API SPEC DRIFT GUARD
 * =============================================================================
 *
 * Keeps docs/technical/openapi.yml honest against the real route table. The
 * spec rotted silently once (it documented /availabilities, /appointments,
 * /services, /providers — none of which matched a real route); this command
 * exists so CI fails the moment it drifts again.
 *
 * Two checks, both hard failures:
 *   1. PHANTOM  — a path+method in the spec has no matching /api/v1 route.
 *   2. MISSING  — an /api/v1 route under a documented resource prefix is not in
 *                 the spec. Internal surfaces (dashboard, users, integrations,
 *                 settings uploads) are intentionally out of scope.
 *
 * No YAML extension or Symfony\Yaml is available, so the spec's paths are read
 * with a small indentation-aware scanner — it only needs path keys (2-space
 * indent) and their HTTP methods (4-space indent), which the spec format
 * guarantees.
 *
 * Usage: php spark api:spec:validate
 * Exit code is non-zero on drift, so it can gate CI.
 * =============================================================================
 */
class ApiSpecValidateCommand extends BaseCommand
{
    protected $group       = 'api';
    protected $name        = 'api:spec:validate';
    protected $description = 'Verify docs/technical/openapi.yml matches the real /api/v1 route table.';
    protected $usage       = 'api:spec:validate';

    /** Resource prefixes the spec is expected to document exhaustively. */
    private const DOCUMENTED_PREFIXES = [
        'appointments',
        'availability',
        'services',
        'providers',
        'customers',
        'categories',
        'business-hours',
        'locations',
    ];

    private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    private const SPEC_PATH = 'docs/technical/openapi.yml';

    public function run(array $params)
    {
        $specFile = ROOTPATH . self::SPEC_PATH;
        if (!is_file($specFile)) {
            CLI::error('Spec not found: ' . self::SPEC_PATH);
            return EXIT_ERROR;
        }

        $specKeys = $this->specKeys($specFile);          // "get api/v1/appointments/*"
        $realKeys = $this->realRouteKeys();              // same shape, from the router

        $phantom = array_values(array_diff($specKeys, $realKeys));

        $documentedReal = array_filter(
            $realKeys,
            fn(string $key): bool => $this->isDocumentedResource($key)
        );
        $missing = array_values(array_diff($documentedReal, $specKeys));

        sort($phantom);
        sort($missing);

        if ($phantom === [] && $missing === []) {
            CLI::write(CLI::color(
                'OK: ' . count($specKeys) . ' spec operations all map to real routes; '
                . count($documentedReal) . ' documented-resource routes all covered.',
                'green'
            ));
            return EXIT_SUCCESS;
        }

        if ($phantom !== []) {
            CLI::write(CLI::color('PHANTOM — in the spec but not a real /api/v1 route:', 'red'));
            foreach ($phantom as $key) {
                CLI::write('  ' . $key);
            }
        }

        if ($missing !== []) {
            CLI::write(CLI::color('MISSING — a documented-resource route absent from the spec:', 'red'));
            foreach ($missing as $key) {
                CLI::write('  ' . $key);
            }
        }

        CLI::newLine();
        CLI::error('API spec is out of sync with the routes. Update ' . self::SPEC_PATH . '.');
        return EXIT_ERROR;
    }

    /**
     * Normalized "method path" keys declared in the spec, scoped to /api/v1.
     *
     * @return array<int, string>
     */
    private function specKeys(string $file): array
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];

        $keys        = [];
        $inPaths     = false;
        $currentPath = null;

        foreach ($lines as $line) {
            // Top-level section boundaries (0-indent, non-comment).
            if (preg_match('/^(\S[^:]*):\s*$/', $line, $m)) {
                $inPaths = ($m[1] === 'paths');
                $currentPath = null;
                continue;
            }

            if (!$inPaths) {
                continue;
            }

            // Path key: exactly 2-space indent, starts with a slash.
            if (preg_match('#^  (/\S*):\s*$#', $line, $m)) {
                $currentPath = $m[1];
                continue;
            }

            // HTTP method: exactly 4-space indent under the current path.
            if ($currentPath !== null && preg_match('/^    ([a-z]+):\s*$/', $line, $m)) {
                if (in_array($m[1], self::HTTP_METHODS, true)) {
                    $keys[] = $this->key($m[1], 'api/v1' . $currentPath);
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Normalized "method path" keys for every registered /api/v1 route.
     *
     * @return array<int, string>
     */
    private function realRouteKeys(): array
    {
        $collection = Services::routes()->loadRoutes();
        $collector  = new DefinedRouteCollector($collection);

        // (No debug scaffolding — the DefinedRouteCollector is the same source
        // the framework's own `spark routes` command uses.)

        $keys = [];
        foreach ($collector->collect() as $route) {
            $verb = strtolower((string) $route['method']);
            $from = (string) $route['route'];

            if (!in_array($verb, self::HTTP_METHODS, true) || !str_starts_with($from, 'api/v1/')) {
                continue;
            }

            $keys[] = $this->key($verb, $from);
        }

        return array_values(array_unique($keys));
    }

    /**
     * True when a normalized "method api/v1/<resource>..." key belongs to a
     * documented resource prefix.
     */
    private function isDocumentedResource(string $key): bool
    {
        // key = "get api/v1/customers/*"
        $path = substr($key, strpos($key, ' ') + 1);      // api/v1/customers/*
        $rest = substr($path, strlen('api/v1/'));         // customers/*
        $head = explode('/', $rest)[0] ?? '';

        return in_array($head, self::DOCUMENTED_PREFIXES, true);
    }

    /**
     * Canonical comparison key: lower-case verb + path with every route
     * placeholder collapsed to `*` so spec `{id}` and route `([0-9]+)` match.
     */
    private function key(string $method, string $path): string
    {
        $path = preg_replace('/\([^)]*\)/', '*', $path);   // ([0-9]+), ([^/]+)
        $path = preg_replace('/\{[^}]*\}/', '*', $path);   // {id}, {slug}
        $path = preg_replace('/\(:[a-z]+\)/', '*', $path); // (:num), (:segment)
        $path = rtrim($path, '/');

        return strtolower($method) . ' ' . $path;
    }
}
