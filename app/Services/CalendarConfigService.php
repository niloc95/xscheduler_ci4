<?php

namespace App\Services;

use App\Models\SettingModel;

/**
 * CalendarConfigService centralizes calendar/scheduler configuration
 * including time format, timezone, business hours, and display preferences.
 * Provides configuration for custom scheduler component.
 * 
 * Note: Previously used for FullCalendar, now adapted for custom scheduler.
 */
class CalendarConfigService
{
    private LocalizationSettingsService $localization;
    private SettingModel $settings;
    private ?array $cache = null;

    public function __construct(
        ?LocalizationSettingsService $localization = null,
        ?SettingModel $settings = null
    ) {
        $this->localization = $localization ?? new LocalizationSettingsService();
        $this->settings = $settings ?? new SettingModel();
    }

    /**
     * Get complete scheduler configuration as array
     * Ready to be JSON-encoded and passed to JavaScript
     * 
     * Note: Adapted from FullCalendar config, will be customized for new scheduler
     */
    public function getCalendarConfig(): array
    {
        return [
            'timeFormat' => $this->getTimeFormat(),
            'slotLabelFormat' => $this->getSlotLabelFormat(),
            'eventTimeFormat' => $this->getEventTimeFormat(),
            'firstDay' => $this->getFirstDayOfWeek(),
            'slotDuration' => $this->getSlotDuration(),
            'slotMinTime' => $this->getSlotMinTime(),
            'slotMaxTime' => $this->getSlotMaxTime(),
            'timezone' => $this->getTimezone(),
            'locale' => $this->getLocale(),
            'businessHours' => $this->getBusinessHoursForCalendar(),
        ];
    }

    /**
     * Get FullCalendar-compatible time format string
     * Returns format for event times and slot labels
     */
    public function getTimeFormat(): string
    {
        return $this->localization->isTwelveHour() 
            ? 'h:mm a'  // 12-hour format (e.g., "2:30 pm")
            : 'HH:mm';  // 24-hour format (e.g., "14:30")
    }

    /**
     * Get slot label format for time grid views
     */
    public function getSlotLabelFormat(): array
    {
        if ($this->localization->isTwelveHour()) {
            return [
                'hour' => 'numeric',
                'minute' => '2-digit',
                'meridiem' => 'short',
                'hour12' => true
            ];
        }

        return [
            'hour' => '2-digit',
            'minute' => '2-digit',
            'hour12' => false
        ];
    }

    /**
     * Get event time format for calendar events
     */
    public function getEventTimeFormat(): array
    {
        return $this->getSlotLabelFormat();
    }

    /**
     * Get first day of week (0 = Sunday, 1 = Monday)
     */
    public function getFirstDayOfWeek(): int
    {
        $value = $this->getSetting('localization.first_day');
        
        if ($value === null) {
            return 0; // Default to Sunday
        }

        // Handle both numeric and string day values
        if (is_numeric($value)) {
            return max(0, min(6, (int) $value));
        }

        // Map day names to numbers
        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        return $dayMap[strtolower($value)] ?? 0;
    }

    /**
     * Get slot duration in minutes (default 30)
     */
    public function getSlotDuration(): string
    {
        $minutes = $this->getSetting('calendar.slot_duration') ?? 30;
        return sprintf('00:%02d:00', (int) $minutes);
    }

    /**
     * Get earliest time to display on calendar
     * Reads from business.work_start setting
     */
    public function getSlotMinTime(): string
    {
        // Try business.work_start first (from Business Hours settings)
        $value = $this->getSetting('business.work_start');
        
        if (!$value) {
            $value = $this->getSetting('calendar.slot_min_time');
        }
        
        if ($value && strlen($value) >= 5) {
            // Already in HH:MM or HH:MM:SS format, just return first 5 chars
            return substr($value, 0, 5);
        }

        return '08:00'; // Default 8 AM
    }

    /**
     * Get latest time to display on calendar
     * Reads from business.work_end setting
     */
    public function getSlotMaxTime(): string
    {
        // Try business.work_end first (from Business Hours settings)
        $value = $this->getSetting('business.work_end');
        
        if (!$value) {
            $value = $this->getSetting('calendar.slot_max_time');
        }
        
        if ($value && strlen($value) >= 5) {
            // Already in HH:MM or HH:MM:SS format, just return first 5 chars
            return substr($value, 0, 5);
        }

        return '18:00'; // Default 6 PM
    }

    /**
     * Get timezone for calendar
     */
    public function getTimezone(): string
    {
        return $this->localization->getTimezone();
    }

