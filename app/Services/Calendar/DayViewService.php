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
 *   isToday:          bool,
 *   isPast:           bool,
 *   businessHours:    { startTime: 'HH:MM', endTime: 'HH:MM' },
 *   grid: {
 *     date, dayStart, dayEnd, totalMinutes, pixelsPerMinute, containerHeight,
 *     slots: [{ time, minutes, label, isHour, isHalf, topPx, appointments[] }]
 *   },
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
            $pid = $event['providerId'] ?? 0;
            if (!isset($providerMap[$pid])) {
                $providerMap[$pid] = count($providers);
                $providers[] = [
                    'id' => $pid,
                    'name' => $event['providerName'] ?? 'Unknown Provider',
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
            $providerGrid = $grid; // Clone base grid
            $providerGrid['slots'] = $this->injectIntoSlots($providerGrid['slots'], $provider['appointments'], $providerGrid);
            
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

    // ─────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────

    /**
     * Inject appointments into the appropriate time slots.
     * An appointment goes into a slot when its start_at falls within that slot.
     *
     * Additionally computes topPx, heightPx, and leftPercent for multi-column
     * overlap layout — keys used directly by the day-view renderer.
     *
     * @param array $slots    Time slot array from CalendarRangeService
     * @param array $events   Formatted appointment array
     * @param array $grid     Grid metadata (dayStart, pixelsPerMinute)
     * @return array Updated slots with appointments injected
     */
    private function injectIntoSlots(array $slots, array $events, array $grid): array
    {
        if (empty($events)) {
            return $slots;
        }

        $startMinutes    = $this->timeToMinutes($grid['dayStart']);
        $pixelsPerMinute = $grid['pixelsPerMinute'];

        // Pre-compute positioning for each event
        $positioned = array_map(function (array $event) use ($startMinutes, $pixelsPerMinute) {
            $eventStart = substr($event['start'] ?? '', 11, 5);   // HH:MM
            $eventEnd   = substr($event['end']   ?? '', 11, 5);

            $startMin   = $this->timeToMinutes($eventStart);
            $endMin     = $this->timeToMinutes($eventEnd);
            $duration   = max($endMin - $startMin, 15);           // minimum 15 min display height

            $event['_topPx']    = ($startMin - $startMinutes) * $pixelsPerMinute;
            $event['_heightPx'] = $duration * $pixelsPerMinute;
            $event['_startMin'] = $startMin;
            $event['_endMin']   = $startMin + $duration;

            return $event;
        }, $events);

        // Resolve overlapping columns (simple sweep-line)
        $positioned = $this->resolveOverlapColumns($positioned);

        // Assign events to slots
        $slotMap = [];
        foreach ($slots as $i => $slot) {
            $slotMap[$slot['time']] = $i;
        }

        foreach ($positioned as $event) {
            $slotTime = substr($event['start'] ?? '', 11, 5);
            // Round down to nearest slot boundary
            $startMin  = $this->timeToMinutes($slotTime);
            $slotMin   = $startMin - ($startMin % $this->resolution);
            $slotKey   = sprintf('%02d:%02d', intdiv($slotMin, 60), $slotMin % 60);

            if (isset($slotMap[$slotKey])) {
                $slots[$slotMap[$slotKey]]['appointments'][] = $event;
            }
        }

        return $slots;
    }

    /**
     * Resolve N-column overlap layout for overlapping events.
     * Sets '_colIndex' (0-based column) and '_colCount' (total columns in group).
     */
    private function resolveOverlapColumns(array $events): array
    {
        if (empty($events)) {
            return $events;
        }

        // Sort by start time
        usort($events, fn($a, $b) => $a['_startMin'] <=> $b['_startMin']);

        $groups  = []; // Each group is a set of overlapping events
        $active  = []; // Currently active events (by end time)

        foreach ($events as &$event) {
            // Remove finished events from active
            $active = array_filter($active, fn($e) => $e['_endMin'] > $event['_startMin']);

            if (empty($active)) {
                // New group
                $groups[] = [&$event];
            } else {
                // Add to last group
                $groups[count($groups) - 1][] = &$event;
            }
            $active[] = &$event;
        }
        unset($event);

        // Assign column positions within each group
        foreach ($groups as &$group) {
            $colCount = count($group);
            foreach ($group as $col => &$e) {
                $e['_colIndex'] = $col;
                $e['_colCount'] = $colCount;
                // Width percentage for CSS layout
                $e['_widthPct']  = round(100 / $colCount, 2);
                $e['_leftPct']   = round(($col / $colCount) * 100, 2);
            }
            unset($e);
        }
        unset($group);

        return $events;
    }

    /**
     * Convert 'HH:MM' string to total minutes.
     */
    private function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time . ':00'));
        return $h * 60 + $m;
    }
}
