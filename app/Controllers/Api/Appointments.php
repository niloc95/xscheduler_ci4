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
 * - sort        : Sort field:direction (e.g., start_time:asc)
 * 
 * REQUEST BODY (POST/PUT):
 * -----------------------------------------------------------------------------
 * {
 *   "provider_id": 2,
 *   "service_id": 1,
 *   "customer_id": 5,        // or customer object for new customer
 *   "start_time": "2025-01-15 09:00:00",
 *   "end_time": "2025-01-15 10:00:00",
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
 * @see         app/Services/SchedulingService.php for business logic
 * @package     App\Controllers\Api
 * @extends     BaseApiController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers\Api;

use App\Models\AppointmentModel;
use App\Services\LocalizationSettingsService;
use App\Services\TimezoneService;
use App\Services\SchedulingService;

/**
 * Appointments API Controller
 * 
 * Provides endpoints for managing appointments.
 */
class Appointments extends BaseApiController
{
    /**
     * List appointments with pagination, filtering, and date range support
     * GET /api/appointments?start=&end=&providerId=&serviceId=&page=&length=&sort=
     */
    public function index()
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        try {
            // Get query parameters
            $start = $this->request->getGet('start');
            $end = $this->request->getGet('end');
            $providerId = $this->request->getGet('providerId');
            $serviceId = $this->request->getGet('serviceId');
            $locationId = $this->request->getGet('locationId');
            
            // Pagination parameters
            // For calendar views, allow up to 1000 per page to load all appointments
            $page = max(1, (int)($this->request->getGet('page') ?? 1));
            $length = min(1000, max(1, (int)($this->request->getGet('length') ?? 50)));
            $offset = ($page - 1) * $length;
            
            // Sort parameters (default: start_time ASC)
            $sortParam = $this->request->getGet('sort') ?? 'start_time:asc';
            [$sortField, $sortDir] = array_pad(explode(':', $sortParam), 2, 'asc');
            $validSortFields = ['id', 'start_time', 'end_time', 'provider_id', 'service_id', 'status'];
            if (!in_array($sortField, $validSortFields)) {
                $sortField = 'start_time';
            }
            $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
            
            $model = new AppointmentModel();
            $builder = $model->builder();
            
            // Select appointments with related data including provider color
            $builder->select('xs_appointments.*, 
                             CONCAT(c.first_name, " ", COALESCE(c.last_name, "")) as customer_name,
                             c.email as customer_email,
                             c.phone as customer_phone,
                             s.name as service_name,
                             s.duration_min as service_duration,
                             s.price as service_price,
                             u.name as provider_name,
                             u.color as provider_color')
                    ->join('xs_customers c', 'c.id = xs_appointments.customer_id', 'left')
                    ->join('xs_services s', 's.id = xs_appointments.service_id', 'left')
                    ->join('xs_users u', 'u.id = xs_appointments.provider_id', 'left')
                    ->orderBy('xs_appointments.' . $sortField, $sortDir);
            
            // Apply date range filter
            if ($start || $end) {
                // Custom scheduler sends ISO 8601 dates (e.g., 2025-10-23T00:00:00Z or 2025-10-23)
                // Parse into DateTimeImmutable for consistent handling
                $startDate = substr($start, 0, 10); // Get YYYY-MM-DD
                $endDate = substr($end, 0, 10);     // Get YYYY-MM-DD
                
                $builder->where('xs_appointments.start_time >=', $startDate . ' 00:00:00')
                        ->where('xs_appointments.start_time <=', $endDate . ' 23:59:59');
            }
            
            // Apply optional filters
            if ($providerId) {
                $builder->where('xs_appointments.provider_id', (int)$providerId);
            }
            
            if ($serviceId) {
                $builder->where('xs_appointments.service_id', (int)$serviceId);
            }
            
            if ($locationId) {
                $builder->where('xs_appointments.location_id', (int)$locationId);
            }
            
            // Get total count for pagination
            $countBuilder = clone $builder;
            $totalCount = $countBuilder->countAllResults(false);
            
            // Apply pagination
            $appointments = $builder->limit($length, $offset)->get()->getResultArray();
            
            log_message('info', '[API/Appointments::index] ========== APPOINTMENTS API RESPONSE ==========');
            log_message('info', '[API/Appointments::index] Query filters:', [
                'start' => $start,
                'end' => $end,
                'providerId' => $providerId,
                'serviceId' => $serviceId
            ]);
            log_message('info', '[API/Appointments::index] Found ' . count($appointments) . ' appointments');
            
            // Transform data for custom scheduler with provider colors
            $events = array_map(function($appointment) {
                $startIso = $this->formatIso($appointment['start_time'] ?? null) ?? ($appointment['start_time'] ?? null);
                $endIso = $this->formatIso($appointment['end_time'] ?? null) ?? ($appointment['end_time'] ?? null);

                log_message('debug', '[API/Appointments::index] Appointment #' . $appointment['id'] . ':', [
                    'customer' => $appointment['customer_name'],
                    'iso_start' => $startIso,
                    'iso_end' => $endIso,
                    'provider' => $appointment['provider_name'],
                    'service' => $appointment['service_name']
                ]);

                return [
                    'id' => (int)$appointment['id'],
                    'hash' => $appointment['hash'] ?? null, // Hash for secure URLs
                    'title' => $appointment['customer_name'] ?? 'Appointment #' . $appointment['id'],
                    'start' => $startIso,
                    'end' => $endIso,
                    'providerId' => (int)$appointment['provider_id'],
                    'serviceId' => (int)$appointment['service_id'],
                    'customerId' => (int)$appointment['customer_id'],
                    'status' => $appointment['status'],
                    'name' => $appointment['customer_name'] ?? null,
                    'serviceName' => $appointment['service_name'] ?? null,
                    'providerName' => $appointment['provider_name'] ?? null,
                    'providerColor' => $appointment['provider_color'] ?? '#3B82F6', // Default blue
                    'serviceDuration' => $appointment['service_duration'] ? (int)$appointment['service_duration'] : null,
                    'servicePrice' => $appointment['service_price'] ? (float)$appointment['service_price'] : null,
                    'email' => $appointment['customer_email'] ?? null,
                    'phone' => $appointment['customer_phone'] ?? null,
                    'notes' => $appointment['notes'] ?? null,
                    'locationId' => $appointment['location_id'] ? (int) $appointment['location_id'] : null,
                    'locationName' => $appointment['location_name'] ?? null,
                    'locationAddress' => $appointment['location_address'] ?? null,
                    'locationContact' => $appointment['location_contact'] ?? null,
                    // Note: start_time/end_time removed - use 'start'/'end' instead (ISO 8601)
                ];
            }, $appointments);
            
            log_message('info', '[API/Appointments::index] Returning ' . count($events) . ' events with app-timezone ISO timestamps');
            log_message('info', '[API/Appointments::index] Note: times carry the app timezone offset (e.g. +02:00); frontend Luxon zone option uses this for display');
            log_message('info', '[API/Appointments::index] ==================================================');
            
            return $response->setJSON([
                'data' => $events,
                'meta' => [
                    'total' => count($events),
                    'filters' => [
                        'start' => $start,
                        'end' => $end,
                        'providerId' => $providerId,
                        'serviceId' => $serviceId
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to fetch appointments',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }
    
    /**
     * Get a single appointment by ID with full details
     * GET /api/appointments/:id
     */
    public function show($id = null)
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        if (!$id) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'Appointment ID is required'
                ]
            ]);
        }
        
        try {
            $model = new AppointmentModel();
            
            // Use centralized method to eliminate duplicate JOIN query
            $appointment = $model->getWithRelations((int)$id);
            
            if (!$appointment) {
                return $response->setStatusCode(404)->setJSON([
                    'error' => [
                        'message' => 'Appointment not found'
                    ]
                ]);
            }
            
            // Format response with consistent field aliases (service_duration, not duration)
            $data = [
                'id' => $appointment['id'],
                'customer_id' => $appointment['customer_id'],
                'customer_name' => $appointment['customer_name'] ?? 'N/A',
                'customer_email' => $appointment['customer_email'] ?? '',
                'customer_phone' => $appointment['customer_phone'] ?? '',
                'provider_id' => $appointment['provider_id'],
                'provider_name' => $appointment['provider_name'] ?? 'N/A',
                'provider_color' => $appointment['provider_color'] ?? '#3B82F6',
                'service_id' => $appointment['service_id'],
                'service_name' => $appointment['service_name'] ?? 'N/A',
                'start_time' => $this->formatIso($appointment['start_time'] ?? null) ?? ($appointment['start_time'] ?? null),
                'end_time' => $this->formatIso($appointment['end_time'] ?? null) ?? ($appointment['end_time'] ?? null),
                'start' => $this->formatIso($appointment['start_time'] ?? null) ?? ($appointment['start_time'] ?? null),
                'end' => $this->formatIso($appointment['end_time'] ?? null) ?? ($appointment['end_time'] ?? null),
                'service_duration' => $appointment['service_duration'], // Standardized field name
                'service_price' => $appointment['service_price'],
                'status' => $appointment['status'],
                'notes' => $appointment['notes'] ?? '',
                'location_id' => $appointment['location_id'] ? (int) $appointment['location_id'] : null,
                'location_name' => $appointment['location_name'] ?? '',
                'location_address' => $appointment['location_address'] ?? '',
                'location_contact' => $appointment['location_contact'] ?? '',
                'is_paid' => $appointment['is_paid'] ?? false,
                'created_at' => $appointment['created_at'] ?? null,
                'updated_at' => $appointment['updated_at'] ?? null,
            ];
            
            return $response->setJSON([
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to fetch appointment',
                    'details' => $e->getMessage()
                ]
            ]);
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
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        try {
            // Get POST data
            $json = $this->request->getJSON(true);
            $providerId = $json['provider_id'] ?? null;
            $serviceId = $json['service_id'] ?? null;
            $startTime = $json['start_time'] ?? null;
            $timezone = $this->request->getHeaderLine('X-Client-Timezone') ?: ($json['timezone'] ?? null);
            $appointmentId = $json['appointment_id'] ?? null; // For edits, exclude this ID
            
            // Validate required fields
            if (!$providerId || !$serviceId || !$startTime) {
                return $response->setStatusCode(400)->setJSON([
                    'error' => [
                        'message' => 'Missing required fields',
                        'required' => ['provider_id', 'service_id', 'start_time']
                    ]
                ]);
            }
            
            if (!$timezone || !TimezoneService::isValidTimezone($timezone)) {
                $timezone = (new LocalizationSettingsService())->getTimezone();
            }

            // Get service details for duration
            $db = \Config\Database::connect();
            $service = $db->table('services')
                ->select('duration_min, name')
                ->where('id', (int)$serviceId)
                ->where('active', 1)
                ->get()
                ->getRowArray();
            
            if (!$service) {
                return $response->setStatusCode(404)->setJSON([
                    'error' => [
                        'message' => 'Service not found or inactive'
                    ]
                ]);
            }
            
            // Calculate end time
            try {
                $startDateTime = new \DateTime($startTime, new \DateTimeZone($timezone));
            } catch (\Exception $e) {
                log_message('error', 'Timezone conversion failed in availability check: ' . $e->getMessage());
                $timezone = 'UTC';
                $startDateTime = new \DateTime($startTime, new \DateTimeZone($timezone));
            }
            $endDateTime = clone $startDateTime;
            $endDateTime->modify('+' . $service['duration_min'] . ' minutes');

            $startLocal = $startDateTime->format('Y-m-d H:i:s');
            $endLocal = $endDateTime->format('Y-m-d H:i:s');
            
            // Use AvailabilityService for consistent availability checking
            $availabilityService = new \App\Services\AvailabilityService();
            $availabilityCheck = $availabilityService->isSlotAvailable(
                (int)$providerId,
                $startLocal,
                $endLocal,
                $timezone,
                $appointmentId
            );
            
            // Convert to UTC for response
            $startUtc = TimezoneService::toUTC($startLocal, $timezone);
            $endUtc = TimezoneService::toUTC($endLocal, $timezone);
            
            // Build response in expected format
            $result = [
                'available' => $availabilityCheck['available'],
                'requestedSlot' => [
                    'provider_id' => (int)$providerId,
                    'service_id' => (int)$serviceId,
                    'service_name' => $service['name'],
                    'duration_min' => (int)$service['duration_min'],
                    'start_time_local' => $startLocal,
                    'end_time_local' => $endLocal,
                    'start_time_utc' => $startUtc,
                    'end_time_utc' => $endUtc,
                    'timezone' => $timezone,
                ],
                'conflicts' => $availabilityCheck['conflicts'] ?? [],
                'reason' => $availabilityCheck['reason'] ?? null,
            ];
            
            // Suggest next available slot if not available
            if (!$availabilityCheck['available']) {
                // Simple suggestion: 30 minutes later
                $nextSlot = clone $startDateTime;
                $nextSlot->modify('+30 minutes');
                $result['suggestedNextSlot'] = [
                    'local' => $nextSlot->format('Y-m-d H:i:s'),
                    'utc' => TimezoneService::toUTC($nextSlot->format('Y-m-d H:i:s'), $timezone)
                ];
            }
            
            return $response->setJSON([
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to check availability',
                    'details' => $e->getMessage()
                ]
            ]);
        }
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
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        if (!$id) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'Appointment ID is required'
                ]
            ]);
        }
        
        try {
            // Get JSON input
            $json = $this->request->getJSON(true);
            $newStatus = $json['status'] ?? null;
            
            if (!$newStatus) {
                return $response->setStatusCode(400)->setJSON([
                    'error' => [
                        'message' => 'Status is required'
                    ]
                ]);
            }
            
            // Validate status
            $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no-show'];
            if (!in_array($newStatus, $validStatuses)) {
                return $response->setStatusCode(400)->setJSON([
                    'error' => [
                        'message' => 'Invalid status',
                        'valid_statuses' => $validStatuses
                    ]
                ]);
            }
            
            // Update appointment
            $model = new AppointmentModel();
            $appointment = $model->find($id);
            
            if (!$appointment) {
                log_message('error', "Appointment not found: ID={$id}");
                return $response->setStatusCode(404)->setJSON([
                    'error' => [
                        'message' => 'Appointment not found'
                    ]
                ]);
            }
            
            log_message('info', "Updating appointment status: ID={$id}, Old={$appointment['status']}, New={$newStatus}");
            
            $updateData = [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $updated = $model->update($id, $updateData);
            
            log_message('info', "Update result: " . ($updated ? 'SUCCESS' : 'FAILED'));
            
            if ($model->errors()) {
                log_message('error', "Model validation errors: " . json_encode($model->errors()));
                return $response->setStatusCode(422)->setJSON([
                    'error' => [
                        'message' => 'Validation failed',
                        'validation_errors' => $model->errors()
                    ]
                ]);
            }
            
            if (!$updated) {
                log_message('error', "Failed to update appointment status: ID={$id}, Status={$newStatus}");
                return $response->setStatusCode(500)->setJSON([
                    'error' => [
                        'message' => 'Failed to update appointment status'
                    ]
                ]);
            }

            // Phase 5: enqueue notifications on status changes (dispatch via cron)
            try {
                $queue = new \App\Services\NotificationQueueService();
                $businessId = \App\Services\NotificationPhase1::BUSINESS_ID_DEFAULT;
                if ($newStatus === 'confirmed') {
                    $queue->enqueueAppointmentEvent($businessId, 'email', 'appointment_confirmed', (int) $id);
                    $queue->enqueueAppointmentEvent($businessId, 'whatsapp', 'appointment_confirmed', (int) $id);
                }
                if ($newStatus === 'cancelled') {
                    $queue->enqueueAppointmentEvent($businessId, 'email', 'appointment_cancelled', (int) $id);
                    $queue->enqueueAppointmentEvent($businessId, 'whatsapp', 'appointment_cancelled', (int) $id);
                }
            } catch (\Throwable $e) {
                log_message('error', 'Notification enqueue failed for appointment {id}: {msg}', ['id' => (int) $id, 'msg' => $e->getMessage()]);
            }
            
            return $response->setJSON([
                'data' => [
                    'id' => $id,
                    'status' => $newStatus,
                    'updated_at' => $updateData['updated_at']
                ],
                'message' => 'Appointment status updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to update appointment status',
                    'details' => $e->getMessage()
                ]
            ]);
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
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        if (!$id) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'Appointment ID is required'
                ]
            ]);
        }
        
        try {
            // Get JSON input
            $json = $this->request->getJSON(true);
            $newNotes = $json['notes'] ?? '';
            
            // Update appointment
            $model = new AppointmentModel();
            $appointment = $model->find($id);
            
            if (!$appointment) {
                log_message('error', "Appointment not found: ID={$id}");
                return $response->setStatusCode(404)->setJSON([
                    'error' => [
                        'message' => 'Appointment not found'
                    ]
                ]);
            }
            
            log_message('info', "Updating appointment notes: ID={$id}");
            
            $updateData = [
                'notes' => $newNotes,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $updated = $model->update($id, $updateData);
            
            if ($model->errors()) {
                log_message('error', "Model validation errors: " . json_encode($model->errors()));
                return $response->setStatusCode(422)->setJSON([
                    'error' => [
                        'message' => 'Validation failed',
                        'validation_errors' => $model->errors()
                    ]
                ]);
            }
            
            if (!$updated) {
                log_message('error', "Failed to update appointment notes: ID={$id}");
                return $response->setStatusCode(500)->setJSON([
                    'error' => [
                        'message' => 'Failed to update appointment notes'
                    ]
                ]);
            }
            
            return $response->setJSON([
                'data' => [
                    'id' => $id,
                    'notes' => $newNotes,
                    'updated_at' => $updateData['updated_at']
                ],
                'message' => 'Appointment notes updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to update appointment notes',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }

    private function formatIso(?string $datetime): ?string
    {
        if (!$datetime) {
            return null;
        }

        try {
            // DB stores times in app timezone — attach the correct offset so
            // JavaScript / Luxon receives a proper absolute moment in time.
            $tz = (new LocalizationSettingsService())->getTimezone();
            $dt = new \DateTime($datetime, new \DateTimeZone($tz));
            return $dt->format('Y-m-d\TH:i:sP'); // e.g. 2026-02-23T16:00:00+02:00
        } catch (\Exception $e) {
            return $datetime;
        }
    }

    /**
     * Create new appointment via API
     * POST /api/appointments
     * Body: { name, email, phone?, providerId, serviceId, date, start, notes? }
     */
    public function create()
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        try {
            $payload = $this->request->getJSON(true) ?? $this->request->getPost();
            
            $rules = [
                'name' => 'required|min_length[2]',
                'email' => 'required|valid_email',
                'providerId' => 'required|is_natural_no_zero',
                'serviceId' => 'required|is_natural_no_zero',
                'date' => 'required|valid_date[Y-m-d]',
                'start' => 'required|regex_match[/^\d{2}:\d{2}$/]',
                'phone' => 'permit_empty|string',
                'notes' => 'permit_empty|string',
            ];
            
            if (!$this->validate($rules)) {
                return $response->setStatusCode(400)->setJSON([
                    'error' => [
                        'message' => 'Invalid request body',
                        'type' => 'validation_error',
                        'details' => $this->validator->getErrors()
                    ]
                ]);
            }
            
            $svc = new SchedulingService();
            
            try {
                $res = $svc->createAppointment($payload);

                // Phase 5: enqueue confirmation notifications
                try {
                    if (!empty($res['appointmentId'])) {
                        $queue = new \App\Services\NotificationQueueService();
                        $businessId = \App\Services\NotificationPhase1::BUSINESS_ID_DEFAULT;
                        $queue->enqueueAppointmentEvent($businessId, 'email', 'appointment_confirmed', (int) $res['appointmentId']);
                        $queue->enqueueAppointmentEvent($businessId, 'whatsapp', 'appointment_confirmed', (int) $res['appointmentId']);
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'Notification enqueue failed for appointment {id}: {msg}', ['id' => (int) ($res['appointmentId'] ?? 0), 'msg' => $e->getMessage()]);
                }
                
                return $response->setStatusCode(201)->setJSON([
                    'data' => $res,
                    'message' => 'Appointment created successfully'
                ]);
            } catch (\InvalidArgumentException $e) {
                return $response->setStatusCode(400)->setJSON([
                    'error' => [
                        'message' => $e->getMessage()
                    ]
                ]);
            } catch (\RuntimeException $e) {
                $status = $e->getMessage() === 'Service not found' ? 404 : 409;
                return $response->setStatusCode($status)->setJSON([
                    'error' => [
                        'message' => $e->getMessage()
                    ]
                ]);
            }
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to create appointment',
                    'details' => $e->getMessage()
                ]
            ]);
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
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        if (!$id) {
            return $response->setStatusCode(400)->setJSON([
                'error' => ['message' => 'Invalid appointment ID']
            ]);
        }
        
        try {
            $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
            
            if (!$payload) {
                return $response->setStatusCode(400)->setJSON([
                    'error' => ['message' => 'No request body']
                ]);
            }

            $update = [];
            if (!empty($payload['start'])) $update['start_time'] = $payload['start'];
            if (!empty($payload['end'])) $update['end_time'] = $payload['end'];
            if (!empty($payload['status'])) $update['status'] = $payload['status'];
            
            if (empty($update)) {
                return $response->setStatusCode(400)->setJSON([
                    'error' => ['message' => 'No updatable fields provided']
                ]);
            }

            $model = new AppointmentModel();
            if (!$model->find($id)) {
                return $response->setStatusCode(404)->setJSON([
                    'error' => ['message' => 'Appointment not found']
                ]);
            }
            
            $ok = $model->update($id, $update);
            
            if (!$ok) {
                return $response->setStatusCode(500)->setJSON([
                    'error' => ['message' => 'Update failed']
                ]);
            }

            // Enqueue rescheduled notification if time was changed
            if (!empty($update['start_time']) || !empty($update['end_time'])) {
                try {
                    $queue = new \App\Services\NotificationQueueService();
                    $businessId = \App\Services\NotificationPhase1::BUSINESS_ID_DEFAULT;
                    $queue->enqueueAppointmentEvent($businessId, 'email', 'appointment_rescheduled', (int) $id);
                    $queue->enqueueAppointmentEvent($businessId, 'whatsapp', 'appointment_rescheduled', (int) $id);
                } catch (\Throwable $e) {
                    log_message('error', 'Notification enqueue failed for rescheduled appointment {id}: {msg}', ['id' => (int) $id, 'msg' => $e->getMessage()]);
                }
            }
            
            return $response->setJSON([
                'data' => ['ok' => true],
                'message' => 'Appointment updated successfully'
            ]);
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to update appointment',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * Delete (cancel) appointment via API
     * DELETE /api/appointments/:id
     */
    public function delete($id = null)
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        if (!$id) {
            return $response->setStatusCode(400)->setJSON([
                'error' => ['message' => 'Invalid appointment ID']
            ]);
        }
        
        try {
            $model = new AppointmentModel();
            
            if (!$model->find($id)) {
                return $response->setStatusCode(404)->setJSON([
                    'error' => ['message' => 'Appointment not found']
                ]);
            }
            
            // Soft cancel instead of hard delete
            $ok = $model->update($id, ['status' => 'cancelled']);
            
            if (!$ok) {
                return $response->setStatusCode(500)->setJSON([
                    'error' => ['message' => 'Delete failed']
                ]);
            }

            // Phase 5: enqueue cancellation notifications
            try {
                $queue = new \App\Services\NotificationQueueService();
                $businessId = \App\Services\NotificationPhase1::BUSINESS_ID_DEFAULT;
                $queue->enqueueAppointmentEvent($businessId, 'email', 'appointment_cancelled', (int) $id);
                $queue->enqueueAppointmentEvent($businessId, 'whatsapp', 'appointment_cancelled', (int) $id);
            } catch (\Throwable $e) {
                log_message('error', 'Notification enqueue failed for appointment {id}: {msg}', ['id' => (int) $id, 'msg' => $e->getMessage()]);
            }
            
            return $response->setJSON([
                'data' => ['ok' => true],
                'message' => 'Appointment cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to delete appointment',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * Get appointment counts by time period
     * GET /api/appointments/counts?providerId=&serviceId=
     */
    public function counts()
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        try {
            $rules = [
                'providerId' => 'permit_empty|is_natural_no_zero',
                'serviceId'  => 'permit_empty|is_natural_no_zero',
            ];
            
            if (!$this->validate($rules)) {
                return $response->setStatusCode(400)->setJSON([
                    'error' => [
                        'message' => 'Invalid parameters',
                        'type' => 'validation_error',
                        'details' => $this->validator->getErrors()
                    ]
                ]);
            }

            $providerId = (int) ($this->request->getGet('providerId') ?? 0);
            $serviceId  = (int) ($this->request->getGet('serviceId') ?? 0);

            // Calculate server-side date ranges (local app timezone)
            $now = new \DateTimeImmutable('now');
            $todayStart = $now->setTime(0, 0, 0);
            $todayEnd   = $now->setTime(23, 59, 59);

            // Week: Sunday (0) to Saturday (6) to match UI
            $dow = (int) $now->format('w'); // 0 (Sun) - 6 (Sat)
            $weekStart = $todayStart->modify('-' . $dow . ' days');
            $weekEnd   = $weekStart->modify('+6 days')->setTime(23, 59, 59);

            // Month: first to last day
            $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
            $monthEnd   = $now->modify('last day of this month')->setTime(23, 59, 59);

            $counts = [
                'today' => $this->countInRange($providerId, $serviceId, $todayStart, $todayEnd),
                'week'  => $this->countInRange($providerId, $serviceId, $weekStart, $weekEnd),
                'month' => $this->countInRange($providerId, $serviceId, $monthStart, $monthEnd),
            ];

            return $response->setJSON([
                'data' => $counts
            ]);
        } catch (\Exception $e) {
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to get appointment counts',
                    'details' => $e->getMessage()
                ]
            ]);
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
     * Helper: Count appointments in a date range
     */
    private function countInRange(int $providerId, int $serviceId, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $model = new AppointmentModel();
        $builder = $model->builder();
        $builder->select('COUNT(*) AS c')
                ->where('start_time >=', $start->format('Y-m-d H:i:s'))
                ->where('start_time <=', $end->format('Y-m-d H:i:s'));
                
        if ($providerId > 0) {
            $builder->where('provider_id', $providerId);
        }
        if ($serviceId > 0) {
            $builder->where('service_id', $serviceId);
        }
        
        $row = $builder->get()->getRowArray();
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Send a manual notification for an appointment
     * POST /api/appointments/:id/notify
     * Body: { channel: 'email'|'sms'|'whatsapp', event_type?: string }
     */
    public function notify($id = null)
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        if (!$id) {
            return $response->setStatusCode(400)->setJSON([
                'error' => ['message' => 'Invalid appointment ID']
            ]);
        }
        
        $model = new AppointmentModel();
        $appointment = $model->find($id);
        
        if (!$appointment) {
            return $response->setStatusCode(404)->setJSON([
                'error' => ['message' => 'Appointment not found']
            ]);
        }
        
        $json = $this->request->getJSON(true) ?? [];
        $channel = strtolower(trim($json['channel'] ?? ''));
        $eventType = trim($json['event_type'] ?? '');
        
        $validChannels = ['email', 'sms', 'whatsapp'];
        if (!in_array($channel, $validChannels)) {
            return $response->setStatusCode(400)->setJSON([
                'error' => ['message' => 'Invalid channel. Must be one of: email, sms, whatsapp']
            ]);
        }
        
        // Auto-determine event type based on appointment status if not provided
        if ($eventType === '') {
            $status = $appointment['status'] ?? 'pending';
            $eventTypeMap = [
                'confirmed' => 'appointment_confirmed',
                'pending' => 'appointment_confirmed',
                'cancelled' => 'appointment_cancelled',
                'completed' => 'appointment_confirmed',
                'booked' => 'appointment_confirmed',
            ];
            $eventType = $eventTypeMap[$status] ?? 'appointment_confirmed';
        }
        
        try {
            $businessId = \App\Services\NotificationPhase1::BUSINESS_ID_DEFAULT;
            
            // Use direct sending for immediate feedback instead of queue
            if ($channel === 'email') {
                $svc = new \App\Services\AppointmentNotificationService();
                $sent = $svc->sendEventEmail($eventType, (int) $id, $businessId);
                
                if (!$sent) {
                    return $response->setStatusCode(400)->setJSON([
                        'error' => ['message' => 'Email not sent. Check if email is enabled for this event type and SMTP is configured.']
                    ]);
                }
                
                return $response->setJSON([
                    'data' => [
                        'ok' => true,
                        'channel' => $channel,
                        'event_type' => $eventType,
                        'message' => 'Email notification sent successfully'
                    ]
                ]);
            } elseif ($channel === 'sms') {
                // SMS implementation via NotificationSmsService
                $smsSvc = new \App\Services\NotificationSmsService();
                $templateSvc = new \App\Services\NotificationTemplateService();
                
                // Get appointment context
                $builder = $model->builder();
                $appt = $builder
                    ->select('xs_appointments.*, c.first_name as customer_first_name, c.last_name as customer_last_name, c.phone as customer_phone, s.name as service_name, u.name as provider_name')
                    ->join('xs_customers c', 'c.id = xs_appointments.customer_id', 'left')
                    ->join('xs_services s', 's.id = xs_appointments.service_id', 'left')
                    ->join('xs_users u', 'u.id = xs_appointments.provider_id', 'left')
                    ->where('xs_appointments.id', $id)
                    ->get()
                    ->getFirstRow('array');
                
                if (!$appt || empty($appt['customer_phone'])) {
                    return $response->setStatusCode(400)->setJSON([
                        'error' => ['message' => 'SMS not sent. Customer phone number not available.']
                    ]);
                }
                
                // Render template and send
                $message = $templateSvc->renderForAppointment($businessId, 'sms', $eventType, (int) $id);
                if (!$message) {
                    $message = "Your appointment on " . ($appt['start_time'] ?? 'TBD') . " is " . ($appt['status'] ?? 'confirmed') . ".";
                }
                
                $result = $smsSvc->sendSms($businessId, $appt['customer_phone'], $message);
                
                if (!($result['ok'] ?? false)) {
                    return $response->setStatusCode(400)->setJSON([
                        'error' => ['message' => 'SMS not sent: ' . ($result['error'] ?? 'Unknown error')]
                    ]);
                }
                
                return $response->setJSON([
                    'data' => [
                        'ok' => true,
                        'channel' => $channel,
                        'event_type' => $eventType,
                        'message' => 'SMS notification sent successfully'
                    ]
                ]);
            } elseif ($channel === 'whatsapp') {
                // WhatsApp - enqueue since it may need Link Generator flow
                $queue = new \App\Services\NotificationQueueService();
                $queue->enqueueAppointmentEvent($businessId, 'whatsapp', $eventType, (int) $id);
                
                return $response->setJSON([
                    'data' => [
                        'ok' => true,
                        'channel' => $channel,
                        'event_type' => $eventType,
                        'message' => 'WhatsApp notification queued for delivery'
                    ]
                ]);
            }
            
        } catch (\Throwable $e) {
            log_message('error', 'Manual notification failed for appointment {id}: {msg}', [
                'id' => (int) $id,
                'msg' => $e->getMessage()
            ]);
            
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to send notification',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }
}
