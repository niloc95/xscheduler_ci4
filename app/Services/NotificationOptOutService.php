<?php

/**
 * =============================================================================
 * NOTIFICATION OPT-OUT SERVICE
 * =============================================================================
 * 
 * @file        app/Services/NotificationOptOutService.php
 * @description Manages notification opt-out preferences for customers.
 *              Allows customers to unsubscribe from specific notification channels.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides opt-out management for compliance with:
 * - GDPR requirements
 * - CAN-SPAM regulations
 * - POPI Act (South Africa)
 * - General customer preferences
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * isOptedOut($businessId, $channel, $recipient)
 *   Check if recipient has opted out of channel
 *   Returns: bool
 * 
 * optOut($businessId, $channel, $recipient, $reason)
 *   Add opt-out record for recipient
 *   Returns: ['ok' => bool, 'inserted' => bool, 'updated' => bool]
 * 
 * optIn($businessId, $channel, $recipient)
 *   Remove opt-out record (re-enable notifications)
 *   Returns: ['ok' => bool]
 * 
 * CHANNELS:
 * -----------------------------------------------------------------------------
 * - email    : Email notifications
 * - sms      : SMS text notifications
 * - whatsapp : WhatsApp notifications
 * 
 * USAGE IN NOTIFICATION FLOW:
 * -----------------------------------------------------------------------------
 * Before sending any notification:
 * 1. Check isOptedOut() for recipient/channel
 * 2. If opted out, skip notification and log as 'skipped'
 * 3. If not opted out, proceed with sending
 * 
 * UNSUBSCRIBE LINKS:
 * -----------------------------------------------------------------------------
 * Email notifications include unsubscribe links that call optOut().
 * SMS/WhatsApp may use STOP keywords that trigger optOut().
 * 
 * @see         app/Models/NotificationOptOutModel.php
 * @see         app/Services/NotificationQueueDispatcher.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\NotificationOptOutModel;

class NotificationOptOutService
{
    public function isOptedOut(int $businessId, string $channel, string $recipient): bool
    {
        $recipient = trim($recipient);
        if ($recipient === '') {
            return false;
        }

        try {
            $model = new NotificationOptOutModel();
            $row = $model
                ->where('business_id', $businessId)
                ->where('channel', $channel)
                ->where('recipient', $recipient)
                ->first();

            return is_array($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function optOut(int $businessId, string $channel, string $recipient, ?string $reason = null): array
    {
        $recipient = trim($recipient);
        $reason = $reason !== null ? trim($reason) : null;
        if ($recipient === '') {
            return ['ok' => false, 'error' => 'Missing recipient'];
        }

        try {
            $model = new NotificationOptOutModel();

            // Upsert-ish: try insert, fall back to update.
            $existing = $model
                ->where('business_id', $businessId)
                ->where('channel', $channel)
                ->where('recipient', $recipient)
                ->first();

            if (!empty($existing['id'])) {
                $model->update((int) $existing['id'], ['reason' => $reason]);
                return ['ok' => true, 'updated' => true];
            }

            $model->insert([
                'business_id' => $businessId,
                'channel' => $channel,
                'recipient' => $recipient,
                'reason' => $reason,
            ]);

            return ['ok' => true, 'inserted' => true];
        } catch (\Throwable $e) {
            log_message('error', 'Opt-out save failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Failed to save opt-out'];
        }
    }
}
