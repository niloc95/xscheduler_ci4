<?php

/**
 * =============================================================================
 * MESSAGE TEMPLATE MODEL
 * =============================================================================
 * 
 * @file        app/Models/MessageTemplateModel.php
 * @description Data model for notification message templates. Stores
 *              customizable templates for emails, SMS, and WhatsApp.
 * 
 * DATABASE TABLE: xs_message_templates
 * -----------------------------------------------------------------------------
 * Columns:
 * - id                  : Primary key
 * - business_id         : Business identifier (multi-tenant)
 * - event_type          : appointment_reminder, appointment_confirmed, etc.
 * - channel             : email, sms, whatsapp
 * - provider            : smtp, twilio, whatsapp_business, etc.
 * - provider_template_id: External template ID (for WhatsApp approved templates)
 * - locale              : Language code (en, es, fr)
 * - subject             : Email subject line (email only)
 * - body                : Message body with placeholders
 * - is_active           : Is template active (0/1)
 * - created_at          : Creation timestamp
 * - updated_at          : Last update timestamp
 * 
 * TEMPLATE PLACEHOLDERS:
 * -----------------------------------------------------------------------------
 * Templates support placeholders like:
 * - {{customer_name}}    : Customer's full name
 * - {{appointment_date}} : Formatted date
 * - {{appointment_time}} : Formatted time
 * - {{service_name}}     : Service booked
 * - {{provider_name}}    : Provider name
 * - {{business_name}}    : Business name
 * - {{cancel_link}}      : Cancellation URL
 * 
 * EVENT TYPES:
 * -----------------------------------------------------------------------------
 * - appointment_reminder   : Reminder before appointment
 * - appointment_confirmed  : Booking confirmation
 * - appointment_cancelled  : Cancellation notice
 * - appointment_rescheduled: Reschedule notification
 * 
 * @see         app/Services/NotificationPhase1.php for template rendering
 * @see         app/Controllers/Settings.php for admin template editor
 * @package     App\Models
 * @extends     BaseModel
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

class MessageTemplateModel extends BaseModel
{
    protected $table = 'xs_message_templates';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'event_type',
        'channel',
        'provider',
        'provider_template_id',
        'locale',
        'subject',
        'body',
        'is_active',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'event_type'  => 'required|max_length[64]',
        'channel'     => 'required|in_list[email,sms,whatsapp]',
    ];
}
