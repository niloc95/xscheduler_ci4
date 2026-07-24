<?php

namespace App\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * API configuration.
 *
 * Authentication is deliberately NOT configured here. External clients
 * authenticate with per-user Bearer tokens stored in `xs_api_keys` — issued,
 * rotated and revoked with `php spark api:key`. There is no shared secret in
 * this file and no shared secret in the repository.
 *
 * @see app/Models/ApiKeyModel.php
 * @see app/Filters/ApiAuthFilter.php
 * @see app/Commands/ApiKeyCommand.php
 */
class Api extends BaseConfig
{
    /**
     * CORS configuration for API endpoints.
     *
     * `allowedOrigins` is empty by default, meaning same-origin only: no
     * `Access-Control-Allow-Origin` header is emitted for cross-origin callers.
     * Add trusted origins via the `api.allowedOrigins` env var (comma-separated
     * scheme + host, e.g. `https://app.example.com,https://admin.example.com`).
     *
     * A wildcard origin is not supported. `/api/*` is CSRF-exempt (see
     * app/Config/Filters.php), so a permissive origin policy combined with
     * cookie auth would be a live CSRF hole.
     */
    public array $cors = [
        'allowedOrigins' => [],
        'allowedMethods' => ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
        'allowedHeaders' => ['Content-Type','Authorization','Accept','X-Requested-With'],
        'exposedHeaders' => [],
        'maxAge' => 600,
        'allowCredentials' => false,
    ];

    /**
     * Origins permitted to make cross-origin API requests.
     *
     * Merges the configured list with the `api.allowedOrigins` env var. A `*`
     * entry is stripped rather than honoured.
     *
     * @return array<int, string>
     */
    public function allowedOrigins(): array
    {
        $origins = $this->cors['allowedOrigins'] ?? [];

        $env = env('api.allowedOrigins');
        if (is_string($env) && trim($env) !== '') {
            foreach (explode(',', $env) as $origin) {
                $origins[] = trim($origin);
            }
        }

        return array_values(array_unique(array_filter(
            $origins,
            static fn($origin): bool => is_string($origin) && $origin !== '' && $origin !== '*'
        )));
    }
}
