<?php

/**
 * =============================================================================
 * TIMEZONE SERVICE
 * =============================================================================
 * 
 * @file        app/Services/TimezoneService.php
 * @description Handles timezone conversions for consistent appointment storage
 *              and display across different user timezones.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides consistent timezone handling for:
 * - Converting user local times to UTC for storage
 * - Converting UTC times to user local times for display
 * - Validating timezone identifiers
 * - Detecting user timezone from browser/session
 * 
 * STORAGE STRATEGY:
 * -----------------------------------------------------------------------------
 * All datetimes are stored in UTC in the database (start_at, end_at columns).
 * User timezone is stored in session and used for display conversion.
 * This ensures consistent comparison and scheduling logic.
 *
 * CANONICAL GATEWAY METHODS (prefer these):
 * -----------------------------------------------------------------------------
 * toStorage($localTime, $tz)   – local → UTC before DB write
 * toDisplay($utcTime, $tz)     – UTC → local for rendering
 * toDisplayIso($utcTime, $tz)  – UTC → ISO 8601 with offset for APIs
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * toUTC($localTime, $timezone)
 *   Convert local time to UTC for database storage
 *   Example: '2025-10-24 10:30:00' NYC -> '2025-10-24 14:30:00' UTC
 * 
 * toLocal($utcTime, $timezone)
 *   Convert UTC time to local timezone for display
 *   Example: '2025-10-24 14:30:00' UTC -> '2025-10-24 10:30:00' NYC
 * 
 * getSessionTimezone()
 *   Get user's timezone from session or app default
 * 
 * setSessionTimezone($timezone)
 *   Store user's timezone in session
 * 
 * isValidTimezone($timezone)
 *   Check if timezone identifier is valid IANA timezone
 * 
 * getAllTimezones()
 *   Get list of all valid timezone identifiers
 * 
 * TIMEZONE FORMATS:
 * -----------------------------------------------------------------------------
 * Uses IANA timezone identifiers:
 * - 'America/New_York'
 * - 'Europe/London'
 * - 'Africa/Johannesburg'
 * - 'Asia/Tokyo'
 * 
 * ERROR HANDLING:
 * -----------------------------------------------------------------------------
 * Invalid timezones fall back to configured default (usually Africa/Johannesburg).
 * Conversion errors are logged and original value returned.
 * 
 * @see         app/Services/LocalizationSettingsService.php
 * @see         app/Filters/TimezoneDetection.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

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
     * Get the business-local timezone from settings.
     *
     * Falls back to 'Africa/Johannesburg' when the setting is not yet seeded.
     * This is the IANA timezone that "today", "this week", "this month" should
     * be evaluated in before converting boundaries to UTC for DB queries.
     *
     * @return string IANA timezone identifier
     */
    public static function businessTimezone(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            $settingModel = new \App\Models\SettingModel();
            $value = $settingModel->getValue('localization.timezone');
            if ($value && \DateTimeZone::listIdentifiers(\DateTimeZone::ALL, 0) !== false) {
                // Validate it's a real IANA identifier
                new \DateTimeZone($value);
                $cached = $value;
                return $cached;
            }
        } catch (\Throwable $e) {
            // Fall through to default
        }

        $cached = 'Africa/Johannesburg';
        return $cached;
    }

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
            $tz = (string) $session->get('client_timezone');
            log_message('debug', "[TimezoneService] Using session timezone: {$tz}");
            return $tz;
        }
        
        // Fallback to app timezone
        $fallback = config('App')->appTimezone ?? 'UTC';
        log_message('debug', "[TimezoneService] Using fallback timezone: {$fallback}");
        return $fallback;
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
        return gmdate('Y-m-d H:i:s');
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

    // ------------------------------------------------------------------
    //  Storage gateway — canonical entry points for all write / read
    // ------------------------------------------------------------------

    /**
     * Convert a user-supplied local time to UTC for database storage.
     *
     * This is the SINGLE entry point every booking path should call before
     * writing start_at / end_at to xs_appointments.
     *
     * @param string      $localDatetime  'Y-m-d H:i:s' in the user's local TZ
     * @param string|null $timezone       IANA identifier; defaults to session TZ
     * @return string     'Y-m-d H:i:s' in UTC
     */
    public static function toStorage(string $localDatetime, ?string $timezone = null): string
    {
        return self::toUTC($localDatetime, $timezone);
    }

    /**
     * Convert a UTC database value to the user's display timezone.
     *
     * This is the SINGLE entry point every read/render path should call
     * when presenting start_at / end_at to the user.
     *
     * @param string      $utcDatetime  'Y-m-d H:i:s' in UTC (from DB)
     * @param string|null $timezone     IANA identifier; defaults to session TZ
     * @return string     'Y-m-d H:i:s' in the target TZ
     */
    public static function toDisplay(string $utcDatetime, ?string $timezone = null): string
    {
        return self::fromUTC($utcDatetime, $timezone);
    }

    /**
     * Format a UTC datetime as ISO 8601 with the display timezone offset.
     *
     * Useful for API responses that need to carry the offset, e.g.
     * "2026-02-26T09:00:00+02:00" for Africa/Johannesburg display.
     *
     * @param string      $utcDatetime  'Y-m-d H:i:s' in UTC
     * @param string|null $timezone     Target TZ; defaults to session TZ
     * @return string     ISO 8601 with offset
     */
    public static function toDisplayIso(string $utcDatetime, ?string $timezone = null): string
    {
        $timezone = $timezone ?: self::getSessionTimezone();

        try {
            $dt = new DateTime($utcDatetime, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone($timezone));
            return $dt->format('c'); // ISO 8601
        } catch (\Exception $e) {
            log_message('error', 'TimezoneService::toDisplayIso - Error: ' . $e->getMessage());
            return $utcDatetime;
        }
    }
}
