<?php

namespace App\Services\Appointment;

use App\Services\LocalizationSettingsService;
use App\Services\TimezoneService;
use DateTimeImmutable;
use DateTimeZone;

final class AppointmentDateTimeNormalizer
{
    private string $appTimezone;

    public function __construct(?string $appTimezone = null)
    {
        $resolvedTimezone = $appTimezone ?? (new LocalizationSettingsService())->getTimezone();
        $this->appTimezone = TimezoneService::isValidTimezone($resolvedTimezone)
            ? $resolvedTimezone
            : TimezoneService::businessTimezone();
    }

    public function resolveInputTimezone(?string $timezone): string
    {
        $candidate = trim((string) $timezone);

        return $candidate !== '' && TimezoneService::isValidTimezone($candidate)
            ? $candidate
            : TimezoneService::businessTimezone();
    }

    public function normalizeDateAndTime(string $date, string $time, ?string $inputTimezone): array
    {
        try {
            $local = new DateTimeImmutable(
                sprintf('%s %s', $date, $this->normalizeTimeString($time)),
                new DateTimeZone($this->resolveInputTimezone($inputTimezone))
            );

            return $this->buildPayload($local);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Invalid appointment date/time input',
            ];
        }
    }

    public function normalizeDateTimeString(string $dateTime, ?string $fallbackTimezone): array
    {
        try {
            $local = new DateTimeImmutable(
                trim($dateTime),
                new DateTimeZone($this->resolveInputTimezone($fallbackTimezone))
            );

            return $this->buildPayload($local);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Invalid datetime input',
            ];
        }
    }

    private function normalizeTimeString(string $time): string
    {
        $trimmed = trim($time);

        return preg_match('/^\d{2}:\d{2}$/', $trimmed) ? $trimmed . ':00' : $trimmed;
    }

    private function buildPayload(DateTimeImmutable $local): array
    {
        $utc = $local->setTimezone(new DateTimeZone('UTC'));
        $appLocal = $utc->setTimezone(new DateTimeZone($this->appTimezone));

        return [
            'success' => true,
            'utc' => $utc->format('Y-m-d H:i:s'),
            'app_date' => $appLocal->format('Y-m-d'),
            'app_time' => $appLocal->format('H:i'),
        ];
    }
}