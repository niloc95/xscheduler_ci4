<?php

/**
 * =============================================================================
 * NOTIFICATION WHATSAPP SERVICE
 * =============================================================================
 * 
 * @file        app/Services/NotificationWhatsAppService.php
 * @description Handles sending WhatsApp notifications with multiple provider
 *              support ranging from zero-config to enterprise solutions.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides WhatsApp notification delivery with flexible provider options:
 * - Link Generator: Zero-config solution for small businesses
 * - Twilio: Moderate complexity, uses Twilio WhatsApp API
 * - Meta Cloud: Enterprise solution with template requirements
 * 
 * PROVIDERS (by complexity):
 * -----------------------------------------------------------------------------
 * 1. link_generator (Simplest)
 *    - Zero configuration required
 *    - Generates wa.me links with pre-filled messages
 *    - Staff clicks link to open WhatsApp and send manually
 *    - No API costs
 * 
 * 2. twilio (Moderate)
 *    - Requires Twilio account with WhatsApp enabled
 *    - Uses same auth as Twilio SMS
 *    - Fully automated sending
 *    - Sandbox or production number required
 * 
 * 3. meta_cloud (Advanced)
 *    - Requires Meta Business verification
 *    - Requires pre-approved message templates
 *    - Full WhatsApp Business API features
 *    - Template-based messaging only
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * sendWhatsApp($businessId, $toPhone, $message, $templateData)
 *   Send WhatsApp message via configured provider
 *   Returns: ['ok' => bool, 'error' => string|null]
 * 
 * getPublicIntegration($businessId)
 *   Get sanitized integration info (without tokens)
 * 
 * configureIntegration($businessId, $provider, $config)
 *   Save provider configuration (encrypted)
 * 
 * generateLink($phone, $message)
 *   Generate wa.me link for link_generator provider
 * 
 * CONFIG BY PROVIDER:
 * -----------------------------------------------------------------------------
 * link_generator:
 *   (No config needed)
 * 
 * twilio:
 *   - twilio_whatsapp_from : WhatsApp-enabled Twilio number
 *   (Auth from SMS config)
 * 
 * meta_cloud:
 *   - phone_number_id : Meta phone number ID
 *   - waba_id         : WhatsApp Business Account ID
 *   - access_token    : Meta API access token
 * 
 * @see         app/Helpers/whatsapp_helper.php for link generation
 * @see         app/Models/MessageTemplateModel.php for Meta templates
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Models\MessageTemplateModel;

/**
 * WhatsApp Notification Service with Multiple Provider Support
 * 
 * Providers:
 * - link_generator: Zero-config wa.me links (opens WhatsApp with pre-filled message)
 * - twilio: Twilio WhatsApp API (uses same credentials as SMS)
 * - meta_cloud: Meta Cloud API (requires Business verification + templates)
 */
class NotificationWhatsAppService
{
    public const CHANNEL = 'whatsapp';

    /**
     * Available WhatsApp providers in order of complexity
     * link_generator = simplest (zero config, manual send)
     * twilio = moderate (API credentials, automated)
     * meta_cloud = advanced (Business verification, templates required)
     */
    public const PROVIDERS = ['link_generator', 'twilio', 'meta_cloud'];

    public const PROVIDER_LABELS = [
        'link_generator' => 'WhatsApp Link Generator (Simplest)',
        'twilio' => 'Twilio WhatsApp (Automated)',
        'meta_cloud' => 'Meta Cloud API (Advanced)',
    ];

    /**
     * Returns a safe-to-render subset of the stored WhatsApp integration.
     * Secrets are never returned. decrypt_error is set if key mismatch.
     */
    public function getPublicIntegration(int $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT): array
    {
        $integration = $this->getIntegrationRow($businessId);
        if (!$integration) {
            return [
                'provider' => 'link_generator',
                'provider_name' => 'link_generator',
                'is_active' => false,
                'config' => [
                    // Meta Cloud
                    'phone_number_id' => '',
                    'waba_id' => '',
                    // Twilio (shares credentials from SMS, but needs whatsapp_from)
                    'twilio_whatsapp_from' => '',
                ],
                'decrypt_error' => null,
            ];
        }

        $decrypted = $this->decryptConfig($integration['encrypted_config'] ?? null, true);
        $config = $decrypted['data'] ?? [];
        $decryptError = $decrypted['error'] ?? null;

        $provider = (string) ($config['provider'] ?? $integration['provider_name'] ?? 'link_generator');
        if (!in_array($provider, self::PROVIDERS, true)) {
            $provider = 'link_generator';
        }

        return [
            'provider' => $provider,
            'provider_name' => $provider,
            'is_active' => (bool) ($integration['is_active'] ?? false),
            'config' => [
                // Meta Cloud (never expose access_token)
                'phone_number_id' => (string) ($config['phone_number_id'] ?? ''),
                'waba_id' => (string) ($config['waba_id'] ?? ''),
                // Twilio WhatsApp
                'twilio_whatsapp_from' => (string) ($config['twilio_whatsapp_from'] ?? ''),
            ],
            'decrypt_error' => $decryptError,
        ];
    }

