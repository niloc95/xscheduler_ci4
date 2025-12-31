<?php

namespace App\Controllers;

use App\Models\SettingModel;
use App\Models\NotificationDeliveryLogModel;
use App\Services\NotificationEmailService;
use App\Services\NotificationSmsService;
use App\Services\NotificationWhatsAppService;
use App\Services\NotificationPhase1;

class Settings extends BaseController
{
    public function index()
    {
        $this->localUploadLog('index_hit', []);
        
        // Load current settings to pass to the view
        $settingModel = new SettingModel();
        $settings = $settingModel->getByKeys([
            'general.company_name',
            'general.company_email', 
            'general.company_link',
            'general.telephone_number',
            'general.mobile_number',
            'general.business_address',
            'localization.time_format',
            'localization.first_day',
            'localization.language',
            'localization.timezone',
            'localization.currency',
            'booking.first_names_display',
            'booking.first_names_required',
            'booking.surname_display',
            'booking.surname_required',
            'booking.email_display',
            'booking.email_required',
            'booking.phone_display',
            'booking.phone_required',
            'booking.address_display',
            'booking.address_required',
            'booking.notes_display',
            'booking.notes_required',
            'booking.custom_field_1_enabled',
            'booking.custom_field_1_title',
            'booking.custom_field_1_type',
            'booking.custom_field_1_required',
            'booking.custom_field_2_enabled',
            'booking.custom_field_2_title',
            'booking.custom_field_2_type',
            'booking.custom_field_2_required',
            'booking.custom_field_3_enabled',
            'booking.custom_field_3_title',
            'booking.custom_field_3_type',
            'booking.custom_field_3_required',
            'booking.custom_field_4_enabled',
            'booking.custom_field_4_title',
            'booking.custom_field_4_type',
            'booking.custom_field_4_required',
            'booking.custom_field_5_enabled',
            'booking.custom_field_5_title',
            'booking.custom_field_5_type',
            'booking.custom_field_5_required',
            'booking.custom_field_6_enabled',
            'booking.custom_field_6_title',
            'booking.custom_field_6_type',
            'booking.custom_field_6_required',
            'booking.fields',
            'booking.custom_fields',
            'booking.statuses',
            'business.work_start',
            'business.work_end',
            'business.break_start', 
            'business.break_end',
            'business.blocked_periods',
            'business.reschedule',
            'business.cancel',
            'business.future_limit',
            'legal.cookie_notice',
            'legal.terms',
            'legal.privacy',
            'legal.cancellation_policy',
            'legal.rescheduling_policy',
            'legal.terms_url',
            'legal.privacy_url',
            'integrations.webhook_url',
            'integrations.analytics',
            'integrations.api_integrations',
            'integrations.ldap_enabled',
            'integrations.ldap_host',
            'integrations.ldap_dn',
            'notifications.default_language'
        ]);

        $notificationPhase1 = new NotificationPhase1();
        $notificationRules = $notificationPhase1->getRules(NotificationPhase1::BUSINESS_ID_DEFAULT);
        $integrationStatus = $notificationPhase1->getIntegrationStatus(NotificationPhase1::BUSINESS_ID_DEFAULT);
        $emailIntegration = (new NotificationEmailService())->getPublicIntegration(NotificationPhase1::BUSINESS_ID_DEFAULT);
        $smsIntegration = (new NotificationSmsService())->getPublicIntegration(NotificationPhase1::BUSINESS_ID_DEFAULT);
        $waIntegration = (new NotificationWhatsAppService())->getPublicIntegration(NotificationPhase1::BUSINESS_ID_DEFAULT);

        $waSvc = new NotificationWhatsAppService();
        $waTemplates = [];
        foreach (array_keys(NotificationPhase1::EVENTS) as $eventType) {
            $waTemplates[$eventType] = $waSvc->getActiveTemplate(NotificationPhase1::BUSINESS_ID_DEFAULT, $eventType) ?? [
                'template_name' => '',
                'locale' => 'en_US',
            ];
        }

        $deliveryLogModel = new NotificationDeliveryLogModel();
        $deliveryLogs = $deliveryLogModel
            ->where('business_id', NotificationPhase1::BUSINESS_ID_DEFAULT)
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->findAll();
        
        // Load message templates from settings
        $messageTemplates = $this->loadMessageTemplates();
        
        $data = [
            'user' => session()->get('user') ?? [
                'name' => 'System Administrator',
                'role' => 'admin',
                'email' => 'admin@webschedulr.com',
            ],
            'settings' => $settings, // Pass settings to view
            'notificationRules' => $notificationRules,
            'notificationIntegrationStatus' => $integrationStatus,
            'notificationEmailIntegration' => $emailIntegration,
            'notificationSmsIntegration' => $smsIntegration,
            'notificationWhatsAppIntegration' => $waIntegration,
            'notificationWhatsAppTemplates' => $waTemplates,
            'notificationEvents' => NotificationPhase1::EVENTS,
            'notificationDeliveryLogs' => $deliveryLogs,
            'notificationMessageTemplates' => $messageTemplates,
        ];

        return view('settings', $data);
    }

