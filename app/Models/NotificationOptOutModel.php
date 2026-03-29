<?php

/**
 * =============================================================================
 * NOTIFICATION OPT-OUT MODEL
 * =============================================================================
 * 
 * @file        app/Models/NotificationOptOutModel.php
 * @description Data model for notification opt-outs. Tracks customers who
 *              have unsubscribed from specific notification channels.
 * 
 * DATABASE TABLE: xs_notification_opt_outs
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - business_id     : Business identifier (multi-tenant)
 * - channel         : email, sms, whatsapp
 * - recipient       : Email address or phone number
 * - reason          : Why they opted out (optional)
 * - created_at      : When opt-out was recorded
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * - Respect customer communication preferences
 * - GDPR/CAN-SPAM compliance
 * - Track unsubscribe reasons for improvement
 * 
 * USAGE:
 * -----------------------------------------------------------------------------
 * Before sending any notification, the system checks this table:
 * 
 *     if ($optOutModel->isOptedOut($email, 'email')) {
 *         // Skip sending email notification
 *     }
 * 
 * OPT-OUT REASONS:
 * -----------------------------------------------------------------------------
 * - 'user_request' : Customer clicked unsubscribe
 * - 'bounce'       : Email bounced (hard bounce)
 * - 'complaint'    : Marked as spam
 * - 'invalid'      : Invalid contact info
 * 
 * @see         app/Services/NotificationPolicyService.php for policy context
 * @package     App\Models
 * @extends     BaseModel
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Models;

class NotificationOptOutModel extends BaseModel
{
    protected $table = 'xs_notification_opt_outs';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'channel',
        'recipient',
        'reason',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'channel' => 'required|in_list[email,sms,whatsapp]',
        'recipient' => 'required|string|max_length[190]',
        'reason' => 'permit_empty|string|max_length[190]',
    ];
}
