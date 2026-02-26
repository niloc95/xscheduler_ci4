<?php

/**
 * =============================================================================
 * Migration: Add missing calendar & booking setting keys
 * =============================================================================
 *
 * Audit finding (Phase 0 / M003):
 * Several scheduling settings were identified as missing from xs_settings.
 * These are required by the new CalendarRangeService, DayViewService, and
 * WeekViewService to function without defaults.
 *
 * Keys added:
 * - booking.time_resolution       : Slot grid interval in minutes (30)
 * - booking.cancellation_window   : Hours before appointment when cancel allowed (24)
 * - booking.reschedule_window     : Hours before appointment when reschedule allowed (24)
 * - calendar.default_view         : Which view opens first (week)
 * - calendar.day_start            : Earliest hour on grid (08:00)
 * - calendar.day_end              : Latest hour on grid (18:00)
 * - general.timezone_storage      : How datetimes are stored: 'local' or 'utc'
 *
 * @package App\Database\Migrations
 */

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddCalendarSchedulingSettings extends MigrationBase
{
    /**
     * Default settings to add (only inserted if key does not already exist)
     */
    private array $settings = [
        [
            'key'   => 'booking.time_resolution',
            'value' => '30',
            'type'  => 'integer',
        ],
        [
            'key'   => 'booking.cancellation_window',
            'value' => '24',
            'type'  => 'integer',
        ],
        [
            'key'   => 'booking.reschedule_window',
            'value' => '24',
            'type'  => 'integer',
        ],
        [
            'key'   => 'calendar.default_view',
            'value' => 'week',
            'type'  => 'string',
        ],
        [
            'key'   => 'calendar.day_start',
            'value' => '08:00',
            'type'  => 'string',
        ],
        [
            'key'   => 'calendar.day_end',
            'value' => '18:00',
            'type'  => 'string',
        ],
        [
            'key'   => 'general.timezone_storage',
            'value' => 'local',
            'type'  => 'string',
        ],
    ];

    public function up(): void
    {
        $prefix  = $this->db->getPrefix();
        $now     = date('Y-m-d H:i:s');

        foreach ($this->settings as $setting) {
            // Only insert if the key does not already exist
            $exists = $this->db
                ->table('settings')
                ->where('setting_key', $setting['key'])
                ->countAllResults();

            if (!$exists) {
                $this->db->table('settings')->insert([
                    'setting_key'   => $setting['key'],
                    'setting_value' => $setting['value'],
                    'setting_type'  => $setting['type'],
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->settings as $setting) {
            $this->db->table('settings')
                ->where('setting_key', $setting['key'])
                ->delete();
        }
    }
}
