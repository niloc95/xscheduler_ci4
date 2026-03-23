<?php

namespace App\Services\Settings;

use App\Models\NotificationDeliveryLogModel;
use App\Models\SettingModel;
use App\Services\NotificationCatalog;
use App\Services\NotificationEmailService;
use App\Services\NotificationPolicyService;
use App\Services\NotificationSmsService;
use App\Services\NotificationWhatsAppService;

class NotificationSettingsService
{
    protected function getSettingModel(): SettingModel
    {
        return new SettingModel();
    }

    protected function getEmailService(): NotificationEmailService
    {
        return new NotificationEmailService();
    }

    protected function getSmsService(): NotificationSmsService
    {
        return new NotificationSmsService();
    }

    protected function getWhatsAppService(): NotificationWhatsAppService
    {
        return new NotificationWhatsAppService();
    }

    public function getIndexData(): array
    {
        $notificationRules = [];
        $integrationStatus = [];
        $emailIntegration = [];
        $smsIntegration = [];
        $whatsAppIntegration = [];
        $whatsAppTemplates = [];
        $deliveryLogs = [];
        $messageTemplates = [];

        try {
            $notificationPolicy = new NotificationPolicyService();
            $notificationRules = $notificationPolicy->getRules(NotificationCatalog::BUSINESS_ID_DEFAULT);
            $integrationStatus = $notificationPolicy->getIntegrationStatus(NotificationCatalog::BUSINESS_ID_DEFAULT);
            $emailIntegration = (new NotificationEmailService())->getPublicIntegration(NotificationCatalog::BUSINESS_ID_DEFAULT);
            $smsIntegration = (new NotificationSmsService())->getPublicIntegration(NotificationCatalog::BUSINESS_ID_DEFAULT);
            $whatsAppIntegration = (new NotificationWhatsAppService())->getPublicIntegration(NotificationCatalog::BUSINESS_ID_DEFAULT);

            $whatsAppService = new NotificationWhatsAppService();
            foreach (array_keys(NotificationCatalog::EVENTS) as $eventType) {
                $whatsAppTemplates[$eventType] = $whatsAppService->getActiveTemplate(NotificationCatalog::BUSINESS_ID_DEFAULT, $eventType) ?? [
                    'template_name' => '',
                    'locale' => 'en_US',
                ];
            }

            $deliveryLogs = (new NotificationDeliveryLogModel())
                ->where('business_id', NotificationCatalog::BUSINESS_ID_DEFAULT)
                ->orderBy('created_at', 'DESC')
                ->limit(50)
                ->findAll();

            $messageTemplates = $this->loadMessageTemplates();
        } catch (\Throwable $e) {
            log_message('warning', 'Settings: notification data unavailable — ' . $e->getMessage());
        }

        return [
            'notificationRules' => $notificationRules,
            'notificationIntegrationStatus' => $integrationStatus,
            'notificationEmailIntegration' => $emailIntegration,
            'notificationSmsIntegration' => $smsIntegration,
            'notificationWhatsAppIntegration' => $whatsAppIntegration,
            'notificationWhatsAppTemplates' => $whatsAppTemplates,
            'notificationEvents' => NotificationCatalog::EVENTS,
            'notificationDeliveryLogs' => $deliveryLogs,
            'notificationMessageTemplates' => $messageTemplates,
        ];
    }

