<?php

/**
 * =============================================================================
 * NOTIFICATION DELIVERY LOG MODEL
 * =============================================================================
 * 
 * @file        app/Models/NotificationDeliveryLogModel.php
 * @description Data model for notification delivery records. Logs every
 *              notification send attempt for reporting and debugging.
 * 
 * DATABASE TABLE: xs_notification_delivery_logs
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - business_id     : Business identifier (multi-tenant)
 * - queue_id        : FK to xs_notification_queue
 * - correlation_id  : Links related log entries
 * - channel         : email, sms, whatsapp
 * - event_type      : Type of notification event
 * - appointment_id  : Related appointment (FK)
 * - recipient       : Email/phone number
 * - provider        : Service provider used (smtp, twilio, etc.)
 * - status          : success, failed, cancelled, skipped
 * - attempt         : Which attempt number this was
 * - error_message   : Error details if failed
 * - created_at      : When delivery was attempted
 * 
 * STATUS VALUES:
 * -----------------------------------------------------------------------------
 * - success   : Notification delivered successfully
 * - failed    : Delivery failed (see error_message)
 * - cancelled : User opted out or notification cancelled
 * - skipped   : Skipped (duplicate, test mode, etc.)
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * - Track notification delivery success/failure
 * - Admin reporting on notification metrics
 * - Debug delivery issues
 * - Audit trail for compliance
 * 
 * @see         app/Controllers/Notifications.php for admin view
 * @see         app/Commands/ExportNotificationDeliveryLogs.php
 * @package     App\Models
 * @extends     BaseModel
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

class NotificationDeliveryLogModel extends BaseModel
{
    protected $table = 'xs_notification_delivery_logs';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'queue_id',
        'correlation_id',
        'channel',
        'event_type',
        'appointment_id',
        'recipient',
        'provider',
        'status',
        'attempt',
        'error_message',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'channel' => 'required|in_list[email,sms,whatsapp]',
        'event_type' => 'required|string|max_length[64]',
        'status' => 'required|in_list[success,failed,cancelled,skipped]',
        'attempt' => 'permit_empty|is_natural_no_zero',
    ];
}
