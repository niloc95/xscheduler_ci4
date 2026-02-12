<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddBookingFieldSettings extends MigrationBase
{
    public function up()
    {
        // Ensure settings table exists
        try {
            $this->db->table('settings')->countAllResults();
        } catch (\Exception $e) {
            // Settings table doesn't exist, skip migration
            return;
        }

        $builder = $this->db->table('settings');

        // Booking field settings
        $newSettings = [
            // Name field configuration
            [
                'setting_key' => 'booking.first_names_display',
                'setting_value' => '1',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'booking.first_names_required',
                'setting_value' => '1',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'booking.surname_display',
                'setting_value' => '1',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'booking.surname_required',
                'setting_value' => '1',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            // Standard contact fields
            [
                'setting_key' => 'booking.email_display',
                'setting_value' => '1',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'booking.email_required',
                'setting_value' => '1',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'booking.phone_display',
                'setting_value' => '1',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'booking.phone_required',
                'setting_value' => '0',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'booking.address_display',
                'setting_value' => '0',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'booking.address_required',
                'setting_value' => '0',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'booking.notes_display',
                'setting_value' => '1',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'booking.notes_required',
                'setting_value' => '0',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        // Add custom field settings (6 fields with enabled, title, type, required)
        for ($i = 1; $i <= 6; $i++) {
            $newSettings[] = [
                'setting_key' => "booking.custom_field_{$i}_enabled",
                'setting_value' => '0',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $newSettings[] = [
                'setting_key' => "booking.custom_field_{$i}_title",
                'setting_value' => "",
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $newSettings[] = [
                'setting_key' => "booking.custom_field_{$i}_type",
                'setting_value' => 'text',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $newSettings[] = [
                'setting_key' => "booking.custom_field_{$i}_required",
                'setting_value' => '0',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        // Add standard booking fields setting if it doesn't exist
        $newSettings[] = [
            'setting_key' => 'booking.fields',
            'setting_value' => '["email","phone"]',
            'setting_type' => 'json',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Insert settings only if they don't already exist
        foreach ($newSettings as $setting) {
            $existing = $builder->where('setting_key', $setting['setting_key'])->get()->getRow();
            if (!$existing) {
                $builder->insert($setting);
            }
        }
    }

    public function down()
    {
        // Remove the settings we added
        try {
            $builder = $this->db->table('settings');
            
            $keysToRemove = [
                'booking.first_names_display',
                'booking.first_names_required',
                'booking.surname_display',
                'booking.surname_required',
                'booking.email_display',
                'booking.email_required',
                'booking.phone_display',
                'booking.phone_required',
                'booking.address_display',
                'booking.address_required',
                'booking.notes_display',
                'booking.notes_required',
            ];

            // Add custom field keys
            for ($i = 1; $i <= 6; $i++) {
                $keysToRemove[] = "booking.custom_field_{$i}_enabled";
                $keysToRemove[] = "booking.custom_field_{$i}_title";
                $keysToRemove[] = "booking.custom_field_{$i}_type";
                $keysToRemove[] = "booking.custom_field_{$i}_required";
            }

            $builder->whereIn('setting_key', $keysToRemove)->delete();
        } catch (\Exception $e) {
            // Settings table doesn't exist, ignore
        }
    }
}