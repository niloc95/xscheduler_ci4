<?php

/**
 * =============================================================================
 * AVAILABILITY SERVICE
 * =============================================================================
 * 
 * @file        app/Services/AvailabilityService.php
 * @description Core service for calculating provider availability. Single
 *              source of truth for all availability-related calculations.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Calculates available booking slots considering ALL constraints:
 * - Global business hours
 * - Provider working schedule (weekly recurring)
 * - Provider breaks (lunch, etc.)
 * - Blocked times (vacations, one-time unavailability)
 * - Existing appointments
 * - Buffer time between appointments
 * - Service duration requirements
 * - Location-based availability (if multi-location)
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * - getAvailableSlots(providerId, date, serviceId, ..., locationId)
 *   Returns array of bookable time slots (optionally filtered by location)
 * 
 * - isSlotAvailable(providerId, start, end, ..., locationId)
 *   Check if specific time range is available (optionally for a location)
 * 
 * - getCalendarAvailability(providerId, startDate, endDate, ..., locationId)
 *   Get availability for date range (optionally filtered by location)
 * 
 * - hasConflict(providerId, start, end, excludeId)
 *   Check for conflicts with existing appointments
 * 
 * SLOT CALCULATION FLOW:
 * -----------------------------------------------------------------------------
 * 1. Get provider's working hours for the day
 * 2. Split into slots based on service duration
 * 3. Remove slots that overlap with breaks
 * 4. Remove slots that overlap with blocked times
 * 5. Remove slots that conflict with existing appointments
 * 6. Apply buffer time between available slots
 * 7. Return remaining slots as available
 * 
 * @see         app/Controllers/Api/Availability.php for API layer
 * @see         app/Models/ProviderScheduleModel.php for schedule data
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\BusinessHourModel;
use App\Models\LocationModel;
use App\Models\ProviderScheduleModel;
use App\Models\BlockedTimeModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use App\Services\ConflictService;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;

/**
 * AvailabilityService
 * 
 * Centralized service for calculating provider availability by combining:
 * - Global business hours
 * - Blocked periods (holidays/closures)
 * - Provider-specific schedules
 * - Provider breaks
 * - Buffer time between appointments
 * - Service duration
 * - Existing appointments
 * 
 * This service ensures all scheduling constraints are respected and provides
 * a single source of truth for availability calculations.
 */
class AvailabilityService
{
    private AppointmentModel $appointmentModel;
    private BusinessHourModel $businessHourModel;
    private LocationModel $locationModel;
    private ProviderScheduleModel $providerScheduleModel;
    private BlockedTimeModel $blockedTimeModel;
    private ServiceModel $serviceModel;
    private SettingModel $settingModel;
    private LocalizationSettingsService $localizationService;
    private ConflictService $conflictService;
    
    private array $cache = [];

    public function __construct()
    {
        $this->appointmentModel = new AppointmentModel();
        $this->businessHourModel = new BusinessHourModel();
        $this->locationModel = new LocationModel();
        $this->providerScheduleModel = new ProviderScheduleModel();
        $this->blockedTimeModel = new BlockedTimeModel();
        $this->serviceModel = new ServiceModel();
        $this->settingModel = new SettingModel();
        $this->localizationService = new LocalizationSettingsService();
        $this->conflictService = new ConflictService();
    }

