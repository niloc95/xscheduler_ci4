<?php

/**
 * =============================================================================
 * TIME GRID SERVICE
 * =============================================================================
 *
 * @file        app/Services/Calendar/TimeGridService.php
 * @description Single source of truth for day time-grid generation.
 *              Wraps CalendarRangeService::generateDaySlots with shared
 *              calendar settings (day start/end + resolution).
 *
 * @package     App\Services\Calendar
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services\Calendar;

use App\Models\SettingModel;

class TimeGridService
{
    private CalendarRangeService $range;
    private string $dayStart;
    private string $dayEnd;
    private int $resolution;

    public function __construct(?CalendarRangeService $range = null)
    {
        $this->range = $range ?? new CalendarRangeService();

        $settings = (new SettingModel())->getByKeys([
            'calendar.day_start',
            'calendar.day_end',
            'booking.time_resolution',
        ]);

        $this->dayStart   = $settings['calendar.day_start']       ?? '08:00';
        $this->dayEnd     = $settings['calendar.day_end']         ?? '18:00';
        $this->resolution = (int) ($settings['booking.time_resolution'] ?? 30);
    }

    public function generateDayGrid(string $date): array
    {
        return $this->range->generateDaySlots(
            $date,
            $this->dayStart,
            $this->dayEnd,
            $this->resolution
        );
    }

    public function getDayStart(): string
    {
        return $this->dayStart;
    }

    public function getDayEnd(): string
    {
        return $this->dayEnd;
    }

    public function getResolution(): int
    {
        return $this->resolution;
    }
}
