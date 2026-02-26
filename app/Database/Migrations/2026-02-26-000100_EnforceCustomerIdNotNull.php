<?php

/**
 * =============================================================================
 * Migration: Enforce customer_id NOT NULL on xs_appointments
 * =============================================================================
 *
 * Audit finding A2: customer_id was declared nullable but all 3,109 rows
 * have a valid customer_id. This migration enforces the NOT NULL constraint
 * to protect data integrity going forward.
 *
 * Safe to run: confirmed 0 null rows in production data (2026-02-26).
 *
 * @package App\Database\Migrations
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class EnforceCustomerIdNotNull extends MigrationBase
{
    public function up(): void
    {
        $prefix = $this->db->getPrefix();

        if (!$this->isSQLite()) {
            // Verify no nulls before applying constraint (safety check)
            $nullCount = $this->db->query(
                "SELECT COUNT(*) as cnt FROM `{$prefix}appointments` WHERE customer_id IS NULL"
            )->getRow()->cnt ?? 0;

            if ((int) $nullCount > 0) {
                log_message('warning', '[Migration EnforceCustomerIdNotNull] Skipped: ' . $nullCount . ' null customer_id rows found. Backfill manually before re-running.');
                return;
            }

            // Drop FK to allow column redefinition, then re-add
            try {
                $this->db->query("ALTER TABLE `{$prefix}appointments` DROP FOREIGN KEY `fk_appointments_customer`");
            } catch (\Throwable $e) {
                // FK may not exist by that name — proceed
            }

            // Alter column to NOT NULL
            $this->db->query(
                "ALTER TABLE `{$prefix}appointments`
                 MODIFY COLUMN `customer_id` INT UNSIGNED NOT NULL"
            );

            // Re-add FK (customers delete → set null no longer valid; use RESTRICT)
            try {
                $this->db->query(
                    "ALTER TABLE `{$prefix}appointments`
                     ADD CONSTRAINT `fk_appointments_customer`
                     FOREIGN KEY (`customer_id`) REFERENCES `{$prefix}customers`(`id`)
                     ON DELETE RESTRICT ON UPDATE CASCADE"
                );
            } catch (\Throwable $e) {
                log_message('warning', '[Migration EnforceCustomerIdNotNull] FK re-add failed (may not exist): ' . $e->getMessage());
            }
        } else {
            // SQLite does not support ALTER COLUMN — handled structurally
            log_message('info', '[Migration EnforceCustomerIdNotNull] SQLite: constraint not enforced at DB level.');
        }
    }

    public function down(): void
    {
        $prefix = $this->db->getPrefix();

        if (!$this->isSQLite()) {
            try {
                $this->db->query("ALTER TABLE `{$prefix}appointments` DROP FOREIGN KEY `fk_appointments_customer`");
            } catch (\Throwable $e) {
                // ignore
            }

            $this->db->query(
                "ALTER TABLE `{$prefix}appointments`
                 MODIFY COLUMN `customer_id` INT UNSIGNED NULL DEFAULT NULL"
            );
        }
    }
}