    /**
     * Save WhatsApp configuration into xs_business_integrations (encrypted_config).
     * Supports multiple providers with different credential requirements.
     */
    public function saveIntegration(int $businessId, array $input): array
    {
        $provider = trim((string) ($input['provider'] ?? 'link_generator'));
        if (!in_array($provider, self::PROVIDERS, true)) {
            $provider = 'link_generator';
        }

        $isActive = (bool) ($input['is_active'] ?? false);

        // Link Generator needs no credentials - always succeeds
        if ($provider === 'link_generator') {
            return $this->saveLinkGeneratorConfig($businessId, $isActive);
        }

        // Twilio WhatsApp
        if ($provider === 'twilio') {
            return $this->saveTwilioConfig($businessId, $input, $isActive);
        }

        // Meta Cloud API
        return $this->saveMetaCloudConfig($businessId, $input, $isActive);
    }

    /**
     * Save Link Generator config (no credentials needed)
     */
    private function saveLinkGeneratorConfig(int $businessId, bool $isActive): array
    {
        $configToStore = [
            'provider' => 'link_generator',
        ];

        try {
            $encryptedConfig = $this->encryptConfig($configToStore);
        } catch (\Throwable $e) {
            log_message('error', 'NotificationWhatsAppService: encrypt failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Encryption is not configured correctly.'];
        }

        return $this->persistIntegration($businessId, 'link_generator', $encryptedConfig, $isActive);
    }

    /**
     * Save Twilio WhatsApp config
     * Uses the same SID/Token from SMS settings, just needs WhatsApp-specific from number
     */
    private function saveTwilioConfig(int $businessId, array $input, bool $isActive): array
    {
        $twilioFrom = trim((string) ($input['twilio_whatsapp_from'] ?? ''));
        
        // Twilio WhatsApp from must be in format: whatsapp:+15551234567
        if ($twilioFrom !== '' && strpos($twilioFrom, 'whatsapp:') !== 0) {
            // Auto-prefix if not present
            if ($this->isValidE164($twilioFrom)) {
                $twilioFrom = 'whatsapp:' . $twilioFrom;
            } else {
                return ['ok' => false, 'error' => 'Twilio WhatsApp From number must be a valid +E.164 phone number.'];
            }
        }

        // Check if Twilio SMS credentials exist
        $smsService = new NotificationSmsService();
        $smsIntegration = $smsService->getPublicIntegration($businessId);
        $hasTwilioCreds = !empty($smsIntegration['config']['twilio_account_sid'] ?? '');
        
        if (!$hasTwilioCreds && $isActive) {
            return ['ok' => false, 'error' => 'Please configure Twilio SMS credentials first (Account SID & Auth Token in SMS settings).'];
        }

        if ($twilioFrom === '' && $isActive) {
            return ['ok' => false, 'error' => 'Twilio WhatsApp From number is required.'];
        }

        $configToStore = [
            'provider' => 'twilio',
            'twilio_whatsapp_from' => $twilioFrom,
        ];

        try {
            $encryptedConfig = $this->encryptConfig($configToStore);
        } catch (\Throwable $e) {
            log_message('error', 'NotificationWhatsAppService: encrypt failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Encryption is not configured correctly.'];
        }

        return $this->persistIntegration($businessId, 'twilio', $encryptedConfig, $isActive);
    }

