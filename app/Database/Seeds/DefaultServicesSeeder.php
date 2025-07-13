<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DefaultServicesSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name'         => 'General Consultation',
                'description'  => 'Standard consultation appointment',
                'duration_min' => 30,
                'price'        => 50.00,
                'created_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'Follow-up Appointment',
                'description'  => 'Follow-up appointment for existing clients',
                'duration_min' => 15,
                'price'        => 25.00,
                'created_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'Extended Session',
                'description'  => 'Extended appointment for complex cases',
                'duration_min' => 60,
                'price'        => 90.00,
                'created_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'Free Consultation',
                'description'  => 'Initial free consultation for new clients',
                'duration_min' => 20,
                'price'        => null,
                'created_at'   => date('Y-m-d H:i:s'),
            ],
        ];

        // Insert data only if services table is empty
        $existingServices = $this->db->table('services')->countAllResults();
        
        if ($existingServices === 0) {
            $this->db->table('services')->insertBatch($data);
            echo "Default services seeded successfully.\n";
        } else {
            echo "Services table already has data, skipping seeding.\n";
        }
    }
}
