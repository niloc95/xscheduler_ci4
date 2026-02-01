<?php

/**
 * =============================================================================
 * BUSINESS HOURS SERVICE
 * =============================================================================
 * 
 * @file        app/Services/BusinessHoursService.php
 * @description Centralizes business hours validation logic for appointment
 *              scheduling across all controllers and services.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides consistent business hours validation to prevent appointments
 * from being booked outside operating hours. Used by:
 * - AppointmentBookingService
 * - AvailabilityService
 * - Public booking API
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * validateAppointmentTime($startTime, $endTime)
 *   Check if appointment is within business hours
 *   Returns: ['valid' => bool, 'reason' => string|null, 'hours' => array|null]
 * 
 * getBusinessHoursForDay($weekdayNum)
 *   Get hours for specific day (0=Sunday, 6=Saturday)
 *   Returns: ['start_time' => 'HH:mm:ss', 'end_time' => 'HH:mm:ss'] or null
 * 
 * isBusinessOpen($weekdayNum)
 *   Check if business is open on given day
 * 
 * getWeekdayNumber($dateTime)
 *   Convert DateTime to weekday number (0-6)
 * 
 * DAY MAPPING:
 * -----------------------------------------------------------------------------
 * - 0: Sunday
 * - 1: Monday
 * - 2: Tuesday
 * - 3: Wednesday
 * - 4: Thursday
 * - 5: Friday
 * - 6: Saturday
 * 
 * VALIDATION FLOW:
 * -----------------------------------------------------------------------------
 * 1. Get weekday number from appointment start time
 * 2. Check if business is open that day
 * 3. Check if time is within start/end hours
 * 4. Return validation result with helpful message
 * 
 * @see         app/Models/BusinessHourModel.php for data storage
 * @see         app/Services/AvailabilityService.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use DateTime;
use DateTimeZone;

/**
 * BusinessHoursService
 * 
 * Centralizes business hours validation logic to eliminate duplication
 * across controllers. Provides consistent validation for appointment scheduling.
 */
class BusinessHoursService
{
    /**
     * Day of week mapping from name to number (0=Sunday, 6=Saturday)
     */
    private const DAY_MAPPING = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6
    ];

    /**
     * Validate if an appointment time is within business hours
     * 
     * @param DateTime $startTime Appointment start time
     * @param DateTime $endTime Appointment end time
     * @return array ['valid' => bool, 'reason' => string|null, 'hours' => array|null]
     */
    public function validateAppointmentTime(DateTime $startTime, DateTime $endTime): array
    {
        $weekdayNum = $this->getWeekdayNumber($startTime);
        $hours = $this->getBusinessHoursForDay($weekdayNum);
        
        // Check if business is closed on this day
        if (!$hours) {
            $dayName = ucfirst($startTime->format('l'));
            return [
                'valid' => false,
                'reason' => "Sorry, we are closed on {$dayName}. Please choose another day.",
                'hours' => null
            ];
        }
        
        // Check if start time is within business hours
        $requestedTime = $startTime->format('H:i:s');
        if ($requestedTime < $hours['start_time'] || $requestedTime >= $hours['end_time']) {
            $startFormatted = date('g:i A', strtotime($hours['start_time']));
            $endFormatted = date('g:i A', strtotime($hours['end_time']));
            return [
                'valid' => false,
                'reason' => "The requested time is outside our business hours ({$startFormatted} - {$endFormatted}). Please choose a different time.",
                'hours' => $hours
            ];
        }
        
        // Check if end time extends past business hours
        $requestedEndTime = $endTime->format('H:i:s');
        if ($requestedEndTime > $hours['end_time']) {
            return [
                'valid' => false,
                'reason' => 'This appointment would extend past our closing time. Please choose an earlier time slot.',
                'hours' => $hours
            ];
        }
        
        // All validations passed
        return [
            'valid' => true,
            'reason' => null,
            'hours' => $hours
        ];
    }

    /**
     * Get business hours for a specific date
     * 
     * @param string $date Date in Y-m-d format
     * @return array|null Business hours array or null if closed
     */
    public function getBusinessHoursForDate(string $date): ?array
    {
        try {
            $dateTime = new DateTime($date);
            $weekdayNum = $this->getWeekdayNumber($dateTime);
            return $this->getBusinessHoursForDay($weekdayNum);
        } catch (\Exception $e) {
            log_message('error', '[BusinessHoursService] Invalid date: ' . $date);
            return null;
        }
    }

    /**
     * Check if a specific date is a working day
     * 
     * @param string $date Date in Y-m-d format
     * @return bool True if business is open on this date
     */
    public function isWorkingDay(string $date): bool
    {
        return $this->getBusinessHoursForDate($date) !== null;
    }

    /**
     * Get weekday number from DateTime (0=Sunday, 6=Saturday)
     * 
     * @param DateTime $dateTime Date/time object
     * @return int Weekday number (0-6)
     */
    private function getWeekdayNumber(DateTime $dateTime): int
    {
        $dayName = strtolower($dateTime->format('l'));
        return self::DAY_MAPPING[$dayName] ?? 0;
    }

    /**
     * Get business hours from database for a specific weekday
     * 
     * @param int $weekdayNum Weekday number (0=Sunday, 6=Saturday)
     * @return array|null Business hours row or null if closed
     */
    private function getBusinessHoursForDay(int $weekdayNum): ?array
    {
        $db = \Config\Database::connect();
        $result = $db->table('business_hours')
            ->where('weekday', $weekdayNum)
            ->get()
            ->getRowArray();
        
        return $result ?: null;
    }

    /**
     * Format business hours for display
     * 
     * @param array $hours Business hours array from database
     * @return string Formatted hours string (e.g., "9:00 AM - 5:00 PM")
     */
    public function formatHours(array $hours): string
    {
        if (empty($hours['start_time']) || empty($hours['end_time'])) {
            return 'Closed';
        }
        
        $startFormatted = date('g:i A', strtotime($hours['start_time']));
        $endFormatted = date('g:i A', strtotime($hours['end_time']));
        
        return "{$startFormatted} - {$endFormatted}";
    }

    /**
     * Get all business hours for the week
     * 
     * @return array Array of business hours indexed by weekday number
     */
    public function getWeeklyHours(): array
    {
        $db = \Config\Database::connect();
        $rows = $db->table('business_hours')
            ->orderBy('weekday', 'ASC')
            ->get()
            ->getResultArray();
        
        $weekly = [];
        foreach ($rows as $row) {
            $weekly[$row['weekday']] = $row;
        }
        
        return $weekly;
    }
}
