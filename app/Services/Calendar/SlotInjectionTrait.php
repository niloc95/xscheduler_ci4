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
            $eventStart = substr($event['start'] ?? '', 11, 5);   // HH:MM
            $eventEnd   = substr($event['end']   ?? '', 11, 5);

            $startMin = $this->timeToMinutes($eventStart);
            $endMin   = $this->timeToMinutes($eventEnd);
            $duration = max($endMin - $startMin, 15); // minimum 15 min display height

            $event['_topPx']    = ($startMin - $startMinutes) * $pixelsPerMinute;
            $event['_heightPx'] = $duration * $pixelsPerMinute;
            $event['_startMin'] = $startMin;
            $event['_endMin']   = $startMin + $duration;

            return $event;
        }, $events);

        // Resolve N-column overlap layout (simple sweep-line)
        $positioned = $this->resolveOverlapColumns($positioned);

        // Build reverse lookup: slot time → index
        $slotMap = [];
        foreach ($slots as $i => $slot) {
            $slotMap[$slot['time']] = $i;
        }

        // Assign each event to its nearest earlier slot boundary
        foreach ($positioned as $event) {
            $slotTime = substr($event['start'] ?? '', 11, 5);
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
     * Resolve N-column overlap layout for events that overlap in time.
     * Sets `_colIndex` (0-based) and `_colCount` (total columns in group),
     * plus CSS-ready `_widthPct` and `_leftPct`.
     */
    protected function resolveOverlapColumns(array $events): array
    {
        if (empty($events)) {
            return $events;
        }

        usort($events, fn($a, $b) => $a['_startMin'] <=> $b['_startMin']);

        $groups = [];
        $active = [];

        foreach ($events as &$event) {
            $active = array_filter($active, fn($e) => $e['_endMin'] > $event['_startMin']);

            if (empty($active)) {
                $groups[] = [&$event];
            } else {
                $groups[count($groups) - 1][] = &$event;
            }
            $active[] = &$event;
        }
        unset($event);

        foreach ($groups as &$group) {
            $colCount = count($group);
            foreach ($group as $col => &$e) {
                $e['_colIndex'] = $col;
                $e['_colCount'] = $colCount;
                $e['_widthPct'] = round(100 / $colCount, 2);
                $e['_leftPct']  = round(($col / $colCount) * 100, 2);
            }
            unset($e);
        }
        unset($group);

        return $events;
    }

    /**
     * Convert 'HH:MM' (or 'HH:MM:SS') string to total minutes.
     */
    protected function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time . ':00'));
        return $h * 60 + $m;
    }
}
