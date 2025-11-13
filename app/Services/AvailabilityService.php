<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\BusinessHourModel;
use App\Models\ProviderScheduleModel;
use App\Models\BlockedTimeModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use DateTime;
use DateTimeZone;
use DateInterval;

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
    private ProviderScheduleModel $providerScheduleModel;
    private BlockedTimeModel $blockedTimeModel;
    private ServiceModel $serviceModel;
    private SettingModel $settingModel;
    private LocalizationSettingsService $localizationService;
    
    private array $cache = [];

    public function __construct()
    {
        $this->appointmentModel = new AppointmentModel();
        $this->businessHourModel = new BusinessHourModel();
        $this->providerScheduleModel = new ProviderScheduleModel();
        $this->blockedTimeModel = new BlockedTimeModel();
        $this->serviceModel = new ServiceModel();
        $this->settingModel = new SettingModel();
        $this->localizationService = new LocalizationSettingsService();
    }

    /**
     * Get available time slots for a provider on a specific date
     * 
     * @param int $providerId Provider ID
     * @param string $date Date in Y-m-d format
     * @param int $serviceId Service ID (determines slot duration)
     * @param int $bufferMinutes Buffer time between appointments (0, 15, or 30)
     * @param string $timezone Timezone for the calculation (default: system timezone)
     * @return array Array of available slots with start and end times
     */
    public function getAvailableSlots(
        int $providerId,
        string $date,
        int $serviceId,
        int $bufferMinutes = 0,
        ?string $timezone = null
    ): array {
        $timezone = $timezone ?? $this->localizationService->getTimezone();
        $tz = new DateTimeZone($timezone);
        
        log_message('info', '[AvailabilityService] Calculating slots for provider ' . $providerId . ' on ' . $date);
        
        // Step 1: Check if date is blocked globally
        if ($this->isDateBlocked($date)) {
            log_message('info', '[AvailabilityService] Date is globally blocked');
            return [];
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
        $busyPeriods = $this->getBusyPeriods($providerId, $date, $timezone);
        
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
            return !$this->slotOverlapsBusyPeriods($slot, $busyPeriods);
        });
        
        log_message('info', '[AvailabilityService] Found ' . count($availableSlots) . ' available slots');
        
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
     * @return array ['available' => bool, 'conflicts' => array, 'reason' => string]
     */
    public function isSlotAvailable(
        int $providerId,
        string $startTime,
        string $endTime,
        string $timezone = 'UTC',
        ?int $excludeAppointmentId = null
    ): array {
        $start = new DateTime($startTime, new DateTimeZone($timezone));
        $end = new DateTime($endTime, new DateTimeZone($timezone));
        $date = $start->format('Y-m-d');
        
        // Check if date is blocked
        if ($this->isDateBlocked($date)) {
            return [
                'available' => false,
                'conflicts' => [],
                'reason' => 'Date is blocked (holiday/closure)'
            ];
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
        
        // Check for appointment conflicts
        $conflicts = $this->getConflictingAppointments(
            $providerId,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $excludeAppointmentId
        );
        
        if (!empty($conflicts)) {
            return [
                'available' => false,
                'conflicts' => $conflicts,
                'reason' => 'Conflicts with ' . count($conflicts) . ' existing appointment(s)'
            ];
        }
        
        // Check for blocked time periods
        $blockedTimes = $this->getBlockedTimesForPeriod(
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
            $blockedPeriodsJson = $this->settingModel->get('business.blocked_periods') ?? '[]';
            $this->cache[$cacheKey] = json_decode($blockedPeriodsJson, true) ?: [];
        }
        
        $blockedPeriods = $this->cache[$cacheKey];
        
        foreach ($blockedPeriods as $period) {
            if ($date >= $period['start'] && $date <= $period['end']) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get provider's working hours for a specific date
     * Returns array with start_time, end_time, and breaks, or null if not working
     */
    private function getProviderHoursForDate(int $providerId, string $date): ?array
    {
        $dateTime = new DateTime($date);
        $weekday = (int) $dateTime->format('w'); // 0=Sunday, 1=Monday, etc.
        
        // First check xs_provider_schedules (provider-specific schedule)
        $providerSchedule = $this->providerScheduleModel
            ->where('provider_id', $providerId)
            ->where('is_active', 1)
            ->first();
        
        if ($providerSchedule) {
            // Provider has a custom schedule
            $dayOfWeek = strtolower($dateTime->format('l')); // 'monday', 'tuesday', etc.
            
            if ($providerSchedule['day_of_week'] === $dayOfWeek) {
                return [
                    'start_time' => $providerSchedule['start_time'],
                    'end_time' => $providerSchedule['end_time'],
                    'breaks' => $this->parseBreaks($providerSchedule['break_start'], $providerSchedule['break_end'])
                ];
            }
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
     * Get all busy periods for a provider on a specific date
     * Returns array of ['start' => DateTime, 'end' => DateTime]
     */
    private function getBusyPeriods(int $providerId, string $date, string $timezone): array
    {
        $busy = [];
        $tz = new DateTimeZone($timezone);
        
        // Get appointments for this date (excluding cancelled)
        $startOfDay = $date . ' 00:00:00';
        $endOfDay = $date . ' 23:59:59';
        
        $appointments = $this->appointmentModel
            ->where('provider_id', $providerId)
            ->where('status !=', 'cancelled')
            ->where('start_time >=', $startOfDay)
            ->where('start_time <=', $endOfDay)
            ->findAll();
        
        foreach ($appointments as $apt) {
            $busy[] = [
                'start' => new DateTime($apt['start_time'], $tz),
                'end' => new DateTime($apt['end_time'], $tz),
                'type' => 'appointment'
            ];
        }
        
        // Get blocked times for this date
        $blockedTimes = $this->blockedTimeModel
            ->where('provider_id', $providerId)
            ->where('start_time <=', $endOfDay)
            ->where('end_time >=', $startOfDay)
            ->findAll();
        
        foreach ($blockedTimes as $block) {
            $busy[] = [
                'start' => new DateTime($block['start_time'], $tz),
                'end' => new DateTime($block['end_time'], $tz),
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
        return $start1 < $end2 && $end1 > $start2;
    }

    /**
     * Check if two DateTime ranges overlap
     */
    private function dateTimesOverlap(DateTime $start1, DateTime $end1, DateTime $start2, DateTime $end2): bool
    {
        return $start1 < $end2 && $end1 > $start2;
    }

    /**
     * Get conflicting appointments
     */
    private function getConflictingAppointments(
        int $providerId,
        string $startTime,
        string $endTime,
        ?int $excludeAppointmentId = null
    ): array {
        $builder = $this->appointmentModel->builder();
        
        $builder->where('provider_id', $providerId)
                ->where('status !=', 'cancelled')
                ->groupStart()
                    // New starts during existing
                    ->groupStart()
                        ->where('start_time <=', $startTime)
                        ->where('end_time >', $startTime)
                    ->groupEnd()
                    // New ends during existing
                    ->orGroupStart()
                        ->where('start_time <', $endTime)
                        ->where('end_time >=', $endTime)
                    ->groupEnd()
                    // New contains existing
                    ->orGroupStart()
                        ->where('start_time >=', $startTime)
                        ->where('end_time <=', $endTime)
                    ->groupEnd()
                ->groupEnd();
        
        if ($excludeAppointmentId) {
            $builder->where('id !=', $excludeAppointmentId);
        }
        
        return $builder->get()->getResultArray();
    }

    /**
     * Get blocked time periods that overlap with the given time range
     */
    private function getBlockedTimesForPeriod(
        int $providerId,
        string $startTime,
        string $endTime
    ): array {
        return $this->blockedTimeModel
            ->where('provider_id', $providerId)
            ->groupStart()
                ->groupStart()
                    ->where('start_time <=', $startTime)
                    ->where('end_time >', $startTime)
                ->groupEnd()
                ->orGroupStart()
                    ->where('start_time <', $endTime)
                    ->where('end_time >=', $endTime)
                ->groupEnd()
                ->orGroupStart()
                    ->where('start_time >=', $startTime)
                    ->where('end_time <=', $endTime)
                ->groupEnd()
            ->groupEnd()
            ->findAll();
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
        $bufferTime = (int) ($this->settingModel->get('business.buffer_time') ?? 0);
        
        // Validate buffer time (must be 0, 15, or 30)
        if (!in_array($bufferTime, [0, 15, 30])) {
            $bufferTime = 0;
        }
        
        return $bufferTime;
    }
}
