<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * =============================================================================
 * BLOCKED TIMES — UTC STANDARDISATION
 * =============================================================================
 *
 * Aligns xs_blocked_times with the rest of the scheduling schema:
 *
 *  1. Renames start_time  → start_at
 *             end_time    → end_at
 *
 *  2. Converts existing rows from the stored business timezone
 *     (Africa/Johannesburg, UTC+2) to UTC.
 *     MySQL CONVERT_TZ is used so the conversion is always correct,
 *     even if the server's system timezone changes later.
 *
 *  3. Drops six redundant/duplicate indexes on xs_appointments that
 *     are superseded by the composite indexes already in place.
 *
 * ROLLBACK:  reverses column renames and re-converts UTC → local.
 *            Redundant indexes are NOT recreated on rollback (they are
 *            genuinely unused).
 * =============================================================================
 */
class BlockedTimesUtc extends MigrationBase
{
    // The business timezone that was used when the rows were inserted.
    // Change this only if the DB rows were stored in a different zone.
    private const LEGACY_TZ = 'Africa/Johannesburg'; // UTC+2, no DST

    // Redundant / superseded indexes to remove from xs_appointments.
    // Each entry is: [ index_name, is_unique ]
    private const REDUNDANT_INDEXES = [
        // Single-column start_at — named after the OLD column, superseded by
        // idx_appts_start_provider, idx_appts_start_status, etc.
        ['start_time',               false],
        // Single-column provider_id — superseded by idx_appts_provider_start
        // (provider_id, start_at)
        ['provider_id',              false],
        // Single-column service_id — superseded by idx_appts_service_start
        // (service_id, start_at)
        ['service_id',               false],
        // (start_at, end_at) — exact duplicate of idx_appts_start_end
        ['idx_start_end_time',       false],
        // Single-column status — superseded by idx_status_start
        ['status',                   false],
        // Single-column customer_id — superseded by idx_appts_customer_start
        ['idx_appointments_customer_id', false],
    ];

    // ─────────────────────────────────────────────────────────────────
    // UP
    // ─────────────────────────────────────────────────────────────────

    public function up(): void
    {
        $prefix = $this->db->getPrefix(); // 'xs_'
        $bt     = $prefix . 'blocked_times';
        $appts  = $prefix . 'appointments';

        // ── 1. Rename columns ─────────────────────────────────────────
        $this->db->query("ALTER TABLE `{$bt}`
            CHANGE `start_time` `start_at` DATETIME NOT NULL,
            CHANGE `end_time`   `end_at`   DATETIME NOT NULL");

        // ── 2. Convert existing data from local TZ → UTC ──────────────
        // CONVERT_TZ requires the mysql.time_zone_name tables to be loaded.
        // If not available, fall back to a fixed-offset subtraction.
        $testConvert = $this->db->query(
            "SELECT CONVERT_TZ('2000-01-01 00:00:00', '" . self::LEGACY_TZ . "', 'UTC') AS r"
        )->getRow();

        if ($testConvert && $testConvert->r !== null) {
            // Named TZ tables available — use the proper conversion.
            $this->db->query("UPDATE `{$bt}` SET
                `start_at` = CONVERT_TZ(`start_at`, '" . self::LEGACY_TZ . "', 'UTC'),
                `end_at`   = CONVERT_TZ(`end_at`,   '" . self::LEGACY_TZ . "', 'UTC')");
        } else {
            // Fallback: subtract fixed UTC+2 offset (safe for Africa/Johannesburg
            // which has never observed DST since 1943).
            $this->db->query("UPDATE `{$bt}` SET
                `start_at` = DATE_SUB(`start_at`, INTERVAL 2 HOUR),
                `end_at`   = DATE_SUB(`end_at`,   INTERVAL 2 HOUR)");
        }

        // ── 3. Rename the old start_time index on blocked_times ───────
        // The original migration added KEY start_time (start_time).
        // After the column rename, MySQL keeps the index but it references
        // the new column name; we rename it for clarity.
        try {
            $this->db->query("ALTER TABLE `{$bt}`
                RENAME INDEX `start_time` TO `idx_blocked_start_at`");
        } catch (\Throwable $e) {
            // Index may already have been renamed or may not exist; skip.
            log_message('warning', '[Migration BlockedTimesUtc] Could not rename blocked_times index: ' . $e->getMessage());
        }

        // ── 4. Drop redundant indexes on xs_appointments ─────────────
        foreach (self::REDUNDANT_INDEXES as [$name, $_]) {
            try {
                $this->db->query("ALTER TABLE `{$appts}` DROP INDEX `{$name}`");
            } catch (\Throwable $e) {
                // Index may not exist in some environments; log and continue.
                log_message('warning', "[Migration BlockedTimesUtc] Could not drop index {$name}: " . $e->getMessage());
            }
        }

        // ── 5. Update timezone_storage setting to 'utc' ───────────────
        $this->db->query("UPDATE `{$prefix}settings`
            SET setting_value = 'utc', updated_at = NOW()
            WHERE setting_key = 'general.timezone_storage'");
    }

    // ─────────────────────────────────────────────────────────────────
    // DOWN
    // ─────────────────────────────────────────────────────────────────

    public function down(): void
    {
        $prefix = $this->db->getPrefix();
        $bt     = $prefix . 'blocked_times';

        // Convert back UTC → local tz
        $testConvert = $this->db->query(
            "SELECT CONVERT_TZ('2000-01-01 00:00:00', 'UTC', '" . self::LEGACY_TZ . "') AS r"
        )->getRow();

        if ($testConvert && $testConvert->r !== null) {
            $this->db->query("UPDATE `{$bt}` SET
                `start_at` = CONVERT_TZ(`start_at`, 'UTC', '" . self::LEGACY_TZ . "'),
                `end_at`   = CONVERT_TZ(`end_at`,   'UTC', '" . self::LEGACY_TZ . "')");
        } else {
            $this->db->query("UPDATE `{$bt}` SET
                `start_at` = DATE_ADD(`start_at`, INTERVAL 2 HOUR),
                `end_at`   = DATE_ADD(`end_at`,   INTERVAL 2 HOUR)");
        }

        // Rename columns back
        $this->db->query("ALTER TABLE `{$bt}`
            CHANGE `start_at` `start_time` DATETIME NOT NULL,
            CHANGE `end_at`   `end_time`   DATETIME NOT NULL");

        // Restore timezone_storage setting
        $this->db->query("UPDATE `{$prefix}settings`
            SET setting_value = 'local', updated_at = NOW()
            WHERE setting_key = 'general.timezone_storage'");

        // Note: redundant indexes on xs_appointments are NOT restored as they
        // were genuinely unused/duplicated.
    }
}