    /**
     * Load message templates from settings
     *
     * @return array Templates indexed by event_type and channel
     */
    private function loadMessageTemplates(): array
    {
        $settingModel = new SettingModel();
        $templateSettings = $settingModel->getByPrefix('notification_template.');
        
        $templates = [];
        foreach ($templateSettings as $key => $value) {
            // Key format: notification_template.{event_type}.{channel}
            $parts = explode('.', $key);
            if (count($parts) === 3) {
                $eventType = $parts[1];
                $channel = $parts[2];
                
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $templates[$eventType][$channel] = $decoded;
                    }
                } elseif (is_array($value)) {
                    $templates[$eventType][$channel] = $value;
                }
            }
        }
        
        return $templates;
    }

    public function saveNotifications()
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return redirect()->to(base_url('settings'));
        }

        $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT;
        $userId = session()->get('user_id');

        $intent = (string) ($this->request->getPost('intent') ?? 'save');
        $intent = trim($intent) === '' ? 'save' : trim($intent);

        $rulesInput = $this->request->getPost('rules') ?? [];
        $reminderOffsetMinutes = $this->request->getPost('reminder_offset_minutes');
        $reminderOffsetMinutes = is_numeric($reminderOffsetMinutes) ? (int) $reminderOffsetMinutes : null;
        if ($reminderOffsetMinutes !== null) {
            $reminderOffsetMinutes = max(0, min(43200, $reminderOffsetMinutes)); // cap at 30 days
        }

        $defaultLang = (string) ($this->request->getPost('notification_default_language') ?? '');
        $defaultLang = trim($defaultLang);
        if ($defaultLang === '') {
            $defaultLang = (string) ($this->request->getPost('language') ?? 'English');
        }

        $settingModel = new SettingModel();
        $settingModel->upsert('notifications.default_language', $defaultLang, 'string', $userId);

        // Determine which services to save based on intent
        $saveEmail = in_array($intent, ['save', 'save_email', 'test_email'], true);
        $saveSms = in_array($intent, ['save', 'save_sms', 'test_sms'], true);
        $saveWhatsApp = in_array($intent, ['save', 'save_whatsapp', 'test_whatsapp'], true);
        $saveRules = ($intent === 'save');

        // Phase 2: Email (SMTP) integration
        $emailSvc = new NotificationEmailService();
        if ($saveEmail) {
            $emailInput = [
                'provider_name' => $this->request->getPost('email_provider_name'),
                'is_active' => $this->request->getPost('email_is_active') ? true : false,
                'host' => $this->request->getPost('smtp_host'),
                'port' => $this->request->getPost('smtp_port'),
                'crypto' => $this->request->getPost('smtp_crypto'),
                'username' => $this->request->getPost('smtp_user'),
                'password' => $this->request->getPost('smtp_pass'),
                'from_email' => $this->request->getPost('smtp_from_email'),
                'from_name' => $this->request->getPost('smtp_from_name'),
            ];

            $emailSave = $emailSvc->saveIntegration($businessId, $emailInput);
            if (!($emailSave['ok'] ?? false)) {
                return redirect()->to(base_url('settings'))
                    ->with('error', (string) ($emailSave['error'] ?? 'Failed to save email integration settings.'));
            }

            if ($intent === 'save_email') {
                return redirect()->to(base_url('settings'))
                    ->with('success', 'Email settings saved successfully.');
            }
        }

        // Phase 3: SMS integration
        $smsSvc = new NotificationSmsService();
        if ($saveSms) {
            $smsInput = [
                'provider' => $this->request->getPost('sms_provider'),
                'is_active' => $this->request->getPost('sms_is_active') ? true : false,
                'clickatell_api_key' => $this->request->getPost('clickatell_api_key'),
                'clickatell_from' => $this->request->getPost('clickatell_from'),
                'twilio_account_sid' => $this->request->getPost('twilio_account_sid'),
                'twilio_auth_token' => $this->request->getPost('twilio_auth_token'),
                'twilio_from_number' => $this->request->getPost('twilio_from_number'),
            ];
            $smsSave = $smsSvc->saveIntegration($businessId, $smsInput);
            if (!($smsSave['ok'] ?? false)) {
                return redirect()->to(base_url('settings'))
                    ->with('error', (string) ($smsSave['error'] ?? 'Failed to save SMS integration settings.'));
            }

            if ($intent === 'save_sms') {
                return redirect()->to(base_url('settings'))
                    ->with('success', 'SMS settings saved successfully.');
            }
        }

        // Phase 4: WhatsApp (Multi-provider) integration + templates
        $waSvc = new NotificationWhatsAppService();
        if ($saveWhatsApp) {
            $waInput = [
                'provider' => $this->request->getPost('whatsapp_provider') ?: 'link_generator',
                'is_active' => $this->request->getPost('whatsapp_is_active') ? true : false,
                // Twilio WhatsApp
                'twilio_whatsapp_from' => $this->request->getPost('twilio_whatsapp_from'),
                // Meta Cloud API
                'phone_number_id' => $this->request->getPost('whatsapp_phone_number_id'),
                'waba_id' => $this->request->getPost('whatsapp_waba_id'),
                'access_token' => $this->request->getPost('whatsapp_access_token'),
            ];
            $waSave = $waSvc->saveIntegration($businessId, $waInput);
            if (!($waSave['ok'] ?? false)) {
                return redirect()->to(base_url('settings'))
                    ->with('error', (string) ($waSave['error'] ?? 'Failed to save WhatsApp integration settings.'));
            }

            // Only save templates for Meta Cloud provider
            if (($waInput['provider'] ?? '') === 'meta_cloud') {
                foreach (array_keys(NotificationPhase1::EVENTS) as $eventType) {
                    $tplName = $this->request->getPost('whatsapp_template_' . $eventType);
                    $tplLocale = $this->request->getPost('whatsapp_locale_' . $eventType);
                    $waSvc->saveTemplate($businessId, $eventType, is_string($tplName) ? $tplName : null, is_string($tplLocale) ? $tplLocale : null);
                }
            }

            if ($intent === 'save_whatsapp') {
                return redirect()->to(base_url('settings'))
                    ->with('success', 'WhatsApp settings saved successfully.');
            }
        }

        if ($intent === 'test_email') {
            $toEmail = (string) ($this->request->getPost('test_email_to') ?? '');
            $result = $emailSvc->sendTestEmail($businessId, $toEmail);
            if ($result['ok'] ?? false) {
                return redirect()->to(base_url('settings'))
                    ->with('success', 'Test email sent successfully.');
            }

            return redirect()->to(base_url('settings'))
                ->with('error', (string) ($result['error'] ?? 'Test email failed.'));
        }

        if ($intent === 'test_sms') {
            $toPhone = (string) ($this->request->getPost('test_sms_to') ?? '');
            $result = $smsSvc->sendTestSms($businessId, $toPhone);
            if ($result['ok'] ?? false) {
                return redirect()->to(base_url('settings'))
                    ->with('success', 'Test SMS sent successfully.');
            }

            return redirect()->to(base_url('settings'))
                ->with('error', (string) ($result['error'] ?? 'Test SMS failed.'));
        }

        if ($intent === 'test_whatsapp') {
            $toPhone = (string) ($this->request->getPost('test_whatsapp_to') ?? '');
            $result = $waSvc->sendTestMessage($businessId, $toPhone);
            if ($result['ok'] ?? false) {
                // For Link Generator, show the link in a special success message
                if (($result['method'] ?? '') === 'link' && !empty($result['link'])) {
                    return redirect()->to(base_url('settings'))
                        ->with('success', 'WhatsApp Link ready! <a href="' . esc($result['link']) . '" target="_blank" class="underline font-semibold">Click here to open WhatsApp</a>')
                        ->with('success_html', true);
                }
                return redirect()->to(base_url('settings'))
                    ->with('success', 'Test WhatsApp message sent successfully.');
            }

            return redirect()->to(base_url('settings'))
                ->with('error', (string) ($result['error'] ?? 'Test WhatsApp message failed.'));
        }

        // Only save rules matrix when using the main "Save Notification Settings" button
        if ($saveRules) {
            $db = \Config\Database::connect();
            $db->transStart();

            $ruleModel = new \App\Models\BusinessNotificationRuleModel();

            foreach (array_keys(NotificationPhase1::EVENTS) as $eventType) {
                foreach (NotificationPhase1::CHANNELS as $channel) {
                    $enabled = isset($rulesInput[$eventType][$channel]) ? 1 : 0;

                    $offset = null;
                    if ($eventType === 'appointment_reminder') {
                        $offset = $reminderOffsetMinutes;
                    }

                    $existing = $ruleModel
                        ->where('business_id', $businessId)
                        ->where('event_type', $eventType)
                        ->where('channel', $channel)
                        ->first();

                    $payload = [
                        'business_id' => $businessId,
                        'event_type' => $eventType,
                        'channel' => $channel,
                        'is_enabled' => $enabled,
                        'reminder_offset_minutes' => $offset,
                    ];

                    if (!empty($existing['id'])) {
                        $ruleModel->update((int) $existing['id'], $payload);
                    } else {
                        $ruleModel->insert($payload);
                    }
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return redirect()->to(base_url('settings'))
                    ->with('error', 'Failed to save notification rules. Please try again.');
            }
        }

        // Handle message templates saving
        $saveTemplates = in_array($intent, ['save', 'save_templates'], true);
        if ($saveTemplates) {
            $templatesInput = $this->request->getPost('templates') ?? [];
            if (!empty($templatesInput) && is_array($templatesInput)) {
                $this->saveMessageTemplates($businessId, $templatesInput);
            }
            
            if ($intent === 'save_templates') {
                return redirect()->to(base_url('settings'))
                    ->with('success', 'Message templates saved successfully.');
            }
        }

        return redirect()->to(base_url('settings'))
            ->with('success', 'Notification settings saved.');
    }

    /**
     * Save message templates to the database
     *
     * @param int $businessId
     * @param array $templates Array of templates indexed by event_type and channel
     */
    private function saveMessageTemplates(int $businessId, array $templates): void
    {
        $settingModel = new SettingModel();
        $userId = session()->get('user_id');

        foreach ($templates as $eventType => $channels) {
            if (!is_array($channels)) {
                continue;
            }
            foreach ($channels as $channel => $template) {
                if (!is_array($template)) {
                    continue;
                }

                $subject = isset($template['subject']) ? trim((string) $template['subject']) : null;
                $body = isset($template['body']) ? trim((string) $template['body']) : '';

                // Store as JSON in settings
                $key = "notification_template.{$eventType}.{$channel}";
                $value = json_encode([
                    'subject' => $subject,
                    'body' => $body,
                ], JSON_UNESCAPED_UNICODE);

                $settingModel->upsert($key, $value, 'json', $userId);
            }
        }
    }

    public function save()
    {
        // Log that we reached the save method
        $this->localUploadLog('save_method_reached', [
            'method' => $this->request->getMethod(),
            'uri' => $this->request->getUri()->getPath(),
            'method_upper' => strtoupper($this->request->getMethod())
        ]);
        
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            $this->localUploadLog('not_post_method', ['method' => $this->request->getMethod()]);
            return redirect()->to(base_url('settings'));
        }

        $this->localUploadLog('save_enter', [
            'post_keys' => array_keys($this->request->getPost() ?? []),
            'has_file' => $this->request->getFile('company_logo') && !$this->request->getFile('company_logo')->getError() ? 'yes' : 'no',
            'form_source' => $this->request->getPost('form_source') ?? 'unknown'
        ]);

        $post = $this->request->getPost();
        $model = new SettingModel();
        $userId = session()->get('user_id');

        $upsert = function (string $key, $value) use ($model, $userId) {
            $type = 'string';
            if (is_string($value) && in_array(strtolower($value), ['on','true','1','yes'], true)) {
                $boolFlags = [
                    'integrations.ldap_enabled',
                ];
                if (in_array($key, $boolFlags, true)) {
                    $value = true;
                    $type = 'bool';
                }
            } elseif (is_array($value)) {
                $type = 'json';
            } elseif (is_string($value)) {
                $trim = trim($value);
                if (($trim !== '') && (($trim[0] === '{' && substr($trim, -1) === '}') || ($trim[0] === '[' && substr($trim, -1) === ']'))) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                        $type = 'json';
                    }
                }
            }
            $model->upsert($key, $value, $type, $userId);
        };

        $map = [
            'general.company_name'  => 'company_name',
            'general.company_email' => 'company_email',
            'general.company_link'  => 'company_link',
            'general.telephone_number' => 'telephone_number',
            'general.mobile_number' => 'mobile_number',
            'general.business_address' => 'business_address',
            'localization.time_format' => 'time_format',
            'localization.first_day'   => 'first_day',
            'localization.language'    => 'language',
            'localization.timezone'    => 'timezone',
            'localization.currency'    => 'currency',
            // NOTE: Checkbox fields are handled separately below in $checkboxFields array
            // Custom field titles and types (non-checkbox fields)
            'booking.custom_field_1_title' => 'booking_custom_field_1_title',
            'booking.custom_field_1_type' => 'booking_custom_field_1_type',
            'booking.custom_field_2_title' => 'booking_custom_field_2_title',
            'booking.custom_field_2_type' => 'booking_custom_field_2_type',
            'booking.custom_field_3_title' => 'booking_custom_field_3_title',
            'booking.custom_field_3_type' => 'booking_custom_field_3_type',
            'booking.custom_field_4_title' => 'booking_custom_field_4_title',
            'booking.custom_field_4_type' => 'booking_custom_field_4_type',
            'booking.custom_field_5_title' => 'booking_custom_field_5_title',
            'booking.custom_field_5_type' => 'booking_custom_field_5_type',
            'booking.custom_field_6_title' => 'booking_custom_field_6_title',
            'booking.custom_field_6_type' => 'booking_custom_field_6_type',
            'booking.custom_fields' => 'custom_fields',
            'booking.statuses'      => 'statuses',
            'business.work_start'     => 'work_start',
            'business.work_end'       => 'work_end',
            'business.break_start'    => 'break_start',
            'business.break_end'      => 'break_end',
            'business.blocked_periods'=> 'blocked_periods',
            'business.reschedule'     => 'reschedule',
            'business.cancel'         => 'cancel',
            'business.future_limit'   => 'future_limit',
            'legal.cookie_notice' => 'cookie_notice',
            'legal.terms'         => 'terms',
            'legal.privacy'       => 'privacy',
            'legal.cancellation_policy' => 'cancellation_policy',
            'legal.rescheduling_policy' => 'rescheduling_policy',
            'legal.terms_url'     => 'terms_url',
            'legal.privacy_url'   => 'privacy_url',
            'integrations.webhook_url'  => 'webhook_url',
            'integrations.analytics'    => 'analytics',
            'integrations.api_integrations' => 'api_integrations',
            'integrations.ldap_enabled' => 'ldap_enabled',
            'integrations.ldap_host'    => 'ldap_host',
            'integrations.ldap_dn'      => 'ldap_dn',
        ];

        if (isset($post['fields']) && is_array($post['fields'])) {
            $upsert('booking.fields', $post['fields']);
        }

        // Handle booking checkbox fields specially (checkboxes only send values when checked)
        $checkboxFields = [
            'booking.first_names_display' => 'booking_first_names_display',
            'booking.first_names_required' => 'booking_first_names_required',
            'booking.surname_display' => 'booking_surname_display',
            'booking.surname_required' => 'booking_surname_required',
            'booking.email_display' => 'booking_email_display',
            'booking.email_required' => 'booking_email_required',
            'booking.phone_display' => 'booking_phone_display',
            'booking.phone_required' => 'booking_phone_required',
            'booking.address_display' => 'booking_address_display',
            'booking.address_required' => 'booking_address_required',
            'booking.notes_display' => 'booking_notes_display',
            'booking.notes_required' => 'booking_notes_required',
        ];
        
        // Add custom field checkboxes
        for ($i = 1; $i <= 6; $i++) {
            $checkboxFields["booking.custom_field_{$i}_enabled"] = "booking_custom_field_{$i}_enabled";
            $checkboxFields["booking.custom_field_{$i}_required"] = "booking_custom_field_{$i}_required";
        }
        
        // Log checkbox processing for debugging
        $this->localUploadLog('checkbox_processing', [
            'total_checkboxes' => count($checkboxFields),
            'posted_checkboxes' => array_keys(array_filter($post, function($key) {
                return strpos($key, 'booking_') === 0 && (strpos($key, '_display') !== false || strpos($key, '_required') !== false || strpos($key, '_enabled') !== false);
            }, ARRAY_FILTER_USE_KEY))
        ]);
        
        foreach ($checkboxFields as $settingKey => $postKey) {
            // For checkboxes, set to '1' if present, '0' if not
            $value = isset($post[$postKey]) && $post[$postKey] === '1' ? '1' : '0';
            $upsert($settingKey, $value);
            
            // Log each checkbox update for debugging
            log_message('debug', 'Checkbox field: {key} = {value} (POST key: {post}, present: {present})', [
                'key' => $settingKey,
                'value' => $value,
                'post' => $postKey,
                'present' => isset($post[$postKey]) ? 'yes' : 'no'
            ]);
        }

        // Special handling for blocked_periods: always store as JSON array of objects
        if (isset($post['blocked_periods'])) {
            $raw = $post['blocked_periods'];
            $parsed = [];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    // Accept only array of objects with start/end
                    foreach ($decoded as $item) {
                        if (is_array($item) && isset($item['start'], $item['end'])) {
                            $parsed[] = [
                                'start' => $item['start'],
                                'end' => $item['end'],
                                'notes' => $item['notes'] ?? ''
                            ];
                        }
                    }
                }
            }
            $upsert('business.blocked_periods', $parsed);
        }

        // Process regular settings fields (except blocked_periods, which is handled above)
        foreach ($map as $settingKey => $postKey) {
            if ($settingKey === 'business.blocked_periods') continue;
            if (array_key_exists($postKey, $post)) {
                $upsert($settingKey, $post[$postKey]);
            }
        }

        // Handle company logo upload with validation
    $file = $this->request->getFile('company_logo');
    if ($file) {
            $this->localUploadLog('begin', [
                'err' => $file->getError(),
                'name' => $file->getName(),
                'size' => (int) $file->getSize(),
                'cm' => (string) $file->getClientMimeType(),
                'rm' => (string) $file->getMimeType(),
            ]);
            log_message('debug', 'Logo upload: error={err} name={name} size={size} clientMime={cm} realMime={rm}', [
                'err' => $file->getError(),
                'name' => $file->getName(),
                'size' => $file->getSize(),
                'cm' => $file->getClientMimeType(),
                'rm' => $file->getMimeType(),
            ]);
            // If no file provided, skip silently
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                // nothing to do
            } elseif (!$file->isValid()) {
                session()->setFlashdata('error', 'Logo upload failed: ' . $file->getErrorString());
                log_message('error', 'Logo upload failed: {err}', ['err' => $file->getErrorString()]);
            } elseif ($file->hasMoved()) {
                // Already moved by PHP for some reason
                session()->setFlashdata('error', 'Logo upload failed: file already moved.');
            } else {
                $sizeBytes = (int) $file->getSize();
                if ($sizeBytes > (2 * 1024 * 1024)) {
                    session()->setFlashdata('error', 'Logo upload too large. Max 2MB.');
                    return redirect()->to(base_url('settings'));
                }

                $clientMime = strtolower((string) $file->getClientMimeType());
                $realMime   = strtolower((string) $file->getMimeType());
                $ext        = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));

                $allowedMimes = [
                    'image/png','image/x-png','image/jpeg','image/pjpeg','image/webp','image/svg+xml','image/svg','image/gif'
                ];
                $allowedExts = ['png','jpg','jpeg','webp','svg','gif'];

                $mimeOk = in_array($clientMime, $allowedMimes, true) || in_array($realMime, $allowedMimes, true);
                $extOk  = in_array($ext, $allowedExts, true);
                if (!$mimeOk && !$extOk) {
                    session()->setFlashdata('error', 'Unsupported logo format. Use PNG, JPG, SVG, WebP, or GIF.');
                    return redirect()->to(base_url('settings'));
                }

                // Store under public assets to serve directly
                $targetDir = rtrim(FCPATH, '/').'/assets/settings';
                if (!is_dir($targetDir)) { @mkdir($targetDir, 0755, true); }
                if (!is_dir($targetDir) || !is_writable($targetDir)) {
                    log_message('error', 'Logo upload: target dir not writable: {dir}', ['dir' => $targetDir]);
                }

                // Remove previous logo if exists
                $existing = $model->getByKeys(['general.company_logo']);
                $prevRel = $existing['general.company_logo'] ?? null;
                if ($prevRel) {
                    $prev = ltrim((string)$prevRel, '/');
                    if (str_starts_with($prev, 'assets/settings/')) {
                        $prevPath = rtrim(FCPATH, '/').'/'.$prev;
                    } elseif (str_starts_with($prev, 'uploads/settings/')) {
                        $prevPath = rtrim(WRITEPATH, '/').'/'.$prev;
                    } else {
                        $prevPath = rtrim(WRITEPATH, '/').'/'.$prev;
                    }
                    if (is_file($prevPath)) { @unlink($prevPath); }
                }

                $safeName = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if ($file->move($targetDir, $safeName)) {
                    $absolute = rtrim($targetDir, '/').'/'.$safeName;
                    $this->localUploadLog('moved', ['path' => $absolute]);
                    log_message('debug', 'Logo upload: moved to {path}', ['path' => $absolute]);
                    // Basic downscale for very large raster images to max width 1200px
                    try {
                        if (!in_array($realMime, ['image/svg+xml','image/svg'], true)) {
                            [$w, $h] = @getimagesize($absolute) ?: [null, null];
                            if ($w && $w > 1200) {
                                $ratio = $h / $w;
                                $newW = 1200;
                                $newH = max(1, (int) round($newW * $ratio));
                                $this->resizeImageInPlace($absolute, $realMime, $newW, $newH);
                            }
                        }
                    } catch (\Throwable $e) {}

                    // 1) File-based path under public assets
                    $relative = 'assets/settings/' . $safeName;
                    $model->upsert('general.company_logo', $relative, 'string', $userId);

                    // 2) Additionally persist bytes to DB for environments without public writable access
                    try {
                        $bytes = @file_get_contents($absolute);
                        if ($bytes !== false) {
                            $fileModel = new \App\Models\SettingFileModel();
                            $okDb = $fileModel->upsert('general.company_logo', $safeName, $realMime ?: $clientMime, $bytes, $userId);
                            $this->localUploadLog('db_store_after_move', ['status' => $okDb ? 'OK' : 'FAIL']);
                            log_message('debug', 'Logo upload: DB store {status}', ['status' => $okDb ? 'OK' : 'FAIL']);
                        } else {
                            $this->localUploadLog('db_read_after_move_fail', ['path' => $absolute]);
                            log_message('warning', 'Logo upload: could not read moved file for DB store: {path}', ['path' => $absolute]);
                        }
                    } catch (\Throwable $e) {
                        $this->localUploadLog('db_store_exception', ['msg' => $e->getMessage()]);
                        log_message('error', 'Logo upload: exception during DB store: {msg}', ['msg' => $e->getMessage()]);
                    }
                } else {
                    // Move failed – attempt to capture more diagnostics and still persist to DB directly from temp file
                    $tmpPath = method_exists($file, 'getTempName') ? $file->getTempName() : ($file->getRealPath() ?: '[unknown]');
                    $errMsg = $file->getErrorString();
                    $perms = @substr(sprintf('%o', @fileperms($targetDir)), -4) ?: '----';
                    $this->localUploadLog('move_failed', [
                        'dir' => $targetDir,
                        'name' => $safeName,
                        'tmp' => $tmpPath,
                        'err' => $errMsg,
                        'perms' => $perms,
                    ]);
                    log_message('error', 'Logo upload: move() failed for {dir}/{name} tmp={tmp} err={err} targetPerms={perms}', [
                        'dir' => $targetDir,
                        'name' => $safeName,
                        'tmp' => $tmpPath,
                        'err' => $errMsg,
                        'perms' => $perms,
                    ]);

                    // Try DB store from temp file as a fallback
                    try {
                        $tmpFile = method_exists($file, 'getTempName') ? $file->getTempName() : $file->getRealPath();
                        if ($tmpFile && is_file($tmpFile)) {
                            $bytes = @file_get_contents($tmpFile);
                            if ($bytes !== false) {
                                $fileModel = new \App\Models\SettingFileModel();
                                $okDb = $fileModel->upsert('general.company_logo', $file->getName(), $realMime ?: $clientMime, $bytes, $userId);
                                $this->localUploadLog('db_fallback_from_tmp', ['status' => $okDb ? 'OK' : 'FAIL']);
                                log_message('warning', 'Logo upload: persisted to DB from temp file due to move failure. status={status}', ['status' => $okDb ? 'OK' : 'FAIL']);
                                if ($okDb) {
                                    // Update setting to reference DB-backed asset
                                    $model->upsert('general.company_logo', 'db://' . $file->getName(), 'string', $userId);
                                    session()->setFlashdata('success', 'Settings saved. Logo stored in database due to filesystem issue.');
                                    return redirect()->to(base_url('settings'));
                                }
                            } else {
                                $this->localUploadLog('db_fallback_read_tmp_failed', []);
                                log_message('error', 'Logo upload: failed reading temp file for DB fallback');
                            }
                        } else {
                            $this->localUploadLog('db_fallback_tmp_missing', []);
                            log_message('error', 'Logo upload: temp file missing for DB fallback');
                        }
                    } catch (\Throwable $e) {
                        $this->localUploadLog('db_fallback_exception', ['msg' => $e->getMessage()]);
                        log_message('error', 'Logo upload: exception during DB fallback: {msg}', ['msg' => $e->getMessage()]);
                    }

                    session()->setFlashdata('error', 'Failed to save uploaded logo.');
                }
            }
        } else {
            $this->localUploadLog('no_file_in_request', []);
        }

        session()->setFlashdata('success', 'Settings saved successfully.');
        return redirect()->to(base_url('settings'));
    }

    private function resizeImageInPlace(string $path, string $mime, int $newW, int $newH): void
    {
        switch ($mime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($path); break;
            case 'image/png': $src = @imagecreatefrompng($path); break;
            case 'image/gif': $src = @imagecreatefromgif($path); break;
            case 'image/webp': if (function_exists('imagecreatefromwebp')) { $src = @imagecreatefromwebp($path); } else { return; } break;
            default: return;
        }
        if (!$src) return;
        $dst = imagecreatetruecolor($newW, $newH);
        if (in_array($mime, ['image/png','image/gif'], true)) {
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        $sw = imagesx($src); $sh = imagesy($src);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $sw, $sh);
        switch ($mime) {
            case 'image/jpeg': @imagejpeg($dst, $path, 85); break;
            case 'image/png': @imagepng($dst, $path, 6); break;
            case 'image/gif': @imagegif($dst, $path); break;
            case 'image/webp': if (function_exists('imagewebp')) { @imagewebp($dst, $path, 85); } break;
        }
        imagedestroy($src); imagedestroy($dst);
    }

    private function localUploadLog(string $event, array $ctx = []): void
    {
        try {
            $line = '[' . date('Y-m-d H:i:s') . "] settings.upload " . $event . ' ' . json_encode($ctx, JSON_UNESCAPED_SLASHES) . "\n";
            @file_put_contents(WRITEPATH . 'logs/upload-debug.log', $line, FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
