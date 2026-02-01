<?php

/**
 * =============================================================================
 * BUSINESS INTEGRATION MODEL
 * =============================================================================
 * 
 * @file        app/Models/BusinessIntegrationModel.php
 * @description Data model for notification provider integrations. Stores
 *              encrypted credentials for email, SMS, and WhatsApp services.
 * 
 * DATABASE TABLE: xs_business_integrations
 * -----------------------------------------------------------------------------
 * Columns:
 * - id              : Primary key
 * - business_id     : Business identifier (multi-tenant)
 * - channel         : email, sms, whatsapp
 * - provider_name   : Service provider (smtp, twilio, messagebird, etc.)
 * - encrypted_config: AES-encrypted JSON credentials
 * - is_active       : Is integration enabled (0/1)
 * - health_status   : Last health check result (ok, error)
 * - last_tested_at  : When last connectivity test ran
 * - created_at      : Creation timestamp
 * - updated_at      : Last update timestamp
 * 
 * SUPPORTED PROVIDERS:
 * -----------------------------------------------------------------------------
 * Email:
 * - smtp : Standard SMTP server
 * - ses  : Amazon SES
 * - mailgun : Mailgun API
 * 
 * SMS:
 * - twilio     : Twilio SMS
 * - messagebird: MessageBird SMS
 * - clicksend  : ClickSend SMS
 * 
 * WhatsApp:
 * - twilio_whatsapp  : Twilio WhatsApp Business
 * - whatsapp_business: Official WhatsApp Business API
 * 
 * SECURITY:
 * -----------------------------------------------------------------------------
 * Credentials are encrypted using application encryption key.
 * Never store plaintext API keys or passwords.
 * 
 * @see         app/Services/NotificationPhase1.php for sending
 * @see         app/Controllers/Settings.php for admin config UI
 * @package     App\Models
 * @extends     BaseModel
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Models;

class BusinessIntegrationModel extends BaseModel
{
    protected $table = 'xs_business_integrations';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'business_id',
        'channel',
        'provider_name',
        'encrypted_config',
        'is_active',
        'health_status',
        'last_tested_at',
    ];

    protected $validationRules = [
        'business_id' => 'required|is_natural_no_zero',
        'channel'     => 'required|in_list[email,sms,whatsapp]',
    ];
}
