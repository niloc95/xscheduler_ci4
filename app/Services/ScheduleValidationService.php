<?php

/**
 * =============================================================================
 * SCHEDULE VALIDATION SERVICE
 * =============================================================================
 * 
 * @file        app/Services/ScheduleValidationService.php
 * @description Centralized validation for provider schedules, business hours,
 *              and time slots. Extracted from controllers for reuse.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides consistent validation for:
 * - Provider weekly schedule input
 * - Business hours configuration
 * - Time slot formatting and validation
 * - Break time validation within schedules
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * validateProviderSchedule($input)
 *   Validate raw schedule form input
 *   Returns: [$cleanData, $errors] tuple
 * 
 * normaliseTimeString($time)
 *   Convert various time formats to HH:mm:ss
 *   Handles: "9:00", "09:00", "9am", "9:00 AM"
 * 
 * validateTimeRange($startTime, $endTime)
 *   Ensure start time is before end time
 * 
 * validateBreakWithinHours($start, $end, $breakStart, $breakEnd)
 *   Ensure break falls within working hours
 * 
 * SCHEDULE DAYS:
 * -----------------------------------------------------------------------------
 * - monday
 * - tuesday
 * - wednesday
 * - thursday
 * - friday
 * - saturday
 * - sunday
 * 
 * INPUT FORMAT:
 * -----------------------------------------------------------------------------
 *     [
 *         'monday' => [
 *             'is_active' => true,
 *             'start_time' => '09:00',
 *             'end_time' => '17:00',
 *             'break_start' => '12:00',
 *             'break_end' => '13:00'
 *         ],
 *         // ... other days
 *     ]
 * 
 * ERROR MESSAGES:
 * -----------------------------------------------------------------------------
 * Returns user-friendly error messages:
 * - "End time must be after start time"
 * - "Break must fall within working hours"
 * - "Invalid time format"
 * 
 * @see         app/Controllers/UserManagement.php for usage
 * @see         app/Models/ProviderScheduleModel.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @since       2.0.0
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

/**
 * Schedule Validation Service
 * 
 * Centralized validation for provider schedules, business hours, and time slots.
 * Extracted from UserManagement controller to promote code reuse.
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */
class ScheduleValidationService
{
    protected LocalizationSettingsService $localization;
    
