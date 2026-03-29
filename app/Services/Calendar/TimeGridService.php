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
 * PRIORITY FOR TIME BOUNDARIES:
 * ─────────────────────────────────────────────────────────────────
 * 1. ProviderScheduleModel (provider-specific override)
 * 2. BusinessHourModel (business-wide setting)
 * 3. CalendarConfigService (fallback defaults)
 *
 * This ensures that provider-specific hours always take precedence
 * over business-wide defaults.
 *
 * @package     App\Services\Calendar
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
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
            'booking.day_start',
            'booking.day_end',
            'booking.time_resolution',
        ]);

        $this->dayStart   = $settings['booking.day_start']       ?? '08:00';
        $this->dayEnd     = $settings['booking.day_end']         ?? '17:00';
        $this->resolution = (int) ($settings['booking.time_resolution'] ?? 30);
    }

    /**
     * Generate day grid using default business hours.
     *
     * @param string $date Y-m-d format
     * @return array Grid with slots
     */
    public function generateDayGrid(string $date): array
    {
        return $this->range->generateDaySlots(
            $date,
            $this->dayStart,
            $this->dayEnd,
            $this->resolution
        );
    }

    /**
     * Generate day grid using provider-specific working hours.
     *
     * Priority:
     * 1. Provider schedule (if available and isActive=true)
     * 2. Business hours (fallback)
     *
     * @param string $date Y-m-d format
     * @param array $providerHours { startTime, endTime, breakStart?, breakEnd?, isActive }
     * @return array Grid with slots
     */
    public function generateDayGridWithProviderHours(string $date, array $providerHours): array
    {
        // Use provider hours if active, otherwise fall back to business hours
        $startTime = (false === $providerHours['isActive'])
            ? $this->dayStart
            : ($providerHours['startTime'] ?? $this->dayStart);

        $endTime = (false === $providerHours['isActive'])
            ? $this->dayEnd
            : ($providerHours['endTime'] ?? $this->dayEnd);

        return $this->range->generateDaySlots(
            $date,
            $startTime,
            $endTime,
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
