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
        // 1. Add location_id column to provider_schedules (prefix applied by forge)
        if (!$this->db->fieldExists('location_id', 'provider_schedules')) {
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

            // Add FK — location deletion nullifies schedule link (non-destructive)
            if (!$this->isSQLite()) {
                $prefix = $this->db->getPrefix();
                $this->db->query("ALTER TABLE `{$prefix}provider_schedules` ADD CONSTRAINT `fk_schedule_location` FOREIGN KEY (`location_id`) REFERENCES `{$prefix}locations`(`id`) ON DELETE SET NULL ON UPDATE CASCADE");
            }
        }

        // 2. Add unique constraint on location_days to prevent duplicate day entries
        if (!$this->isSQLite()) {
            try {
                $prefix = $this->db->getPrefix();
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
        if (!$this->isSQLite()) {
            try {
                $this->db->query("ALTER TABLE `{$prefix}provider_schedules` DROP FOREIGN KEY `fk_schedule_location`");
            } catch (\Throwable $e) {
                // FK might not exist
            }
        }

        if ($this->db->fieldExists('location_id', 'provider_schedules')) {
            $this->forge->dropColumn('provider_schedules', 'location_id');
        }

        if (!$this->isSQLite()) {
            try {
                $this->db->query("ALTER TABLE `{$prefix}location_days` DROP INDEX `uq_location_day`");
            } catch (\Throwable $e) {
                // Index might not exist
            }
        }
    }
}