    public function save(array $post, ?int $userId): array
    {
        $businessId = NotificationCatalog::BUSINESS_ID_DEFAULT;
        $intent = trim((string) ($post['intent'] ?? 'save'));
        $intent = $intent === '' ? 'save' : $intent;

        $rulesInput = $post['rules'] ?? [];

        $reminderOffsetMinutes = $post['reminder_offset_minutes'] ?? null;
        $reminderOffsetMinutes = is_numeric($reminderOffsetMinutes) ? (int) $reminderOffsetMinutes : null;
        if ($reminderOffsetMinutes !== null) {
            $reminderOffsetMinutes = max(0, min(43200, $reminderOffsetMinutes));
        }

        $defaultLanguage = trim((string) ($post['notification_default_language'] ?? ''));
        if ($defaultLanguage === '') {
            $defaultLanguage = (string) ($post['language'] ?? 'English');
        }

        $settingModel = $this->getSettingModel();
        $settingModel->upsert('notifications.default_language', $defaultLanguage, 'string', $userId);

        $saveEmail = in_array($intent, ['save', 'save_email', 'test_email'], true);
        $saveSms = in_array($intent, ['save', 'save_sms', 'test_sms'], true);
        $saveWhatsApp = in_array($intent, ['save', 'save_whatsapp', 'test_whatsapp'], true);
        $saveRules = $intent === 'save';
        $saveTemplates = in_array($intent, ['save', 'save_templates'], true);
        $strictIntegrationErrors = $intent !== 'save';

        $emailService = $this->getEmailService();
        $smsService = $this->getSmsService();
        $whatsAppService = $this->getWhatsAppService();

        if ($saveEmail) {
            $emailSave = $emailService->saveIntegration($businessId, [
                'provider_name' => $post['email_provider_name'] ?? null,
                'is_active' => !empty($post['email_is_active']),
                'host' => $post['smtp_host'] ?? null,
                'port' => $post['smtp_port'] ?? null,
                'crypto' => $post['smtp_crypto'] ?? null,
                'username' => $post['smtp_user'] ?? null,
                'password' => $post['smtp_pass'] ?? null,
                'from_email' => $post['smtp_from_email'] ?? null,
                'from_name' => $post['smtp_from_name'] ?? null,
            ]);

            if (!($emailSave['ok'] ?? false) && $strictIntegrationErrors) {
                return $this->errorResult((string) ($emailSave['error'] ?? 'Failed to save email integration settings.'));
            }

            if (!($emailSave['ok'] ?? false)) {
                log_message('debug', 'Email integration save skipped: ' . ($emailSave['error'] ?? 'unknown'));
            }

            if ($intent === 'save_email') {
                return $this->successResult('Email settings saved successfully.');
            }
        }

        if ($saveSms) {
            $smsSave = $smsService->saveIntegration($businessId, [
                'provider' => $post['sms_provider'] ?? null,
                'is_active' => !empty($post['sms_is_active']),
                'clickatell_api_key' => $post['clickatell_api_key'] ?? null,
                'clickatell_from' => $post['clickatell_from'] ?? null,
                'twilio_account_sid' => $post['twilio_account_sid'] ?? null,
                'twilio_auth_token' => $post['twilio_auth_token'] ?? null,
                'twilio_from_number' => $post['twilio_from_number'] ?? null,
            ]);

            if (!($smsSave['ok'] ?? false) && $strictIntegrationErrors) {
                return $this->errorResult((string) ($smsSave['error'] ?? 'Failed to save SMS integration settings.'));
            }

            if (!($smsSave['ok'] ?? false)) {
                log_message('debug', 'SMS integration save skipped: ' . ($smsSave['error'] ?? 'unknown'));
            }

            if ($intent === 'save_sms') {
                return $this->successResult('SMS settings saved successfully.');
            }
        }

        if ($saveWhatsApp) {
            $whatsAppProvider = trim((string) ($post['whatsapp_provider'] ?? ''));
            $whatsAppInput = [
                'provider' => $whatsAppProvider !== '' ? $whatsAppProvider : 'link_generator',
                'is_active' => !empty($post['whatsapp_is_active']),
                'twilio_whatsapp_from' => $post['twilio_whatsapp_from'] ?? null,
                'phone_number_id' => $post['whatsapp_phone_number_id'] ?? null,
                'waba_id' => $post['whatsapp_waba_id'] ?? null,
                'access_token' => $post['whatsapp_access_token'] ?? null,
            ];

            $whatsAppSave = $whatsAppService->saveIntegration($businessId, $whatsAppInput);
            if (!($whatsAppSave['ok'] ?? false) && $strictIntegrationErrors) {
                return $this->errorResult((string) ($whatsAppSave['error'] ?? 'Failed to save WhatsApp integration settings.'));
            }

            if (!($whatsAppSave['ok'] ?? false)) {
                log_message('debug', 'WhatsApp integration save skipped: ' . ($whatsAppSave['error'] ?? 'unknown'));
            }

            if (($whatsAppInput['provider'] ?? '') === 'meta_cloud') {
                foreach (array_keys(NotificationCatalog::EVENTS) as $eventType) {
                    $whatsAppService->saveTemplate(
                        $businessId,
                        $eventType,
                        isset($post['whatsapp_template_' . $eventType]) ? (string) $post['whatsapp_template_' . $eventType] : null,
                        isset($post['whatsapp_locale_' . $eventType]) ? (string) $post['whatsapp_locale_' . $eventType] : null
                    );
                }
            }

            if ($intent === 'save_whatsapp') {
                return $this->successResult('WhatsApp settings saved successfully.');
            }
        }

        if ($intent === 'test_email') {
            $result = $emailService->sendTestEmail($businessId, (string) ($post['test_email_to'] ?? ''));

            return ($result['ok'] ?? false)
                ? $this->successResult('Test email sent successfully.')
                : $this->errorResult((string) ($result['error'] ?? 'Test email failed.'));
        }

        if ($intent === 'test_sms') {
            $result = $smsService->sendTestSms($businessId, (string) ($post['test_sms_to'] ?? ''));

            return ($result['ok'] ?? false)
                ? $this->successResult('Test SMS sent successfully.')
                : $this->errorResult((string) ($result['error'] ?? 'Test SMS failed.'));
        }

        if ($intent === 'test_whatsapp') {
            $result = $whatsAppService->sendTestMessage($businessId, (string) ($post['test_whatsapp_to'] ?? ''));
            if ($result['ok'] ?? false) {
                if (($result['method'] ?? '') === 'link' && !empty($result['link'])) {
                    return $this->successResult(
                        'WhatsApp Link ready! <a href="' . esc((string) $result['link']) . '" target="_blank" class="underline font-semibold">Click here to open WhatsApp</a>',
                        true
                    );
                }

                return $this->successResult('Test WhatsApp message sent successfully.');
            }

            return $this->errorResult((string) ($result['error'] ?? 'Test WhatsApp message failed.'));
        }

        if ($saveRules) {
            $db = \Config\Database::connect();
            $db->transStart();
            $ruleModel = new \App\Models\BusinessNotificationRuleModel();

            foreach (array_keys(NotificationCatalog::EVENTS) as $eventType) {
                foreach (NotificationCatalog::CHANNELS as $channel) {
                    $enabled = isset($rulesInput[$eventType][$channel]) ? 1 : 0;
                    $offset = $eventType === 'appointment_reminder' ? $reminderOffsetMinutes : null;

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
                return $this->errorResult('Failed to save notification rules. Please try again.');
            }
        }

        if ($saveTemplates) {
            $templatesInput = $post['templates'] ?? [];
            if (!empty($templatesInput) && is_array($templatesInput)) {
                $this->saveMessageTemplates($templatesInput, $userId);
            }

            if ($intent === 'save_templates') {
                return $this->successResult('Message templates saved successfully.');
            }
        }

        return $this->successResult('Notification settings saved.');
    }

    private function loadMessageTemplates(): array
    {
        $templateSettings = $this->getSettingModel()->getByPrefix('notification_template.');
        $templates = [];

        foreach ($templateSettings as $key => $value) {
            $parts = explode('.', $key);
            if (count($parts) !== 3) {
                continue;
            }

            $eventType = $parts[1];
            $channel = $parts[2];

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $templates[$eventType][$channel] = $decoded;
                }
                continue;
            }

            if (is_array($value)) {
                $templates[$eventType][$channel] = $value;
            }
        }

        return $templates;
    }

    private function saveMessageTemplates(array $templates, ?int $userId): void
    {
        $settingModel = $this->getSettingModel();

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

                $settingModel->upsert(
                    "notification_template.{$eventType}.{$channel}",
                    [
                        'subject' => $subject,
                        'body' => $body,
                    ],
                    'json',
                    $userId
                );
            }
        }
    }

    private function successResult(string $message, bool $html = false): array
    {
        return [
            'type' => 'success',
            'message' => $message,
            'html' => $html,
        ];
    }

    private function errorResult(string $message): array
    {
        return [
            'type' => 'error',
            'message' => $message,
            'html' => false,
        ];
    }
}