<?php

namespace App\Services;

use App\Models\SettingModel;

/**
 * LocalizationSettingsService centralizes access to localization-related
 * configuration such as time format and timezone. It also provides helpers
 * for parsing and formatting time strings used throughout scheduling flows.
 */
class LocalizationSettingsService
{
    private SettingModel $settings;
    private ?array $cache = null;

    public function __construct(?SettingModel $settings = null)
    {
        $this->settings = $settings ?? new SettingModel();
    }

    /**
     * Get the configured time format ("24h" or "12h").
     */
    public function getTimeFormat(): string
    {
        $value = $this->get('localization.time_format');
        return $value === '12h' ? '12h' : '24h';
    }

    /**
     * Whether the instance should render times in 12-hour format.
     */
    public function isTwelveHour(): bool
    {
        return $this->getTimeFormat() === '12h';
    }

    /**
     * Return the configured timezone or a safe fallback.
     */
    public function getTimezone(): string
    {
        $value = $this->get('localization.timezone');
        if (!$value || strtolower($value) === 'automatic') {
            return 'UTC';
        }

        return $value;
    }

    /**
     * Convenience bundle for passing localization context to views/controllers.
     */
    public function getContext(): array
    {
        return [
            'time_format'         => $this->getTimeFormat(),
            'timezone'            => $this->getTimezone(),
            'format_example'      => $this->getFormatExample(),
            'format_description'  => $this->describeExpectedFormat(),
        ];
    }

    /**
     * Normalise an incoming time string to database format (HH:MM:SS).
     */
    public function normaliseTimeInput(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Accept 24-hour input first to preserve API compatibility.
        if (preg_match('/^(?P<hour>[01]?\d|2[0-3]):(?P<minute>[0-5]\d)(?::(?P<second>[0-5]\d))?$/', $value, $matches)) {
            $hour   = (int) ($matches['hour'] ?? 0);
            $minute = (int) ($matches['minute'] ?? 0);
            $second = isset($matches['second']) ? (int) $matches['second'] : 0;

            return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        }

        // Fallback to 12-hour parsing.
        if (preg_match('/^(?P<hour>0?[1-9]|1[0-2]):(?P<minute>[0-5]\d)\s*(?P<period>AM|PM)$/i', $value, $matches)) {
            $hour   = (int) ($matches['hour'] ?? 0);
            $minute = (int) ($matches['minute'] ?? 0);
            $period = strtoupper($matches['period'] ?? 'AM');

            if ($period === 'PM' && $hour !== 12) {
                $hour += 12;
            } elseif ($period === 'AM' && $hour === 12) {
                $hour = 0;
            }

            return sprintf('%02d:%02d:%02d', $hour, $minute, 0);
        }

        return null;
    }

    /**
     * Format a stored time for display according to current localization.
     */
    public function formatTimeForDisplay(?string $value): string
    {
        $normalised = $this->normaliseTimeInput($value);
        if ($normalised === null) {
            return '';
        }

        [$hour, $minute] = explode(':', substr($normalised, 0, 5));
        $hourInt = (int) $hour;

        if ($this->isTwelveHour()) {
            $period = $hourInt >= 12 ? 'PM' : 'AM';
            $hour12 = $hourInt % 12;
            if ($hour12 === 0) {
                $hour12 = 12;
            }

            return sprintf('%02d:%s %s', $hour12, $minute, $period);
        }

        return sprintf('%s:%s', $hour, $minute);
    }

    /**
     * Convert a time string into minutes since midnight.
     */
    public function toMinutes(?string $value): ?int
    {
        $normalised = $this->normaliseTimeInput($value);
        if ($normalised === null) {
            return null;
        }

        [$hour, $minute] = explode(':', substr($normalised, 0, 5));

        return ((int) $hour * 60) + (int) $minute;
    }

    /**
     * Provide an example string that aligns with the current format.
     */
    public function getFormatExample(): string
    {
        return $this->isTwelveHour() ? '09:00 AM' : '09:00';
    }

    /**
     * Human-friendly description of expected input format for validation errors.
     */
    public function describeExpectedFormat(): string
    {
        if ($this->isTwelveHour()) {
            return 'Use HH:MM AM/PM (e.g. ' . $this->getFormatExample() . ').';
        }

        return 'Use 24-hour HH:MM (e.g. ' . $this->getFormatExample() . ').';
    }

    /**
     * Internal cached settings accessor.
     */
    private function get(string $key): ?string
    {
        if ($this->cache === null) {
            $this->cache = $this->settings->getByKeys([
                'localization.time_format',
                'localization.timezone',
                'localization.first_day',
            ]);
        }

        return $this->cache[$key] ?? null;
    }
}