    /**
     * Save Meta Cloud API config
     */
    private function saveMetaCloudConfig(int $businessId, array $input, bool $isActive): array
    {
        $phoneNumberId = trim((string) ($input['phone_number_id'] ?? ''));
        $wabaId = trim((string) ($input['waba_id'] ?? ''));
        $accessToken = (string) ($input['access_token'] ?? '');

        $hasAny = ($phoneNumberId !== '' || $wabaId !== '' || $accessToken !== '' || $isActive);
        if (!$hasAny) {
            return ['ok' => true, 'cleared' => false];
        }

        if ($phoneNumberId === '' || !ctype_digit($phoneNumberId)) {
            return ['ok' => false, 'error' => 'WhatsApp Phone Number ID is required and must be numeric.'];
        }
        
        if ($accessToken === '') {
            // Preserve existing token
            $existing = $this->getIntegrationRow($businessId);
            $existingConfig = $this->decryptConfig($existing['encrypted_config'] ?? null);
            if (!empty($existingConfig['access_token'])) {
                $accessToken = (string) $existingConfig['access_token'];
            } else {
                return ['ok' => false, 'error' => 'WhatsApp Access Token is required.'];
            }
        }

        $configToStore = [
            'provider' => 'meta_cloud',
            'phone_number_id' => $phoneNumberId,
            'waba_id' => $wabaId,
            'access_token' => $accessToken,
        ];

        try {
            $encryptedConfig = $this->encryptConfig($configToStore);
        } catch (\Throwable $e) {
            log_message('error', 'NotificationWhatsAppService: encrypt failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Encryption is not configured correctly.'];
        }

        return $this->persistIntegration($businessId, 'meta_cloud', $encryptedConfig, $isActive);
    }

    /**
     * Persist integration to database
     */
    private function persistIntegration(int $businessId, string $provider, string $encryptedConfig, bool $isActive): array
    {
        $model = new BusinessIntegrationModel();
        $existing = $this->getIntegrationRow($businessId);

        $payload = [
            'business_id' => $businessId,
            'channel' => self::CHANNEL,
            'provider_name' => $provider,
            'encrypted_config' => $encryptedConfig,
            'is_active' => $isActive ? 1 : 0,
        ];

        if (!empty($existing['id'])) {
            $model->update((int) $existing['id'], $payload);
        } else {
            $model->insert($payload);
        }

        return ['ok' => true, 'cleared' => false];
    }

    /**
     * Store a WhatsApp template reference for an event (Meta template name + locale).
     * Only used for Meta Cloud API provider.
     */
    public function saveTemplate(int $businessId, string $eventType, ?string $templateName, ?string $locale): array
    {
        $eventType = trim($eventType);
        $templateName = trim((string) $templateName);
        $locale = trim((string) $locale);
        if ($locale === '') {
            $locale = 'en_US';
        }

        if ($eventType === '') {
            return ['ok' => false, 'error' => 'Missing event type.'];
        }

        $isActive = $templateName !== '';

        $model = new MessageTemplateModel();
        $existing = $model
            ->where('business_id', $businessId)
            ->where('event_type', $eventType)
            ->where('channel', self::CHANNEL)
            ->first();

        $payload = [
            'business_id' => $businessId,
            'event_type' => $eventType,
            'channel' => self::CHANNEL,
            'provider' => 'meta_cloud',
            'provider_template_id' => $templateName !== '' ? $templateName : null,
            'locale' => $locale,
            'subject' => null,
            'body' => null,
            'is_active' => $isActive ? 1 : 0,
        ];

        if (!empty($existing['id'])) {
            $model->update((int) $existing['id'], $payload);
        } else {
            $model->insert($payload);
        }

        return ['ok' => true];
    }

