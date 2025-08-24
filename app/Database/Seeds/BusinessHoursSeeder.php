<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class BusinessHoursSeeder extends Seeder
{
    public function run()
    {
        $builder = $this->db->table('business_hours');
        // Default 9-17 Mon-Fri for provider 1
        for ($w = 1; $w <= 5; $w++) {
            $builder->insert([
                'provider_id' => 1,
                'weekday' => $w,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'breaks_json' => json_encode([
                    ['start' => '12:00', 'end' => '13:00']
                ]),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
