<?php

namespace App\Services;

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

        // Check availability using proper validation method
        $availabilityService = new AvailabilityService();
        $startTimeLocal = $startDateTime->format('Y-m-d H:i:s');
        $endTimeLocal = $endDateTime->format('Y-m-d H:i:s');
        
        $availabilityCheck = $availabilityService->isSlotAvailable(
            $providerId,
            $startTimeLocal,
            $endTimeLocal,
            $timezone
        );
        
        if (!$availabilityCheck['available']) {
            $reason = $availabilityCheck['reason'] ?? 'Time slot not available';
            log_message('warning', '[SchedulingService::createAppointment] Slot unavailable: ' . $reason);
            throw new \RuntimeException('Time slot no longer available. ' . $reason);
        }

        // Create appointment
        // NOTE: Database stores times in LOCAL timezone (not UTC)
        $apptModel = new AppointmentModel();
        $id = $apptModel->insert([
            'customer_id' => $customerId,
            // Maintain NOT NULL user_id by pointing to provider (system user)
            'user_id' => $providerId,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'appointment_date' => $startDateTime->format('Y-m-d'),
            'appointment_time' => $startDateTime->format('H:i:s'),
            'start_time' => $startTimeLocal,  // Store in local timezone
            'end_time' => $endTimeLocal,      // Store in local timezone
            'status' => 'pending',
            'notes' => $payload['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        log_message('info', '[SchedulingService::createAppointment] Created appointment #' . $id . ' for provider ' . $providerId . ' at ' . $startTimeLocal);
        
        return ['appointmentId' => $id];
    }
}
