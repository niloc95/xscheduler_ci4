<?php

/**
 * =============================================================================
 * Migration: Add calendar rebuild feature flag
 * =============================================================================
 *
 * Adds a boolean setting to control access to /api/calendar/* endpoints
 * during the rebuild process.
 *
 * Key:
 * - calendar.rebuild_enabled : true/false
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddCalendarRebuildFlag extends MigrationBase
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $exists = $this->db->table('settings')
            ->where('setting_key', 'calendar.rebuild_enabled')
            ->countAllResults();

        if (!$exists) {
            $this->db->table('settings')->insert([
                'setting_key'   => 'calendar.rebuild_enabled',
                'setting_value' => 'true',
                'setting_type'  => 'boolean',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('settings')
            ->where('setting_key', 'calendar.rebuild_enabled')
            ->delete();
    }
}
