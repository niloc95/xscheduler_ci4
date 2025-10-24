<?php

namespace App\Services;

use DateTime;
use DateTimeZone;

/**
 * Timezone Service
 * 
 * Handles conversion between user's local timezone and UTC for consistent
 * appointment storage and display across different timezones.
 * 
 * @package App\Services
 */
class TimezoneService
{
    /**
     * Convert local time to UTC
     * 
     * Takes a datetime in the user's local timezone and converts it to UTC
     * for consistent storage in the database.
     * 
     * @param string $localTime Local datetime (format: YYYY-MM-DD HH:mm:ss or ISO 8601)
     * @param string|null $timezone User's timezone (IANA format, e.g., 'America/New_York')
     *                              If null, uses session timezone or app default
     * @return string UTC datetime (format: YYYY-MM-DD HH:mm:ss)
     * 
     * @example
     * // User in NYC (EDT = UTC-4) enters 10:30
     * TimezoneService::toUTC('2025-10-24 10:30:00', 'America/New_York');
     * // Returns: '2025-10-24 14:30:00' (UTC)
     */
    public static function toUTC($localTime, $timezone = null)
    {
        if (!$timezone) {
            $timezone = self::getSessionTimezone();
        }
        
        try {
            $tz = new DateTimeZone($timezone);
            $date = new DateTime($localTime, $tz);
            $date->setTimezone(new DateTimeZone('UTC'));
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            log_message('error', 'TimezoneService::toUTC - Conversion error: ' . $e->getMessage());
            // Fallback: assume input is already UTC
            return $localTime;
        }
    }
    
    /**
     * Convert UTC time to local timezone
     * 
     * Takes a UTC datetime and converts it to the user's local timezone
     * for display purposes.
     * 
     * @param string $utcTime UTC datetime (format: YYYY-MM-DD HH:mm:ss)
     * @param string|null $timezone Target timezone (defaults to session timezone)
     * @return string Local datetime (format: YYYY-MM-DD HH:mm:ss)
     * 
     * @example
     * // UTC time 14:30 converted to NYC time (EDT = UTC-4)
     * TimezoneService::fromUTC('2025-10-24 14:30:00', 'America/New_York');
     * // Returns: '2025-10-24 10:30:00'
     */
    public static function fromUTC($utcTime, $timezone = null)
    {
        if (!$timezone) {
            $timezone = self::getSessionTimezone();
        }
        
        try {
            $date = new DateTime($utcTime, new DateTimeZone('UTC'));
            $tz = new DateTimeZone($timezone);
            $date->setTimezone($tz);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            log_message('error', 'TimezoneService::fromUTC - Conversion error: ' . $e->getMessage());
            return $utcTime;
        }
    }
    
    /**
     * Get timezone offset in minutes
     * 
     * Calculates the offset between a specified timezone and UTC.
     * Positive values are east of UTC, negative values are west.
     * Handles Daylight Saving Time automatically.
     * 
     * @param string $timezone IANA timezone identifier
     * @param DateTime|null $date Reference date (for DST handling). If null, uses current date.
     * @return int Offset in minutes from UTC
     * 
     * @example
     * // Get current offset for NYC (EDT in October)
     * TimezoneService::getOffsetMinutes('America/New_York');
     * // Returns: -240 (EDT = UTC-4, so -240 minutes)
     * 
     * // Get offset for a specific date
     * $date = new DateTime('2025-01-15');
     * TimezoneService::getOffsetMinutes('America/New_York', $date);
     * // Returns: -300 (EST in January = UTC-5, so -300 minutes)
     */
    public static function getOffsetMinutes($timezone, $date = null)
    {
        if (!$date) {
            $date = new DateTime('now');
        }
        
        try {
            $tz = new DateTimeZone($timezone);
            $date->setTimezone($tz);
            // PHP returns offset in seconds, PHP convention is opposite to standard
            // (negative for east, positive for west), so negate it
            return -($tz->getOffset($date) / 60);
        } catch (\Exception $e) {
            log_message('error', 'TimezoneService::getOffsetMinutes - Error: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get timezone from session or app config
     * 
     * Retrieves the user's timezone from the session (set by middleware)
     * or falls back to the application's default timezone.
     * 
     * @return string IANA timezone identifier (e.g., 'UTC', 'America/New_York')
     */
    private static function getSessionTimezone()
    {
        $session = session();
        
        // Check if client timezone was detected and stored
        if ($session && $session->has('client_timezone')) {
            return $session->get('client_timezone');
        }
        
        // Fallback to app timezone
        return config('App')->appTimezone ?? 'UTC';
    }
    
    /**
     * Validate if timezone is valid IANA identifier
     * 
     * @param string $timezone Timezone to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidTimezone($timezone)
    {
        try {
            new DateTimeZone($timezone);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get list of all valid IANA timezones
     * 
     * Useful for providing timezone selection dropdowns.
     * 
     * @return array Associative array of [timezone => display_name]
     */
    public static function getTimezoneList()
    {
        $timezones = DateTimeZone::listIdentifiers();
        $list = [];
        
        foreach ($timezones as $tz) {
            // Skip deprecated timezones
            if (strpos($tz, 'Etc/') === 0 || strpos($tz, 'UTC') === 0) {
                continue;
            }
            
            // Get offset for display
            try {
                $offset = self::getOffsetMinutes($tz);
                $sign = $offset >= 0 ? '+' : '-';
                $hours = floor(abs($offset) / 60);
                $mins = abs($offset) % 60;
                $displayName = sprintf('%s (UTC%s%02d:%02d)', $tz, $sign, $hours, $mins);
                $list[$tz] = $displayName;
            } catch (\Exception $e) {
                $list[$tz] = $tz;
            }
        }
        
        asort($list);
        return $list;
    }
    
    /**
     * Get current server time in UTC
     * 
     * @return string Current UTC datetime (format: YYYY-MM-DD HH:mm:ss)
     */
    public static function nowUTC()
    {
        return date('Y-m-d H:i:s', time());
    }
    
    /**
     * Get current time in specified timezone
     * 
     * @param string $timezone Target timezone
     * @return string Current datetime in specified timezone
     */
    public static function now($timezone)
    {
        return self::fromUTC(self::nowUTC(), $timezone);
    }
}
