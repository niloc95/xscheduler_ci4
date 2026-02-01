<?php

/**
 * =============================================================================
 * NOTIFICATION QUEUE MODEL
 * =============================================================================
 * 
 * @file        app/Models/NotificationQueueModel.php
 * @description Data model for queued notifications. Notifications are queued
 *              for async processing via CLI commands.
 * 
 * DATABASE TABLE: xs_notification_queue
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - business_id     : Business identifier (multi-tenant support)
 * - channel         : Delivery channel (email, sms, whatsapp)
 * - event_type      : Event that triggered notification
 * - appointment_id  : Related appointment (FK to xs_appointments)
 * - status          : pending, processing, sent, failed
 * - attempts        : Number of send attempts
 * - max_attempts    : Maximum retry attempts
 * - run_after       : Don't process before this time
 * - locked_at       : When worker locked this item
 * - lock_token      : Unique lock identifier
 * - last_error      : Last failure error message
 * - sent_at         : When successfully sent
 * - idempotency_key : Prevents duplicate sends
 * - correlation_id  : Links related notifications
 * 
 * QUEUE FLOW:
 * -----------------------------------------------------------------------------
 * 1. Event triggers notification -> queued as 'pending'
 * 2. CLI worker picks up item -> status = 'processing'
 * 3. Send attempt made -> success = 'sent', fail = retry or 'failed'
 * 4. Delivery log created in xs_notification_delivery_logs
 * 
 * EVENT TYPES:
 * -----------------------------------------------------------------------------
 * - appointment_reminder   : Upcoming appointment reminder
 * - appointment_confirmed  : Booking confirmation
 * - appointment_cancelled  : Cancellation notice
 * - appointment_rescheduled: Reschedule notification
 * 
 * CLI COMMANDS:
 * -----------------------------------------------------------------------------
 * - php spark notifications:dispatch - Process queue
 * - php spark notifications:retry   - Retry failed items
 * 
 * @see         app/Commands/DispatchNotificationQueue.php
 * @see         app/Services/NotificationPhase1.php
 * @package     App\Models
 * @extends     BaseModel
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

class NotificationQueueModel extends BaseModel
{
    protected $table = 'xs_notification_queue';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'channel',
        'event_type',
        'appointment_id',
        'status',
        'attempts',
        'max_attempts',
        'run_after',
        'locked_at',
        'lock_token',
        'last_error',
        'sent_at',
        'idempotency_key',
        'correlation_id',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'channel' => 'required|in_list[email,sms,whatsapp]',
        'event_type' => 'required|string|max_length[64]',
        'idempotency_key' => 'required|string|max_length[128]',
        'correlation_id' => 'permit_empty|string|max_length[64]',
    ];
}
