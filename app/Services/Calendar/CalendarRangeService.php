<?php

/**
 * =============================================================================
 * CALENDAR RANGE SERVICE
 * =============================================================================
 *
 * @file        app/Services/Calendar/CalendarRangeService.php
 * @description Generates structured date ranges for all calendar views.
 *              Single source of truth for date/grid generation.
 *
 * This service handles ONLY date structure — no business logic, no DB queries.
 *
 * KEY METHODS:
 * ─────────────────────────────────────────────────────────────────
 * - generateMonthGrid(year, month)
 *   Returns 42 cells (6 weeks × 7 days) always, with precomputed flags.
 *
 * - generateWeekRange(date)
 *   Returns 7 DateTimeImmutable objects starting from firstDayOfWeek.
 *
 * - generateDaySlots(date, startTime, endTime, resolutionMinutes)
 *   Returns time slots array for a day view time grid.
 *
 * - normalizeDayOfWeek(date)
 *   Returns normalized day-of-week in both integer and string formats.
 *
 * DATA MODELS:
 * ─────────────────────────────────────────────────────────────────
 * Month cell:
 *   ['date' => 'Y-m-d', 'isCurrentMonth' => bool, 'isToday' => bool,
 *    'isPast' => bool, 'isFuture' => bool, 'weekday' => int,
 *    'weekdayName' => string, 'dayNumber' => int, 'week' => int]
 *
 * Time slot:
 *   ['time' => 'H:i', 'minutes' => int, 'label' => string,
 *    'isHour' => bool, 'isHalf' => bool]
 *
 * @package     App\Services\Calendar
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services\Calendar;

use App\Models\SettingModel;
use DateTimeImmutable;
use DateTimeZone;

class CalendarRangeService
{
    /** Day name strings matching xs_provider_schedules.day_of_week ENUM */
    public const DAY_NAMES_STRING = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    /** Map from localization.first_day setting to weekday integer */
    private const FIRST_DAY_MAP = [
        'monday'  => 1,
        'sunday'  => 0,
        'saturday' => 6,
    ];

    private string $timezone;
    private int    $firstDayOfWeek; // 0=Sunday, 1=Monday, 6=Saturday

    public function __construct(?string $timezone = null, ?int $firstDayOfWeek = null)
    {
        if ($timezone === null || $firstDayOfWeek === null) {
            $settings = (new SettingModel())->getByKeys([
                'localization.timezone',
                'localization.first_day',
            ]);
            $timezone       ??= $settings['localization.timezone'] ?? 'UTC';
            $firstDayRaw     = strtolower($settings['localization.first_day'] ?? 'monday');
            $firstDayOfWeek ??= self::FIRST_DAY_MAP[$firstDayRaw] ?? 1;
        }

        $this->timezone       = $timezone;
        $this->firstDayOfWeek = $firstDayOfWeek;
    }

    // ─────────────────────────────────────────────────────────────────
    // MONTH GRID
    // ─────────────────────────────────────────────────────────────────

    /**
     * Generate a 42-cell month grid (always 6 weeks × 7 days).
     *
     * @param int $year  Full year (e.g. 2026)
     * @param int $month Month number 1–12
     * @return array {
     *   year: int, month: int, monthName: string,
     *   startDate: string, endDate: string,
     *   weeks: array[6][7][cell]
     * }
     */
    public function generateMonthGrid(int $year, int $month): array
    {
        $tz    = new DateTimeZone($this->timezone);
        $today = new DateTimeImmutable('today', $tz);

        // First day of the month
        $firstOfMonth = new DateTimeImmutable("{$year}-{$month}-01", $tz);

        // Find start of the grid (may be in previous month)
        $gridStart = $this->getGridStart($firstOfMonth);

        $weeks = [];
        $current = $gridStart;

        for ($week = 0; $week < 6; $week++) {
            $weekRow = [];
            for ($day = 0; $day < 7; $day++) {
                $weekRow[] = $this->buildMonthCell($current, $month, $today);
                $current = $current->modify('+1 day');
            }
            $weeks[] = $weekRow;
        }

        return [
            'year'      => $year,
            'month'     => $month,
            'monthName' => $firstOfMonth->format('F'),
            'startDate' => $gridStart->format('Y-m-d'),
            'endDate'   => $current->modify('-1 day')->format('Y-m-d'),
            'weeks'     => $weeks,
        ];
    }

    /**
     * Find the start of the grid (first cell of the first week row).
     * Goes back to the firstDayOfWeek before or on the 1st of the month.
     */
    private function getGridStart(DateTimeImmutable $firstOfMonth): DateTimeImmutable
    {
        $dow = (int) $firstOfMonth->format('w'); // 0=Sunday, 6=Saturday
        $diff = ($dow - $this->firstDayOfWeek + 7) % 7;
        return $firstOfMonth->modify("-{$diff} days");
    }

    /**
     * Build a single day-cell for the month grid.
     */
    private function buildMonthCell(DateTimeImmutable $date, int $currentMonth, DateTimeImmutable $today): array
    {
        $isToday        = $date->format('Y-m-d') === $today->format('Y-m-d');
        $isCurrentMonth = (int) $date->format('n') === $currentMonth;
        $isPast         = $date < $today && !$isToday;
        $isFuture       = $date > $today;
        $weekday        = (int) $date->format('w'); // 0=Sunday

        return [
            'date'           => $date->format('Y-m-d'),
            'dayNumber'      => (int) $date->format('j'),
            'weekday'        => $weekday,
            'weekdayName'    => self::DAY_NAMES_STRING[$weekday],
            'week'           => (int) $date->format('W'),
            'isCurrentMonth' => $isCurrentMonth,
            'isToday'        => $isToday,
            'isPast'         => $isPast,
            'isFuture'       => $isFuture,
            // Appointment data injected later by MonthViewService
            'appointments'      => [],
            'appointmentCount'  => 0,
            'hasAvailability'   => false,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // WEEK RANGE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Generate 7-day range for a week view, anchored to firstDayOfWeek.
     *
     * @param string $date Any date within the desired week (Y-m-d)
     * @return array {
     *   startDate: string, endDate: string,
     *   days: array[7][{date, dayNumber, weekdayName, weekday, isToday, isPast}]
     * }
     */
    public function generateWeekRange(string $date): array
    {
        $tz      = new DateTimeZone($this->timezone);
        $today   = new DateTimeImmutable('today', $tz);
        $anchor  = new DateTimeImmutable($date, $tz);

        // Align to start of week
        $dow   = (int) $anchor->format('w');
        $diff  = ($dow - $this->firstDayOfWeek + 7) % 7;
        $start = $anchor->modify("-{$diff} days");

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d       = $start->modify("+{$i} days");
            $weekday = (int) $d->format('w');
            $days[]  = [
                'date'        => $d->format('Y-m-d'),
                'dayNumber'   => (int) $d->format('j'),
                'monthName'   => $d->format('M'),
                'fullDate'    => $d->format('D, M j'),
                'weekday'     => $weekday,
                'weekdayName' => self::DAY_NAMES_STRING[$weekday],
                'isToday'     => $d->format('Y-m-d') === $today->format('Y-m-d'),
                'isPast'      => $d < $today,
                // Appointments injected later
                'appointments' => [],
            ];
        }

        return [
            'startDate' => $start->format('Y-m-d'),
            'endDate'   => $start->modify('+6 days')->format('Y-m-d'),
            'days'      => $days,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // DAY TIME GRID
    // ─────────────────────────────────────────────────────────────────

    /**
     * Generate time slots for a day-view time grid.
     *
     * @param string $date              Date (Y-m-d)
     * @param string $startTime         Grid start time (H:i, e.g. '08:00')
     * @param string $endTime           Grid end time  (H:i, e.g. '18:00')
     * @param int    $resolutionMinutes Slot interval (15, 30, or 60)
     * @return array {
     *   date: string, dayStart: string, dayEnd: string,
     *   totalMinutes: int, pixelsPerMinute: int, containerHeight: int,
     *   slots: array[{time, minutes, label, isHour, isHalf}]
     * }
     */
    public function generateDaySlots(
        string $date,
        string $startTime = '08:00',
        string $endTime   = '18:00',
        int $resolutionMinutes = 30
    ): array {
        [$startH, $startM] = array_map('intval', explode(':', $startTime));
        [$endH,   $endM]   = array_map('intval', explode(':', $endTime));

        $startMinutes = $startH * 60 + $startM;
        $endMinutes   = $endH   * 60 + $endM;
        $totalMinutes = $endMinutes - $startMinutes;

        // 2px per minute — standard density for time grids
        $pixelsPerMinute = 2;

        $slots = [];
        for ($min = $startMinutes; $min < $endMinutes; $min += $resolutionMinutes) {
            $h      = intdiv($min, 60);
            $m      = $min % 60;
            $timeStr = sprintf('%02d:%02d', $h, $m);
            $slots[] = [
                'time'     => $timeStr,
                'minutes'  => $min - $startMinutes, // offset from day start
                'label'    => $this->formatTimeLabel($h, $m),
                'isHour'   => $m === 0,
                'isHalf'   => $m === 30,
                'topPx'    => ($min - $startMinutes) * $pixelsPerMinute,
            ];
        }

        return [
            'date'            => $date,
            'dayStart'        => $startTime,
            'dayEnd'          => $endTime,
            'totalMinutes'    => $totalMinutes,
            'pixelsPerMinute' => $pixelsPerMinute,
            'containerHeight' => $totalMinutes * $pixelsPerMinute,
            'slots'           => $slots,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Normalize a date string to Y-m-d, adjusting for timezone.
     */
    public function normalizeDate(string $date): string
    {
        $tz = new DateTimeZone($this->timezone);
        return (new DateTimeImmutable($date, $tz))->format('Y-m-d');
    }

    /**
     * Return weekday integer and string name for a date.
     * Bridges the gap between xs_business_hours (int) and xs_provider_schedules (string).
     *
     * @return array ['int' => 0-6, 'string' => 'sunday'|'monday'|...]
     */
    public function normalizeDayOfWeek(string $date): array
    {
        $tz      = new DateTimeZone($this->timezone);
        $d       = new DateTimeImmutable($date, $tz);
        $int     = (int) $d->format('w');
        return [
            'int'    => $int,
            'string' => self::DAY_NAMES_STRING[$int],
        ];
    }

    /**
     * Format a time label (respects 12h/24h preference if needed — currently 24h).
     */
    private function formatTimeLabel(int $hour, int $minute): string
    {
        return sprintf('%02d:%02d', $hour, $minute);
    }

    /**
     * Get first and last date of a month (Y-m-d strings).
     */
    public function getMonthBounds(int $year, int $month): array
    {
        $tz    = new DateTimeZone($this->timezone);
        $first = new DateTimeImmutable("{$year}-{$month}-01", $tz);
        $last  = $first->modify('last day of this month');
        return [
            'start' => $first->format('Y-m-d'),
            'end'   => $last->format('Y-m-d'),
        ];
    }
}
