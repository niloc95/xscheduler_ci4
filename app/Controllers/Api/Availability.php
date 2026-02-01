<?php

/**
 * =============================================================================
 * AVAILABILITY API CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Api/Availability.php
 * @description API for checking provider availability and retrieving
 *              available time slots for appointment booking.
 * 
 * API ENDPOINTS:
 * -----------------------------------------------------------------------------
 * GET  /api/availability/slots          : Get available slots for a date
 * GET  /api/availability/calendar       : Get availability for date range
 * GET  /api/availability/check          : Check if specific slot is available
 * GET  /api/availability/providers      : Get providers with availability
 * 
 * SLOTS ENDPOINT PARAMETERS:
 * -----------------------------------------------------------------------------
 * - provider_id (required)  : Provider to check availability for
 * - date (required)         : Date in Y-m-d format
 * - service_id (required)   : Service to book (determines duration)
 * - buffer_minutes          : Buffer between appointments (0, 15, 30)
 * - timezone                : Client timezone for conversions
 * 
 * SLOTS RESPONSE:
 * -----------------------------------------------------------------------------
 * {
 *   "ok": true,
 *   "data": {
 *     "date": "2025-11-13",
 *     "provider_id": 2,
 *     "service_id": 1,
 *     "slots": [
 *       {
 *         "start": "09:00",
 *         "end": "10:00",
 *         "startTime": "2025-11-13T09:00:00+02:00",
 *         "endTime": "2025-11-13T10:00:00+02:00"
 *       }
 *     ],
 *     "timezone": "Africa/Johannesburg"
 *   }
 * }
 * 
 * AVAILABILITY LOGIC:
 * -----------------------------------------------------------------------------
 * Considers all scheduling constraints:
 * - Provider working hours
 * - Existing appointments
 * - Buffer time between appointments
 * - Service duration
 * - Provider breaks
 * - Business hours from settings
 * 
 * @see         app/Services/AvailabilityService.php for calculation logic
 * @see         app/Models/ProviderScheduleModel.php for schedules
 * @package     App\Controllers\Api
 * @extends     BaseApiController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers\Api;

use App\Services\AvailabilityService;
use App\Services\LocalizationSettingsService;

/**
 * Availability API Controller
 * 
 * Provides endpoints for checking provider availability and
 * fetching available time slots based on all scheduling constraints.
 */
class Availability extends BaseApiController
{
    private AvailabilityService $availabilityService;
    private LocalizationSettingsService $localizationService;

    public function __construct()
    {
        $this->availabilityService = new AvailabilityService();
        $this->localizationService = new LocalizationSettingsService();
    }

    /**
     * GET /api/availability/slots
     * 
     * Get available time slots for a provider on a specific date
     * 
     * Query Parameters:
     * - provider_id (required): Provider ID
     * - date (required): Date in Y-m-d format
     * - service_id (required): Service ID
     * - buffer_minutes (optional): Buffer time between appointments (0, 15, 30)
     * - timezone (optional): Timezone for calculations (defaults to system timezone)
     * 
     * Response:
     * {
     *   "ok": true,
     *   "data": {
     *     "date": "2025-11-13",
     *     "provider_id": 2,
     *     "service_id": 1,
     *     "slots": [
     *       {"start": "09:00", "end": "10:00", "startTime": "2025-11-13T09:00:00+02:00", "endTime": "2025-11-13T10:00:00+02:00"},
     *       {"start": "10:15", "end": "11:15", "startTime": "2025-11-13T10:15:00+02:00", "endTime": "2025-11-13T11:15:00+02:00"}
     *     ],
     *     "timezone": "Africa/Johannesburg"
     *   }
     * }
     */
    public function slots()
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        // Validate required parameters
        $providerId = $this->request->getGet('provider_id');
        $date = $this->request->getGet('date');
        $serviceId = $this->request->getGet('service_id');
        
