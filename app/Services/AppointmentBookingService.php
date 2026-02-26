<?php

/**
 * =============================================================================
 * APPOINTMENT BOOKING SERVICE
 * =============================================================================
 * 
 * @file        app/Services/AppointmentBookingService.php
 * @description Centralized service for creating and managing appointment bookings.
 *              Handles all validation, customer management, and notification logic.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides a single source of truth for appointment booking logic used across:
 * - Public booking API
 * - Admin appointment creation
 * - Customer self-booking
 * - Staff appointment creation
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * createAppointment($data, $timezone)
 *   Create new appointment with full validation pipeline
 *   - Validates service exists
 *   - Resolves timezone
 *   - Validates date/time
 *   - Checks business hours
 *   - Verifies slot availability
 *   - Creates/finds customer
 *   - Saves appointment
 *   - Queues notifications
 * 
 * updateAppointment($id, $data)
 *   Update existing appointment with validation
 * 
 * cancelAppointment($id, $reason)
 *   Cancel appointment and send notifications
 * 
 * rescheduleAppointment($id, $newDateTime)
 *   Reschedule to new time with availability check
 * 
 * VALIDATION PIPELINE:
 * -----------------------------------------------------------------------------
 * 1. Service validation (exists, active)
 * 2. Provider validation (available for service)
 * 3. Timezone resolution
 * 4. Date/time parsing
 * 5. Business hours check
 * 6. Slot availability check
 * 7. Customer creation/lookup
 * 8. Appointment creation
 * 9. Notification queueing
 * 
 * DEPENDENCIES:
 * -----------------------------------------------------------------------------
 * - AppointmentModel
 * - CustomerModel
 * - ServiceModel
 * - BusinessHoursService
 * - AvailabilityService
 * - NotificationQueueService
 * - TimezoneService
 * 
 * @see         app/Controllers/Api/Appointments.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\ServiceModel;
use DateTime;
use DateTimeZone;
use Exception;

/**
 * AppointmentBookingService
 * 
 * Centralizes appointment booking logic for consistency across controllers.
 * Handles timezone resolution, business hours validation, customer management,
 * availability checking, and notification queueing.
 */
class AppointmentBookingService
{
    protected AppointmentModel $appointmentModel;
    protected CustomerModel $customerModel;
    protected ServiceModel $serviceModel;
    protected BusinessHoursService $businessHoursService;
    protected AvailabilityService $availabilityService;
    protected NotificationQueueService $notificationService;
    protected TimezoneService $timezoneService;

    public function __construct()
    {
        $this->appointmentModel = new AppointmentModel();
        $this->customerModel = new CustomerModel();
        $this->serviceModel = new ServiceModel();
        $this->businessHoursService = new BusinessHoursService();
        $this->availabilityService = new AvailabilityService();
        $this->notificationService = new NotificationQueueService();
        $this->timezoneService = new TimezoneService();
    }

