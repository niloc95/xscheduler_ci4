<?php

/**
 * @file    MigrationBase.php
 * @brief   Cross-database compatible migration base class
 *
 * Provides automatic SQLite compatibility for migrations originally written for
 * MySQL. Strips MySQL-only syntax (UNSIGNED, ENUM, AFTER, LONGBLOB) when the
 * active database driver is SQLite3.
 *
 * All project migrations should extend this class instead of the framework
 * Migration class directly.
 *
 * @package App\Database
 */

namespace App\Database;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;

/**
 * Cross-database compatible migration base.
 *
 * Provides typed properties for IDEs / static analysers and automatic
 * field-definition sanitisation when the active driver is SQLite3.
 *
 * @property BaseConnection $db
 * @property Forge          $forge
 */
abstract class MigrationBase extends Migration
{
    /** @var BaseConnection */
    protected $db;

    /** @var Forge */
    protected $forge;

    // ------------------------------------------------------------------
    //  SQLite compatibility helpers
    // ------------------------------------------------------------------

    /**
     * Check whether the current database driver is SQLite.
     */
    protected function isSQLite(): bool
    {
        return $this->db->DBDriver === 'SQLite3';
    }

    /**
     * Sanitise an array of Forge field definitions so they are compatible
     * with SQLite.  The original array is not mutated.
     *
     * Transformations applied when the driver is SQLite3:
     *  - Removes `unsigned`   (SQLite has no UNSIGNED modifier)
     *  - Removes `after`      (SQLite does not support column positioning)
     *  - Converts `ENUM` →    `VARCHAR(255)` with the default preserved
     *  - Converts `LONGBLOB`  → `BLOB`
     *  - Converts `JSON`      → `TEXT`
     *
     * When the driver is NOT SQLite the fields are returned unchanged.
     *
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    protected function sanitiseFields(array $fields): array
    {
        if (!$this->isSQLite()) {
            return $fields;
        }

        $sanitised = [];

        foreach ($fields as $name => $definition) {
            $def = $definition;

            // Strip UNSIGNED — not supported by SQLite
            unset($def['unsigned']);

            // Strip AFTER — SQLite ignores column ordering
            unset($def['after']);

            // Convert ENUM → VARCHAR(255)
            $type = strtoupper($def['type'] ?? '');
            if ($type === 'ENUM') {
                $def['type']       = 'VARCHAR';
                $def['constraint'] = 255;
            }

            // Convert LONGBLOB → BLOB
            if ($type === 'LONGBLOB') {
                $def['type'] = 'BLOB';
            }

            // Convert JSON → TEXT (SQLite stores JSON as text)
            if ($type === 'JSON') {
                $def['type'] = 'TEXT';
            }

            $sanitised[$name] = $def;
        }

        return $sanitised;
    }

    /**
     * Check whether an index exists on a table (cross-database).
     *
     * @param string $table  Table name (unprefixed — prefix is applied automatically)
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        $prefixed = $this->db->prefixTable($table);

        if ($this->isSQLite()) {
            $row = $this->db->query(
                "SELECT name FROM sqlite_master WHERE type='index' AND name=?",
                [$indexName]
            )->getFirstRow();

            return $row !== null;
        }

        $row = $this->db->query(
            "SHOW INDEX FROM `{$prefixed}` WHERE Key_name = ?",
            [$indexName]
        )->getFirstRow();

        return $row !== null;
    }

    /**
     * Create an index if it does not already exist (cross-database).
     *
     * @param string $table  Table name (unprefixed — prefix is applied automatically)
     */
    protected function createIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $prefixed   = $this->db->prefixTable($table);
        $columnList = implode(', ', array_map(
            static fn(string $col) => "`{$col}`",
            $columns
        ));

        $this->db->query("CREATE INDEX `{$indexName}` ON `{$prefixed}` ({$columnList})");
    }

    /**
     * Drop an index if it exists (cross-database).
     *
     * @param string $table  Table name (unprefixed — prefix is applied automatically)
     */
    protected function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        if ($this->isSQLite()) {
            $this->db->query("DROP INDEX `{$indexName}`");
        } else {
            $prefixed = $this->db->prefixTable($table);
            $this->db->query("ALTER TABLE `{$prefixed}` DROP INDEX `{$indexName}`");
        }
    }

    /**
     * Execute a raw SQL statement only when the driver is MySQL.
     *
     * Useful for ALTER TABLE ... ADD CONSTRAINT, MODIFY COLUMN, etc.
     * that have no SQLite equivalent.
     */
    protected function mysqlOnly(string $sql): void
    {
        if (!$this->isSQLite()) {
            $this->db->query($sql);
        }
    }

    /**
     * Modify an ENUM column definition safely across databases.
     *
     * On SQLite, ENUM columns are stored as VARCHAR(255), so modifyColumn
     * to change ENUM constraint values is meaningless AND crashes due to
     * CI4's internal table recreation conflicting with auto-index names.
     * This helper skips the operation on SQLite entirely.
     *
     * On MySQL, it runs $this->forge->modifyColumn() with sanitised fields
     * as normal.
     *
     * @param string $table  Unprefixed table name
     * @param array  $fields Field definitions (will be passed through sanitiseFields)
     */
    protected function modifyEnumColumn(string $table, array $fields): void
    {
        if ($this->isSQLite()) {
            return; // ENUM is VARCHAR(255) on SQLite — nothing to change
        }

        $this->forge->modifyColumn($table, $this->sanitiseFields($fields));
    }
}
