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
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Services;

use App\Models\AuditLogModel;
use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Models\ServiceModel;
use App\Services\Appointment\AppointmentStatus;
use App\Services\NotificationCatalog;
use App\Services\NotificationQueueDispatcher;
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
    protected TimezoneService $timezoneService;
    protected LocalizationSettingsService $localizationSettingsService;
    protected \App\Models\LocationModel $locationModel;
    protected AppointmentEventService $appointmentEventService;
    protected AuditLogModel $auditLogModel;
    protected PhoneNumberService $phoneNumberService;

    public function __construct(
        ?AppointmentModel $appointmentModel = null,
        ?CustomerModel $customerModel = null,
        ?ServiceModel $serviceModel = null,
        ?BusinessHoursService $businessHoursService = null,
        ?AvailabilityService $availabilityService = null,
        ?TimezoneService $timezoneService = null,
        ?LocalizationSettingsService $localizationSettingsService = null,
        ?\App\Models\LocationModel $locationModel = null,
        ?AppointmentEventService $appointmentEventService = null,
        ?AuditLogModel $auditLogModel = null,
        ?PhoneNumberService $phoneNumberService = null,
    )
    {
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
        $this->customerModel = $customerModel ?? new CustomerModel();
        $this->serviceModel = $serviceModel ?? new ServiceModel();
        $this->businessHoursService = $businessHoursService ?? new BusinessHoursService();
        $this->availabilityService = $availabilityService ?? new AvailabilityService();
        $this->timezoneService = $timezoneService ?? new TimezoneService();
        $this->localizationSettingsService = $localizationSettingsService ?? new LocalizationSettingsService();
        $this->locationModel = $locationModel ?? new \App\Models\LocationModel();
        $this->appointmentEventService = $appointmentEventService ?? new AppointmentEventService();
        $this->auditLogModel = $auditLogModel ?? new AuditLogModel();
        $this->phoneNumberService = $phoneNumberService ?? new PhoneNumberService();
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
        helper('logging');

        $channel = (string) ($data['booking_channel'] ?? 'internal');
        $actorUserId = $this->resolveActorUserId();

        log_structured('info', 'appointment.create_attempt', [
            'booking_channel' => $channel,
            'actor_user_id' => $actorUserId,
            'provider_id' => isset($data['provider_id']) ? (int) $data['provider_id'] : null,
            'service_id' => isset($data['service_id']) ? (int) $data['service_id'] : null,
            'appointment_date' => (string) ($data['appointment_date'] ?? ''),
            'appointment_time' => (string) ($data['appointment_time'] ?? ''),
        ]);

        log_message('info', '[AppointmentBookingService::createAppointment] ========== START ==========');
        log_message('info', '[AppointmentBookingService::createAppointment] Booking data: ' . json_encode($data));
        
        try {
            // Step 1: Validate service exists
            $service = $this->serviceModel->find($data['service_id']);
            if (!$service) {
                log_structured('warning', 'appointment.create_validation_failed', [
                    'booking_channel' => $channel,
                    'actor_user_id' => $actorUserId,
                    'reason' => 'invalid_service',
                    'service_id' => isset($data['service_id']) ? (int) $data['service_id'] : null,
                ]);
                return $this->error('Invalid service selected');
            }

            // Step 2: Resolve timezone
            if (!TimezoneService::isValidTimezone($timezone)) {
                $timezone = $this->localizationSettingsService->getTimezone();
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
                log_structured('warning', 'appointment.create_validation_failed', [
                    'booking_channel' => $channel,
                    'actor_user_id' => $actorUserId,
                    'reason' => 'invalid_location_context',
                    'errors' => $locationContext['errors'] ?? [],
                ]);
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
                log_structured('warning', 'appointment.create_validation_failed', [
                    'booking_channel' => $channel,
                    'actor_user_id' => $actorUserId,
                    'reason' => 'business_hours',
                    'details' => $businessHoursValidation['reason'],
                ]);
                return $this->error($businessHoursValidation['reason']);
            }

            // Step 5: Check slot availability
            $availabilityCheck = $this->availabilityService->isSlotAvailable(
                (int)$data['provider_id'],
                $timeData['startDateTime']->format('Y-m-d H:i:s'),
                $timeData['endDateTime']->format('Y-m-d H:i:s'),
                $timeData['timezone'],
                $data['exclude_appointment_id'] ?? null,
                $resolvedLocationId
            );
            
            if (!$availabilityCheck['available']) {
                $reason = $availabilityCheck['reason'] ?? 'Time slot not available';
                log_message('warning', '[AppointmentBookingService] Availability check failed: ' . $reason);
                log_structured('warning', 'appointment.create_conflict', [
                    'booking_channel' => $channel,
                    'actor_user_id' => $actorUserId,
                    'reason' => $reason,
                    'conflicts' => $availabilityCheck['conflicts'] ?? [],
                ]);
                return $this->error($reason, ['conflicts' => $availabilityCheck['conflicts'] ?? []]);
            }

            // Step 6: Handle customer (find or create)
            $customerResult = $this->resolveCustomer($data);
            if (!$customerResult['success']) {
                log_structured('warning', 'appointment.create_validation_failed', [
                    'booking_channel' => $channel,
                    'actor_user_id' => $actorUserId,
                    'reason' => 'customer_resolution_failed',
                    'errors' => $customerResult['errors'] ?? [],
                ]);
                return $customerResult;
            }
            $customerId = $customerResult['customerId'];

            // Step 7: Create appointment record
            $status = array_key_exists('status', $data)
                ? AppointmentStatus::normalize((string) $data['status'])
                : AppointmentStatus::PENDING;
            if ($status === null) {
                log_structured('warning', 'appointment.create_validation_failed', [
                    'booking_channel' => $channel,
                    'actor_user_id' => $actorUserId,
                    'reason' => 'invalid_status',
                    'status' => (string) ($data['status'] ?? ''),
                ]);
                return $this->error('Invalid appointment status', ['errors' => ['status' => 'invalid']]);
            }

            $appointmentData = [
                'customer_id' => $customerId,
                'provider_id' => $data['provider_id'],
                'service_id' => $data['service_id'],
                'start_at' => $timeData['startUtc'],
                'end_at' => $timeData['endUtc'],
                'status' => $status,
                'notes' => $data['notes'] ?? '',
                'location_id' => $resolvedLocationId,
            ];

            if (!empty($data['public_token'])) {
                $appointmentData['public_token'] = $data['public_token'];
                $appointmentData['public_token_expires_at'] = $data['public_token_expires_at'] ?? null;
            }

            // Snapshot location data if location_id provided
            if (!empty($resolvedLocationId)) {
                $snapshot = $this->locationModel->getLocationSnapshot((int) $resolvedLocationId);
                $appointmentData = array_merge($appointmentData, $snapshot);
            }

            log_message('info', '[AppointmentBookingService] Inserting appointment: ' . json_encode($appointmentData));
            $appointmentId = $this->appointmentModel->insert($appointmentData);

            if (!$appointmentId) {
                $errors = $this->appointmentModel->errors();
                log_message('error', '[AppointmentBookingService] Insert failed. Errors: ' . json_encode($errors));
                log_structured('error', 'appointment.create_failed', [
                    'booking_channel' => $channel,
                    'actor_user_id' => $actorUserId,
                    'errors' => $errors,
                ]);
                return $this->error('Failed to create appointment', ['validationErrors' => $errors]);
            }

            log_message('info', '[AppointmentBookingService] ✅ Appointment created! ID: ' . $appointmentId);

            log_structured('info', 'appointment.created', [
                'booking_channel' => $channel,
                'actor_user_id' => $actorUserId,
                'appointment_id' => (int) $appointmentId,
                'customer_id' => (int) $customerId,
                'provider_id' => (int) $data['provider_id'],
                'service_id' => (int) $data['service_id'],
                'location_id' => $resolvedLocationId,
                'status' => $status,
                'start_at' => (string) $appointmentData['start_at'],
                'end_at' => (string) $appointmentData['end_at'],
            ]);

            $this->writeAppointmentAudit(
                'appointment_created',
                (int) $appointmentId,
                null,
                [
                    'booking_channel' => $channel,
                    'status' => $status,
                    'provider_id' => (int) $data['provider_id'],
                    'service_id' => (int) $data['service_id'],
                    'customer_id' => (int) $customerId,
                    'start_at' => (string) $appointmentData['start_at'],
                    'end_at' => (string) $appointmentData['end_at'],
                    'location_id' => $resolvedLocationId,
                ]
            );

            // Step 8: Queue notifications (email, SMS, WhatsApp)
            $event = AppointmentStatus::notificationEvent($status, '');
            $this->queueNotifications($appointmentId, $data['notification_types'] ?? ['email', 'whatsapp'], $event);

            $successMessage = $status === AppointmentStatus::PENDING
                ? 'Appointment booked successfully! We will notify you once it is confirmed.'
                : 'Appointment booked successfully! Confirmation will be sent shortly.';

            return $this->success($appointmentId, $successMessage);

        } catch (Exception $e) {
            log_message('error', '[AppointmentBookingService] Exception: ' . $e->getMessage());
            log_message('error', '[AppointmentBookingService] Trace: ' . $e->getTraceAsString());
            log_structured('error', 'appointment.create_exception', [
                'booking_channel' => (string) ($data['booking_channel'] ?? 'internal'),
                'actor_user_id' => $this->resolveActorUserId(),
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
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
    public function updateAppointment(
        int $appointmentId,
        array $data,
        string $timezone = 'UTC',
        ?string $notificationEvent = null,
        ?array $notificationTypes = null
    ): array
    {
        helper('logging');

        $channel = (string) ($data['booking_channel'] ?? 'internal');
        $actorUserId = $this->resolveActorUserId();

        log_structured('info', 'appointment.update_attempt', [
            'booking_channel' => $channel,
            'actor_user_id' => $actorUserId,
            'appointment_id' => $appointmentId,
            'fields' => array_keys($data),
        ]);

        log_message('info', '[AppointmentBookingService::updateAppointment] Updating appointment #' . $appointmentId);
        
        try {
            // Verify appointment exists
            $existing = $this->appointmentModel->find($appointmentId);
            if (!$existing) {
                log_structured('warning', 'appointment.update_failed', [
                    'booking_channel' => $channel,
                    'actor_user_id' => $actorUserId,
                    'appointment_id' => $appointmentId,
                    'reason' => 'not_found',
                ]);
                return $this->error('Appointment not found');
            }

            // Validate service if changed
            if (isset($data['service_id'])) {
                $service = $this->serviceModel->find($data['service_id']);
                if (!$service) {
                    log_structured('warning', 'appointment.update_validation_failed', [
                        'booking_channel' => $channel,
                        'actor_user_id' => $actorUserId,
                        'appointment_id' => $appointmentId,
                        'reason' => 'invalid_service',
                    ]);
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
                    log_structured('warning', 'appointment.update_validation_failed', [
                        'booking_channel' => $channel,
                        'actor_user_id' => $actorUserId,
                        'appointment_id' => $appointmentId,
                        'reason' => 'business_hours',
                        'details' => $businessHoursValidation['reason'],
                    ]);
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
                    log_structured('warning', 'appointment.update_validation_failed', [
                        'booking_channel' => $channel,
                        'actor_user_id' => $actorUserId,
                        'appointment_id' => $appointmentId,
                        'reason' => 'invalid_location_context',
                        'errors' => $locationContext['errors'] ?? [],
                    ]);
                    return $this->error($locationContext['message'], ['errors' => $locationContext['errors'] ?? []]);
                }
                $resolvedLocationId = $locationContext['location_id'];

                // Check availability (exclude this appointment from conflict check)
                $availabilityCheck = $this->availabilityService->isSlotAvailable(
                    $providerId,
                    $timeData['startDateTime']->format('Y-m-d H:i:s'),
                    $timeData['endDateTime']->format('Y-m-d H:i:s'),
                    $timeData['timezone'],
                    $appointmentId,
                    $resolvedLocationId
                );
                
                if (!$availabilityCheck['available']) {
                    log_structured('warning', 'appointment.update_conflict', [
                        'booking_channel' => $channel,
                        'actor_user_id' => $actorUserId,
                        'appointment_id' => $appointmentId,
                        'reason' => $availabilityCheck['reason'] ?? 'Time slot not available',
                        'conflicts' => $availabilityCheck['conflicts'] ?? [],
                    ]);
                    return $this->error($availabilityCheck['reason'] ?? 'Time slot not available');
                }

                $data['start_at'] = $timeData['startUtc'];
                $data['end_at'] = $timeData['endUtc'];
                $data['location_id'] = $resolvedLocationId;
                unset($data['appointment_date'], $data['appointment_time']);

                if (!empty($resolvedLocationId)) {
                    $snapshot = $this->locationModel->getLocationSnapshot((int) $resolvedLocationId);
                    $data = array_merge($data, $snapshot);
                }
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
                    log_structured('warning', 'appointment.update_validation_failed', [
                        'booking_channel' => $channel,
                        'actor_user_id' => $actorUserId,
                        'appointment_id' => $appointmentId,
                        'reason' => 'invalid_location_context',
                        'errors' => $locationContext['errors'] ?? [],
                    ]);
                    return $this->error($locationContext['message'], ['errors' => $locationContext['errors'] ?? []]);
                }
                $data['location_id'] = $locationContext['location_id'];

                if (!empty($data['location_id'])) {
                    $snapshot = $this->locationModel->getLocationSnapshot((int) $data['location_id']);
                    $data = array_merge($data, $snapshot);
                }
            }

            if (array_key_exists('status', $data)) {
                $normalizedStatus = AppointmentStatus::normalize((string) $data['status']);
                if ($normalizedStatus === null) {
                    log_structured('warning', 'appointment.update_validation_failed', [
                        'booking_channel' => $channel,
                        'actor_user_id' => $actorUserId,
                        'appointment_id' => $appointmentId,
                        'reason' => 'invalid_status',
                    ]);
                    return $this->error('Invalid appointment status', ['errors' => ['status' => 'invalid']]);
                }

                $data['status'] = $normalizedStatus;
            }

            // Update appointment
            $success = $this->appointmentModel->update($appointmentId, $data);
            
            if (!$success) {
                $errors = $this->appointmentModel->errors();
                log_message('error', '[AppointmentBookingService] Update failed. Errors: ' . json_encode($errors));
                log_structured('error', 'appointment.update_failed', [
                    'booking_channel' => $channel,
                    'actor_user_id' => $actorUserId,
                    'appointment_id' => $appointmentId,
                    'errors' => $errors,
                ]);
                return $this->error('Failed to update appointment', ['validationErrors' => $errors]);
            }

            log_message('info', '[AppointmentBookingService] ✅ Appointment #' . $appointmentId . ' updated successfully');

            $diff = $this->extractAppointmentDiff($existing, $data);

            log_structured('info', 'appointment.updated', [
                'booking_channel' => $channel,
                'actor_user_id' => $actorUserId,
                'appointment_id' => $appointmentId,
                'changed_fields' => array_keys($diff['new']),
            ]);

            $auditAction = 'appointment_updated';
            if (($data['status'] ?? null) === AppointmentStatus::CANCELLED) {
                $auditAction = 'appointment_cancelled';
            } elseif (isset($data['start_at']) || isset($data['appointment_date']) || isset($data['appointment_time'])) {
                $auditAction = 'appointment_rescheduled';
            }

            $this->writeAppointmentAudit(
                $auditAction,
                $appointmentId,
                $diff['old'],
                array_merge($diff['new'], ['booking_channel' => $channel])
            );

            // Queue notifications when status or time changed
            $statusChanged = array_key_exists('status', $data)
                && (string) $data['status'] !== (string) ($existing['status'] ?? '');
            $timeChanged = isset($data['start_at'])
                && (string) $data['start_at'] !== (string) ($existing['start_at'] ?? '');

            if ($statusChanged || $timeChanged) {
                $event = $notificationEvent;
                if ($event === null) {
                    $event = $timeChanged
                        ? 'appointment_rescheduled'
                        : AppointmentStatus::notificationEvent($data['status'] ?? null, '');
                }

                if ($event !== '') {
                    $types = $notificationTypes ?? ['email', 'whatsapp'];
                    $this->queueNotifications($appointmentId, $types, $event);
                }
            }

            return $this->success($appointmentId, 'Appointment updated successfully!');

        } catch (Exception $e) {
            log_message('error', '[AppointmentBookingService] Update exception: ' . $e->getMessage());
            log_structured('error', 'appointment.update_exception', [
                'booking_channel' => (string) ($data['booking_channel'] ?? 'internal'),
                'actor_user_id' => $this->resolveActorUserId(),
                'appointment_id' => $appointmentId,
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return $this->error('An error occurred while updating the appointment: ' . $e->getMessage());
        }
    }

    private function resolveActorUserId(): ?int
    {
        try {
            if (function_exists('session')) {
                $userId = session()->get('user_id');
                if (is_numeric($userId) && (int) $userId > 0) {
                    return (int) $userId;
                }
            }
        } catch (\Throwable $e) {
            // Session service may be unavailable in CLI contexts.
        }

        return null;
    }

    private function writeAppointmentAudit(string $action, int $appointmentId, ?array $oldValue, ?array $newValue): void
    {
        $actorUserId = $this->resolveActorUserId();
        if ($actorUserId === null) {
            return;
        }

        try {
            $this->auditLogModel->log($action, $actorUserId, 'appointment', $appointmentId, $oldValue, $newValue);
        } catch (\Throwable $e) {
            helper('logging');
            log_structured('error', 'appointment.audit_write_failed', [
                'action' => $action,
                'appointment_id' => $appointmentId,
                'actor_user_id' => $actorUserId,
                'exception_message' => $e->getMessage(),
            ]);
        }
    }

    private function extractAppointmentDiff(array $existing, array $updateData): array
    {
        $trackedFields = [
            'provider_id',
            'service_id',
            'customer_id',
            'start_at',
            'end_at',
            'status',
            'notes',
            'location_id',
        ];

        $old = [];
        $new = [];

        foreach ($trackedFields as $field) {
            if (array_key_exists($field, $updateData)) {
                $old[$field] = $existing[$field] ?? null;
                $new[$field] = $updateData[$field];
            }
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Calculate appointment start/end times and convert to UTC for storage.
     *
     * Time input from admin/public forms arrives in local (app) timezone.
     * DB stores times as UTC. This method converts local → UTC.
     *
     * @param string $date           Date in Y-m-d format
     * @param string $time           Time in H:i format (local/app timezone)
     * @param int    $durationMinutes Service duration
     * @param string $timezone       Client TZ hint — falls back to app TZ (kept for BC)
     * @return array DateTime objects (local) and UTC time strings for DB
     */
    protected function calculateAppointmentTimes(
        string $date,
        string $time,
        int $durationMinutes,
        string $timezone
    ): array {
        // Always use the app timezone for interpreting form input.
        $appTimezone = $this->localizationSettingsService->getTimezone();
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

        // Convert to UTC for DB storage
        $utcTz = new DateTimeZone('UTC');
        $startUtcDt = (clone $startDateTime)->setTimezone($utcTz);
        $endUtcDt   = (clone $endDateTime)->setTimezone($utcTz);
        $startUtc = $startUtcDt->format('Y-m-d H:i:s');
        $endUtc   = $endUtcDt->format('Y-m-d H:i:s');

        log_message('info', '[AppointmentBookingService] Time calculation:', [
            'localStart' => $startDateTime->format('Y-m-d H:i:s'),
            'localEnd'   => $endDateTime->format('Y-m-d H:i:s'),
            'utcStart'   => $startUtc,
            'utcEnd'     => $endUtc,
            'timezone'   => $appTimezone,
        ]);

        return [
            'startDateTime' => $startDateTime,  // local TZ — used for business-hours validation
            'endDateTime'   => $endDateTime,     // local TZ — used for business-hours validation
            'startUtc'      => $startUtc,        // UTC — for DB writes
            'endUtc'        => $endUtc,          // UTC — for DB writes
            'startTime'     => $startDateTime->format('Y-m-d H:i:s'), // local (BC alias)
            'endTime'       => $endDateTime->format('Y-m-d H:i:s'),   // local (BC alias)
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
            'phone' => $this->phoneNumberService->normalize(
                $data['customer_phone'] ?? null,
                $data['customer_phone_country_code'] ?? null
            ) ?? '',
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
        $activeLocations = $this->locationModel->getProviderLocations($providerId, true);

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
     * Queue notifications for appointment events and dispatch them immediately.
     *
     * All channels are enqueued via the canonical queue system. After enqueuing,
     * the dispatcher runs synchronously so email is delivered without waiting for
     * the cron job, while still benefiting from idempotency keys, delivery logs,
     * and opt-out checks.
     *
     * @param int $appointmentId Appointment ID
     * @param array $types Notification types (email, sms, whatsapp)
     * @param string $event Event type (appointment_confirmed, appointment_rescheduled, etc.)
     */
    protected function queueNotifications(int $appointmentId, array $types = ['email'], string $event = ''): void
    {
        try {
            if ($event === '') {
                log_message('debug', '[AppointmentBookingService] Skipping notification queue because event type is empty.');
                return;
            }

            // Enqueue all channels via the canonical event/queue system.
            $this->appointmentEventService->dispatch($event, $appointmentId, $types, NotificationCatalog::BUSINESS_ID_DEFAULT);
            log_message('info', '[AppointmentBookingService] Queued notifications (' . $event . '): ' . implode(', ', $types));

            // Run the dispatcher immediately so email (and any other channels) are sent
            // without requiring the cron job to trigger first.
            $dispatcher = new NotificationQueueDispatcher();
            $stats = $dispatcher->dispatch(NotificationCatalog::BUSINESS_ID_DEFAULT);
            log_message('info', '[AppointmentBookingService] Immediate dispatch stats: ' . json_encode($stats));
        } catch (Exception $e) {
            log_message('error', '[AppointmentBookingService] Notification dispatch failed: ' . $e->getMessage());
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
