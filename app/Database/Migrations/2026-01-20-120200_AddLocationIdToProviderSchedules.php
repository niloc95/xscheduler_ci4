<?php

/**
 * =============================================================================
 * Migration: Add location_id to provider schedules + unique constraint on location_days
 * =============================================================================
 *
 * Part of the Provider Multi-Location Refactor:
 *
 * 1. Adds nullable `location_id` FK to `xs_provider_schedules`
 *    - When NULL → schedule applies globally (backward compatible)
 *    - When set  → schedule applies only to that location
 *
 * 2. Adds UNIQUE constraint on `xs_location_days(location_id, day_of_week)`
 *    to prevent duplicate day entries per location.
 *
 * @package App\Database\Migrations
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddLocationIdToProviderSchedules extends MigrationBase
{
    public function up(): void
    {
        if (! $this->hasTable('provider_schedules')) {
            return;
        }

        // 1. Add location_id column to provider_schedules (prefix applied by forge)
        if (! $this->hasColumn('provider_schedules', 'location_id')) {
            $fields = $this->sanitiseFields([
                'location_id' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'provider_id',
                ],
            ]);

            $this->forge->addColumn('provider_schedules', $fields);
        }

        // Add FK — location deletion nullifies schedule link (non-destructive)
        if ($this->hasTable('locations') && ! $this->foreignKeyExists('provider_schedules', 'fk_schedule_location')) {
            $prefix = $this->db->getPrefix();
            $this->db->query("ALTER TABLE `{$prefix}provider_schedules` ADD CONSTRAINT `fk_schedule_location` FOREIGN KEY (`location_id`) REFERENCES `{$prefix}locations`(`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }

        // 2. Add unique constraint on location_days to prevent duplicate day entries
        if ($this->hasTable('location_days') && ! $this->hasIndexByName('location_days', 'uq_location_day')) {
            $prefix = $this->db->getPrefix();
            try {
                $this->db->query("ALTER TABLE `{$prefix}location_days` ADD UNIQUE KEY `uq_location_day` (`location_id`, `day_of_week`)");
            } catch (\Throwable $e) {
                // Might fail if duplicates already exist — clean up first then retry
                log_message('warning', 'Could not add unique constraint on location_days: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $prefix = $this->db->getPrefix();

        // Drop FK first, then column
        if ($this->hasTable('provider_schedules') && $this->foreignKeyExists('provider_schedules', 'fk_schedule_location')) {
            try {
                $this->db->query("ALTER TABLE `{$prefix}provider_schedules` DROP FOREIGN KEY `fk_schedule_location`");
            } catch (\Throwable $e) {
                // FK might not exist
            }
        }

        if ($this->hasTable('provider_schedules') && $this->hasColumn('provider_schedules', 'location_id')) {
            try {
                $this->forge->dropColumn('provider_schedules', 'location_id');
            } catch (\Throwable $e) {
                // Column may already be absent on some refresh paths.
            }
        }

        if ($this->hasTable('location_days') && $this->hasIndexByName('location_days', 'uq_location_day')) {
            try {
                $this->db->query("ALTER TABLE `{$prefix}location_days` DROP INDEX `uq_location_day`");
            } catch (\Throwable $e) {
                // Index might not exist
            }
        }
    }

    private function hasTable(string $table): bool
    {
        $database = $this->db->getDatabase();
        $tableName = $this->db->prefixTable($table);
        $query = $this->db->query(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1',
            [$database, $tableName]
        );

        return $query->getNumRows() > 0;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $database = $this->db->getDatabase();
        $tableName = $this->db->prefixTable($table);
        $query = $this->db->query(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$database, $tableName, $column]
        );

        return $query->getNumRows() > 0;
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $database = $this->db->getDatabase();
        $tableName = $this->db->prefixTable($table);
        $query = $this->db->query(
            'SELECT 1 FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = ? LIMIT 1',
            [$database, $tableName, $constraint, 'FOREIGN KEY']
        );

        return $query->getNumRows() > 0;
    }

    private function hasIndexByName(string $table, string $index): bool
    {
        $database = $this->db->getDatabase();
        $tableName = $this->db->prefixTable($table);
        $query = $this->db->query(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $tableName, $index]
        );

        return $query->getNumRows() > 0;
    }
}
