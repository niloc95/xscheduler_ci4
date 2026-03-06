<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AvailabilityIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $providerEmail = 'ci-availability-provider@test.local';
        $provider = $this->db->table('users')->where('email', $providerEmail)->get()->getRowArray();

        if ($provider) {
            $providerId = (int) $provider['id'];
        } else {
            $this->db->table('users')->insert([
                'name' => 'CI Availability Provider',
                'email' => $providerEmail,
                'password_hash' => password_hash('ci-test-password', PASSWORD_DEFAULT),
                'role' => 'provider',
                'is_active' => 1,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $providerId = (int) $this->db->insertID();
        }

        $serviceName = 'CI Availability Service';
        $service = $this->db->table('services')->where('name', $serviceName)->get()->getRowArray();

        if (!$service) {
            $this->db->table('services')->insert([
                'name' => $serviceName,
                'description' => 'Availability integration baseline service',
                'category_id' => null,
                'duration_min' => 30,
                'price' => 120.00,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $locationName = 'CI Availability Primary Location';
        $location = $this->db->table('locations')
            ->where('provider_id', $providerId)
            ->where('name', $locationName)
            ->get()
            ->getRowArray();

        if ($location) {
            $locationId = (int) $location['id'];
        } else {
            $this->db->table('locations')->insert([
                'provider_id' => $providerId,
                'name' => $locationName,
                'address' => 'CI Integration Address',
                'contact_number' => '0000000000',
                'is_primary' => 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $locationId = (int) $this->db->insertID();
        }

        $existingSchedule = $this->db->table('provider_schedules')
            ->where('provider_id', $providerId)
            ->where('location_id', $locationId)
            ->where('day_of_week', 'tuesday')
            ->get()
            ->getRowArray();

        if (!$existingSchedule) {
            $this->db->table('provider_schedules')->insert([
                'provider_id' => $providerId,
                'location_id' => $locationId,
                'day_of_week' => 'tuesday',
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'break_start' => null,
                'break_end' => null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
