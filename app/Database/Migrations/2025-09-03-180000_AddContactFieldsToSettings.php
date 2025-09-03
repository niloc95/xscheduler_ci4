<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddContactFieldsToSettings extends Migration
{
    public function up()
    {
        // Ensure settings table exists
        if (!$this->db->tableExists('settings')) {
            return;
        }

        $builder = $this->db->table('settings');

        // Default values for the new contact fields
        $newSettings = [
            [
                'setting_key' => 'general.telephone_number',
                'setting_value' => '',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'general.mobile_number',
                'setting_value' => '',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'setting_key' => 'general.business_address',
                'setting_value' => '',
                'setting_type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        // Insert each setting if it doesn't already exist
        foreach ($newSettings as $setting) {
            $exists = $builder->where('setting_key', $setting['setting_key'])->countAllResults() > 0;
            if (!$exists) {
                $builder->insert($setting);
            }
        }
    }

    public function down()
    {
        // Remove the settings we added
        if ($this->db->tableExists('settings')) {
            $builder = $this->db->table('settings');
            $builder->whereIn('setting_key', [
                'general.telephone_number',
                'general.mobile_number',
                'general.business_address'
            ])->delete();
        }
    }
}