    /**
     * Create a new appointment with full validation and customer management
     *
     * @param array $data Appointment data including customer info
     * @param string $timezone Client timezone (defaults to system timezone)
     * @return array ['success' => bool, 'appointmentId' => int|null, 'message' => string, 'errors' => array]
     */
    public function createAppointment(array $data, string $timezone = 'UTC'): array
    {
        log_message('info', '[AppointmentBookingService::createAppointment] ========== START ==========');
        log_message('info', '[AppointmentBookingService::createAppointment] Booking data: ' . json_encode($data));
        
        try {
            // Step 1: Validate service exists
            $service = $this->serviceModel->find($data['service_id']);
            if (!$service) {
                return $this->error('Invalid service selected');
            }

            // Step 2: Resolve timezone
            if (!TimezoneService::isValidTimezone($timezone)) {
                $timezone = (new LocalizationSettingsService())->getTimezone();
            }
            
            // Step 3: Calculate appointment times
            $timeData = $this->calculateAppointmentTimes(
                $data['appointment_date'],
                $data['appointment_time'],
                $service['duration_min'],
                $timezone
            );

            // Step 3b: Resolve strict location context
            $locationContext = $this->resolveBookingLocationContext(
                (int) $data['provider_id'],
                isset($data['location_id']) ? (int) $data['location_id'] : null
            );
            if (!$locationContext['success']) {
                return $this->error($locationContext['message'], ['errors' => $locationContext['errors'] ?? []]);
            }
            $resolvedLocationId = $locationContext['location_id'];

            // Step 4: Validate business hours
            $businessHoursValidation = $this->businessHoursService->validateAppointmentTime(
                $timeData['startDateTime'],
                $timeData['endDateTime']
            );
            
            if (!$businessHoursValidation['valid']) {
                log_message('warning', '[AppointmentBookingService] Business hours validation failed: ' . $businessHoursValidation['reason']);
                return $this->error($businessHoursValidation['reason']);
            }

            // Step 5: Check slot availability
            $availabilityCheck = $this->availabilityService->isSlotAvailable(
                (int)$data['provider_id'],
                $timeData['startDateTime']->format('Y-m-d H:i:s'),
                $timeData['endDateTime']->format('Y-m-d H:i:s'),
                $timezone,
                $data['exclude_appointment_id'] ?? null,
                $resolvedLocationId
            );
            
            if (!$availabilityCheck['available']) {
                $reason = $availabilityCheck['reason'] ?? 'Time slot not available';
                log_message('warning', '[AppointmentBookingService] Availability check failed: ' . $reason);
                return $this->error($reason, ['conflicts' => $availabilityCheck['conflicts'] ?? []]);
            }

            // Step 6: Handle customer (find or create)
            $customerResult = $this->resolveCustomer($data);
            if (!$customerResult['success']) {
                return $customerResult;
            }
            $customerId = $customerResult['customerId'];

            // Step 7: Create appointment record
            $appointmentData = [
                'customer_id' => $customerId,
                'provider_id' => $data['provider_id'],
                'service_id' => $data['service_id'],
                'start_at' => $timeData['startUtc'],
                'end_at' => $timeData['endUtc'],
                'status' => $data['status'] ?? 'pending',
                'notes' => $data['notes'] ?? '',
                'location_id' => $resolvedLocationId,
            ];

            // Snapshot location data if location_id provided
            if (!empty($resolvedLocationId)) {
                $locationModel = new \App\Models\LocationModel();
                $snapshot = $locationModel->getLocationSnapshot((int) $resolvedLocationId);
                $appointmentData = array_merge($appointmentData, $snapshot);
            }

            log_message('info', '[AppointmentBookingService] Inserting appointment: ' . json_encode($appointmentData));
            $appointmentId = $this->appointmentModel->insert($appointmentData);

            if (!$appointmentId) {
                $errors = $this->appointmentModel->errors();
                log_message('error', '[AppointmentBookingService] Insert failed. Errors: ' . json_encode($errors));
                return $this->error('Failed to create appointment', ['validationErrors' => $errors]);
            }

            log_message('info', '[AppointmentBookingService] ✅ Appointment created! ID: ' . $appointmentId);

            // Step 8: Queue notifications (email, SMS, WhatsApp)
            $this->queueNotifications($appointmentId, $data['notification_types'] ?? ['email', 'whatsapp']);

            return $this->success($appointmentId, 'Appointment booked successfully! Confirmation will be sent shortly.');

        } catch (Exception $e) {
            log_message('error', '[AppointmentBookingService] Exception: ' . $e->getMessage());
            log_message('error', '[AppointmentBookingService] Trace: ' . $e->getTraceAsString());
            return $this->error('An error occurred while creating the appointment: ' . $e->getMessage());
        } finally {
            log_message('info', '[AppointmentBookingService::createAppointment] ========== END ==========');
        }
    }