    /**
     * Get locale for calendar (default 'en')
     */
    public function getLocale(): string
    {
        return $this->getSetting('localization.locale') ?? 'en';
    }

    /**
     * Get business hours configuration for scheduler
     * Fetches from business_hours table and formats for scheduler component
     * 
     * For now, aggregates all provider business hours.
     * In the future, this can be filtered by provider_id for provider-specific views.
     */
    public function getBusinessHoursForCalendar(): array
    {
        $db = \Config\Database::connect();
        
        // Get all business hours grouped by weekday
        // weekday: 0=Sunday, 1=Monday, ..., 6=Saturday (matches FullCalendar)
        $businessHours = $db->table('business_hours')
            ->select('weekday, MIN(start_time) as start_time, MAX(end_time) as end_time')
            ->groupBy('weekday')
            ->orderBy('weekday', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($businessHours)) {
            // Default business hours: Monday-Friday 9 AM - 5 PM
            return [
                [
                    'daysOfWeek' => [1, 2, 3, 4, 5], // Monday - Friday
                    'startTime' => '09:00',
                    'endTime' => '17:00'
                ]
            ];
        }

        // Convert database format to FullCalendar format
        $result = [];
        foreach ($businessHours as $hours) {
            $result[] = [
                'daysOfWeek' => [(int) $hours['weekday']],
                'startTime' => substr($hours['start_time'], 0, 5), // HH:MM
                'endTime' => substr($hours['end_time'], 0, 5),     // HH:MM
            ];
        }

        return $result;
    }

    /**
     * Get calendar view preferences
     */
    public function getDefaultView(): string
    {
        return $this->getSetting('calendar.default_view') ?? 'timeGridWeek';
    }

    /**
     * Check if weekends should be displayed
     */
    public function shouldShowWeekends(): bool
    {
        $value = $this->getSetting('calendar.show_weekends');
        return $value === null ? true : (bool) $value;
    }

    /**
     * Get blocked periods from settings
     * Returns array of date ranges where appointments cannot be scheduled
     */
    public function getBlockedPeriods(): array
    {
        $value = $this->getSetting('business.blocked_periods');
        
        if (!$value) {
            return [];
        }

        // Handle both JSON string and already decoded array
        $periods = is_string($value) ? json_decode($value, true) : $value;
        
        if (!is_array($periods)) {
            return [];
        }

        return $periods;
    }

    /**
     * Get calendar height setting
     */
    public function getCalendarHeight(): string
    {
        return $this->getSetting('calendar.height') ?? 'auto';
    }

    /**
     * Get all calendar/scheduler-related settings for JavaScript
     * This is the main method to use when initializing the scheduler
     * 
     * Note: Configuration keys may need adjustment for custom scheduler component
     */
    public function getJavaScriptConfig(): array
    {
        return [
            'initialView' => $this->getDefaultView(),
            'firstDay' => $this->getFirstDayOfWeek(),
            'slotDuration' => $this->getSlotDuration(),
            'slotMinTime' => $this->getSlotMinTime(),
            'slotMaxTime' => $this->getSlotMaxTime(),
            'slotLabelFormat' => $this->getSlotLabelFormat(),
            'eventTimeFormat' => $this->getEventTimeFormat(),
            'weekends' => $this->shouldShowWeekends(),
            'height' => $this->getCalendarHeight(),
            'businessHours' => $this->getBusinessHoursForCalendar(),
            'blockedPeriods' => $this->getBlockedPeriods(),
            'timeZone' => $this->getTimezone(),
            'locale' => $this->getLocale(),
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay'
            ],
            'navLinks' => true,
            'editable' => true,
            'selectable' => true,
            'selectMirror' => true,
            'dayMaxEvents' => true,
        ];
    }

    /**
     * Get localization context for views (time format examples, etc.)
     */
    public function getLocalizationContext(): array
    {
        return $this->localization->getContext();
    }

    /**
     * Internal cached settings accessor
     */
    private function getSetting(string $key): ?string
    {
        if ($this->cache === null) {
            $this->cache = $this->settings->getByKeys([
                'localization.time_format',
                'localization.timezone',
                'localization.first_day',
                'localization.locale',
                'business.work_start',
                'business.work_end',
                'business.blocked_periods',
                'calendar.slot_duration',
                'calendar.slot_min_time',
                'calendar.slot_max_time',
                'calendar.default_view',
                'calendar.show_weekends',
                'calendar.height',
            ]);
        }

        return $this->cache[$key] ?? null;
    }
}