    /**
     * Get available time slots for a provider on a specific date
     * 
     * @param int $providerId Provider ID
     * @param string $date Date in Y-m-d format
     * @param int $serviceId Service ID (determines slot duration)
     * @param int $bufferMinutes Buffer time between appointments (0, 15, or 30)
     * @param string $timezone Timezone for the calculation (default: system timezone)
     * @param int|null $excludeAppointmentId Appointment to exclude (for reschedule)
     * @param int|null $locationId Location ID — when set, verify location operates on this day
     * @return array Array of available slots with start and end times
     */
    public function getAvailableSlots(
        int $providerId,
        string $date,
        int $serviceId,
        int $bufferMinutes = 0,
        ?string $timezone = null,
        ?int $excludeAppointmentId = null,
        ?int $locationId = null
    ): array {
        $timezone = $timezone ?? $this->localizationService->getTimezone();
        $tz = new DateTimeZone($timezone);

        $locationContext = $this->resolveLocationContext($providerId, $locationId);
        if (!$locationContext['valid']) {
            log_message('warning', '[AvailabilityService] Location context invalid: ' . ($locationContext['reason'] ?? 'unknown'));
            return [];
        }
        $locationId = $locationContext['location_id'];
        
        log_message('info', '[AvailabilityService] Calculating slots for provider ' . $providerId . ' on ' . $date);
        
        // Step 1: Check if date is blocked globally
        if ($this->isDateBlocked($date)) {
            log_message('info', '[AvailabilityService] Date is globally blocked');
            return [];
        }

        // Step 1b: If a location is specified, check that the location operates on this day
        if ($locationId !== null) {
            $dateObj = new \DateTime($date);
            $dayInt = (int) $dateObj->format('w'); // 0=Sun … 6=Sat
            $locationDays = $this->locationModel->getLocationDays($locationId);
            if (!in_array($dayInt, $locationDays, true)) {
                log_message('info', '[AvailabilityService] Location ' . $locationId . ' does not operate on day ' . $dayInt);
                return [];
            }
        }
        
        // Step 2: Get service duration
        $service = $this->serviceModel->find($serviceId);
        if (!$service) {
            log_message('error', '[AvailabilityService] Service not found: ' . $serviceId);
            return [];
        }
        $serviceDuration = (int) ($service['duration_min'] ?? 60);
        
        // Step 3: Get provider's working hours for this day
        $providerHours = $this->getProviderHoursForDate($providerId, $date);
        if (!$providerHours) {
            log_message('info', '[AvailabilityService] Provider not working on this date');
            return [];
        }
        
        // Step 4: Get all busy periods (appointments + blocked times)
        $busyPeriods = $this->getBusyPeriods($providerId, $date, $timezone, $bufferMinutes, $excludeAppointmentId, $locationId);
        log_message('info', '[AvailabilityService] Found ' . count($busyPeriods) . ' busy periods for provider ' . $providerId . ' on ' . $date);
        foreach ($busyPeriods as $busy) {
            log_message('info', '[AvailabilityService] Busy: ' . $busy['start']->format('Y-m-d H:i:s') . ' to ' . $busy['end']->format('Y-m-d H:i:s') . ' (' . $busy['type'] . ')');
        }
        
        // Step 5: Generate candidate slots
        $candidateSlots = $this->generateCandidateSlots(
            $date,
            $providerHours,
            $serviceDuration,
            $bufferMinutes,
            $timezone
        );
        
        // Step 6: Filter out slots that overlap with busy periods
        $availableSlots = array_filter($candidateSlots, function($slot) use ($busyPeriods) {
            $overlaps = $this->slotOverlapsBusyPeriods($slot, $busyPeriods);
            if ($overlaps) {
                log_message('info', '[AvailabilityService] Slot ' . $slot['start']->format('H:i') . '-' . $slot['end']->format('H:i') . ' overlaps with busy period, filtered out');
            }
            return !$overlaps;
        });
        
        log_message('info', '[AvailabilityService] Candidate slots: ' . count($candidateSlots) . ', Available slots after filtering: ' . count($availableSlots));
        
        return array_values($availableSlots);
    }

