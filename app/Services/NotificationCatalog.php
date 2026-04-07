<?php

namespace App\Services;

final class NotificationCatalog
{
    public const BUSINESS_ID_DEFAULT = 1;

    public const CHANNELS = ['email', 'sms', 'whatsapp'];

    public const EVENTS = [
        'appointment_pending' => 'Appointment Pending',
        'appointment_confirmed' => 'Appointment Confirmed',
        'appointment_reminder' => 'Appointment Reminder',
        'appointment_cancelled' => 'Appointment Cancelled',
        'appointment_rescheduled' => 'Appointment Rescheduled',
    ];

    public static function normalizeChannels(array $channels): array
    {
        return array_values(array_intersect($channels, self::CHANNELS));
    }
}