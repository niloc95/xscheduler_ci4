<?php

/**
 * @file    MigrationBase.php
 * @brief   Shared migration helper base class
 *
 * Provides shared helpers for project migrations running on MySQL/MariaDB.
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
 * Shared migration helper base.
 *
 * Provides typed properties for IDEs / static analysers and helper methods used
 * across the project's migrations.
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

    /**
     * Normalise Forge field definitions before they are passed to Forge.
     * The original array is not mutated.
     *
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    protected function sanitiseFields(array $fields): array
    {
        $sanitised = [];

        foreach ($fields as $name => $definition) {
            $sanitised[$name] = $definition;
        }

        return $sanitised;
    }

    /**
    * Check whether an index exists on a table.
     *
     * @param string $table  Table name (unprefixed — prefix is applied automatically)
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        $prefixed = $this->db->prefixTable($table);

        $row = $this->db->query(
            "SHOW INDEX FROM `{$prefixed}` WHERE Key_name = ?",
            [$indexName]
        )->getFirstRow();

        return $row !== null;
    }

    /**
    * Create an index if it does not already exist.
     *
     * @param string $table  Table name (unprefixed — prefix is applied automatically)
     */
    protected function createIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        foreach ($columns as $column) {
            if (!$this->db->fieldExists($column, $table)) {
                log_message('warning', sprintf(
                    'Skipping index %s on %s because column %s does not exist.',
                    $indexName,
                    $table,
                    $column
                ));

                return;
            }
        }

        $prefixed   = $this->db->prefixTable($table);
        $columnList = implode(', ', array_map(
            static fn(string $col) => "`{$col}`",
            $columns
        ));

        try {
            $this->db->query("CREATE INDEX `{$indexName}` ON `{$prefixed}` ({$columnList})");
        } catch (\Throwable $exception) {
            log_message('warning', sprintf(
                'Skipping index %s on %s: %s',
                $indexName,
                $table,
                $exception->getMessage()
            ));
        }
    }

    /**
    * Drop an index if it exists.
     *
     * @param string $table  Table name (unprefixed — prefix is applied automatically)
     */
    protected function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        $prefixed = $this->db->prefixTable($table);
        $this->db->query("ALTER TABLE `{$prefixed}` DROP INDEX `{$indexName}`");
    }

    /**
     * Execute a raw SQL statement on MySQL/MariaDB only.
     */
    protected function mysqlOnly(string $sql): void
    {
        $this->db->query($sql);
    }

    /**
     * Modify an ENUM column definition.
     *
     * @param string $table  Unprefixed table name
     * @param array  $fields Field definitions (will be passed through sanitiseFields)
     */
    protected function modifyEnumColumn(string $table, array $fields): void
    {
        $this->forge->modifyColumn($table, $this->sanitiseFields($fields));
    }
}
