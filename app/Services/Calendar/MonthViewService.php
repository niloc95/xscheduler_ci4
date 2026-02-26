<?php

/**
 * =============================================================================
 * MONTH VIEW SERVICE
 * =============================================================================
 *
 * @file        app/Services/Calendar/MonthViewService.php
 * @description Builds the complete server-side render model for the month view.
 *              Produces a 42-cell grid (6×7) with appointments injected per cell.
 *
 * OUTPUT MODEL:
 * ─────────────────────────────────────────────────────────────────
 * {
 *   year:               int,
 *   month:              int,
 *   monthName:          'February',
 *   monthLabel:         'February 2026',
 *   startDate:          'Y-m-d', // first cell (may be prior month)
 *   endDate:            'Y-m-d', // last cell (may be next month)
 *   weeks: [
 *     [
 *       {
 *         date, dayNumber, weekday, weekdayName, week,
 *         isCurrentMonth, isToday, isPast, isFuture,
 *         appointments: [...formatted],
 *         appointmentCount: int,
 *         hasMore: bool,       // true when appointmentCount > maxPerCell
 *         moreCount: int,      // how many hidden appointments
 *       },
 *       ...  (7 per row)
 *     ],
 *     ...  (6 rows)
 *   ],
 *   appointments:       [ ...all appointments flat ],
 *   totalAppointments:  int,
 * }
 *
 * @see         CalendarRangeService  — 42-cell month grid
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
use App\Services\AvailabilityService;

class MonthViewService
{
    /** Maximum appointments to show per cell before "X more" overflow */
    private const MAX_PER_CELL = 3;

    private CalendarRangeService        $range;
    private AppointmentQueryService     $query;
    private AppointmentFormatterService $formatter;
    private AvailabilityService         $availability;

    public function __construct(
        ?CalendarRangeService $range = null,
        ?AppointmentQueryService $query = null,
        ?AppointmentFormatterService $formatter = null,
        ?AvailabilityService $availability = null
    ) {
        $this->range        = $range        ?? new CalendarRangeService();
        $this->query        = $query        ?? new AppointmentQueryService();
        $this->formatter    = $formatter    ?? new AppointmentFormatterService();
        $this->availability = $availability ?? new AvailabilityService();
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Build the month view render model.
     *
     * @param int   $year
     * @param int   $month   1–12
     * @param array $filters provider_id, service_id, location_id, status,
     *                         user_role, scope_to_user_id
     * @param int   $maxPerCell Override the per-cell appointment cap (default 3)
     * @return array Full render model (JSON-safe)
     */
    public function build(int $year, int $month, array $filters = [], int $maxPerCell = self::MAX_PER_CELL): array
    {
        // 1. Generate 42-cell grid
        $grid = $this->range->generateMonthGrid($year, $month);

        // 2. Fetch appointments for the whole grid range in one query
        $grouped = $this->query->getGroupedByDate(
            $grid['startDate'],
            $grid['endDate'],
            $filters
        );

        // 3. Format all rows once
        $allFormatted = [];
        $formattedByDate = [];
        foreach ($grouped as $date => $rows) {
            $events = $this->formatter->formatManyForCalendar($rows);
            $formattedByDate[$date] = $events;
            foreach ($events as $e) {
                $allFormatted[] = $e;
            }
        }

        // 4. Inject into grid cells
        $weeks = [];
        $providerId = isset($filters['provider_id']) ? (int)$filters['provider_id'] : null;

        foreach ($grid['weeks'] as $week) {
            $row = [];
            foreach ($week as $cell) {
                $cellEvents = $formattedByDate[$cell['date']] ?? [];
                $count      = count($cellEvents);
                $visible    = array_slice($cellEvents, 0, $maxPerCell);
                $hasMore    = $count > $maxPerCell;

                $cell['appointments']     = $visible;
                $cell['appointmentCount'] = $count;
                $cell['hasMore']          = $hasMore;
                $cell['moreCount']        = $hasMore ? ($count - $maxPerCell) : 0;
                $cell['hasAvailability']  = $this->availability->hasWorkingHours($cell['date'], $providerId);

                $row[] = $cell;
            }
            $weeks[] = $row;
        }

        return [
            'year'              => $year,
            'month'             => $month,
            'monthName'         => $grid['monthName'],
            'monthLabel'        => $grid['monthName'] . ' ' . $year,
            'startDate'         => $grid['startDate'],
            'endDate'           => $grid['endDate'],
            'weeks'             => $weeks,
            'appointments'      => $allFormatted,
            'totalAppointments' => count($allFormatted),
        ];
    }
}