        if (!$providerId || !$date || !$serviceId) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'Missing required parameters',
                    'required' => ['provider_id', 'date', 'service_id']
                ]
            ]);
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $response->setStatusCode(400)->setJSON([
                'error' => ['message' => 'Invalid date format. Use Y-m-d']
            ]);
        }
        
        // Get optional parameters
        $bufferMinutes = (int) ($this->request->getGet('buffer_minutes') ?? 
                                $this->availabilityService->getBufferTime((int)$providerId));
        $timezone = $this->request->getGet('timezone') ?? $this->localizationService->getTimezone();
        $excludeAppointmentId = $this->request->getGet('exclude_appointment_id');
        $excludeAppointmentId = $excludeAppointmentId !== null ? (int) $excludeAppointmentId : null;
        
        try {
            // Get available slots
            $slots = $this->availabilityService->getAvailableSlots(
                (int) $providerId,
                $date,
                (int) $serviceId,
                $bufferMinutes,
                $timezone,
                $excludeAppointmentId
            );
            
            // Format slots for response
            $formattedSlots = array_map(function($slot) use ($timezone) {
                return [
                    'start' => $slot['startFormatted'],
                    'end' => $slot['endFormatted'],
                    'startTime' => $slot['start']->format('c'), // ISO 8601 with timezone
                    'endTime' => $slot['end']->format('c')
                ];
            }, $slots);
            
            return $response->setJSON([
                'ok' => true,
                'data' => [
                    'date' => $date,
                    'provider_id' => (int) $providerId,
                    'service_id' => (int) $serviceId,
                    'buffer_minutes' => $bufferMinutes,
                    'slots' => $formattedSlots,
                    'total_slots' => count($formattedSlots),
                    'timezone' => $timezone
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', '[API/Availability::slots] Error: ' . $e->getMessage());
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to calculate available slots',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * POST /api/availability/check
     * 
     * Check if a specific time slot is available
     * 
     * JSON Body:
     * {
     *   "provider_id": 2,
     *   "start_time": "2025-11-13 09:00:00",
     *   "end_time": "2025-11-13 10:00:00",
     *   "timezone": "Africa/Johannesburg",
     *   "exclude_appointment_id": 5  // optional, for update checks
     * }
     * 
     * Response:
     * {
     *   "ok": true,
     *   "data": {
     *     "available": true,
     *     "conflicts": [],
     *     "reason": "",
     *     "checked_at": "2025-11-13T08:45:00+02:00"
     *   }
     * }
     */
    public function check()
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        $json = $this->request->getJSON(true);
        
        // Validate required fields
        $requiredFields = ['provider_id', 'start_time', 'end_time'];
        $missing = array_filter($requiredFields, fn($field) => !isset($json[$field]));
        
        if (!empty($missing)) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'Missing required fields',
                    'required' => $requiredFields,
                    'missing' => array_values($missing)
                ]
            ]);
        }
        
        $providerId = (int) $json['provider_id'];
        $startTime = $json['start_time'];
        $endTime = $json['end_time'];
        $timezone = $json['timezone'] ?? $this->localizationService->getTimezone();
        $excludeAppointmentId = isset($json['exclude_appointment_id']) ? (int) $json['exclude_appointment_id'] : null;
        
        try {
            // Check availability
            $result = $this->availabilityService->isSlotAvailable(
                $providerId,
                $startTime,
                $endTime,
                $timezone,
                $excludeAppointmentId
            );
            
            return $response->setJSON([
                'ok' => true,
                'data' => array_merge($result, [
                    'checked_at' => (new \DateTime('now', new \DateTimeZone($timezone)))->format('c')
                ])
            ]);
            
        } catch (\Exception $e) {
            log_message('error', '[API/Availability::check] Error: ' . $e->getMessage());
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to check availability',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * GET /api/availability/summary
     * 
     * Get availability summary for a provider across multiple days
     * 
     * Query Parameters:
     * - provider_id (required): Provider ID
     * - start_date (required): Start date in Y-m-d format
     * - end_date (required): End date in Y-m-d format
     * - service_id (required): Service ID
     * 
     * Response:
     * {
     *   "ok": true,
     *   "data": {
     *     "2025-11-13": {"total_slots": 8, "available_slots": 5, "working_day": true},
     *     "2025-11-14": {"total_slots": 8, "available_slots": 3, "working_day": true},
     *     "2025-11-15": {"total_slots": 0, "available_slots": 0, "working_day": false}
     *   }
     * }
     */
    public function summary()
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        $providerId = $this->request->getGet('provider_id');
        $startDate = $this->request->getGet('start_date');
        $endDate = $this->request->getGet('end_date');
        $serviceId = $this->request->getGet('service_id');
        
        if (!$providerId || !$startDate || !$endDate || !$serviceId) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'Missing required parameters',
                    'required' => ['provider_id', 'start_date', 'end_date', 'service_id']
                ]
            ]);
        }
        
        try {
            $summary = [];
            $current = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            $timezone = $this->localizationService->getTimezone();
            $bufferMinutes = $this->availabilityService->getBufferTime((int)$providerId);
            
            while ($current <= $end) {
                $dateStr = $current->format('Y-m-d');
                
                $slots = $this->availabilityService->getAvailableSlots(
                    (int) $providerId,
                    $dateStr,
                    (int) $serviceId,
                    $bufferMinutes,
                    $timezone
                );
                
                $summary[$dateStr] = [
                    'total_slots' => count($slots),
                    'available_slots' => count($slots),
                    'working_day' => count($slots) > 0,
                    'date' => $dateStr,
                    'day_of_week' => $current->format('l')
                ];
                
                $current->modify('+1 day');
            }
            
            return $response->setJSON([
                'ok' => true,
                'data' => $summary
            ]);
            
        } catch (\Exception $e) {
            log_message('error', '[API/Availability::summary] Error: ' . $e->getMessage());
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to generate availability summary',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }

    public function calendar()
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');

        $providerId = (int) ($this->request->getGet('provider_id') ?? 0);
        $serviceId = (int) ($this->request->getGet('service_id') ?? 0);
        $startDate = $this->request->getGet('start_date');
        $days = (int) ($this->request->getGet('days') ?? 60);
        $timezone = $this->request->getGet('timezone') ?? $this->localizationService->getTimezone();

        if ($providerId <= 0 || $serviceId <= 0) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'provider_id and service_id are required',
                ],
            ]);
        }

        if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'start_date must use Y-m-d format',
                ],
            ]);
        }

        $days = max(1, min($days, 120));

        // Optional: exclude current appointment when editing
        $excludeAppointmentId = $this->request->getGet('exclude_appointment_id');
        $excludeAppointmentId = $excludeAppointmentId !== null ? (int) $excludeAppointmentId : null;

        try {
            $calendar = $this->availabilityService->getCalendarAvailability(
                $providerId,
                $serviceId,
                $startDate,
                $days,
                $timezone,
                $excludeAppointmentId
            );

            return $response->setJSON([
                'ok' => true,
                'data' => $calendar,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[API/Availability::calendar] Error: ' . $e->getMessage());
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to calculate availability calendar',
                    'details' => $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * GET /api/availability/next-available
     * 
     * Find the next available slot for a provider starting from a given date/time
     * 
     * Query Parameters:
     * - provider_id (required): Provider ID
     * - service_id (required): Service ID
     * - from_date (optional): Start searching from this date (defaults to today)
     * - from_time (optional): Start searching from this time (defaults to now)
     * - days_ahead (optional): How many days to search ahead (default 30, max 90)
     * 
     * Response:
     * {
     *   "ok": true,
     *   "data": {
     *     "found": true,
     *     "slot": {"start": "09:00", "end": "10:00", "date": "2025-11-14"},
     *     "days_from_now": 1
     *   }
     * }
     */
    public function nextAvailable()
    {
        $response = $this->response->setHeader('Content-Type', 'application/json');
        
        $providerId = $this->request->getGet('provider_id');
        $serviceId = $this->request->getGet('service_id');
        
        if (!$providerId || !$serviceId) {
            return $response->setStatusCode(400)->setJSON([
                'error' => [
                    'message' => 'Missing required parameters',
                    'required' => ['provider_id', 'service_id']
                ]
            ]);
        }
        
        $fromDate = $this->request->getGet('from_date') ?? date('Y-m-d');
        $daysAhead = min((int) ($this->request->getGet('days_ahead') ?? 30), 90);
        $timezone = $this->localizationService->getTimezone();
        $bufferMinutes = $this->availabilityService->getBufferTime((int)$providerId);
        
        try {
            $current = new \DateTime($fromDate, new \DateTimeZone($timezone));
            $endSearch = clone $current;
            $endSearch->modify("+{$daysAhead} days");
            
            while ($current <= $endSearch) {
                $dateStr = $current->format('Y-m-d');
                
                $slots = $this->availabilityService->getAvailableSlots(
                    (int) $providerId,
                    $dateStr,
                    (int) $serviceId,
                    $bufferMinutes,
                    $timezone
                );
                
                if (!empty($slots)) {
                    // Found available slot(s)
                    $firstSlot = $slots[0];
                    $daysFromNow = $current->diff(new \DateTime('now', new \DateTimeZone($timezone)))->days;
                    
                    return $response->setJSON([
                        'ok' => true,
                        'data' => [
                            'found' => true,
                            'slot' => [
                                'start' => $firstSlot['startFormatted'],
                                'end' => $firstSlot['endFormatted'],
                                'date' => $dateStr,
                                'startTime' => $firstSlot['start']->format('c'),
                                'endTime' => $firstSlot['end']->format('c')
                            ],
                            'days_from_now' => $daysFromNow,
                            'searched_until' => $dateStr
                        ]
                    ]);
                }
                
                $current->modify('+1 day');
            }
            
            // No available slots found
            return $response->setJSON([
                'ok' => true,
                'data' => [
                    'found' => false,
                    'message' => 'No available slots found in the next ' . $daysAhead . ' days',
                    'searched_until' => $endSearch->format('Y-m-d')
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', '[API/Availability::nextAvailable] Error: ' . $e->getMessage());
            return $response->setStatusCode(500)->setJSON([
                'error' => [
                    'message' => 'Failed to find next available slot',
                    'details' => $e->getMessage()
                ]
            ]);
        }
    }
}