    /**
     * Check if a specific time slot is available
     * 
     * @param int $providerId Provider ID
     * @param string $startTime Start time in Y-m-d H:i:s format
     * @param string $endTime End time in Y-m-d H:i:s format
     * @param string $timezone Timezone for the check
     * @param int|null $excludeAppointmentId Appointment ID to exclude (for updates)
     * @param int|null $locationId Location ID — when set, verify location operates on this day
     * @return array ['available' => bool, 'conflicts' => array, 'reason' => string]
     */
    public function isSlotAvailable(
        int $providerId,
        string $startTime,
        string $endTime,
        string $timezone = 'UTC',
        ?int $excludeAppointmentId = null,
        ?int $locationId = null
    ): array {
        $start = new DateTime($startTime, new DateTimeZone($timezone));
        $end = new DateTime($endTime, new DateTimeZone($timezone));
        $date = $start->format('Y-m-d');

        $locationContext = $this->resolveLocationContext($providerId, $locationId);
        if (!$locationContext['valid']) {
            return [
                'available' => false,
                'conflicts' => [],
                'reason' => $locationContext['reason'] ?? 'Invalid location context'
            ];
        }
        $locationId = $locationContext['location_id'];
        
        // Check if date is blocked
        if ($this->isDateBlocked($date)) {
            return [
                'available' => false,
                'conflicts' => [],
                'reason' => 'Date is blocked (holiday/closure)'
            ];
        }

        // Check location operating day (if location specified)
        if ($locationId !== null) {
            $dayInt = (int) $start->format('w'); // 0=Sun … 6=Sat
            $locationDays = $this->locationModel->getLocationDays($locationId);
            if (!in_array($dayInt, $locationDays, true)) {
                return [
                    'available' => false,
                    'conflicts' => [],
                    'reason' => 'Location does not operate on this day'
                ];
            }
        }
        
        // Check provider working hours
        $providerHours = $this->getProviderHoursForDate($providerId, $date);
        if (!$providerHours) {
            return [
                'available' => false,
                'conflicts' => [],
                'reason' => 'Provider not working on this date'
            ];
        }
        
        // Check if within provider's working hours
        $slotStart = $start->format('H:i:s');
        $slotEnd = $end->format('H:i:s');
        
        if ($slotStart < $providerHours['start_time'] || $slotEnd > $providerHours['end_time']) {
            return [
                'available' => false,
                'conflicts' => [],
                'reason' => 'Outside provider working hours (' . 
                    $providerHours['start_time'] . ' - ' . 
                    $providerHours['end_time'] . ')'
            ];
        }
        
        // Check if slot overlaps with provider's breaks
        if (isset($providerHours['breaks']) && is_array($providerHours['breaks'])) {
            foreach ($providerHours['breaks'] as $break) {
                if ($this->timesOverlap($slotStart, $slotEnd, $break['start'], $break['end'])) {
                    return [
                        'available' => false,
                        'conflicts' => [],
                        'reason' => 'Overlaps with provider break time'
                    ];
                }
            }
        }
        
        // Check for appointment conflicts (DB stores UTC — convert)
        $utcTz = new DateTimeZone('UTC');
        $startUtc = (clone $start)->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $endUtc   = (clone $end)->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $conflicts = $this->conflictService->getConflictingAppointments(
            $providerId,
            $startUtc,
            $endUtc,
            $excludeAppointmentId,
            $locationId
        );
        
        if (!empty($conflicts)) {
            return [
                'available' => false,
                'conflicts' => $conflicts,
                'reason' => 'Conflicts with ' . count($conflicts) . ' existing appointment(s)'
            ];
        }
        
        // Check for blocked time periods
        $blockedTimes = $this->conflictService->getBlockedTimesForPeriod(
            $providerId,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );
        
        if (!empty($blockedTimes)) {
            return [
                'available' => false,
                'conflicts' => [],
                'reason' => 'Time period is blocked'
            ];
        }
        
        return [
            'available' => true,
            'conflicts' => [],
            'reason' => ''
        ];
    }

