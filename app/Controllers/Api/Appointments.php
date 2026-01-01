<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AppointmentModel;
use App\Services\LocalizationSettingsService;
use App\Services\TimezoneService;
use App\Services\SchedulingService;

class Appointments extends BaseController
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
                $startIso = $this->formatUtc($appointment['start_time'] ?? null) ?? ($appointment['start_time'] ?? null);
                $endIso = $this->formatUtc($appointment['end_time'] ?? null) ?? ($appointment['end_time'] ?? null);

                log_message('debug', '[API/Appointments::index] Appointment #' . $appointment['id'] . ':', [
                    'customer' => $appointment['customer_name'],
                    'utc_start' => $startIso,
                    'utc_end' => $endIso,
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
                    // Note: start_time/end_time removed - use 'start'/'end' instead (ISO 8601)
                ];
            }, $appointments);
            
            log_message('info', '[API/Appointments::index] Returning ' . count($events) . ' events in UTC format');
            log_message('info', '[API/Appointments::index] Note: Frontend custom scheduler will handle timezone conversion');
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
            $builder = $model->builder();
            
            // Select appointment with all related data
            $builder->select('xs_appointments.*, 
                             CONCAT(c.first_name, " ", COALESCE(c.last_name, "")) as customer_name,
                             c.email as customer_email,
                             c.phone as customer_phone,
                             s.name as service_name,
                             s.duration_min as duration,
                             s.price as price,
                             u.name as provider_name,
                             u.color as provider_color')
                    ->join('xs_customers c', 'c.id = xs_appointments.customer_id', 'left')
                    ->join('xs_services s', 's.id = xs_appointments.service_id', 'left')
                    ->join('xs_users u', 'u.id = xs_appointments.provider_id', 'left')
                    ->where('xs_appointments.id', (int)$id);
            
            $appointment = $builder->get()->getRowArray();
            
            if (!$appointment) {
                return $response->setStatusCode(404)->setJSON([
                    'error' => [
                        'message' => 'Appointment not found'
                    ]
                ]);
            }
            
            // Format response
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
                'start_time' => $this->formatUtc($appointment['start_time'] ?? null) ?? ($appointment['start_time'] ?? null),
                'end_time' => $this->formatUtc($appointment['end_time'] ?? null) ?? ($appointment['end_time'] ?? null),
                'start' => $this->formatUtc($appointment['start_time'] ?? null) ?? ($appointment['start_time'] ?? null),
                'end' => $this->formatUtc($appointment['end_time'] ?? null) ?? ($appointment['end_time'] ?? null),
                'duration' => $appointment['duration'],
                'price' => $appointment['price'],
                'status' => $appointment['status'],
                'notes' => $appointment['notes'] ?? '',
                'location' => $appointment['location'] ?? '',
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

            $startTimeUtc = TimezoneService::toUTC($startDateTime->format('Y-m-d H:i:s'), $timezone);
            $endTimeUtc = TimezoneService::toUTC($endDateTime->format('Y-m-d H:i:s'), $timezone);
            
            // Check for overlapping appointments
            $model = new AppointmentModel();
            $builder = $model->builder();
            $builder->where('provider_id', (int)$providerId)
                    ->where('status !=', 'cancelled')
                    ->groupStart()
                        // New appointment starts during existing appointment
                        ->groupStart()
                            ->where('start_time <=', $startTimeUtc)
                            ->where('end_time >', $startTimeUtc)
                        ->groupEnd()
                        // New appointment ends during existing appointment
                        ->orGroupStart()
                            ->where('start_time <', $endTimeUtc)
                            ->where('end_time >=', $endTimeUtc)
                        ->groupEnd()
                        // New appointment completely contains existing appointment
                        ->orGroupStart()
                            ->where('start_time >=', $startTimeUtc)
                            ->where('end_time <=', $endTimeUtc)
                        ->groupEnd()
                    ->groupEnd();
            
            // If editing, exclude current appointment
            if ($appointmentId) {
                $builder->where('id !=', (int)$appointmentId);
            }
            
            $conflicts = $builder->get()->getResultArray();
            
            // Check business hours
            // Convert day name to weekday number (0=Sunday, 1=Monday, etc.)
            $dayOfWeekName = strtolower($startDateTime->format('l'));
            $dayMapping = [
                'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                'thursday' => 4, 'friday' => 5, 'saturday' => 6
            ];
            $weekdayNum = $dayMapping[$dayOfWeekName] ?? 0;
            
            // Query business_hours table - uses 'weekday' column (0-6)
            // If no row exists for this weekday, business is closed on that day
            $businessHours = $db->table('business_hours')
                ->where('weekday', $weekdayNum)
                ->get()
                ->getRowArray();
            
            $businessHoursViolation = null;
            if (!$businessHours) {
                $businessHoursViolation = 'Business is closed on ' . ucfirst($dayOfWeekName);
            } else {
                $requestedTime = $startDateTime->format('H:i:s');
                if ($requestedTime < $businessHours['start_time'] || $requestedTime >= $businessHours['end_time']) {
                    $businessHoursViolation = 'Requested time is outside business hours (' . 
                        date('g:i A', strtotime($businessHours['start_time'])) . ' - ' . 
                        date('g:i A', strtotime($businessHours['end_time'])) . ')';
                }
                
                // Check if end time exceeds business hours
                $requestedEndTime = $endDateTime->format('H:i:s');
                if ($requestedEndTime > $businessHours['end_time']) {
                    $businessHoursViolation = 'Appointment would extend past business hours';
                }
            }
            
            // Check blocked times
            $blockedTimes = $db->table('blocked_times')
                ->where('provider_id', (int)$providerId)
                ->groupStart()
                    // Blocked time overlaps with appointment start
                    ->groupStart()
                        ->where('start_time <=', $startTimeUtc)
                        ->where('end_time >', $startTimeUtc)
                    ->groupEnd()
                    // Blocked time overlaps with appointment end
                    ->orGroupStart()
                        ->where('start_time <', $endTimeUtc)
                        ->where('end_time >=', $endTimeUtc)
                    ->groupEnd()
                    // Appointment completely contains blocked time
                    ->orGroupStart()
                        ->where('start_time >=', $startTimeUtc)
                        ->where('end_time <=', $endTimeUtc)
                    ->groupEnd()
                ->groupEnd()
                ->get()
                ->getResultArray();
            
            // Determine availability
            $available = (count($conflicts) === 0 && !$businessHoursViolation && count($blockedTimes) === 0);
            
            // Build response
            $result = [
                'available' => $available,
                'requestedSlot' => [
                    'provider_id' => (int)$providerId,
                    'service_id' => (int)$serviceId,
                    'service_name' => $service['name'],
                    'duration_min' => (int)$service['duration_min'],
                    'start_time_local' => $startDateTime->format('Y-m-d H:i:s'),
                    'end_time_local' => $endDateTime->format('Y-m-d H:i:s'),
                    'start_time_utc' => $startTimeUtc,
                    'end_time_utc' => $endTimeUtc,
                    'timezone' => $timezone,
                ],
                'conflicts' => [],
                'businessHoursViolation' => $businessHoursViolation,
                'blockedTimeConflicts' => count($blockedTimes),
            ];
            
            // Add conflict details
            if (count($conflicts) > 0) {
                foreach ($conflicts as $conflict) {
                    $result['conflicts'][] = [
                        'id' => $conflict['id'],
                        'start_time' => $this->formatUtc($conflict['start_time'] ?? null) ?? ($conflict['start_time'] ?? null),
                        'end_time' => $this->formatUtc($conflict['end_time'] ?? null) ?? ($conflict['end_time'] ?? null),
                        'status' => $conflict['status'],
                    ];
                }
            }
            
            // Suggest next available slot if not available
            if (!$available) {
                // Find next available time slot (simplified - just suggest 30 min later)
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

    private function formatUtc(?string $datetime): ?string
    {
        if (!$datetime) {
            return null;
        }

        try {
            $dt = new \DateTime($datetime, new \DateTimeZone('UTC'));
            return $dt->format('Y-m-d\TH:i:s\Z');
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
