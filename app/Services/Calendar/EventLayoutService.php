<?php

/**
 * =============================================================================
 * EVENT LAYOUT SERVICE
 * =============================================================================
 *
 * @file        app/Services/Calendar/EventLayoutService.php
 * @description Solves the overlapping appointments problem by detecting
 *              overlapping clusters and assigning column positions.
 *
 * PROBLEM SOLVED:
 * ─────────────────────────────────────────────────────────────────
 * When multiple appointments overlap in time, they must be rendered
 * side-by-side. This service:
 * - Detects overlapping clusters
 * - Assigns each event a column index
 * - Calculates total columns needed
 * - Computes column width (% of container)
 * - Returns positioning metadata for rendering
 *
 * ALGORITHM:
 * ─────────────────────────────────────────────────────────────────
 * Uses a sweep-line algorithm:
 * 1. Sort events by start time, then end time
 * 2. Track active intervals at each time point
 * 3. Assign each event to the lowest available column
 * 4. Calculate final dimensions for rendering
 *
 * OUTPUT SHAPE:
 * ─────────────────────────────────────────────────────────────────
 * [
 *   [
 *     'id' => 12,
 *     'start_at' => '2026-03-09T09:00:00Z',
 *     'end_at' => '2026-03-09T10:00:00Z',
 *     'column' => 0,          // Column index (0-based)
 *     'columns_total' => 3,   // Total columns in this cluster
 *     'column_width_pct' => 33.33,
 *     'column_left_pct' => 0.0,
 *     // ... original event fields
 *   ],
 *   // ... more events
 * ]
 *
 * EXAMPLE:
 * ─────────────────────────────────────────────────────────────────
 * Input: 3 events, 2 overlap in same hour, 1 doesn't overlap
 * Event A: 09:00-10:00
 * Event B: 09:00-10:00 (overlaps A)
 * Event C: 10:00-11:00
 *
 * Output:
 * Event A: column=0, columns_total=2, column_width_pct=50
 * Event B: column=1, columns_total=2, column_width_pct=50
 * Event C: column=0, columns_total=1, column_width_pct=100
 *
 * @package     App\Services\Calendar
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services\Calendar;

use DateTimeImmutable;
use DateTimeZone;

class EventLayoutService
{
    /** Soft guard to surface potential quadratic-cost layouts in logs. */
    private const LARGE_DATASET_WARNING_THRESHOLD = 200;

    /**
     * Resolve overlapping events and assign column positions.
     *
     * @param array $events Array of events with 'start_at' and 'end_at' (ISO strings or DateTime)
     * @return array Events with added '_column', '_columns_total', '_column_width_pct', '_column_left_pct'
     */
    public function resolveLayout(array $events): array
    {
        if (empty($events)) {
            return [];
        }

        if (count($events) >= self::LARGE_DATASET_WARNING_THRESHOLD) {
            log_message('warning', sprintf(
                '[EventLayoutService] Large overlap layout input (%d events). Current assignment pass may degrade at O(n^2).',
                count($events)
            ));
        }

        // Sort events by start time, then end time
        $sorted = $this->sortEvents($events);

        // Use sweep-line algorithm to assign columns
        $columnsAssigned = $this->assignColumns($sorted);

        // Add positioning metadata to each event
        return $this->addPositioningMetadata($columnsAssigned);
    }

    /**
     * Sort events by start time (ascending), then end time (ascending).
     *
     * @param array $events
     * @return array Sorted events
     */
    private function sortEvents(array $events): array
    {
        $sorted = $events;

        usort($sorted, function ($a, $b) {
            $aStart = $this->toMillis($a['start_at'] ?? $a['start_datetime'] ?? $a['startDateTime']);
            $bStart = $this->toMillis($b['start_at'] ?? $b['start_datetime'] ?? $b['startDateTime']);

            if ($aStart !== $bStart) {
                return $aStart - $bStart;
            }

            $aEnd = $this->toMillis($a['end_at'] ?? $a['end_datetime'] ?? $a['endDateTime']);
            $bEnd = $this->toMillis($b['end_at'] ?? $b['end_datetime'] ?? $b['endDateTime']);

            return $aEnd - $bEnd;
        });

        return $sorted;
    }

    /**
     * Assign column indices using sweep-line algorithm.
     *
     * @param array $sorted Sorted events
     * @return array Events with '_col' and '_col_count' fields
     */
    private function assignColumns(array $sorted): array
    {
        // Build events with numeric indices for tracking
        $events = [];
        foreach ($sorted as $idx => $event) {
            $events[$idx] = $event;
        }

        // Create timeline events (start/end markers)
        $timeline = [];
        foreach (array_keys($events) as $idx) {
            $event = $events[$idx];
            $start = $this->toMillis($event['start_at'] ?? $event['start_datetime'] ?? $event['startDateTime']);
            $end = $this->toMillis($event['end_at'] ?? $event['end_datetime'] ?? $event['endDateTime']);

            $timeline[] = ['time' => $start, 'type' => 'start', 'idx' => $idx];
            $timeline[] = ['time' => $end, 'type' => 'end', 'idx' => $idx];
        }

        // Sort timeline events (ends before starts at same time)
        usort($timeline, function ($a, $b) {
            if ($a['time'] !== $b['time']) {
                return $a['time'] - $b['time'];
            }
            return ($a['type'] === 'end' ? -1 : 1) - ($b['type'] === 'end' ? -1 : 1);
        });

        // Process timeline to assign columns
        $activeEvents = [];
        $columnMap = []; // idx => assigned column

        foreach ($timeline as $marker) {
            if ($marker['type'] === 'start') {
                // Find lowest available column
                $usedColumns = [];
                foreach ($activeEvents as $activeIdx) {
                    $usedColumns[] = $columnMap[$activeIdx] ?? 0;
                }

                $col = 0;
                while (in_array($col, $usedColumns, true)) {
                    $col++;
                }

                $columnMap[$marker['idx']] = $col;
                $activeEvents[] = $marker['idx'];
            } else {
                // Remove from active
                $key = array_search($marker['idx'], $activeEvents, true);
                if ($key !== false) {
                    unset($activeEvents[$key]);
                }
            }
        }

        // Calculate max column for each cluster
        $clusterMap = []; // idx => cluster_col_count

        foreach (array_keys($events) as $idx) {
            $event = $events[$idx];
            $eventStart = $this->toMillis($event['start_at'] ?? $event['start_datetime'] ?? $event['startDateTime']);
            $eventEnd = $this->toMillis($event['end_at'] ?? $event['end_datetime'] ?? $event['endDateTime']);

            // Find all events that overlap with this event
            $maxCol = $columnMap[$idx];

            foreach (array_keys($events) as $otherIdx) {
                if ($otherIdx === $idx) {
                    continue;
                }

                $otherEvent = $events[$otherIdx];
                $otherStart = $this->toMillis($otherEvent['start_at'] ?? $otherEvent['start_datetime'] ?? $otherEvent['startDateTime']);
                $otherEnd = $this->toMillis($otherEvent['end_at'] ?? $otherEvent['end_datetime'] ?? $otherEvent['endDateTime']);

                // Check if overlap
                if ($eventStart < $otherEnd && $otherStart < $eventEnd) {
                    $maxCol = max($maxCol, $columnMap[$otherIdx]);
                }
            }

            $clusterMap[$idx] = $maxCol + 1; // +1 because columns are 0-indexed
        }

        // Add to events
        foreach (array_keys($events) as $idx) {
            $events[$idx]['_col'] = $columnMap[$idx];
            $events[$idx]['_col_count'] = $clusterMap[$idx];
        }

        return $events;
    }

    /**
     * Add positioning metadata to events for rendering.
     *
     * @param array $events Events with '_col' and '_col_count'
     * @return array Events with full positioning data
     */
    private function addPositioningMetadata(array $events): array
    {
        foreach ($events as &$event) {
            $col = $event['_col'] ?? 0;
            $colCount = $event['_col_count'] ?? 1;

            $event['_column'] = $col;
            $event['_columns_total'] = $colCount;
            $event['_column_width_pct'] = ($colCount > 0) ? (100 / $colCount) : 100;
            $event['_column_left_pct'] = $col * $event['_column_width_pct'];

            // Clean up temporary fields
            unset($event['_col']);
            unset($event['_col_count']);
        }

        return array_values($events);
    }

    /**
     * Convert datetime (string, DateTime, or similar) to milliseconds since epoch.
     *
     * @param mixed $dt DateTime, ISO string, or similar
     * @return int Milliseconds since epoch
     */
    private function toMillis($dt): int
    {
        if (is_numeric($dt)) {
            return (int) $dt;
        }

        if (is_string($dt)) {
            try {
                // Parse all naive timestamps as UTC so overlap checks are timezone-stable.
                $date = new DateTimeImmutable($dt, new DateTimeZone('UTC'));
                return (int) ($date->setTimezone(new DateTimeZone('UTC'))->getTimestamp() * 1000);
            } catch (\Exception $e) {
                return 0;
            }
        }

        if (is_object($dt) && method_exists($dt, 'getTimestamp')) {
            return (int) ($dt->getTimestamp() * 1000);
        }

        return 0;
    }
}