    /**
     * Check if a date is globally blocked (holiday/closure)
     */
    private function isDateBlocked(string $date): bool
    {
        $cacheKey = 'blocked_periods';
        
        if (!isset($this->cache[$cacheKey])) {
            $settings = $this->settingModel->getByKeys(['business.blocked_periods']);
            $blockedPeriods = $settings['business.blocked_periods'] ?? [];
            
            // If it's a string, decode it
            if (is_string($blockedPeriods)) {
                $blockedPeriods = json_decode($blockedPeriods, true) ?: [];
            }
            
            $this->cache[$cacheKey] = $blockedPeriods;
        }
        
        $blockedPeriods = $this->cache[$cacheKey];
        
        foreach ($blockedPeriods as $period) {
            if (isset($period['start']) && isset($period['end']) && 
                $date >= $period['start'] && $date <= $period['end']) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if a provider has working hours on a specific date.
     * If no providerId is given, checks if ANY provider has working hours.
     */
    public function hasWorkingHours(string $date, ?int $providerId = null): bool
    {
        if ($providerId !== null) {
            return $this->getProviderHoursForDate($providerId, $date) !== null;
        }

        // If no provider specified, check if any provider has hours
        $dateTime = new DateTime($date);
        $weekday = (int) $dateTime->format('w');
        $dayOfWeek = strtolower($dateTime->format('l'));

        // Check custom schedules first
        $hasCustom = $this->providerScheduleModel
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', 1)
            ->countAllResults() > 0;

        if ($hasCustom) {
            return true;
        }

        // Fallback to global business hours
        return $this->businessHourModel
            ->where('weekday', $weekday)
            ->where('is_closed', 0)
            ->countAllResults() > 0;
    }

    /**
     * Return the set of weekday numbers (0=Sun … 6=Sat) that have working hours.
     *
     * Executes at most 2 DB queries regardless of the date range, making it safe
     * to call once before iterating over a 42-cell month grid instead of calling
     * hasWorkingHours() per cell (which would trigger up to 42 queries).
     *
     * @param int|null $providerId  null = check global business hours / any provider
     * @return int[]  e.g. [1, 2, 3, 4, 5]
     */
    public function getWorkingWeekdays(?int $providerId = null): array
    {
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        if ($providerId !== null) {
            // Provider-specific custom schedule
            $customRows = $this->providerScheduleModel
                ->where('provider_id', $providerId)
                ->where('is_active', 1)
                ->findAll();

            if (!empty($customRows)) {
                $nums = [];
                foreach ($customRows as $row) {
                    $idx = array_search($row['day_of_week'], $dayNames, true);
                    if ($idx !== false) {
                        $nums[] = $idx;
                    }
                }
                return array_values(array_unique($nums));
            }

            // Fall back to global business hours for this provider
            $rows = $this->businessHourModel
                ->where('provider_id', $providerId)
                ->where('is_closed', 0)
                ->findAll();

            return array_values(array_unique(array_column($rows, 'weekday')));
        }

        // No specific provider — check all custom schedules first
        $customRows = $this->providerScheduleModel
            ->where('is_active', 1)
            ->findAll();

        if (!empty($customRows)) {
            $nums = [];
            foreach ($customRows as $row) {
                $idx = array_search($row['day_of_week'], $dayNames, true);
                if ($idx !== false) {
                    $nums[] = $idx;
                }
            }
            return array_values(array_unique($nums));
        }

        // Fall back to global business hours (no provider_id filter)
        $rows = $this->businessHourModel
            ->where('is_closed', 0)
            ->findAll();

        return array_values(array_unique(array_column($rows, 'weekday')));
    }

    /**
     * Get provider's working hours for a specific date
     * Returns array with start_time, end_time, and breaks, or null if not working
     */
    private function getProviderHoursForDate(int $providerId, string $date): ?array
    {
        $dateTime = new DateTime($date);
        $weekday = (int) $dateTime->format('w'); // 0=Sunday, 1=Monday, etc.
        $dayOfWeek = strtolower($dateTime->format('l')); // 'monday', 'tuesday', etc.
        
        // First check xs_provider_schedules (provider-specific schedule)
        // Use the model's dedicated method instead of manual query
        $providerSchedule = $this->providerScheduleModel->getActiveDay($providerId, $dayOfWeek);
        
        if ($providerSchedule) {
            // Provider has a custom schedule for this day
            return [
                'start_time' => $providerSchedule['start_time'],
                'end_time' => $providerSchedule['end_time'],
                'breaks' => $this->parseBreaks($providerSchedule['break_start'], $providerSchedule['break_end'])
            ];
        }
        
        // Fall back to xs_business_hours (global business hours per provider)
        $businessHours = $this->businessHourModel
            ->where('provider_id', $providerId)
            ->where('weekday', $weekday)
            ->first();
        
        if ($businessHours) {
            $breaks = [];
            if (!empty($businessHours['breaks_json'])) {
                $breaks = json_decode($businessHours['breaks_json'], true) ?: [];
            }
            
            return [
                'start_time' => $businessHours['start_time'],
                'end_time' => $businessHours['end_time'],
                'breaks' => $breaks
            ];
        }
        
        // No schedule found for this provider/day
        return null;
    }

    /**
     * Parse break times into array format
     */
    private function parseBreaks(?string $breakStart, ?string $breakEnd): array
    {
        if (!$breakStart || !$breakEnd) {
            return [];
        }
        
        return [
            [
                'start' => $breakStart,
                'end' => $breakEnd
            ]
        ];
    }

    /**
     * Resolve and validate provider location context.
     *
    * Rules:
    * - No active locations => location not required.
    * - Any active locations => explicit location is required.
    * - Provided location must be active and owned by provider.
     *
     * @return array{valid:bool, location_id:?int, reason:?string}
     */
    private function resolveLocationContext(int $providerId, ?int $locationId): array
    {
        $activeLocations = $this->locationModel->getProviderLocations($providerId, true);
        $activeLocationIds = array_values(array_filter(array_map(static fn(array $loc): int => (int) ($loc['id'] ?? 0), $activeLocations)));

        if (empty($activeLocationIds)) {
            return ['valid' => true, 'location_id' => null, 'reason' => null];
        }

        if ($locationId !== null) {
            if (!in_array($locationId, $activeLocationIds, true)) {
                return [
                    'valid' => false,
                    'location_id' => null,
                    'reason' => 'Selected location is unavailable for this provider'
                ];
            }

            return ['valid' => true, 'location_id' => $locationId, 'reason' => null];
        }

        return [
            'valid' => false,
            'location_id' => null,
            'reason' => 'location_id is required for providers with active locations'
        ];
    }

    /**
     * Get all busy periods for a provider on a specific date
     * Returns array of ['start' => DateTime (local TZ), 'end' => DateTime (local TZ)]
     */
    private function getBusyPeriods(int $providerId, string $date, string $timezone, int $bufferMinutes = 0, ?int $excludeAppointmentId = null, ?int $locationId = null): array
    {
        $busy = [];
        $tz = new DateTimeZone($timezone);
        $utcTz = new DateTimeZone('UTC');
        
        // Appointments are stored in UTC — convert local day boundaries to UTC for query
        $localStartOfDay = new DateTime($date . ' 00:00:00', $tz);
        $localEndOfDay   = new DateTime($date . ' 23:59:59', $tz);
        $startOfDayUtc = (clone $localStartOfDay)->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $endOfDayUtc   = (clone $localEndOfDay)->setTimezone($utcTz)->format('Y-m-d H:i:s');
        
        // Also keep local boundaries for blocked_times (still stored in local TZ)
        $startOfDayLocal = $date . ' 00:00:00';
        $endOfDayLocal   = $date . ' 23:59:59';
        
        $appointmentsQuery = $this->appointmentModel
            ->where('provider_id', $providerId)
            ->where('status !=', 'cancelled')
            ->where('start_at >=', $startOfDayUtc)
            ->where('start_at <=', $endOfDayUtc)
            ->when($excludeAppointmentId !== null, function($builder) use ($excludeAppointmentId) {
                $builder->where('id !=', $excludeAppointmentId);
            });

        if ($locationId !== null) {
            $appointmentsQuery->where('location_id', $locationId);
        }

        $appointments = $appointmentsQuery->findAll();
        
        foreach ($appointments as $apt) {
            // DB stores UTC — parse as UTC, then convert to local for busy-period comparison
            $start = (new DateTime($apt['start_at'], $utcTz))->setTimezone($tz);
            $end   = (new DateTime($apt['end_at'], $utcTz))->setTimezone($tz);
            
            // Add buffer time after appointment ends to prevent back-to-back bookings
            if ($bufferMinutes > 0) {
                $end->add(new DateInterval('PT' . $bufferMinutes . 'M'));
            }
            
            $busy[] = [
                'start' => $start,
                'end' => $end,
                'type' => 'appointment'
            ];
        }
        
        // Blocked times are still stored in local timezone
        $blockedTimes = $this->blockedTimeModel
            ->where('provider_id', $providerId)
            ->where('start_time <=', $endOfDayLocal)
            ->where('end_time >=', $startOfDayLocal)
            ->findAll();
        
        foreach ($blockedTimes as $block) {
            // Blocked times stored in local timezone
            $blockStart = new DateTime($block['start_time'], $tz);
            $blockEnd = new DateTime($block['end_time'], $tz);
            
            $busy[] = [
                'start' => $blockStart,
                'end' => $blockEnd,
                'type' => 'blocked'
            ];
        }
        
        return $busy;
    }

    /**
     * Generate candidate time slots for a day
     */
    private function generateCandidateSlots(
        string $date,
        array $providerHours,
        int $serviceDuration,
        int $bufferMinutes,
        string $timezone
    ): array {
        $slots = [];
        $tz = new DateTimeZone($timezone);
        
        $currentTime = new DateTime($date . ' ' . $providerHours['start_time'], $tz);
        $endTime = new DateTime($date . ' ' . $providerHours['end_time'], $tz);
        $now = new DateTime('now', $tz);
        
        // If the current time is later than the start time (for today), adjust start time
        if ($currentTime < $now) {
            $currentTime = clone $now;
            // Round up to next slot interval (e.g., if now is 14:37, start at 14:45 or 15:00)
            $minutes = (int)$currentTime->format('i');
            $slotInterval = $serviceDuration + $bufferMinutes;
            // Round up to next interval
            $minutesToAdd = $slotInterval - ($minutes % $slotInterval);
            if ($minutesToAdd < $slotInterval) {
                $currentTime->add(new DateInterval('PT' . $minutesToAdd . 'M'));
            }
        }
        
        $slotDuration = $serviceDuration + $bufferMinutes;
        $interval = new DateInterval('PT' . $slotDuration . 'M');
        
        while ($currentTime < $endTime) {
            $slotEnd = clone $currentTime;
            $slotEnd->add(new DateInterval('PT' . $serviceDuration . 'M'));
            
            // Check if slot end exceeds working hours
            if ($slotEnd > $endTime) {
                break;
            }
            
            // Check if slot overlaps with breaks
            $slotStart = $currentTime->format('H:i:s');
            $slotEndTime = $slotEnd->format('H:i:s');
            $overlapsBreak = false;
            
            if (isset($providerHours['breaks'])) {
                foreach ($providerHours['breaks'] as $break) {
                    if ($this->timesOverlap($slotStart, $slotEndTime, $break['start'], $break['end'])) {
                        $overlapsBreak = true;
                        break;
                    }
                }
            }
            
            if (!$overlapsBreak) {
                $slots[] = [
                    'start' => clone $currentTime,
                    'end' => $slotEnd,
                    'startFormatted' => $currentTime->format('H:i'),
                    'endFormatted' => $slotEnd->format('H:i')
                ];
            }
            
            $currentTime->add($interval);
        }
        
        return $slots;
    }

    /**
     * Check if a slot overlaps with any busy periods
     */
    private function slotOverlapsBusyPeriods(array $slot, array $busyPeriods): bool
    {
        foreach ($busyPeriods as $busy) {
            if ($this->dateTimesOverlap($slot['start'], $slot['end'], $busy['start'], $busy['end'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if two time ranges overlap (HH:MM:SS format)
     */
    private function timesOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        // Use <= and >= to prevent appointments from touching at boundaries
        return $start1 < $end2 && $end1 > $start2;
    }

    /**
     * Check if two DateTime ranges overlap
     * Prevents appointments from starting exactly when another ends (no back-to-back)
     */
    private function dateTimesOverlap(DateTime $start1, DateTime $end1, DateTime $start2, DateTime $end2): bool
    {
        // Use <= instead of < to prevent slots from starting exactly when busy period ends
        // This blocks appointments that would start at the exact moment another ends
        return $start1 <= $end2 && $end1 >= $start2;
    }

    /**
     * Get buffer time setting (minutes between appointments)
     * 
     * @param int $providerId Provider ID (for provider-specific settings in future)
     * @return int Buffer time in minutes (0, 15, or 30)
     */
    public function getBufferTime(int $providerId): int
    {
        // TODO: Implement provider-specific buffer times
        // For now, return global default
        $settings = $this->settingModel->getByKeys(['business.buffer_time']);
        $bufferTime = (int) ($settings['business.buffer_time'] ?? 0);
        
        // Validate buffer time (must be 0, 15, or 30)
        if (!in_array($bufferTime, [0, 15, 30])) {
            $bufferTime = 0;
        }
        
        return $bufferTime;
    }

    public function getCalendarAvailability(
        int $providerId,
        int $serviceId,
        ?string $startDate = null,
        int $days = 60,
        ?string $timezone = null,
        ?int $excludeAppointmentId = null,
        ?int $locationId = null
    ): array {
        $timezone = $timezone ?? $this->localizationService->getTimezone();
        $tz = new DateTimeZone($timezone);
        $days = max(1, min($days, 120));
        $bufferMinutes = $this->getBufferTime($providerId);

        $startDate = $startDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)
            ? $startDate
            : (new DateTimeImmutable('today', $tz))->format('Y-m-d');

        $start = new DateTimeImmutable($startDate, $tz);
        $end = $start->modify('+' . ($days - 1) . ' days');

        $cacheKey = sprintf(
            'availability_calendar_%d_%d_%s_%d_%s_%d_%s_%s',
            $providerId,
            $serviceId,
            $start->format('Ymd'),
            $days,
            str_replace(['/', '\\'], '_', $timezone),
            $bufferMinutes,
            $excludeAppointmentId ? (string) $excludeAppointmentId : 'none',
            $locationId ? (string) $locationId : 'all'
        );

        $cacheTtl = 300; // 5 minutes
        $cache = cache();
        if ($cache) {
            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $availableDates = [];
        $slotsByDate = [];

        for ($i = 0; $i < $days; $i++) {
            $current = $start->modify("+{$i} days");
            $dateStr = $current->format('Y-m-d');
            $slots = $this->getAvailableSlots(
                $providerId,
                $dateStr,
                $serviceId,
                $bufferMinutes,
                $timezone,
                $excludeAppointmentId,
                $locationId
            );

            if (empty($slots)) {
                continue;
            }

            $availableDates[] = $dateStr;
            $slotsByDate[$dateStr] = array_map(function (array $slot) use ($timezone) {
                $startTime = ($slot['start'] instanceof DateTimeImmutable || $slot['start'] instanceof \DateTime)
                    ? $slot['start']
                    : new DateTimeImmutable((string) $slot['start']);
                $endTime = ($slot['end'] instanceof DateTimeImmutable || $slot['end'] instanceof \DateTime)
                    ? $slot['end']
                    : new DateTimeImmutable((string) $slot['end']);

                $label = $slot['label'] ?? sprintf(
                    '%s - %s',
                    $this->localizationService->formatTimeForDisplay($startTime->format('H:i:s')),
                    $this->localizationService->formatTimeForDisplay($endTime->format('H:i:s'))
                );

                return [
                    'start' => $startTime->format(DATE_ATOM),
                    'end' => $endTime->format(DATE_ATOM),
                    'startFormatted' => $slot['startFormatted'] ?? $startTime->format('H:i'),
                    'endFormatted' => $slot['endFormatted'] ?? $endTime->format('H:i'),
                    'label' => $label,
                    'timezone' => $slot['timezone'] ?? $timezone,
                ];
            }, $slots);
        }

        $result = [
            'provider_id' => $providerId,
            'service_id' => $serviceId,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'days' => $days,
            'timezone' => $timezone,
            'availableDates' => $availableDates,
            'slotsByDate' => $slotsByDate,
            'default_date' => $availableDates[0] ?? null,
            'generated_at' => (new DateTimeImmutable('now', $tz))->format(DATE_ATOM),
        ];

        if ($cache) {
            $cache->save($cacheKey, $result, $cacheTtl);
        }

        return $result;
    }
}
