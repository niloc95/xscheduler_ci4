<?php

namespace App\Services;

use App\Libraries\SlotGenerator;
use App\Models\AppointmentModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Models\CustomerModel;

class SchedulingService
{
    public function getAvailabilities(int $providerId, int $serviceId, string $date): array
    {
        $slotGen = new SlotGenerator();
        return $slotGen->getAvailableSlots($providerId, $serviceId, $date);
    }

    public function createAppointment(array $payload): array
    {
        $required = ['name','email','providerId','serviceId','date','start'];
        foreach ($required as $r) {
            if (empty($payload[$r])) {
                throw new \InvalidArgumentException("Missing field: $r");
            }
        }

        $providerId = (int)$payload['providerId'];
        $serviceId  = (int)$payload['serviceId'];
        $date       = $payload['date'];
        $start      = $payload['start'];

        $service = (new ServiceModel())->find($serviceId);
        if (!$service) {
            throw new \RuntimeException('Service not found');
        }
        $duration = (int)($service['duration_min'] ?? 30);

        $startDT = strtotime($date . ' ' . $start);
        $endDT   = $startDT + ($duration * 60);

        // Upsert customer in xs_customers
        $custModel = new CustomerModel();
        $customer = $custModel->where('email', $payload['email'])->first();
        if (!$customer) {
            $names = preg_split('/\s+/', trim((string)$payload['name']));
            $first = $names[0] ?? '';
            $last  = count($names) > 1 ? trim(implode(' ', array_slice($names, 1))) : null;
            $customerId = $custModel->insert([
                'first_name' => $first,
                'last_name'  => $last,
                'email'      => $payload['email'],
                'phone'      => $payload['phone'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ], false);
        } else {
            $customerId = $customer['id'];
        }

        // Check availability
        $available = $this->getAvailabilities($providerId, $serviceId, $date);
        $isAvailable = false;
        foreach ($available as $s) {
            if ($s['start'] === date('H:i', $startDT) && $s['end'] === date('H:i', $endDT)) {
                $isAvailable = true; break;
            }
        }
        if (!$isAvailable) {
            throw new \RuntimeException('Time slot no longer available');
        }

        // Create appointment
        $apptModel = new AppointmentModel();
        $id = $apptModel->insert([
            'customer_id' => $customerId,
            // Maintain NOT NULL user_id by pointing to provider (system user)
            'user_id' => $providerId,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'start_time' => date('Y-m-d H:i:s', $startDT),
            'end_time' => date('Y-m-d H:i:s', $endDT),
            'status' => 'booked',
            'notes' => $payload['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['appointmentId' => $id];
    }
}