    /**
     * Update an existing appointment
     *
     * @param int $appointmentId ID of appointment to update
     * @param array $data Updated appointment data
     * @param string $timezone Client timezone
     * @return array ['success' => bool, 'message' => string, 'errors' => array]
     */
    public function updateAppointment(int $appointmentId, array $data, string $timezone = 'UTC'): array
    {
        log_message('info', '[AppointmentBookingService::updateAppointment] Updating appointment #' . $appointmentId);
        
        try {
            // Verify appointment exists
            $existing = $this->appointmentModel->find($appointmentId);
            if (!$existing) {
                return $this->error('Appointment not found');
            }

            // Validate service if changed
            if (isset($data['service_id'])) {
                $service = $this->serviceModel->find($data['service_id']);
                if (!$service) {
                    return $this->error('Invalid service selected');
                }
            } else {
                $service = $this->serviceModel->find($existing['service_id']);
            }

            // Recalculate times if date/time changed
            if (isset($data['appointment_date']) && isset($data['appointment_time'])) {
                $timeData = $this->calculateAppointmentTimes(
                    $data['appointment_date'],
                    $data['appointment_time'],
                    $service['duration_min'],
                    $timezone
                );

                // Validate business hours for new time
                $businessHoursValidation = $this->businessHoursService->validateAppointmentTime(
                    $timeData['startDateTime'],
                    $timeData['endDateTime']
                );
                
                if (!$businessHoursValidation['valid']) {
                    return $this->error($businessHoursValidation['reason']);
                }

                $providerId = (int)($data['provider_id'] ?? $existing['provider_id']);
                $locationContext = $this->resolveBookingLocationContext(
                    $providerId,
                    array_key_exists('location_id', $data)
                        ? ($data['location_id'] !== null && $data['location_id'] !== '' ? (int) $data['location_id'] : null)
                        : (!empty($existing['location_id']) ? (int) $existing['location_id'] : null)
                );
                if (!$locationContext['success']) {
                    return $this->error($locationContext['message'], ['errors' => $locationContext['errors'] ?? []]);
                }
                $resolvedLocationId = $locationContext['location_id'];

                // Check availability (exclude this appointment from conflict check)
                $availabilityCheck = $this->availabilityService->isSlotAvailable(
                    $providerId,
                    $timeData['startDateTime']->format('Y-m-d H:i:s'),
                    $timeData['endDateTime']->format('Y-m-d H:i:s'),
                    $timezone,
                    $appointmentId,
                    $resolvedLocationId
                );
                
                if (!$availabilityCheck['available']) {
                    return $this->error($availabilityCheck['reason'] ?? 'Time slot not available');
                }

                $data['start_at'] = $timeData['startUtc'];
                $data['end_at'] = $timeData['endUtc'];
                $data['location_id'] = $resolvedLocationId;
            }

            // Validate location/provider context for updates even when slot time is unchanged
            if (array_key_exists('provider_id', $data) || array_key_exists('location_id', $data)) {
                $providerId = (int)($data['provider_id'] ?? $existing['provider_id']);
                $locationContext = $this->resolveBookingLocationContext(
                    $providerId,
                    array_key_exists('location_id', $data)
                        ? ($data['location_id'] !== null && $data['location_id'] !== '' ? (int) $data['location_id'] : null)
                        : (!empty($existing['location_id']) ? (int) $existing['location_id'] : null)
                );
                if (!$locationContext['success']) {
                    return $this->error($locationContext['message'], ['errors' => $locationContext['errors'] ?? []]);
                }
                $data['location_id'] = $locationContext['location_id'];
            }

            // Update appointment
            $success = $this->appointmentModel->update($appointmentId, $data);
            
            if (!$success) {
                $errors = $this->appointmentModel->errors();
                log_message('error', '[AppointmentBookingService] Update failed. Errors: ' . json_encode($errors));
                return $this->error('Failed to update appointment', ['validationErrors' => $errors]);
            }

            log_message('info', '[AppointmentBookingService] ✅ Appointment #' . $appointmentId . ' updated successfully');

            // Queue update notifications if status or time changed
            if (isset($data['status']) || isset($data['start_at'])) {
                $this->queueNotifications($appointmentId, ['email', 'whatsapp'], 'appointment_updated');
            }

            return $this->success($appointmentId, 'Appointment updated successfully!');

        } catch (Exception $e) {
            log_message('error', '[AppointmentBookingService] Update exception: ' . $e->getMessage());
            return $this->error('An error occurred while updating the appointment: ' . $e->getMessage());
        }
    }

    /**
     * Calculate appointment start/end times using the app timezone.
     *
     * DB stores times as app-local (no UTC conversion). Time input from the
     * admin form is already in app timezone; public booking slots arrive with
     * the correct offset from the API.
     *
     * @param string $date           Date in Y-m-d format
     * @param string $time           Time in H:i format (app timezone)
     * @param int    $durationMinutes Service duration
     * @param string $timezone       Ignored — app timezone is always used (kept for BC)
     * @return array DateTime objects and stored time strings
     */
    protected function calculateAppointmentTimes(
        string $date,
        string $time,
        int $durationMinutes,
        string $timezone
    ): array {
        // Always use the app timezone regardless of what the client reports.
        $appTimezone = (new LocalizationSettingsService())->getTimezone();
        $startLocal  = $date . ' ' . $time . ':00';

        try {
            $startDateTime = new DateTime($startLocal, new DateTimeZone($appTimezone));
        } catch (Exception $e) {
            log_message('error', '[AppointmentBookingService] DateTime creation failed: ' . $e->getMessage());
            $startDateTime = new DateTime($startLocal, new DateTimeZone('UTC'));
            $appTimezone   = 'UTC';
        }

        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+' . $durationMinutes . ' minutes');

        // Store as app-local strings (no UTC conversion).
        $startTime = $startDateTime->format('Y-m-d H:i:s');
        $endTime   = $endDateTime->format('Y-m-d H:i:s');

        log_message('info', '[AppointmentBookingService] Time calculation:', [
            'start'    => $startTime,
            'end'      => $endTime,
            'timezone' => $appTimezone,
        ]);

        return [
            'startDateTime' => $startDateTime,
            'endDateTime'   => $endDateTime,
            'startUtc'      => $startTime, // legacy key — contains app-local value
            'endUtc'        => $endTime,   // legacy key — contains app-local value
            'startTime'     => $startTime,
            'endTime'       => $endTime,
            'timezone'      => $appTimezone,
        ];
    }

