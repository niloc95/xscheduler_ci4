<?php

/**
 * =============================================================================
 * BUSINESS NOTIFICATION RULE MODEL
 * =============================================================================
 * 
 * @file        app/Models/BusinessNotificationRuleModel.php
 * @description Data model for notification rules. Defines which events trigger
 *              notifications on which channels.
 * 
 * DATABASE TABLE: xs_business_notification_rules
 * -----------------------------------------------------------------------------
 * Columns:
 * - id                      : Primary key
 * - business_id             : Business identifier (multi-tenant)
 * - event_type              : Event that triggers notification
 * - channel                 : email, sms, whatsapp
 * - is_enabled              : Is this rule active (0/1)
 * - reminder_offset_minutes : For reminders, minutes before event
 * - created_at              : Creation timestamp
 * - updated_at              : Last update timestamp
 * 
 * EVENT TYPES:
 * -----------------------------------------------------------------------------
 * - appointment_reminder    : Send X minutes before appointment
 * - appointment_confirmed   : When booking is confirmed
 * - appointment_cancelled   : When appointment is cancelled
 * - appointment_rescheduled : When appointment time changes
 * - appointment_completed   : After appointment finishes
 * 
 * REMINDER OFFSETS:
 * -----------------------------------------------------------------------------
 * For appointment_reminder event:
 * - 1440 = 24 hours before
 * - 60   = 1 hour before
 * - 30   = 30 minutes before
 * 
 * RULE LOGIC:
 * -----------------------------------------------------------------------------
 * When an event occurs, the system checks rules for that event:
 * 1. Find all enabled rules for event_type
 * 2. For each channel with enabled rule, queue notification
 * 3. Respect customer opt-outs per channel
 * 
 * @see         app/Services/NotificationPhase1.php for rule processing
 * @see         app/Controllers/Settings.php for admin rule config
 * @package     App\Models
 * @extends     BaseModel
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

class BusinessNotificationRuleModel extends BaseModel
{
    protected $table = 'xs_business_notification_rules';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'event_type',
        'channel',
        'is_enabled',
        'reminder_offset_minutes',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'event_type'  => 'required|max_length[64]',
        'channel'     => 'required|in_list[email,sms,whatsapp]',
        'is_enabled'  => 'permit_empty|in_list[0,1]',
    ];
}
