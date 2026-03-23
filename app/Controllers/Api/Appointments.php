<?php

/**
 * =============================================================================
 * APPOINTMENTS API CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Api/Appointments.php
 * @description RESTful API for appointment CRUD operations, status updates,
 *              and appointment-related actions like rescheduling.
 * 
 * API ENDPOINTS:
 * -----------------------------------------------------------------------------
 * GET    /api/appointments              : List appointments (filtered, paginated)
 * POST   /api/appointments              : Create new appointment
 * GET    /api/appointments/:id          : Get appointment details
 * PUT    /api/appointments/:id          : Update appointment
 * PATCH  /api/appointments/:id          : Partial update
 * DELETE /api/appointments/:id          : Cancel/delete appointment
 * PATCH  /api/appointments/:id/status   : Update status only
 * PATCH  /api/appointments/:id/notes    : Update notes only
 * POST   /api/appointments/:id/reschedule : Reschedule appointment
 * 
 * QUERY PARAMETERS (GET /api/appointments):
 * -----------------------------------------------------------------------------
 * - start       : Start date (Y-m-d) for date range filter
 * - end         : End date (Y-m-d) for date range filter
 * - providerId  : Filter by provider ID
 * - serviceId   : Filter by service ID
 * - status      : Filter by status (pending, confirmed, completed, cancelled)
 * - page        : Page number (default: 1)
 * - length      : Items per page (default: 50, max: 1000 for calendar views)
 * - sort        : Sort field:direction (e.g., start_at:asc)
 * 
 * REQUEST BODY (POST/PUT):
 * -----------------------------------------------------------------------------
 * {
 *   "provider_id": 2,
 *   "service_id": 1,
 *   "customer_id": 5,        // or customer object for new customer
 *   "start_at": "2025-01-15 09:00:00",
 *   "end_at": "2025-01-15 10:00:00",
 *   "notes": "Special requests...",
 *   "status": "confirmed"
 * }
 * 
 * RESPONSE FORMAT:
 * -----------------------------------------------------------------------------
 * Success: { "data": { appointment }, "meta": { pagination } }
 * Error:   { "error": { "message": "...", "code": "..." } }
 * 
 * @see         app/Models/AppointmentModel.php for data layer
 * @see         app/Services/AppointmentBookingService.php for booking logic
 * @package     App\Controllers\Api
 * @extends     BaseApiController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers\Api;

use App\Models\AppointmentModel;
use App\Services\Appointment\AppointmentAvailabilityService;
use App\Services\Appointment\AppointmentDateTimeNormalizer;
use App\Services\Appointment\AppointmentManualNotificationService;
use App\Services\Appointment\AppointmentMutationService;
use App\Services\NotificationCatalog;
use App\Services\TimezoneService;
use App\Services\Appointment\AppointmentQueryService;
use App\Services\Appointment\AppointmentFormatterService;

/**
 * Appointments API Controller
 * 
 * Provides endpoints for managing appointments.
 */
class Appointments extends BaseApiController
{
    private ?AppointmentDateTimeNormalizer $appointmentDateTimeNormalizer = null;
    private ?AppointmentMutationService $appointmentMutationService = null;
    private ?AppointmentManualNotificationService $appointmentManualNotificationService = null;
    private ?AppointmentAvailabilityService $appointmentAvailabilityService = null;