    public function getActiveTemplate(int $businessId, string $eventType): ?array
    {
        try {
            $model = new MessageTemplateModel();
            $row = $model
                ->where('business_id', $businessId)
                ->where('event_type', $eventType)
                ->where('channel', self::CHANNEL)
                ->where('is_active', 1)
                ->first();
        } catch (\Throwable $e) {
            log_message('debug', 'NotificationWhatsAppService::getActiveTemplate — table unavailable: ' . $e->getMessage());
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        $name = (string) ($row['provider_template_id'] ?? '');
        if (trim($name) === '') {
            return null;
        }

        return [
            'template_name' => $name,
            'locale' => (string) ($row['locale'] ?? 'en_US'),
        ];
    }

    /**
     * Send WhatsApp message using the configured provider
     * 
     * For Link Generator: Returns a wa.me link (no actual send)
     * For Twilio: Sends via Twilio WhatsApp API
     * For Meta Cloud: Sends via Meta Cloud API template messages
     * 
     * @param int $businessId Business ID
     * @param string $toPhone Recipient phone in E.164 format
     * @param string $eventType Event type (appointment_confirmed, etc.)
     * @param array $bodyParameters Parameters for Meta Cloud templates
     * @param array $appointment Appointment data for link generator / fallback
     * @param array $business Business data for link generator / fallback
     * @param string|null $renderedMessage Pre-rendered message from NotificationTemplateService (for Twilio/Link Generator)
     */
    public function sendMessage(int $businessId, string $toPhone, string $eventType, array $bodyParameters = [], array $appointment = [], array $business = [], ?string $renderedMessage = null): array
    {
        $toPhone = trim($toPhone);
        if (!$this->isValidE164($toPhone)) {
            return ['ok' => false, 'error' => 'Invalid recipient phone number (+E.164).'];
        }

        $integration = $this->getIntegrationRow($businessId);
        $config = $this->decryptConfig($integration['encrypted_config'] ?? null);
        $provider = (string) ($config['provider'] ?? $integration['provider_name'] ?? 'link_generator');

        // For Link Generator, always return a link (doesn't need to be active)
        if ($provider === 'link_generator') {
            return $this->generateWhatsAppLink($toPhone, $eventType, $appointment, $business, $renderedMessage);
        }

        // For API-based providers, check if configured and active
        if (!$integration || empty($integration['encrypted_config'])) {
            return ['ok' => false, 'error' => 'WhatsApp integration is not configured.'];
        }
        if (empty($integration['is_active'])) {
            return ['ok' => false, 'error' => 'WhatsApp integration is not active.'];
        }

        if ($provider === 'twilio') {
            return $this->sendTwilioWhatsApp($businessId, $config, $toPhone, $eventType, $bodyParameters, $appointment, $business, $renderedMessage);
        }

        return $this->sendMetaCloudWhatsApp($businessId, $config, $toPhone, $eventType, $bodyParameters);
    }

    /**
     * Generate a WhatsApp link (wa.me) with pre-filled message
     * This doesn't actually send - it creates a link the user can click to open WhatsApp
     */
    private function generateWhatsAppLink(string $toPhone, string $eventType, array $appointment = [], array $business = [], ?string $renderedMessage = null): array
    {
        helper('whatsapp');
        
        // Use pre-rendered message from templates if available
        if ($renderedMessage !== null && $renderedMessage !== '') {
            $link = whatsapp_link($toPhone, $renderedMessage);
        } else {
            $link = whatsapp_generate_link_for_event($eventType, $toPhone, $appointment, [], [], $business);
        }
        
        return [
            'ok' => true,
            'method' => 'link',
            'link' => $link,
            'message' => 'Click the link to send WhatsApp message',
        ];
    }

    /**
     * Send WhatsApp message via Twilio
     * Uses the Twilio credentials from SMS settings
     * 
     * @param int $businessId Business ID
     * @param array $config WhatsApp integration config
     * @param string $toPhone Recipient phone
     * @param string $eventType Event type
     * @param array $bodyParameters Template parameters (for fallback)
     * @param array $appointment Appointment data (for fallback)
     * @param array $business Business data (for fallback)
     * @param string|null $renderedMessage Pre-rendered message from NotificationTemplateService
     */
    private function sendTwilioWhatsApp(int $businessId, array $config, string $toPhone, string $eventType, array $bodyParameters, array $appointment, array $business, ?string $renderedMessage = null): array
    {
        // Get Twilio credentials from SMS integration
        $smsService = new NotificationSmsService();
        $smsIntegration = $smsService->getFullConfig($businessId);
        
        if (empty($smsIntegration['twilio_account_sid']) || empty($smsIntegration['twilio_auth_token'])) {
            return ['ok' => false, 'error' => 'Twilio SMS credentials not configured. Please set up Twilio in SMS settings first.'];
        }

        $twilioFrom = (string) ($config['twilio_whatsapp_from'] ?? '');
        if ($twilioFrom === '') {
            return ['ok' => false, 'error' => 'Twilio WhatsApp From number not configured.'];
        }

        // Use pre-rendered message from templates if available
        $message = '';
        if ($renderedMessage !== null && $renderedMessage !== '') {
            $message = $renderedMessage;
        } else {
            // Fallback: Build message from event type using helper functions
            helper('whatsapp');
            switch ($eventType) {
                case 'appointment_confirmed':
                case 'appointment_created':
                    $message = whatsapp_appointment_confirmed_message($appointment, [], [], $business);
                    break;
                case 'appointment_reminder':
                    $message = whatsapp_appointment_reminder_message($appointment, [], [], $business);
                    break;
                case 'appointment_cancelled':
                    $message = whatsapp_appointment_cancelled_message($appointment, [], [], $business);
                    break;
                case 'appointment_rescheduled':
                    $message = whatsapp_appointment_rescheduled_message($appointment, [], [], [], $business);
                    break;
                default:
                    // Use body parameters for custom messages
                    $message = implode("\n", $bodyParameters);
                    if ($message === '') {
                        $message = 'Message from ' . ($business['business_name'] ?? 'WebSchedulr');
                    }
            }
        }

        // Twilio WhatsApp requires whatsapp: prefix on numbers
        $to = 'whatsapp:' . $toPhone;
        $from = $twilioFrom;
        if (strpos($from, 'whatsapp:') !== 0) {
            $from = 'whatsapp:' . $from;
        }

        $sid = $smsIntegration['twilio_account_sid'];
        $token = $smsIntegration['twilio_auth_token'];

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $post = http_build_query([
            'To' => $to,
            'From' => $from,
            'Body' => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $resp = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo) {
            log_message('error', 'Twilio WhatsApp curl error: {err}', ['err' => $err]);
            return ['ok' => false, 'error' => 'WhatsApp request failed.'];
        }

        if ($http < 200 || $http >= 300) {
            log_message('error', 'Twilio WhatsApp HTTP {code}: {resp}', ['code' => $http, 'resp' => (string) $resp]);
            $decoded = json_decode((string) $resp, true);
            $errorMsg = $decoded['message'] ?? 'WhatsApp request was rejected.';
            return ['ok' => false, 'error' => $errorMsg];
        }

        return ['ok' => true, 'method' => 'twilio'];
    }

    /**
     * Send WhatsApp message via Meta Cloud API (template-based)
     */
    private function sendMetaCloudWhatsApp(int $businessId, array $config, string $toPhone, string $eventType, array $bodyParameters): array
    {
        $phoneNumberId = (string) ($config['phone_number_id'] ?? '');
        $accessToken = (string) ($config['access_token'] ?? '');

        if ($phoneNumberId === '' || $accessToken === '') {
            return ['ok' => false, 'error' => 'WhatsApp integration is missing required settings.'];
        }

        $tpl = $this->getActiveTemplate($businessId, $eventType);
        if (!$tpl) {
            return ['ok' => false, 'error' => 'No active WhatsApp template configured for this event.'];
        }

        $toDigits = ltrim($toPhone, '+');
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toDigits,
            'type' => 'template',
            'template' => [
                'name' => $tpl['template_name'],
                'language' => [
                    'code' => $tpl['locale'] ?: 'en_US',
                ],
            ],
        ];

        if (!empty($bodyParameters)) {
            $params = [];
            foreach ($bodyParameters as $p) {
                $params[] = ['type' => 'text', 'text' => (string) $p];
            }
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => $params,
                ],
            ];
        }

