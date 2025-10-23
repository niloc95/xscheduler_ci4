<?php

namespace App\Services;

use App\Models\SettingModel;

/**
 * CalendarConfigService centralizes FullCalendar-specific configuration
 * including time format, timezone, business hours, and display preferences.
 * It bridges application settings with FullCalendar's expected format.
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
     * Get complete FullCalendar configuration as array
     * Ready to be JSON-encoded and passed to JavaScript
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
     */
    public function getSlotMinTime(): string
    {
        $value = $this->getSetting('calendar.slot_min_time');
        
        if ($value) {
            $normalized = $this->localization->normaliseTimeInput($value);
            if ($normalized) {
                return substr($normalized, 0, 5); // HH:MM format
            }
        }

        return '08:00'; // Default 8 AM
    }

    /**
     * Get latest time to display on calendar
     */
    public function getSlotMaxTime(): string
    {
        $value = $this->getSetting('calendar.slot_max_time');
        
        if ($value) {
            $normalized = $this->localization->normaliseTimeInput($value);
            if ($normalized) {
                return substr($normalized, 0, 5); // HH:MM format
            }
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
     * Get business hours configuration for FullCalendar
     * Fetches from business_hours table and formats for FullCalendar
     */
    public function getBusinessHoursForCalendar(): array
    {
        $db = \Config\Database::connect();
        $businessHours = $db->table('business_hours')
            ->where('is_working_day', 1)
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
        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        foreach ($businessHours as $hours) {
            $dayOfWeek = $dayMap[strtolower($hours['day_of_week'])] ?? null;
            
            if ($dayOfWeek !== null) {
                $result[] = [
                    'daysOfWeek' => [$dayOfWeek],
                    'startTime' => substr($hours['start_time'], 0, 5), // HH:MM
                    'endTime' => substr($hours['end_time'], 0, 5),     // HH:MM
                ];
            }
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
     * Get calendar height setting
     */
    public function getCalendarHeight(): string
    {
        return $this->getSetting('calendar.height') ?? 'auto';
    }

    /**
     * Get all calendar-related settings for JavaScript
     * This is the main method to use when initializing the calendar
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