    /**
     * Days of the week for schedule validation
     */
    protected array $scheduleDays = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'
    ];

    public function __construct(?LocalizationSettingsService $localization = null)
    {
        $this->localization = $localization ?? new LocalizationSettingsService();
    }

    /**
     * Validate provider schedule input
     * 
     * @param array $input Raw schedule input from form
     * @return array [cleanData, errors] tuple
     */
    public function validateProviderSchedule(array $input): array
    {
        $clean = [];
        $errors = [];

        foreach ($this->scheduleDays as $day) {
            if (!isset($input[$day])) {
                continue;
            }

            $row = $input[$day];
            $isActive = $this->toBool($row['is_active'] ?? null);

            if (!$isActive) {
                continue;
            }

            $rawStart = $row['start_time'] ?? null;
            $rawEnd = $row['end_time'] ?? null;
            $rawBreakStart = $row['break_start'] ?? null;
            $rawBreakEnd = $row['break_end'] ?? null;

            $start = $this->normaliseTimeString($rawStart);
            $end = $this->normaliseTimeString($rawEnd);
            $breakStart = $this->normaliseTimeString($rawBreakStart);
            $breakEnd = $this->normaliseTimeString($rawBreakEnd);

            // Validate required times
            if (!$start || !$end) {
                $errors[$day] = 'Start and end times are required. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            // Validate time order
            if (strtotime($start) >= strtotime($end)) {
                $errors[$day] = 'Start time must be earlier than end time.';
                continue;
            }

            // Validate break times format
            $hasBreakStartInput = is_string($rawBreakStart) && trim($rawBreakStart) !== '';
            $hasBreakEndInput = is_string($rawBreakEnd) && trim($rawBreakEnd) !== '';

            if ($hasBreakStartInput && !$breakStart) {
                $errors[$day] = 'Break start must use the expected time format. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            if ($hasBreakEndInput && !$breakEnd) {
                $errors[$day] = 'Break end must use the expected time format. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            // Validate break completeness
            if (($breakStart && !$breakEnd) || (!$breakStart && $breakEnd)) {
                $errors[$day] = 'Provide both break start and end times. ' . $this->localization->describeExpectedFormat();
                continue;
            }

            // Validate break times
            if ($breakStart && $breakEnd) {
                if (strtotime($breakStart) >= strtotime($breakEnd)) {
                    $errors[$day] = 'Break start must be earlier than break end.';
                    continue;
                }

                if (strtotime($breakStart) < strtotime($start) || strtotime($breakEnd) > strtotime($end)) {
                    $errors[$day] = 'Break must fall within working hours.';
                    continue;
                }
            }

            $clean[$day] = [
                'is_active' => 1,
                'start_time' => $start,
                'end_time' => $end,
                'break_start' => $breakStart,
                'break_end' => $breakEnd,
            ];
        }

        return [$clean, $errors];
    }

    /**
     * Prepare schedule data for view display.
     *
     * Returns time values in HH:MM 24-hour format suitable for native
     * <input type="time"> elements.
     * 
     * @param array|null $source Source schedule data
     * @return array Prepared schedule with all days
     */
    public function prepareScheduleForView($source): array
    {
        $prepared = [];
        
        foreach ($this->scheduleDays as $day) {
            $prepared[$day] = [
                'is_active' => false,
                'start_time' => '',
                'end_time' => '',
                'break_start' => '',
                'break_end' => '',
            ];
        }

        if (!is_array($source)) {
            return $prepared;
        }

        foreach ($this->scheduleDays as $day) {
            if (!isset($source[$day]) || !is_array($source[$day])) {
                continue;
            }

            $row = $source[$day];
            $prepared[$day] = [
                'is_active' => $this->toBool($row['is_active'] ?? null),
                'start_time' => $this->formatTimeForNativeInput($row['start_time'] ?? ''),
                'end_time' => $this->formatTimeForNativeInput($row['end_time'] ?? ''),
                'break_start' => $this->formatTimeForNativeInput($row['break_start'] ?? ''),
                'break_end' => $this->formatTimeForNativeInput($row['break_end'] ?? ''),
            ];
        }

        return $prepared;
    }

    /**
     * Validate a single time slot
     * 
     * @param string|null $startTime Start time
     * @param string|null $endTime End time
     * @param string|null $timezone Optional timezone
     * @return array ['valid' => bool, 'errors' => array, 'normalized' => array]
     */
    public function validateTimeSlot(?string $startTime, ?string $endTime, ?string $timezone = null): array
    {
        $errors = [];
        $normalized = ['start' => null, 'end' => null];

        if (!$startTime || !$endTime) {
            $errors[] = 'Start and end times are required.';
            return ['valid' => false, 'errors' => $errors, 'normalized' => $normalized];
        }

        $start = $this->normaliseTimeString($startTime);
        $end = $this->normaliseTimeString($endTime);

        if (!$start) {
            $errors[] = 'Invalid start time format. ' . $this->localization->describeExpectedFormat();
        }

        if (!$end) {
            $errors[] = 'Invalid end time format. ' . $this->localization->describeExpectedFormat();
        }

        if ($start && $end && strtotime($start) >= strtotime($end)) {
            $errors[] = 'Start time must be earlier than end time.';
        }

        $normalized['start'] = $start;
        $normalized['end'] = $end;

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'normalized' => $normalized,
        ];
    }

    /**
     * Validate business hours against a time slot
     * 
     * @param string $startTime Appointment start time
     * @param string $endTime Appointment end time
     * @param array $businessHours Business hours for the day
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateAgainstBusinessHours(string $startTime, string $endTime, array $businessHours): array
    {
        $errors = [];

        if (empty($businessHours) || !($businessHours['is_active'] ?? false)) {
            $errors[] = 'Business is closed on this day.';
            return ['valid' => false, 'errors' => $errors];
        }

        $businessStart = $businessHours['start_time'] ?? null;
        $businessEnd = $businessHours['end_time'] ?? null;

        if (!$businessStart || !$businessEnd) {
            $errors[] = 'Business hours not configured for this day.';
            return ['valid' => false, 'errors' => $errors];
        }

        // Convert to timestamps for comparison
        $apptStart = strtotime($startTime);
        $apptEnd = strtotime($endTime);
        $bizStart = strtotime($businessStart);
        $bizEnd = strtotime($businessEnd);

        if ($apptStart < $bizStart) {
            $errors[] = 'Appointment starts before business hours (' . $this->formatTimeForDisplay($businessStart) . ').';
        }

        if ($apptEnd > $bizEnd) {
            $errors[] = 'Appointment ends after business hours (' . $this->formatTimeForDisplay($businessEnd) . ').';
        }

        // Check if appointment falls within break time
        $breakStart = $businessHours['break_start'] ?? null;
        $breakEnd = $businessHours['break_end'] ?? null;

        if ($breakStart && $breakEnd) {
            $breakStartTs = strtotime($breakStart);
            $breakEndTs = strtotime($breakEnd);

            // Check for overlap with break
            if ($apptStart < $breakEndTs && $apptEnd > $breakStartTs) {
                $errors[] = 'Appointment overlaps with break time (' . 
                           $this->formatTimeForDisplay($breakStart) . ' - ' . 
                           $this->formatTimeForDisplay($breakEnd) . ').';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get list of schedule days
     * 
     * @return array
     */
    public function getScheduleDays(): array
    {
        return $this->scheduleDays;
    }

    /**
     * Normalize a time string to 24-hour format (HH:MM:SS)
     * 
     * @param mixed $value Raw time value
     * @return string|null Normalized time or null if invalid
     */
    protected function normaliseTimeString($value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->localization->normaliseTimeInput(trim($value));
    }

    /**
     * Format time for display using localized format
     * 
     * @param string|null $time Time in HH:MM:SS format
     * @return string Formatted time or empty string
     */
    protected function formatTimeForDisplay(?string $time): string
    {
        if (!$time || trim($time) === '') {
            return '';
        }

        return $this->localization->formatTimeForDisplay($time);
    }

    /**
     * Format time for native <input type="time"> elements (HH:MM 24h).
     *
     * @param string|null $time Time in any accepted format
     * @return string HH:MM string or empty string
     */
    protected function formatTimeForNativeInput(?string $time): string
    {
        if (!$time || trim($time) === '') {
            return '';
        }

        return $this->localization->formatTimeForNativeInput($time);
    }

    /**
     * Convert value to boolean
     * 
     * @param mixed $value Value to convert
     * @return bool
     */
    protected function toBool($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
