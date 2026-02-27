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

class DayViewService
{
    use SlotInjectionTrait;
    private CalendarRangeService      $range;
    private AppointmentQueryService   $query;
    private AppointmentFormatterService $formatter;
    private TimeGridService $timeGrid;

    public function __construct(
        ?CalendarRangeService $range = null,
        ?AppointmentQueryService $query = null,
        ?AppointmentFormatterService $formatter = null,
        ?TimeGridService $timeGrid = null
    ) {
        $this->range     = $range     ?? new CalendarRangeService();
        $this->query     = $query     ?? new AppointmentQueryService();
        $this->formatter = $formatter ?? new AppointmentFormatterService();
        $this->timeGrid  = $timeGrid ?? new TimeGridService($this->range);
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
        // 1. Generate time grid
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

        // 4. Inject appointments into matching time slots PER PROVIDER
        $providerColumns = [];
        foreach ($providers as $provider) {
            $providerGrid = $grid;
            $providerGrid['slots'] = $this->injectIntoSlots(
                $providerGrid['slots'],
                $provider['appointments'],
                $providerGrid,
                $this->timeGrid->getResolution()
            );
            
            $providerColumns[] = [
                'provider' => [
                    'id' => $provider['id'],
                    'name' => $provider['name']
                ],
                'grid' => $providerGrid
            ];
        }

        // 5. Build day metadata
        $dayInfo  = $this->range->normalizeDayOfWeek($date);
        $dateObj  = new \DateTimeImmutable($date);
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
            'providerColumns'  => $providerColumns, // New multi-column layout
            'appointments'     => $formatted,
            'totalAppointments'=> count($formatted),
        ];
    }

}
