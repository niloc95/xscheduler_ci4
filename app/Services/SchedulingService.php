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

        $booking = new AppointmentBookingService();

        $bookingPayload = [
            'provider_id' => (int) $payload['providerId'],
            'service_id' => (int) $payload['serviceId'],
            'appointment_date' => $payload['date'],
            'appointment_time' => $payload['start'],
            'customer_first_name' => $payload['name'],
            'customer_last_name' => '',
            'customer_email' => $payload['email'],
            'customer_phone' => $payload['phone'] ?? '',
            'notes' => $payload['notes'] ?? null,
            'notification_types' => ['email', 'whatsapp'],
        ];

        if (!empty($payload['location_id'])) {
            $bookingPayload['location_id'] = (int) $payload['location_id'];
        }

        $timezone = $payload['timezone'] ?? null;
        $result = $booking->createAppointment($bookingPayload, $timezone ?: 'UTC');
        if (!$result['success']) {
            throw new \RuntimeException($result['message'] ?? 'Failed to create appointment');
        }

        return ['appointmentId' => $result['appointmentId']];
    }
}
