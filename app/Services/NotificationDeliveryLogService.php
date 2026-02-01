<?php

/**
 * =============================================================================
 * NOTIFICATION DELIVERY LOG SERVICE
 * =============================================================================
 * 
 * @file        app/Services/NotificationDeliveryLogService.php
 * @description Logs notification delivery attempts for auditing and debugging.
 *              Records success/failure status for all notification channels.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides comprehensive logging of all notification delivery attempts:
 * - Track delivery success/failure rates
 * - Debug delivery issues
 * - Compliance auditing
 * - Analytics and reporting
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * logAttempt($businessId, $queueId, $correlationId, $channel, $eventType, 
 *            $appointmentId, $recipient, $status, $attempt, $errorMessage)
 *   Log a delivery attempt with full context
 *   Never throws - fails silently to avoid blocking notification dispatch
 * 
 * getProviderName($businessId, $channel)
 *   Get the provider name from business integration (e.g., 'mailgun', 'twilio')
 * 
 * LOG FIELDS:
 * -----------------------------------------------------------------------------
 * - business_id     : Business context
 * - queue_id        : Related queue entry (if applicable)
 * - correlation_id  : UUID for tracking related logs
 * - channel         : email, sms, whatsapp
 * - event_type      : appointment_confirmed, reminder, etc.
 * - appointment_id  : Related appointment
 * - recipient       : Email/phone number
 * - provider        : Integration provider (mailgun, twilio, etc.)
 * - status          : sent, failed, queued, retry
 * - attempt         : Attempt number (1, 2, 3...)
 * - error_message   : Error details if failed
 * 
 * ERROR HANDLING:
 * -----------------------------------------------------------------------------
 * All methods catch exceptions and log to system logs.
 * Never throws exceptions to avoid blocking notification dispatch.
 * 
 * @see         app/Models/NotificationDeliveryLogModel.php
 * @see         app/Commands/ExportNotificationDeliveryLogs.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Models\NotificationDeliveryLogModel;

class NotificationDeliveryLogService
{
    public function logAttempt(
        int $businessId,
        ?int $queueId,
        ?string $correlationId,
        string $channel,
        string $eventType,
        ?int $appointmentId,
        ?string $recipient,
        string $status,
        int $attempt,
        ?string $errorMessage = null
    ): void {
        try {
            $model = new NotificationDeliveryLogModel();

            $model->insert([
                'business_id' => $businessId,
                'queue_id' => $queueId,
                'correlation_id' => $correlationId,
                'channel' => $channel,
                'event_type' => $eventType,
                'appointment_id' => $appointmentId,
                'recipient' => $recipient,
                'provider' => $this->getProviderName($businessId, $channel),
                'status' => $status,
                'attempt' => max(1, $attempt),
                'error_message' => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            // Never block dispatch due to logging failures.
            log_message('error', 'Delivery log insert failed: {msg}', ['msg' => $e->getMessage()]);
        }
    }

    public function getProviderName(int $businessId, string $channel): ?string
    {
        try {
            $model = new BusinessIntegrationModel();
            $row = $model
                ->where('business_id', $businessId)
                ->where('channel', $channel)
                ->first();

            $provider = (string) ($row['provider_name'] ?? '');
            return trim($provider) !== '' ? $provider : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
