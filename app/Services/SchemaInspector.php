<?php

/**
 * =============================================================================
 * SCHEMA INSPECTOR
 * =============================================================================
 *
 * @file        app/Services/SchemaInspector.php
 * @description Cached "does this column exist?" probe for schema-compatibility
 *              branches in runtime code (models/services that must work across
 *              installs that have not yet run every migration).
 *
 * Accepts the table name with or without the DB prefix (`users`, `xs_users`)
 * and never probes a double-prefixed name. Results are cached per connection
 * for the life of the process. Migrations keep their own self-contained checks.
 */

namespace App\Services;

class SchemaInspector
{
    /** @var array<string, bool> */
    private static array $cache = [];

    /**
     * @param object|null $db Connection to probe (defaults to the shared connection).
     */
    public static function hasColumn(string $table, string $column, ?object $db = null): bool
    {
        $db ??= db_connect();

        $key = spl_object_id($db) . ':' . $table . '.' . $column;
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        return self::$cache[$key] = self::probe($db, $table, $column);
    }

    /**
     * Drop cached results (tests that alter the schema mid-run).
     */
    public static function reset(): void
    {
        self::$cache = [];
    }

    private static function probe(object $db, string $table, string $column): bool
    {
        if (!method_exists($db, 'getFieldData')) {
            return true;
        }

        $prefix = method_exists($db, 'getPrefix') ? (string) $db->getPrefix() : '';
        $unprefixed = ($prefix !== '' && str_starts_with($table, $prefix))
            ? substr($table, strlen($prefix))
            : $table;

        $candidates = array_values(array_unique(array_filter([
            $table,
            method_exists($db, 'prefixTable') ? (string) $db->prefixTable($unprefixed) : null,
            $unprefixed,
        ])));

        foreach ($candidates as $candidate) {
            try {
                foreach ($db->getFieldData($candidate) as $field) {
                    if ((string) ($field->name ?? '') === $column) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                // Try the next candidate table name.
            }
        }

        return false;
    }
}
