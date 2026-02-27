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
 *       providerColumns: [           // per-provider grids for multi-column layout
 *         { provider: { id, name }, grid: { slots: [...] } }, ...
 *       ],
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

use App\Services\Appointment\AppointmentQueryService;
use App\Services\Appointment\AppointmentFormatterService;

class WeekViewService
{
    private CalendarRangeService        $range;
    private AppointmentQueryService     $query;
    private AppointmentFormatterService $formatter;
    private TimeGridService             $timeGrid;
    private DayViewService              $dayView;

    public function __construct(
        ?CalendarRangeService $range = null,
        ?AppointmentQueryService $query = null,
        ?AppointmentFormatterService $formatter = null,
        ?TimeGridService $timeGrid = null,
        ?DayViewService $dayView = null
    ) {
        $this->range     = $range     ?? new CalendarRangeService();
        $this->query     = $query     ?? new AppointmentQueryService();
        $this->formatter = $formatter ?? new AppointmentFormatterService();
        $this->timeGrid  = $timeGrid ?? new TimeGridService($this->range);
        $this->dayView   = $dayView ?? new DayViewService($this->range, $this->query, $this->formatter, $this->timeGrid);
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

        // 4. Build per-day structures via DayViewService
        $formattedByDate = [];
        foreach ($allFormatted as $event) {
            $d = substr($event['start'], 0, 10); // Y-m-d
            $formattedByDate[$d][] = $event;
        }

        $days = $week['days'];
        foreach ($days as &$day) {
            $d    = $day['date'];
            $dayEvents = $formattedByDate[$d] ?? [];
            $dayModel  = $this->dayView->build($d, $filters, $dayEvents);

            $day['appointments'] = $dayModel['appointments'];
            $day['appointmentCount'] = $dayModel['totalAppointments'];
            $day['dayGrid'] = $dayModel['grid'];
            $day['providerColumns'] = $dayModel['providerColumns'];
        }
        unset($day);

        // 6. Week label  e.g. "Feb 23 – Mar 1, 2026"
        $weekLabel = $this->buildWeekLabel($week['startDate'], $week['endDate']);

        return [
            'startDate'          => $week['startDate'],
            'endDate'            => $week['endDate'],
            'weekLabel'          => $weekLabel,
            'businessHours'      => [
                'startTime' => $this->timeGrid->getDayStart(),
                'endTime'   => $this->timeGrid->getDayEnd(),
            ],
            'slotDuration'       => $this->timeGrid->getResolution(),
            'days'               => $days,
            'appointments'       => $allFormatted,
            'totalAppointments'  => count($allFormatted),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────

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

}
