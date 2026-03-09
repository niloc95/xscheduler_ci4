<?php

namespace App\Services\Calendar;

/**
 * SlotInjectionTrait
 *
 * Shared slot-injection and time-conversion logic for DayViewService
 * and WeekViewService. Extracted to prevent copy-paste duplication.
 *
 * @package App\Services\Calendar
 */
trait SlotInjectionTrait
{
    /**
     * Inject formatted appointments into the appropriate time slots.
     *
     * An appointment is placed in the slot whose boundary it falls within.
     * Also adds absolute-position metadata (_topPx, _heightPx, _colIndex, etc.)
     * used by the JS renderer.
     *
     * @param array  $slots       Time slot array from CalendarRangeService
     * @param array  $events      Formatted appointment array
     * @param array  $grid        Grid metadata (dayStart, pixelsPerMinute)
     * @param int    $resolution  Slot duration in minutes (for rounding)
     * @return array Updated slots with appointments injected
     */
    protected function injectIntoSlots(array $slots, array $events, array $grid, int $resolution): array
    {
        if (empty($events)) {
            return $slots;
        }

        $startMinutes    = $this->timeToMinutes($grid['dayStart']);
        $pixelsPerMinute = $grid['pixelsPerMinute'];

        // Pre-compute absolute position for each event
        $positioned = array_map(function (array $event) use ($startMinutes, $pixelsPerMinute) {
            $eventStart = $this->extractLocalTimeHHMM($event['start'] ?? null);
            $eventEnd   = $this->extractLocalTimeHHMM($event['end'] ?? null);

            $startMin = $this->timeToMinutes($eventStart);
            $endMin   = $this->timeToMinutes($eventEnd);
            $duration = max($endMin - $startMin, 15); // minimum 15 min display height

            $event['_topPx']    = ($startMin - $startMinutes) * $pixelsPerMinute;
            $event['_heightPx'] = $duration * $pixelsPerMinute;
            $event['_startMin'] = $startMin;
            $event['_endMin']   = $startMin + $duration;

            return $event;
        }, $events);

        // EventLayoutService already resolves overlap metadata. Keep fields in sync
        // for renderers expecting either legacy or canonical keys.
        $positioned = array_map(function (array $event) {
            if (isset($event['_column'])) {
                $event['_colIndex'] = (int) $event['_column'];
            }
            if (isset($event['_columns_total'])) {
                $event['_colCount'] = (int) $event['_columns_total'];
            }
            if (isset($event['_column_width_pct'])) {
                $event['_widthPct'] = (float) $event['_column_width_pct'];
            }
            if (isset($event['_column_left_pct'])) {
                $event['_leftPct'] = (float) $event['_column_left_pct'];
            }

            return $event;
        }, $positioned);

        // Build reverse lookup: slot time → index
        $slotMap = [];
        foreach ($slots as $i => $slot) {
            $slotMap[$slot['time']] = $i;
        }

        // Assign each event to its nearest earlier slot boundary
        foreach ($positioned as $event) {
            $slotTime = $this->extractLocalTimeHHMM($event['start'] ?? null);
            $startMin = $this->timeToMinutes($slotTime);
            $slotMin  = $startMin - ($startMin % $resolution);
            $slotKey  = sprintf('%02d:%02d', intdiv($slotMin, 60), $slotMin % 60);

            if (isset($slotMap[$slotKey])) {
                $slots[$slotMap[$slotKey]]['appointments'][] = $event;
            }
        }

        return $slots;
    }

    /**
     * Convert 'HH:MM' (or 'HH:MM:SS') string to total minutes.
     */
    protected function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time . ':00'));
        return $h * 60 + $m;
    }

    /**
     * Convert any datetime string into HH:MM in business-local timezone.
     *
     * Supports:
     * - ISO UTC/offset strings (e.g. 2026-03-10T09:00:00Z)
     * - SQL UTC strings (e.g. 2026-03-10 09:00:00)
     */
    protected function extractLocalTimeHHMM(?string $datetime): string
    {
        if (!$datetime) {
            return '00:00';
        }

        try {
            $businessTz = new \DateTimeZone(\App\Services\TimezoneService::businessTimezone());

            // If timezone info exists in the string, DateTimeImmutable will honor it.
            // Otherwise we treat input as UTC per storage contract.
            $hasTzInfo = (bool) preg_match('/(Z|[+\-]\d{2}:?\d{2})$/', trim($datetime));
            $dt = $hasTzInfo
                ? new \DateTimeImmutable($datetime)
                : new \DateTimeImmutable($datetime, new \DateTimeZone('UTC'));

            return $dt->setTimezone($businessTz)->format('H:i');
        } catch (\Throwable $e) {
            return substr((string) $datetime, 11, 5) ?: '00:00';
        }
    }
}
