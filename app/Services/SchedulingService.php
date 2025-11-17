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
        $availabilityService = new AvailabilityService();
        return $availabilityService->getAvailableSlots($providerId, $date, $serviceId);
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

        $timezone = $payload['timezone'] ?? null;
        if (!$timezone || !TimezoneService::isValidTimezone($timezone)) {
            $timezone = (new LocalizationSettingsService())->getTimezone();
        }

        try {
            $startDateTime = new \DateTime($date . ' ' . $start, new \DateTimeZone($timezone));
        } catch (\Exception $e) {
            $startDateTime = new \DateTime($date . ' ' . $start, new \DateTimeZone('UTC'));
            $timezone = 'UTC';
        }

        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+' . $duration . ' minutes');

        // Find or create customer - using helper method
        $custModel = new CustomerModel();
        $customerId = $custModel->findOrCreateByEmail(
            $payload['email'],
            $payload['name'],
            $payload['phone'] ?? null
        );

        // Check availability
        $available = $this->getAvailabilities($providerId, $serviceId, $date);
        $isAvailable = false;
        foreach ($available as $s) {
            if ($s['start'] === $startDateTime->format('H:i') && $s['end'] === $endDateTime->format('H:i')) {
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
            'appointment_date' => $startDateTime->format('Y-m-d'),
            'appointment_time' => $startDateTime->format('H:i:s'),
            'start_time' => TimezoneService::toUTC($startDateTime->format('Y-m-d H:i:s'), $timezone),
            'end_time' => TimezoneService::toUTC($endDateTime->format('Y-m-d H:i:s'), $timezone),
            'status' => 'pending',
            'notes' => $payload['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['appointmentId' => $id];
    }
}
