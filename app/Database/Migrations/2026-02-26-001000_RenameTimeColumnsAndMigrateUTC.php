<?php

/**
 * =============================================================================
 * MIGRATION: Rename time columns & migrate data to UTC
 * =============================================================================
 *
 * Step 1 of the calendar rebuild foundation:
 *
 *   1. Rename xs_appointments.start_time  → start_at
 *   2. Rename xs_appointments.end_time    → end_at
 *   3. Shift all existing datetime values from Africa/Johannesburg (UTC+2) to UTC
 *   4. Add stored_timezone column (VARCHAR 40, default 'UTC')
 *   5. Rebuild composite indexes for the new column names
 *
 * This migration is IRREVERSIBLE in production because it modifies existing
 * data.  The down() method reverses the schema changes but does NOT shift
 * the data back — run that only in dev / test environments.
 *
 * @package App\Database\Migrations
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class RenameTimeColumnsAndMigrateUTC extends MigrationBase
{
    // Africa/Johannesburg = UTC+2 (no DST changes historically for SAST)
    private const OFFSET_HOURS = 2;

    public function up()
    {
        $table   = 'appointments';             // unprefixed — helpers add prefix
        $prefix  = $this->db->getPrefix();       // 'xs_'
        $full    = $prefix . 'appointments';       // 'xs_appointments'

        if (! $this->db->tableExists($table)) {
            log_message('warning', "[Migration] Table {$table} does not exist — skipping.");
            return;
        }

        // -----------------------------------------------------------------
        // 1. Rename columns: start_time → start_at, end_time → end_at
        // -----------------------------------------------------------------
        $this->db->query("ALTER TABLE {$full} CHANGE COLUMN start_time start_at DATETIME NOT NULL");
        $this->db->query("ALTER TABLE {$full} CHANGE COLUMN end_time   end_at   DATETIME NOT NULL");

        // -----------------------------------------------------------------
        // 2. Shift existing data from Africa/Johannesburg → UTC
        //    SAST is always UTC+2 (no DST), so subtract 2 hours.
        // -----------------------------------------------------------------
        $hours = self::OFFSET_HOURS;
        $this->db->query("UPDATE {$full} SET start_at = DATE_SUB(start_at, INTERVAL {$hours} HOUR)");
        $this->db->query("UPDATE {$full} SET end_at   = DATE_SUB(end_at,   INTERVAL {$hours} HOUR)");

        // -----------------------------------------------------------------
        // 3. Add stored_timezone column
        // -----------------------------------------------------------------
        $fields = $this->sanitiseFields([
            'stored_timezone' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => false,
                'default'    => 'UTC',
                'after'      => 'end_at',
            ],
        ]);

        $this->forge->addColumn($table, $fields);

        // -----------------------------------------------------------------
        // 4. Ensure the additional composite index exists with renamed columns
        // -----------------------------------------------------------------
        $this->createIndexIfMissing($table, 'idx_appts_start_end',      ['start_at',    'end_at']);

        log_message('info', "[Migration] Renamed start_time→start_at, end_time→end_at; data shifted to UTC.");
    }

    public function down()
    {
        $table  = 'appointments';              // unprefixed
        $prefix = $this->db->getPrefix();
        $full   = $prefix . 'appointments';

        if (! $this->db->tableExists($table)) {
            return;
        }

        // Drop new index added by this migration
        $this->dropIndexIfExists($table, 'idx_appts_start_end');

        // Drop stored_timezone column
        if ($this->db->fieldExists('stored_timezone', $table)) {
            $this->forge->dropColumn($table, 'stored_timezone');
        }

        // Rename columns back
        $this->db->query("ALTER TABLE {$full} CHANGE COLUMN start_at start_time DATETIME NOT NULL");
        $this->db->query("ALTER TABLE {$full} CHANGE COLUMN end_at   end_time   DATETIME NOT NULL");

        // NOTE: Data is NOT shifted back to local time — this is a dev-only rollback.

        log_message('info', "[Migration] Reverted start_at→start_time, end_at→end_time.");
    }
}