    /**
     * List appointments with pagination, filtering, and date range support.
     * GET /api/appointments?start=&end=&provider_id=&service_id=&page=&length=&sort=
     *
    * Role scoping is enforced automatically:
    * - role=provider: always restricted to their own appointments (RISK-06 fix)
    * - role=staff: restricted to assigned providers
    * - role=admin: sees all appointments
     */
    public function index()
    {
        try {
            // Query parameters
            $start      = $this->request->getGet('start');
            $end        = $this->request->getGet('end');
            $providerId = $this->request->getGet('provider_id') ?? $this->request->getGet('providerId');
            $serviceId  = $this->request->getGet('service_id') ?? $this->request->getGet('serviceId');
            $locationId = $this->request->getGet('location_id') ?? $this->request->getGet('locationId');
            $sortParam  = $this->request->getGet('sort') ?? 'start_at:asc';
            $timezone   = $this->request->getGet('timezone')
                ?: $this->request->getHeaderLine('X-Client-Timezone')
                ?: TimezoneService::businessTimezone();

            if (!TimezoneService::isValidTimezone($timezone)) {
                $timezone = TimezoneService::businessTimezone();
            }

            // Pagination (calendar views may request up to 1000)
            $page   = max(1, (int) ($this->request->getGet('page') ?? 1));
            $length = min(1000, max(1, (int) ($this->request->getGet('length') ?? 50)));

            // Session-based role scoping (fixes RISK-06)
            $userRole      = current_user_role();
            $scopeToUserId = session()->get('user_id');

            $queryService = new AppointmentQueryService();
            $formatter    = new AppointmentFormatterService();

            $result = $queryService->getForCalendar([
                'start'            => $start,
                'end'              => $end,
                'provider_id'      => $providerId ? (int) $providerId : null,
                'service_id'       => $serviceId  ? (int) $serviceId  : null,
                'location_id'      => $locationId ? (int) $locationId : null,
                'sort'             => $sortParam,
                'page'             => $page,
                'length'           => $length,
                'user_role'        => $userRole,
                'scope_to_user_id' => $scopeToUserId,
                'timezone'         => $timezone,
            ]);

            $events = $formatter->formatManyForCalendar($result['rows']);

            return $this->ok($events, [
                'total'   => $result['total'],
                'page'    => $result['page'],
                'length'  => $result['length'],
                'filters' => [
                    'start'       => $start,
                    'end'         => $end,
                    'provider_id' => $providerId,
                    'service_id'  => $serviceId,
                    'location_id' => $locationId,
                    'timezone'    => $timezone,
                ],
            ]);

        } catch (\Exception $e) {
            return $this->serverError('Failed to fetch appointments', ['exception' => $e->getMessage()]);
        }
    }
    
    /**
     * Get a single appointment by ID with full details
     * GET /api/appointments/:id
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->badRequest('Appointment ID is required');
        }
        
        try {
            $appointment = $this->getAppointmentQueryService()->getDetailById((int) $id);
            
            if (!$appointment) {
                return $this->notFound('Appointment not found', ['appointment_id' => (int) $id]);
            }

            return $this->ok($this->getAppointmentFormatterService()->formatForApiDetail($appointment));
            
        } catch (\Exception $e) {
            return $this->serverError('Failed to fetch appointment', ['exception' => $e->getMessage()]);
        }
    }
    
    /**
     * Check appointment availability
     * POST /api/appointments/check-availability
     * 
     * Refactored to use AvailabilityService for consistent validation
     */
    public function checkAvailability()
    {
        try {
            $result = $this->getAppointmentAvailabilityService()->checkFromPayload(
                $this->request->getJSON(true) ?? [],
                $this->request->getHeaderLine('X-Client-Timezone')
            );

            if (!$result['success']) {
                return match ($result['statusCode'] ?? 400) {
                    404 => $this->notFound($result['message'], $result['errors'] ?? []),
                    422 => $this->unprocessable($result['message'], $result['errors'] ?? []),
                    default => $this->badRequest($result['message'], $result['errors'] ?? []),
                };
            }

            return $this->ok($result['data']);
            
        } catch (\Exception $e) {
            return $this->serverError('Failed to check availability', ['exception' => $e->getMessage()]);
        }
    }

    private function getCreateValidationRules(): array
    {
        return [
            'name' => 'required|min_length[2]',
            'email' => 'required|valid_email',
            'providerId' => 'required|is_natural_no_zero',
            'serviceId' => 'required|is_natural_no_zero',
            'date' => 'required|valid_date[Y-m-d]',
            'start' => 'required|regex_match[/^\d{2}:\d{2}$/]',
            'phone' => 'permit_empty|string',
            'notes' => 'permit_empty|string',
        ];
    }

    private function getCountsValidationRules(): array
    {
        return [
            'providerId' => 'permit_empty|is_natural_no_zero',
            'serviceId' => 'permit_empty|is_natural_no_zero',
        ];
    }
    
