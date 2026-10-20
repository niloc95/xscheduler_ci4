<?php

/**
 * =============================================================================
 * SLUG SERVICE
 * =============================================================================
 *
 * @file        app/Services/SlugService.php
 * @description Builds URL slugs for public booking pages (providers, services)
 *              and guarantees uniqueness within a table by suffixing -2, -3, …
 */

namespace App\Services;

class SlugService
{
    /**
     * Lowercase, hyphen-separated, [a-z0-9-] only.
     */
    public function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    /**
     * Resolve the slug to store: the requested slug if given, otherwise one
     * derived from $name, falling back to $fallback; then made unique in $table.
     *
     * @param string   $table     Unprefixed table name (e.g. 'users', 'services')
     * @param int|null $excludeId Row being updated (its current slug is not a clash)
     */
    public function resolve(string $table, $requestedSlug, $name, string $fallback, ?int $excludeId = null): string
    {
        $base = trim((string) $requestedSlug);
        if ($base === '') {
            $base = trim((string) $name);
        }

        $slug = $this->slugify($base);
        if ($slug === '') {
            $slug = $fallback;
        }

        return $this->ensureUnique($table, $slug, $excludeId);
    }

    /**
     * Append -2, -3, … until no other row in $table uses the slug.
     */
    public function ensureUnique(string $table, string $baseSlug, ?int $excludeId = null): string
    {
        $db = db_connect();
        $prefixedTable = $db->prefixTable($table);

        $candidate = $baseSlug;
        $suffix = 2;

        while (true) {
            $builder = $db->table($prefixedTable)->select('id')->where('slug', $candidate);
            if ($excludeId !== null) {
                $builder->where('id !=', $excludeId);
            }

            if (!$builder->get()->getRowArray()) {
                return $candidate;
            }

            $candidate = $baseSlug . '-' . $suffix;
            $suffix++;
        }
    }
}