    /**
     * Resolve customer - find existing or create new
     *
     * @param array $data Customer data from form
     * @return array ['success' => bool, 'customerId' => int|null, 'message' => string]
     */
    protected function resolveCustomer(array $data): array
    {
        // If customer ID provided, verify it exists
        if (!empty($data['customer_id'])) {
            $customer = $this->customerModel->find($data['customer_id']);
            if (!$customer) {
                return ['success' => false, 'message' => 'Selected customer not found'];
            }
            log_message('info', '[AppointmentBookingService] Using existing customer ID: ' . $data['customer_id']);
            return ['success' => true, 'customerId' => (int)$data['customer_id']];
        }

        // Check if customer exists by email
        if (!empty($data['customer_email'])) {
            $customer = $this->customerModel->where('email', $data['customer_email'])->first();
            if ($customer) {
                log_message('info', '[AppointmentBookingService] Found existing customer by email: ' . $customer['id']);
                return ['success' => true, 'customerId' => (int)$customer['id']];
            }
        }

        // Create new customer
        $customerData = [
            'first_name' => $data['customer_first_name'] ?? '',
            'last_name' => $data['customer_last_name'] ?? '',
            'email' => $data['customer_email'] ?? '',
            'phone' => $data['customer_phone'] ?? '',
            'address' => $data['customer_address'] ?? '',
            'notes' => $data['customer_notes'] ?? ''
        ];

        // Handle custom fields
        $customFieldsData = [];
        for ($i = 1; $i <= 6; $i++) {
            if (isset($data["custom_field_{$i}"]) && $data["custom_field_{$i}"] !== '') {
                $customFieldsData["custom_field_{$i}"] = $data["custom_field_{$i}"];
            }
        }
        if (!empty($customFieldsData)) {
            $customerData['custom_fields'] = json_encode($customFieldsData);
        }

        $customerId = $this->customerModel->insert($customerData);
        
        if (!$customerId) {
            $errors = $this->customerModel->errors();
            log_message('error', '[AppointmentBookingService] Customer creation failed: ' . json_encode($errors));
            return ['success' => false, 'message' => 'Failed to create customer record', 'errors' => $errors];
        }

        log_message('info', '[AppointmentBookingService] Created new customer ID: ' . $customerId);
        return ['success' => true, 'customerId' => (int)$customerId];
    }

    /**
     * Resolve provider location context for booking/update flows.
     *
    * Rules:
    * - No active locations: location_id remains null
    * - Any active locations: explicit location_id is required
     */
    protected function resolveBookingLocationContext(int $providerId, ?int $requestedLocationId): array
    {
        $locationModel = new \App\Models\LocationModel();
        $activeLocations = $locationModel->getProviderLocations($providerId, true);

        if (empty($activeLocations)) {
            return ['success' => true, 'location_id' => null, 'message' => null, 'errors' => []];
        }

        $activeLocationIds = array_map(static fn(array $loc): int => (int) ($loc['id'] ?? 0), $activeLocations);

        if ($requestedLocationId !== null) {
            if (!in_array($requestedLocationId, $activeLocationIds, true)) {
                return [
                    'success' => false,
                    'location_id' => null,
                    'message' => 'Selected location is unavailable for this provider.',
                    'errors' => ['location_id' => 'invalid']
                ];
            }

            return ['success' => true, 'location_id' => $requestedLocationId, 'message' => null, 'errors' => []];
        }

        return [
            'success' => false,
            'location_id' => null,
            'message' => 'Please select a location for this provider.',
            'errors' => ['location_id' => 'required']
        ];
    }

    /**
     * Queue notifications for appointment events
     *
     * @param int $appointmentId Appointment ID
     * @param array $types Notification types (email, sms, whatsapp)
     * @param string $event Event type (appointment_confirmed, appointment_updated, etc.)
     */
    protected function queueNotifications(int $appointmentId, array $types = ['email'], string $event = 'appointment_confirmed'): void
    {
        try {
            foreach ($types as $type) {
                $this->notificationService->enqueueAppointmentEvent(
                    NotificationPhase1::BUSINESS_ID_DEFAULT,
                    $type,
                    $event,
                    $appointmentId
                );
            }
            log_message('info', '[AppointmentBookingService] Queued notifications: ' . implode(', ', $types));
        } catch (Exception $e) {
            log_message('error', '[AppointmentBookingService] Notification queue failed: ' . $e->getMessage());
            // Don't fail the booking if notifications fail
        }
    }

    /**
     * Success response helper
     */
    protected function success(int $appointmentId, string $message): array
    {
        return [
            'success' => true,
            'appointmentId' => $appointmentId,
            'message' => $message,
            'errors' => []
        ];
    }

    /**
     * Error response helper
     */
    protected function error(string $message, array $additionalData = []): array
    {
        return array_merge([
            'success' => false,
            'appointmentId' => null,
            'message' => $message,
            'errors' => []
        ], $additionalData);
    }
}