    /**
     * Update appointment status only (quick status changes)
     * PATCH /api/appointments/:id/status
     * 
     * PURPOSE: Fast status updates from calendar modal without full form validation.
     * Use this for quick status changes (pending → confirmed, etc.) from the UI.
     * 
     * For full appointment updates (dates, customer info, etc.), use update() method below.
     * 
     * @param int|null $id Appointment ID
     * @return ResponseInterface JSON response with success/error
     */
    public function updateStatus($id = null)
    {
        if (!$id) {
            return $this->badRequest('Appointment ID is required');
        }
        
        try {
            // Get JSON input
            $json = $this->request->getJSON(true);
            $newStatus = $json['status'] ?? null;
            
            if (!$newStatus) {
                return $this->badRequest('Status is required');
            }
            
            $result = $this->getAppointmentMutationService()->updateStatus((int) $id, (string) $newStatus);
            if (!$result['success']) {
                return match ($result['statusCode'] ?? 422) {
                    400 => $this->badRequest($result['message'], $result['errors'] ?? []),
                    404 => $this->notFound($result['message'], $result['errors'] ?? []),
                    default => $this->unprocessable($result['message'], $result['errors'] ?? []),
                };
            }

            return $this->ok($result['data'], ['message' => $result['message']]);
            
        } catch (\Exception $e) {
            return $this->serverError('Failed to update appointment status', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Update appointment notes
     * PATCH /api/appointments/{id}/notes
     * Body: { notes: string }
     * 
     * Quick endpoint for updating just the notes field.
     * 
     * @param int|null $id Appointment ID
     * @return ResponseInterface JSON response with success/error
     */
    public function updateNotes($id = null)
    {
        if (!$id) {
            return $this->badRequest('Appointment ID is required');
        }
        
        try {
            // Get JSON input
            $json = $this->request->getJSON(true);
            $newNotes = $json['notes'] ?? '';
            
            $result = $this->getAppointmentMutationService()->updateNotes((int) $id, (string) $newNotes);
            if (!$result['success']) {
                return match ($result['statusCode'] ?? 422) {
                    404 => $this->notFound($result['message'], $result['errors'] ?? []),
                    default => $this->unprocessable($result['message'], $result['errors'] ?? []),
                };
            }

            return $this->ok($result['data'], ['message' => $result['message']]);
            
        } catch (\Exception $e) {
            return $this->serverError('Failed to update appointment notes', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Create new appointment via API
     * POST /api/appointments
     * Body: { name, email, phone?, providerId, serviceId, date, start, notes? }
     */
    public function create()
    {
        try {
            $payload = $this->request->getJSON(true) ?? $this->request->getPost();
            
            $rules = $this->getCreateValidationRules();
            
            if (!$this->validate($rules)) {
                return $this->validationError($this->validator->getErrors());
            }
            
            $result = $this->getAppointmentMutationService()->createFromApiPayload(
                $payload,
                $this->resolveApiInputTimezone($payload)
            );
            if (!$result['success']) {
                return $this->error(
                    $result['statusCode'] ?? 409,
                    $result['message'] ?? 'Unable to create appointment',
                    $result['code'] ?? 'CONFLICT',
                    $result['errors'] ?? []
                );
            }

            return $this->created($result['data'], ['message' => $result['message']]);
        } catch (\Exception $e) {
            return $this->serverError('Failed to create appointment', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Update appointment (flexible field updates)
     * PATCH /api/appointments/:id
     * Body: { start?, end?, status? }
     * 
     * PURPOSE: Flexible updates for multiple fields (reschedule, status, etc.).
     * Used by drag-drop reschedule and other programmatic updates.
     * Accepts partial updates - only provided fields are updated.
     * 
     * For status-only updates, you can use updateStatus() above for clarity,
     * but this method handles status changes too.
     */
    public function update($id = null)
    {
        if (!$id) {
            return $this->badRequest('Invalid appointment ID');
        }
        
        try {
            $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
            
            if (!$payload) {
                return $this->badRequest('No request body');
            }

            $result = $this->getAppointmentMutationService()->updateFromApiPayload(
                (int) $id,
                $payload,
                $this->resolveApiInputTimezone($payload)
            );
            if (!$result['success']) {
                return match ($result['statusCode'] ?? 422) {
                    400 => $this->badRequest($result['message'], $result['errors'] ?? []),
                    404 => $this->notFound($result['message'], $result['errors'] ?? []),
                    default => $this->unprocessable($result['message'], $result['errors'] ?? []),
                };
            }

            return $this->ok($result['data'], ['message' => $result['message']]);
        } catch (\Exception $e) {
            return $this->serverError('Failed to update appointment', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Delete (cancel) appointment via API
     * DELETE /api/appointments/:id
     */
    public function delete($id = null)
    {
        if (!$id) {
            return $this->badRequest('Invalid appointment ID');
        }
        
        try {
            $result = $this->getAppointmentMutationService()->cancelAppointment((int) $id);
            if (!$result['success']) {
                return match ($result['statusCode'] ?? 422) {
                    404 => $this->notFound($result['message'], $result['errors'] ?? []),
                    default => $this->unprocessable($result['message'], $result['errors'] ?? []),
                };
            }

            return $this->ok($result['data'], ['message' => $result['message']]);
        } catch (\Exception $e) {
            return $this->serverError('Failed to delete appointment', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Resolve timezone hint from payload/header with business timezone fallback.
     */
    private function resolveApiInputTimezone(array $payload): string
    {
        $tz = (string) ($payload['timezone']
            ?? $this->request->getHeaderLine('X-Client-Timezone')
            ?? TimezoneService::businessTimezone());

        return $this->getAppointmentDateTimeNormalizer()->resolveInputTimezone($tz);
    }

    private function getAppointmentDateTimeNormalizer(): AppointmentDateTimeNormalizer
    {
        if ($this->appointmentDateTimeNormalizer === null) {
            $this->appointmentDateTimeNormalizer = new AppointmentDateTimeNormalizer();
        }

        return $this->appointmentDateTimeNormalizer;
    }

    private function getAppointmentMutationService(): AppointmentMutationService
    {
        if ($this->appointmentMutationService === null) {
            $this->appointmentMutationService = new AppointmentMutationService(
                new AppointmentModel(),
                null,
                $this->getAppointmentDateTimeNormalizer()
            );
        }

        return $this->appointmentMutationService;
    }

    /**
     * Get appointment counts by time period
     * GET /api/appointments/counts?providerId=&serviceId=
     */
    public function counts()
    {
        try {
            $rules = $this->getCountsValidationRules();
            
            if (!$this->validate($rules)) {
                return $this->validationError($this->validator->getErrors());
            }

            $providerId = (int) ($this->request->getGet('providerId') ?? 0);
            $serviceId  = (int) ($this->request->getGet('serviceId') ?? 0);

            $counts = $this->getAppointmentQueryService()->getPeriodCounts([
                'provider_id' => $providerId,
                'service_id' => $serviceId,
            ]);

            return $this->ok($counts);
        } catch (\Exception $e) {
            return $this->serverError('Failed to get appointment counts', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Get appointment summary (alias for counts)
     * GET /api/appointments/summary?providerId=&serviceId=
     */
    public function summary()
    {
        return $this->counts();
    }

    /**
     * Send a manual notification for an appointment
     * POST /api/appointments/:id/notify
     * Body: { channel: 'email'|'sms'|'whatsapp', event_type?: string }
     */
    public function notify($id = null)
    {
        if (!$id) {
            return $this->badRequest('Invalid appointment ID');
        }

        try {
            $json = $this->request->getJSON(true) ?? [];
            $result = $this->getAppointmentManualNotificationService()->send(
                (int) $id,
                (string) ($json['channel'] ?? ''),
                $json['event_type'] ?? null
            );

            if (!$result['success']) {
                return match ($result['statusCode'] ?? 400) {
                    404 => $this->notFound($result['message'], $result['errors'] ?? []),
                    default => $this->badRequest($result['message'], $result['errors'] ?? []),
                };
            }

            return $this->ok($result['data']);
        } catch (\Throwable $e) {
            log_message('error', 'Manual notification failed for appointment {id}: {msg}', [
                'id' => (int) $id,
                'msg' => $e->getMessage()
            ]);
            
            return $this->serverError('Failed to send notification', ['exception' => $e->getMessage()]);
        }
    }

    private function getAppointmentQueryService(): AppointmentQueryService
    {
        return new AppointmentQueryService();
    }

    private function getAppointmentFormatterService(): AppointmentFormatterService
    {
        return new AppointmentFormatterService();
    }

    private function getAppointmentManualNotificationService(): AppointmentManualNotificationService
    {
        if ($this->appointmentManualNotificationService === null) {
            $this->appointmentManualNotificationService = new AppointmentManualNotificationService(
                new AppointmentModel(),
                $this->getAppointmentQueryService()
            );
        }

        return $this->appointmentManualNotificationService;
    }

    private function getAppointmentAvailabilityService(): AppointmentAvailabilityService
    {
        if ($this->appointmentAvailabilityService === null) {
            $this->appointmentAvailabilityService = new AppointmentAvailabilityService();
        }

        return $this->appointmentAvailabilityService;
    }
}
