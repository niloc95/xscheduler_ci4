<?php

/**
 * =============================================================================
 * Migration: Add default appointment status setting
 * =============================================================================
 *
 * Adds booking.default_appointment_status to xs_settings so that business
 * owners can choose whether newly created appointments default to 'pending'
 * (requires confirmation) or 'confirmed' (auto-approve).
 *
 * Applies to all creation paths:
 *   - Admin/staff appointment form
 *   - Public booking flow
 *   - API direct creation
 *
 * Default value is 'pending' — preserves existing behaviour for all current
 * deployments with zero behavioural change on migration.
 *
 * @package App\Database\Migrations
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddDefaultAppointmentStatusSetting extends MigrationBase
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $exists = $this->db
            ->table('settings')
            ->where('setting_key', 'booking.default_appointment_status')
            ->countAllResults();

        if (!$exists) {
            $this->db->table('settings')->insert([
                'setting_key'   => 'booking.default_appointment_status',
                'setting_value' => 'pending',
                'setting_type'  => 'string',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('settings')
            ->where('setting_key', 'booking.default_appointment_status')
            ->delete();
    }
}
