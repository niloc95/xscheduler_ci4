<?php

/**
 * =============================================================================
 * SCHEDULING SERVICE
 * =============================================================================
 * 
 * @file        app/Services/SchedulingService.php
 * @description High-level service for appointment booking operations.
 *              Orchestrates availability checks and appointment creation.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides booking workflow orchestration:
 * - Fetch available time slots for booking UI
 * - Validate and create new appointments
 * - Handle customer creation/lookup during booking
 * - Coordinate notifications after booking
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - getAvailabilities(providerId, serviceId, date)
 *   Get available slots for booking selection
 * 
 * - createAppointment(payload)
 *   Full booking workflow: validate, create customer, create appointment
 * 
 * - rescheduleAppointment(appointmentId, newStart, newEnd)
 *   Move appointment to new time slot
 * 
 * BOOKING PAYLOAD:
 * -----------------------------------------------------------------------------
 * {
 *   "name": "John Doe",
 *   "email": "john@example.com",
 *   "phone": "+27 82 555 1234",
 *   "providerId": 2,
 *   "serviceId": 1,
 *   "date": "2025-01-15",
 *   "start": "09:00",
 *   "notes": "First visit",
 *   "timezone": "Africa/Johannesburg"
 * }
 * 
 * WORKFLOW:
 * -----------------------------------------------------------------------------
 * 1. Validate required fields
 * 2. Lookup/create customer
 * 3. Calculate end time from service duration
 * 4. Verify slot is still available
 * 5. Create appointment record
 * 6. Trigger confirmation notification
 * 7. Return appointment details
 * 
 * @see         app/Services/AvailabilityService.php for availability
 * @see         app/Controllers/Appointments.php for controller layer
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

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
        $startLocal = $startDateTime->format('Y-m-d H:i:s');
        $endLocal = $endDateTime->format('Y-m-d H:i:s');
        
        $availabilityCheck = $availabilityService->isSlotAvailable(
            $providerId,
            $startLocal,
            $endLocal,
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
            'start_time' => $startLocal,  // Store in local timezone
            'end_time' => $endLocal,      // Store in local timezone
            'status' => 'pending',
            'notes' => $payload['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        log_message('info', '[SchedulingService::createAppointment] Created appointment #' . $id . ' for provider ' . $providerId . ' at ' . $startLocal);

        // Phase 5: enqueue notifications (dispatch handled by cron via notifications:dispatch-queue)
        try {
            $queue = new NotificationQueueService();
            $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT;
            $queue->enqueueAppointmentEvent($businessId, 'email', 'appointment_confirmed', (int) $id);
            $queue->enqueueAppointmentEvent($businessId, 'whatsapp', 'appointment_confirmed', (int) $id);
        } catch (\Throwable $e) {
            log_message('error', '[SchedulingService::createAppointment] Notification enqueue failed: {msg}', ['msg' => $e->getMessage()]);
        }
        
        return ['appointmentId' => $id];
    }
}
