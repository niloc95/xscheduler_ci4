<?php

/**
 * =============================================================================
 * WEEK VIEW SERVICE
 * =============================================================================
 *
 * @file        app/Services/Calendar/WeekViewService.php
 * @description Builds the complete server-side render model for the week view.
 *              Replaces client-side slot generation for the 7-day week grid.
 *
 * OUTPUT MODEL:
 * ─────────────────────────────────────────────────────────────────
 * {
 *   startDate:          'Y-m-d',
 *   endDate:            'Y-m-d',
 *   weekLabel:          'Feb 23 – Mar 1, 2026',
 *   businessHours:      { startTime, endTime },
 *   slotDuration:       int  (minutes),
 *   days: [
 *     {
 *       date, dayNumber, monthName, fullDate, weekday, weekdayName,
 *       isToday, isPast,
 *       dayGrid: { slots: [...] },   // time grid with appointments injected
 *       appointments: [...formatted]
 *     },
 *     ...  (7 days)
 *   ],
 *   appointments:       [ ...all appointments flat ],
 *   totalAppointments:  int,
 * }
 *
 * @see         CalendarRangeService  — week range + day slot generation
 * @see         AppointmentQueryService — data retrieval (grouped by date)
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

class WeekViewService
{
    private CalendarRangeService        $range;
    private AppointmentQueryService     $query;
    private AppointmentFormatterService $formatter;
    private DayViewService              $dayView;
    private string $dayStart;
    private string $dayEnd;
    private int    $resolution;

    public function __construct(
        ?CalendarRangeService $range = null,
        ?AppointmentQueryService $query = null,
        ?AppointmentFormatterService $formatter = null,
        ?DayViewService $dayView = null
    ) {
        $this->range     = $range     ?? new CalendarRangeService();
        $this->query     = $query     ?? new AppointmentQueryService();
        $this->formatter = $formatter ?? new AppointmentFormatterService();
        $this->dayView   = $dayView   ?? new DayViewService($this->range, $this->query, $this->formatter);

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
     * Build the week view render model.
     *
     * @param string $date    Any date within the desired week (Y-m-d)
     * @param array  $filters provider_id, service_id, location_id, status,
     *                         user_role, scope_to_user_id
     * @return array Full render model (JSON-safe)
     */
    public function build(string $date, array $filters = []): array
    {
        // 1. Generate the 7-day week structure
        $week = $this->range->generateWeekRange($date);

        // 2. Fetch ALL appointments for the week in one query, grouped by date
        $grouped = $this->query->getGroupedByDate(
            $week['startDate'],
            $week['endDate'],
            $filters
        );

        // 3. Flat list of all formatted appointments
        $allRows = [];
        foreach ($grouped as $rows) {
            foreach ($rows as $row) {
                $allRows[] = $row;
            }
        }
        $allFormatted = $this->formatter->formatManyForCalendar($allRows);

        // 4. Group appointments by provider (for multi-column layout)
        $providers = [];
        $providerMap = [];
        foreach ($allFormatted as $event) {
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

        if (empty($providers)) {
            $providers[] = [
                'id' => 0,
                'name' => 'All Providers',
                'appointments' => []
            ];
        }

        // 5. Build per-day structures with inline time grids
        $formattedByDate = [];
        foreach ($allFormatted as $event) {
            $d = substr($event['start'], 0, 10); // Y-m-d
            $formattedByDate[$d][] = $event;
        }

        $days = $week['days'];
        foreach ($days as &$day) {
            $d    = $day['date'];
            $dayEvents = $formattedByDate[$d] ?? [];

            // Generate base time grid
            $baseGrid = $this->range->generateDaySlots($d, $this->dayStart, $this->dayEnd, $this->resolution);
            
            // Inject appointments into matching time slots PER PROVIDER
            $providerColumns = [];
            foreach ($providers as $provider) {
                $providerGrid = $baseGrid;
                
                // Filter events for this provider on this day
                $providerDayEvents = array_filter($dayEvents, function($e) use ($provider) {
                    $pid = $e['providerId'] ?? 0;
                    return $pid == $provider['id'] || ($provider['id'] == 0 && empty($providers[1]));
                });
                
                $providerGrid['slots'] = $this->injectIntoSlots($providerGrid['slots'], $providerDayEvents, $providerGrid);
                
                $providerColumns[] = [
                    'provider' => [
                        'id' => $provider['id'],
                        'name' => $provider['name']
                    ],
                    'grid' => $providerGrid
                ];
            }

            // Keep base grid for backward compatibility
            $baseGrid['slots'] = $this->injectIntoSlots($baseGrid['slots'], $dayEvents, $baseGrid);

            $day['appointments'] = $dayEvents;
            $day['appointmentCount'] = count($dayEvents);
            $day['dayGrid'] = $baseGrid;
            $day['providerColumns'] = $providerColumns;
        }
        unset($day);

        // 6. Week label  e.g. "Feb 23 – Mar 1, 2026"
        $weekLabel = $this->buildWeekLabel($week['startDate'], $week['endDate']);

        return [
            'startDate'          => $week['startDate'],
            'endDate'            => $week['endDate'],
            'weekLabel'          => $weekLabel,
            'businessHours'      => [
                'startTime' => $this->dayStart,
                'endTime'   => $this->dayEnd,
            ],
            'slotDuration'       => $this->resolution,
            'days'               => $days,
            'appointments'       => $allFormatted,
            'totalAppointments'  => count($allFormatted),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────

    /**
     * Inject formatted appointments into the matching time slots of a day grid.
     * Mirrors DayViewService::injectIntoSlots for per-day use in the week grid.
     */
    private function injectIntoSlots(array $slots, array $events, array $grid): array
    {
        if (empty($events)) {
            return $slots;
        }

        $startMinutes    = $this->timeToMinutes($grid['dayStart']);
        $pixelsPerMinute = $grid['pixelsPerMinute'];

        $positioned = array_map(function (array $event) use ($startMinutes, $pixelsPerMinute) {
            $eventStart = substr($event['start'] ?? '', 11, 5);
            $eventEnd   = substr($event['end']   ?? '', 11, 5);
            $startMin   = $this->timeToMinutes($eventStart);
            $endMin     = $this->timeToMinutes($eventEnd);
            $duration   = max($endMin - $startMin, 15);

            $event['_topPx']    = ($startMin - $startMinutes) * $pixelsPerMinute;
            $event['_heightPx'] = $duration * $pixelsPerMinute;
            $event['_startMin'] = $startMin;
            $event['_endMin']   = $startMin + $duration;

            return $event;
        }, $events);

        $slotMap = [];
        foreach ($slots as $i => $slot) {
            $slotMap[$slot['time']] = $i;
        }

        foreach ($positioned as $event) {
            $slotTime = substr($event['start'] ?? '', 11, 5);
            $startMin = $this->timeToMinutes($slotTime);
            $slotMin  = $startMin - ($startMin % $this->resolution);
            $slotKey  = sprintf('%02d:%02d', intdiv($slotMin, 60), $slotMin % 60);

            if (isset($slotMap[$slotKey])) {
                $slots[$slotMap[$slotKey]]['appointments'][] = $event;
            }
        }

        return $slots;
    }

    /**
     * Build a human-readable week range label.
     * Same month  → "Feb 23 – Mar 1, 2026"
     */
    private function buildWeekLabel(string $startDate, string $endDate): string
    {
        $s = new \DateTimeImmutable($startDate);
        $e = new \DateTimeImmutable($endDate);

        if ($s->format('F Y') === $e->format('F Y')) {
            return $s->format('M j') . ' – ' . $e->format('j, Y');
        }
        if ($s->format('Y') === $e->format('Y')) {
            return $s->format('M j') . ' – ' . $e->format('M j, Y');
        }
        return $s->format('M j, Y') . ' – ' . $e->format('M j, Y');
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time . ':00'));
        return $h * 60 + $m;
    }
}
