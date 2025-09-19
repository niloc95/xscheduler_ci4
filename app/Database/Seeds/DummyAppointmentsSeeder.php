<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DummyAppointmentsSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();

        // Fetch candidate users, providers, services
        $users = $db->table('users')->select('id')->where('is_active', 1)->get()->getResultArray();
        $providers = $db->table('users')->select('id')->where('role', 'provider')->where('is_active', 1)->get()->getResultArray();
        $services = $db->table('services')->select('id')->where('active', 1)->get()->getResultArray();

        if (empty($users) || empty($providers) || empty($services)) {
            echo "Need at least one user, one provider and one service to seed appointments.\n";
            return;
        }

        $userIds = array_map(fn($r) => (int)$r['id'], $users);
        $providerIds = array_map(fn($r) => (int)$r['id'], $providers);
        $serviceIds = array_map(fn($r) => (int)$r['id'], $services);

        $startDate = new \DateTime();
        $endDate = (clone $startDate)->modify('+6 months');

        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate);
        $inserted = 0;

        foreach ($period as $day) {
            // Create 3 appointments spread through the day
            $slots = [9, 13, 16]; // 9am, 1pm, 4pm
            foreach ($slots as $hour) {
                $serviceId = $serviceIds[array_rand($serviceIds)];
                $providerId = $providerIds[array_rand($providerIds)];
                $userId = $userIds[array_rand($userIds)];

                // Assume service duration default 60 minutes; if service has duration, fetch it
                $service = $db->table('services')->select('duration_min')->where('id', $serviceId)->get()->getRowArray();
                $duration = $service['duration_min'] ?? 60;

                $start = (clone $day)->setTime($hour, 0, 0);
                $end = (clone $start)->modify("+{$duration} minutes");

                $data = [
                    'user_id' => $userId,
                    'provider_id' => $providerId,
                    'service_id' => $serviceId,
                    'start_time' => $start->format('Y-m-d H:i:s'),
                    'end_time' => $end->format('Y-m-d H:i:s'),
                    'status' => 'booked',
                    'notes' => 'Dummy appointment',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                $db->table('appointments')->insert($data);
                $inserted++;
            }
        }

        echo "Inserted {$inserted} dummy appointments.\n";
    }
}