        $url = 'https://graph.facebook.com/v20.0/' . rawurlencode($phoneNumberId) . '/messages';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $resp = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo) {
            log_message('error', 'WhatsApp curl error: {err}', ['err' => $err]);
            return ['ok' => false, 'error' => 'WhatsApp request failed.'];
        }

        if ($http < 200 || $http >= 300) {
            log_message('error', 'WhatsApp HTTP {code}: {resp}', ['code' => $http, 'resp' => (string) $resp]);
            return ['ok' => false, 'error' => 'WhatsApp request was rejected.'];
        }

        return ['ok' => true, 'method' => 'meta_cloud'];
    }

    /**
     * Legacy method - Template-only enforcement for Meta Cloud API
     * @deprecated Use sendMessage() instead
     * 
     * @param int $businessId Business ID
     * @param string $toPhone Recipient phone
     * @param string $eventType Event type
     * @param array $bodyParameters Parameters for Meta Cloud templates
     * @param string|null $renderedMessage Pre-rendered message from NotificationTemplateService
     */
    public function sendTemplateMessage(int $businessId, string $toPhone, string $eventType, array $bodyParameters = [], ?string $renderedMessage = null): array
    {
        return $this->sendMessage($businessId, $toPhone, $eventType, $bodyParameters, [], [], $renderedMessage);
    }

    /**
     * Sends a test WhatsApp message using the configured provider
     */
    public function sendTestMessage(int $businessId, string $toPhone): array
    {
        $toPhone = trim($toPhone);
        if (!$this->isValidE164($toPhone)) {
            return ['ok' => false, 'error' => 'Please provide a valid test recipient phone number in +E.164 format.'];
        }

        $integration = $this->getIntegrationRow($businessId);
        $config = $this->decryptConfig($integration['encrypted_config'] ?? null);
        $provider = (string) ($config['provider'] ?? $integration['provider_name'] ?? 'link_generator');

        // Prepare test appointment data
        $testAppointment = [
            'customer_name' => 'Test Customer',
            'start_datetime' => date('Y-m-d H:i:s', strtotime('+1 day 10:00')),
            'service_name' => 'Test Service',
            'provider_name' => 'Test Provider',
        ];

        // Render message using NotificationTemplateService (includes legal placeholders)
        $templateSvc = new NotificationTemplateService();
        $rendered = $templateSvc->render('appointment_confirmed', 'whatsapp', $testAppointment);
        $message = $rendered['body'] ?? '';

        // For Link Generator, return the test link with rendered message
        if ($provider === 'link_generator') {
            helper('whatsapp');
            $link = whatsapp_link($toPhone, $message);
            
            return [
                'ok' => true,
                'method' => 'link',
                'link' => $link,
                'message' => 'WhatsApp Link Generator is ready! Click the link to test.',
            ];
        }

        // Check configuration
        if (!$integration || empty($integration['encrypted_config'])) {
            return ['ok' => false, 'error' => 'WhatsApp integration is not configured yet.'];
        }

        $now = date('Y-m-d H:i:s');
        $model = new BusinessIntegrationModel();

        try {
            $business = ['business_name' => 'WebSchedulr'];

            // Use rendered message from template service
            $send = $this->sendMessage($businessId, $toPhone, 'appointment_confirmed', ['Test', 'Service', 'Provider', $now], $testAppointment, $business, $message);
            
            if ($send['ok'] ?? false) {
                $this->updateHealth($model, $integration, 'healthy', $now);
                return $send;
            }

            $this->updateHealth($model, $integration, 'unhealthy', $now);
            return ['ok' => false, 'error' => (string) ($send['error'] ?? 'WhatsApp test failed.')];
        } catch (\Throwable $e) {
            log_message('error', 'WhatsApp test exception: {msg}', ['msg' => $e->getMessage()]);
            $this->updateHealth($model, $integration, 'unhealthy', $now);
            return ['ok' => false, 'error' => 'WhatsApp test failed due to a server error.'];
        }
    }

    /**
     * Get a WhatsApp link for an appointment (for Link Generator provider or fallback)
     * Now uses NotificationTemplateService to render message with legal placeholders
     */
    public function getAppointmentWhatsAppLink(int $businessId, string $eventType, string $toPhone, array $appointment, array $provider = [], array $service = [], array $business = []): string
    {
        helper('whatsapp');
        
        // Merge provider and service data into appointment for template rendering
        $templateData = array_merge($appointment, [
            'provider_name' => $provider['name'] ?? $appointment['provider_name'] ?? '',
            'service_name' => $service['name'] ?? $appointment['service_name'] ?? '',
        ]);
        
        // Render message using NotificationTemplateService (includes legal placeholders)
        $templateSvc = new NotificationTemplateService();
        $rendered = $templateSvc->render($eventType, 'whatsapp', $templateData);
        $message = $rendered['body'] ?? '';
        
        if ($message !== '') {
            return whatsapp_link($toPhone, $message);
        }
        
        // Fallback to helper function if template rendering fails
        return whatsapp_generate_link_for_event($eventType, $toPhone, $appointment, $provider, $service, $business);
    }

    private function getIntegrationRow(int $businessId): ?array
    {
        try {
            $model = new BusinessIntegrationModel();
            $row = $model
                ->where('business_id', $businessId)
                ->where('channel', self::CHANNEL)
                ->first();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            log_message('debug', 'NotificationWhatsAppService::getIntegrationRow — table unavailable: ' . $e->getMessage());
            return null;
        }
    }

    private function encryptConfig(array $config): string
    {
        helper('notification');
        return notification_encrypt_config($config, 'WhatsApp');
    }

    /**
     * Decrypt config, returning ['data' => array, 'error' => string|null].
     */
    private function decryptConfig($encrypted, bool $returnError = false): array
    {
        helper('notification');
        return notification_decrypt_config($encrypted, $returnError, 'NotificationWhatsAppService');
    }

    private function updateHealth(BusinessIntegrationModel $model, array $integration, string $healthStatus, string $testedAt): void
    {
        $payload = [
            'health_status' => $healthStatus,
            'last_tested_at' => $testedAt,
        ];
        if (!empty($integration['id'])) {
            $model->update((int) $integration['id'], $payload);
        }
    }

    private function isValidE164(string $phone): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $phone);
    }
}
