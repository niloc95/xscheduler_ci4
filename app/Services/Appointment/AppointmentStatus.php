<?php

namespace App\Services\Appointment;

final class AppointmentStatus
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const NO_SHOW = 'no-show';

    public const ALL = [
        self::PENDING,
        self::CONFIRMED,
        self::COMPLETED,
        self::CANCELLED,
        self::NO_SHOW,
    ];

    public const UPCOMING = [
        self::PENDING,
        self::CONFIRMED,
    ];

    public const PAST = [
        self::COMPLETED,
        self::CANCELLED,
        self::NO_SHOW,
    ];

    public const VALIDATION_RULE = 'required|in_list[pending,confirmed,completed,cancelled,no-show]';

    private const LABELS = [
        self::PENDING => 'Pending',
        self::CONFIRMED => 'Confirmed',
        self::COMPLETED => 'Completed',
        self::CANCELLED => 'Cancelled',
        self::NO_SHOW => 'No Show',
    ];

    private const COLORS = [
        self::CONFIRMED => '#34a853',
        self::PENDING => '#fbbc04',
        self::COMPLETED => '#1a73e8',
        self::CANCELLED => '#ea4335',
        self::NO_SHOW => '#9aa0a6',
    ];

    private const NORMALIZED_ALIASES = [
        'no_show' => self::NO_SHOW,
        'booked' => self::PENDING,
        'rescheduled' => self::PENDING,
    ];

    private const NOTIFICATION_EVENTS = [
        self::PENDING => 'appointment_pending',
        self::CONFIRMED => 'appointment_confirmed',
        self::COMPLETED => 'appointment_confirmed',
        self::CANCELLED => 'appointment_cancelled',
        self::NO_SHOW => 'appointment_no_show',
        'booked' => 'appointment_pending',
        'rescheduled' => 'appointment_rescheduled',
    ];

    public static function normalize(?string $status): ?string
    {
        $normalized = strtolower(trim((string) $status));
        if ($normalized === '') {
            return null;
        }

        $normalized = self::NORMALIZED_ALIASES[$normalized] ?? $normalized;

        return in_array($normalized, self::ALL, true) ? $normalized : null;
    }

    public static function isValid(?string $status): bool
    {
        return self::normalize($status) !== null;
    }

    public static function label(?string $status): string
    {
        $normalized = self::normalize($status) ?? strtolower(trim((string) $status));
        if ($normalized === '') {
            return '';
        }

        return self::LABELS[$normalized] ?? ucwords(str_replace(['-', '_'], ' ', $normalized));
    }

    public static function color(?string $status, string $fallback = '#5f6368'): string
    {
        $normalized = self::normalize($status) ?? strtolower(trim((string) $status));

        return self::COLORS[$normalized] ?? $fallback;
    }

    public static function options(): array
    {
        return array_map(static fn(string $status): array => [
            'value' => $status,
            'label' => self::label($status),
        ], self::ALL);
    }

    public static function defaultCounts(int $initial = 0): array
    {
        return array_fill_keys(self::ALL, $initial);
    }

    public static function notificationEvent(?string $status, string $default = ''): string
    {
        $raw = strtolower(trim((string) $status));
        if ($raw === '') {
            return $default;
        }

        if (isset(self::NOTIFICATION_EVENTS[$raw])) {
            return self::NOTIFICATION_EVENTS[$raw];
        }

        $normalized = self::normalize($raw);
        if ($normalized !== null && isset(self::NOTIFICATION_EVENTS[$normalized])) {
            return self::NOTIFICATION_EVENTS[$normalized];
        }

        return $default;
    }
}