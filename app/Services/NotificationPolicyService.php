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
            ];
        }

        return $rules;
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