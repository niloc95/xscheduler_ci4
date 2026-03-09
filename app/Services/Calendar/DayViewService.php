<?php

/**
 * =============================================================================
 * DAY VIEW SERVICE
 * =============================================================================
 *
 * @file        app/Services/Calendar/DayViewService.php
 * @description Builds the complete server-side render model for the day view.
 *              Replaces client-side slot generation (slot-engine.js::generateSlots).
 *
 * OUTPUT MODEL:
 * ─────────────────────────────────────────────────────────────────
 * {
 *   date:             'Y-m-d',
 *   dayName:          'Thursday',
 *   dayLabel:         'Thursday, February 26, 2026',
 *   weekdayName:      'thursday',       // lowercase day name string
 *   weekday:          int,              // 0=Sun … 6=Sat
 *   isToday:          bool,
 *   isPast:           bool,
 *   businessHours:    { startTime: 'HH:MM', endTime: 'HH:MM' },
 *   grid:             { dayStart, dayEnd, slots: [...] },  // base grid (all providers)
 *   providerColumns:  [
 *     { provider: { id, name }, grid: { dayStart, dayEnd, slots: [...] } },
 *     ...
 *   ],
 *   appointments:     [ ...formatted ],
 *   totalAppointments: int,
 * }
 *
 * @see         CalendarRangeService  — date/grid generation
 * @see         AppointmentQueryService — data retrieval
 * @see         AppointmentFormatterService — normalization
 * @package     App\Services\Calendar
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services\Calendar;

use App\Services\Appointment\AppointmentQueryService;
use App\Services\Appointment\AppointmentFormatterService;
use App\Models\ProviderScheduleModel;

class DayViewService
{
    use SlotInjectionTrait;
    private CalendarRangeService      $range;
    private AppointmentQueryService   $query;
    private AppointmentFormatterService $formatter;
    private TimeGridService $timeGrid;
    private EventLayoutService $eventLayout;
    private ProviderScheduleModel $providerScheduleModel;

    public function __construct(
        ?CalendarRangeService $range = null,
        ?AppointmentQueryService $query = null,
        ?AppointmentFormatterService $formatter = null,
        ?TimeGridService $timeGrid = null,
        ?EventLayoutService $eventLayout = null,
        ?ProviderScheduleModel $providerScheduleModel = null
    ) {
        $this->range     = $range     ?? new CalendarRangeService();
        $this->query     = $query     ?? new AppointmentQueryService();
        $this->formatter = $formatter ?? new AppointmentFormatterService();
        $this->timeGrid  = $timeGrid ?? new TimeGridService($this->range);
        $this->eventLayout = $eventLayout ?? new EventLayoutService();
        $this->providerScheduleModel = $providerScheduleModel ?? new ProviderScheduleModel();
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Build the day view render model.
     *
     * @param string     $date    Y-m-d
     * @param array      $filters provider_id, service_id, location_id, status,
     *                            user_role, scope_to_user_id
     * @param array|null $appointments Pre-formatted appointment rows (optional)
     * @return array Full render model (JSON-safe)
     */
    public function build(string $date, array $filters = [], ?array $appointments = null): array
    {
        // 1. Generate base time grid using business hours
        $grid = $this->timeGrid->generateDayGrid($date);

        // 2. Fetch appointments for the day
        if ($appointments === null) {
            $rows      = $this->query->getForRange($date, $date, $filters);
            $formatted = $this->formatter->formatManyForCalendar($rows);
        } else {
            $formatted = $appointments;
        }

        // 3. Group appointments by provider
        $providers = [];
        $providerMap = []; // provider_id => index
        
        // First pass: identify unique providers
        foreach ($formatted as $event) {
            $pid = $event['provider_id'] ?? 0;
            if (!isset($providerMap[$pid])) {
                $providerMap[$pid] = count($providers);
                $providers[] = [
                    'id' => $pid,
                    'name' => $event['provider_name'] ?? 'Unknown Provider',
                    'appointments' => []
                ];
            }
            $providers[$providerMap[$pid]]['appointments'][] = $event;
        }

        // If no appointments, create a default empty provider column
        if (empty($providers)) {
            $providers[] = [
                'id' => 0,
                'name' => 'All Providers',
                'appointments' => []
            ];
        }

        // 4. Get day-of-week for provider schedule lookup
        $dateObj = new \DateTimeImmutable($date);
        $dayOfWeek = (int) $dateObj->format('w'); // 0=Sun, 6=Sat

        // 5. Inject appointments into matching time slots PER PROVIDER
        $providerColumns = [];
        foreach ($providers as $provider) {
            // Get provider-specific working hours
            $workingHours = $this->getProviderWorkingHours($provider['id'], $dayOfWeek);
            
            // Generate provider-specific time grid
            $providerGrid = $this->timeGrid->generateDayGridWithProviderHours(
                $date,
                $workingHours
            );
            
            // SHARED ENGINE: Resolve overlapping appointments
            $positioned = $this->eventLayout->resolveLayout($provider['appointments']);
            
            // Inject positioned appointments into time slots
            $providerGrid['slots'] = $this->injectIntoSlots(
                $providerGrid['slots'],
                $positioned,
                $providerGrid,
                $this->timeGrid->getResolution()
            );
            
            $providerColumns[] = [
                'provider' => [
                    'id' => $provider['id'],
                    'name' => $provider['name']
                ],
                'workingHours' => $workingHours,
                'grid' => $providerGrid
            ];
        }

        // 6. Build day metadata
        $dayInfo  = $this->range->normalizeDayOfWeek($date);
        $today    = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $isToday  = $date === $today;
        $isPast   = $date < $today;

        return [
            'date'             => $date,
            'dayName'          => $dateObj->format('l'),          // 'Thursday'
            'dayLabel'         => $dateObj->format('l, F j, Y'),  // 'Thursday, February 26, 2026'
            'weekdayName'      => $dayInfo['string'],
            'weekday'          => $dayInfo['int'],
            'isToday'          => $isToday,
            'isPast'           => $isPast,
            'businessHours'    => [
                'startTime' => $this->timeGrid->getDayStart(),
                'endTime'   => $this->timeGrid->getDayEnd(),
            ],
            'grid'             => $grid, // Keep base grid for backward compatibility
            'providerColumns'  => $providerColumns, // New multi-column layout with EventLayoutService positioning
            'appointments'     => $formatted,
            'totalAppointments'=> count($formatted),
        ];
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Get provider working hours for a specific day.
     * Falls back to business hours if provider schedule not found.
     *
     * Priority:
     * 1. ProviderScheduleModel (provider-specific)
     * 2. BusinessHourModel (business-wide)
     * 3. CalendarConfigService (defaults)
     *
     * @param int    $providerId   Provider ID
     * @param int    $dayOfWeek    Day number (0=Sun, 6=Sat)
     * @return array { startTime: 'HH:MM', endTime: 'HH:MM', breakStart?, breakEnd?, source: 'provider'|'business', isActive: bool }
     */
    private function getProviderWorkingHours(int $providerId, int $dayOfWeek): array
    {
        // Skip lookup for providerId 0 (All Providers placeholder)
        if ($providerId === 0) {
            return [
                'startTime' => $this->timeGrid->getDayStart(),
                'endTime'   => $this->timeGrid->getDayEnd(),
                'source'    => 'business',
                'isActive'  => true,
            ];
        }

        // Query provider schedule for this day
        $schedule = $this->providerScheduleModel
            ->where('provider_id', $providerId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if ($schedule && $schedule['is_active']) {
            // Provider has working hours for this day
            return [
                'startTime'  => substr($schedule['start_time'], 0, 5), // 'HH:MM' format
                'endTime'    => substr($schedule['end_time'], 0, 5),
                'breakStart' => $schedule['break_start'] ? substr($schedule['break_start'], 0, 5) : null,
                'breakEnd'   => $schedule['break_end'] ? substr($schedule['break_end'], 0, 5) : null,
                'source'     => 'provider_schedule',
                'isActive'   => true,
            ];
        }

        // Fallback: Use business hours
        return [
            'startTime' => $this->timeGrid->getDayStart(),
            'endTime'   => $this->timeGrid->getDayEnd(),
            'source'    => 'business_hours',
            'isActive'  => false,
        ];
    }

}
