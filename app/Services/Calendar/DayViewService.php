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

use App\Models\SettingModel;
use App\Services\Appointment\AppointmentQueryService;
use App\Services\Appointment\AppointmentFormatterService;

class DayViewService
{
    use SlotInjectionTrait;
    private CalendarRangeService      $range;
    private AppointmentQueryService   $query;
    private AppointmentFormatterService $formatter;
    private string $dayStart;
    private string $dayEnd;
    private int    $resolution;

    public function __construct(
        ?CalendarRangeService $range = null,
        ?AppointmentQueryService $query = null,
        ?AppointmentFormatterService $formatter = null
    ) {
        $this->range     = $range     ?? new CalendarRangeService();
        $this->query     = $query     ?? new AppointmentQueryService();
        $this->formatter = $formatter ?? new AppointmentFormatterService();

        // Read calendar slot settings
        $settings = (new SettingModel())->getByKeys([
            'calendar.day_start',
            'calendar.day_end',
            'booking.time_resolution',
        ]);

        $this->dayStart   = $settings['calendar.day_start']       ?? '08:00';
        $this->dayEnd     = $settings['calendar.day_end']         ?? '18:00';
        $this->resolution = (int) ($settings['booking.time_resolution'] ?? 30);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Build the day view render model.
     *
     * @param string $date    Y-m-d
     * @param array  $filters provider_id, service_id, location_id, status,
     *                         user_role, scope_to_user_id
     * @return array Full render model (JSON-safe)
     */
    public function build(string $date, array $filters = []): array
    {
        // 1. Generate time grid
        $grid = $this->range->generateDaySlots(
            $date,
            $this->dayStart,
            $this->dayEnd,
            $this->resolution
        );

        // 2. Fetch appointments for the day
        $rows      = $this->query->getForRange($date, $date, $filters);
        $formatted = $this->formatter->formatManyForCalendar($rows);

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
            $providerGrid['slots'] = $this->injectIntoSlots($providerGrid['slots'], $provider['appointments'], $providerGrid, $this->resolution);
            
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
                'startTime' => $this->dayStart,
                'endTime'   => $this->dayEnd,
            ],
            'grid'             => $grid, // Keep base grid for backward compatibility
            'providerColumns'  => $providerColumns, // New multi-column layout
            'appointments'     => $formatted,
            'totalAppointments'=> count($formatted),
        ];
    }

}
