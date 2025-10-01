<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class UpdateLocalizationSettings extends MigrationBase
{
    public function up()
    {
        // Ensure settings table exists
        if (!$this->db->tableExists('settings')) {
            return;
        }

        $builder = $this->db->table('settings');

        // Add currency setting with South African Rand as default
        $currencyExists = $builder->where('setting_key', 'localization.currency')->countAllResults() > 0;
        if (!$currencyExists) {
            $builder->insert([
                'setting_key' => 'localization.currency',
                'setting_value' => 'ZAR',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Update timezone default to South Africa (Africa/Johannesburg)
        $timezoneExists = $builder->where('setting_key', 'localization.timezone')->countAllResults() > 0;
        if (!$timezoneExists) {
            // Insert with South African timezone as default
            $builder->insert([
                'setting_key' => 'localization.timezone',
                'setting_value' => 'Africa/Johannesburg',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            // Update existing timezone if it's empty or set to a generic value
            $currentTimezone = $builder->where('setting_key', 'localization.timezone')->get()->getRow();
            if ($currentTimezone && (empty($currentTimezone->setting_value) || $currentTimezone->setting_value === 'UTC')) {
                $builder->where('setting_key', 'localization.timezone')
                        ->update([
                            'setting_value' => 'Africa/Johannesburg',
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
            }
        }

        // Optionally remove date_format setting as it's no longer used
        // (keeping this optional to prevent data loss)
        // $builder->where('setting_key', 'localization.date_format')->delete();
    }

    public function down()
    {
        // Remove the currency setting
        if ($this->db->tableExists('settings')) {
            $builder = $this->db->table('settings');
            $builder->where('setting_key', 'localization.currency')->delete();
            
            // Restore date_format setting if needed
            $dateFormatExists = $builder->where('setting_key', 'localization.date_format')->countAllResults() > 0;
            if (!$dateFormatExists) {
                $builder->insert([
                    'setting_key' => 'localization.date_format',
                    'setting_value' => 'DMY',
                    'setting_type' => 'string',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
