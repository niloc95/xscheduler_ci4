<?php

/**
 * =============================================================================
 * NOTIFICATION POLICY SERVICE
 * =============================================================================
 * 
 * @file        app/Services/NotificationPolicyService.php
 * @description Stable notification-policy service for runtime rule matrices,
 *              integration status, and admin preview generation.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Central coordinator for notification policy configuration:
 * - Define supported notification channels
 * - Define notification event types
 * - Manage notification rules (which events trigger which channels)
 * - Provide rule configuration to the admin UI
 * - Project notification integration status for settings screens
 * - Generate channel preview content for policy review
 * 
 * ARCHITECTURE:
 * -----------------------------------------------------------------------------
 * NotificationPolicyService (rules/policy) → NotificationQueueService (queue)
 * → NotificationQueueDispatcher (process) → Channel Services (send)
 * 
 * @see         app/Commands/DispatchNotificationQueue.php
 * @see         app/Controllers/Settings.php for admin UI
 * @package     App\Services
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Models\BusinessNotificationRuleModel;

class NotificationPolicyService
{
    public const BUSINESS_ID_DEFAULT = NotificationCatalog::BUSINESS_ID_DEFAULT;

    public const CHANNELS = NotificationCatalog::CHANNELS;

    public const EVENTS = NotificationCatalog::EVENTS;

    public function getRules(int $businessId = self::BUSINESS_ID_DEFAULT): array
    {
        $rules = [];
        foreach (array_keys(NotificationCatalog::EVENTS) as $eventType) {
            foreach (NotificationCatalog::CHANNELS as $channel) {
                $rules[$eventType][$channel] = [
                    'is_enabled' => 0,
                    'reminder_offset_minutes' => null,
                    'reminder_offsets_minutes' => [],
                ];
            }
        }

        try {
            $model = new BusinessNotificationRuleModel();
            $rows = $model->where('business_id', $businessId)->findAll();
        } catch (\Throwable $e) {
            log_message('debug', 'NotificationPolicyService::getRules — table unavailable: ' . $e->getMessage());
            return $rules;
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
                'reminder_offsets_minutes' => $this->extractReminderOffsets($row),
            ];
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, int>
     */
    private function extractReminderOffsets(array $row): array
    {
        $offsets = [];

        $jsonRaw = $row['reminder_offsets_json'] ?? null;
        if (is_string($jsonRaw) && trim($jsonRaw) !== '') {
            $decoded = json_decode($jsonRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $value) {
                    if (!is_numeric($value)) {
                        continue;
                    }
                    $offsets[] = max(0, min(43200, (int) $value));
                }
            }
        }

        if ($offsets === []) {
            $legacy = $row['reminder_offset_minutes'] ?? null;
            if ($legacy !== null && $legacy !== '' && is_numeric($legacy)) {
                $offsets[] = max(0, min(43200, (int) $legacy));
            }
        }

        if ($offsets === []) {
            return [];
        }

        $seen = [];
        $normalized = [];
        foreach ($offsets as $offset) {
            if (isset($seen[$offset])) {
                continue;
            }
            $seen[$offset] = true;
            $normalized[] = $offset;
        }

        return $normalized;
    }

    public function getIntegrationStatus(int $businessId = self::BUSINESS_ID_DEFAULT): array
    {
        $status = [];
        foreach (NotificationCatalog::CHANNELS as $channel) {
            $status[$channel] = [
                'configured' => false,
                'is_active' => false,
                'provider_name' => null,
                'health_status' => 'unknown',
                'last_tested_at' => null,
            ];
        }

        try {
            $model = new BusinessIntegrationModel();
            $rows = $model->where('business_id', $businessId)->findAll();
        } catch (\Throwable $e) {
            log_message('debug', 'NotificationPolicyService::getIntegrationStatus — table unavailable: ' . $e->getMessage());
            return $status;
        }

        foreach ($rows as $row) {
            $channel = $row['channel'] ?? null;
            if (!$channel || !isset($status[$channel])) {
                continue;
            }
            $status[$channel] = [
                'configured' => !empty($row['encrypted_config']),
                'is_active' => (bool) ($row['is_active'] ?? false),
                'provider_name' => $row['provider_name'] ?? null,
                'health_status' => $row['health_status'] ?? 'unknown',
                'last_tested_at' => $row['last_tested_at'] ?? null,
            ];
        }

        return $status;
    }

    public function buildPreview(string $eventType, string $channel): string
    {
        $event = NotificationCatalog::EVENTS[$eventType] ?? $eventType;

        if ($channel === 'email') {
            return "Subject: {$event} | Body: Hi {customer_name}, your appointment with {provider_name} is {date} at {time}.";
        }

        if ($channel === 'sms') {
            return "WebScheduler: {$event}. {date} {time}. Reply STOP to opt out.";
        }

        return "(Coming soon) WhatsApp templates required for {$event}.";
    }
}