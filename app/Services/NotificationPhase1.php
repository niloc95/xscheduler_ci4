<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Models\BusinessNotificationRuleModel;

class NotificationPhase1
{
    public const BUSINESS_ID_DEFAULT = 1;

    public const CHANNELS = ['email', 'sms', 'whatsapp'];

    public const EVENTS = [
        'appointment_confirmed' => 'Appointment Confirmed',
        'appointment_reminder'  => 'Appointment Reminder',
        'appointment_cancelled' => 'Appointment Cancelled',
    ];

    public function getRules(int $businessId = self::BUSINESS_ID_DEFAULT): array
    {
        $model = new BusinessNotificationRuleModel();
        $rows = $model->where('business_id', $businessId)->findAll();

        $rules = [];
        foreach (array_keys(self::EVENTS) as $eventType) {
            foreach (self::CHANNELS as $channel) {
                $rules[$eventType][$channel] = [
                    'is_enabled' => 0,
                    'reminder_offset_minutes' => null,
                ];
            }
        }

        foreach ($rows as $row) {
            $eventType = $row['event_type'] ?? null;
            $channel = $row['channel'] ?? null;
            if (!$eventType || !$channel) {
                continue;
            }
            if (!isset($rules[$eventType][$channel])) {
                continue;
            }
            $rules[$eventType][$channel] = [
                'is_enabled' => (int) ($row['is_enabled'] ?? 0),
                'reminder_offset_minutes' => $row['reminder_offset_minutes'] ?? null,
            ];
        }

        return $rules;
    }

    public function getIntegrationStatus(int $businessId = self::BUSINESS_ID_DEFAULT): array
    {
        $model = new BusinessIntegrationModel();
        $rows = $model->where('business_id', $businessId)->findAll();

        $status = [];
        foreach (self::CHANNELS as $channel) {
            $status[$channel] = [
                'configured' => false,
                'is_active' => false,
                'provider_name' => null,
                'health_status' => 'unknown',
            ];
        }

        foreach ($rows as $row) {
            $channel = $row['channel'] ?? null;
            if (!$channel || !isset($status[$channel])) {
                continue;
            }
            $status[$channel] = [
                'configured' => !empty($row['encrypted_config']) || !empty($row['provider_name']),
                'is_active' => (bool) ($row['is_active'] ?? false),
                'provider_name' => $row['provider_name'] ?? null,
                'health_status' => $row['health_status'] ?? 'unknown',
            ];
        }

        return $status;
    }

    public function buildPreview(string $eventType, string $channel): string
    {
        // Phase 1: placeholders only.
        // Later phases should pull from message_templates with locale fallback.
        $event = self::EVENTS[$eventType] ?? $eventType;

        if ($channel === 'email') {
            return "Subject: {$event} | Body: Hi {customer_name}, your appointment with {provider_name} is {date} at {time}.";
        }

        if ($channel === 'sms') {
            return "WebSchedulr: {$event}. {date} {time}. Reply STOP to opt out.";
        }

        return "(Coming soon) WhatsApp templates required for {$event}.";
    }
}
