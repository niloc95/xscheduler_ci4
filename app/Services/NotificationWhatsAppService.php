<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Models\MessageTemplateModel;

class NotificationWhatsAppService
{
    public const CHANNEL = 'whatsapp';

    public const PROVIDER = 'meta_cloud';

    /**
     * Returns a safe-to-render subset of the stored WhatsApp integration.
     * Secrets are never returned.
     */
    public function getPublicIntegration(int $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT): array
    {
        $integration = $this->getIntegrationRow($businessId);
        if (!$integration) {
            return [
                'provider_name' => self::PROVIDER,
                'is_active' => false,
                'config' => [
                    'phone_number_id' => '',
                    'waba_id' => '',
                ],
            ];
        }

        $config = $this->decryptConfig($integration['encrypted_config'] ?? null);

        return [
            'provider_name' => (string) ($integration['provider_name'] ?? self::PROVIDER),
            'is_active' => (bool) ($integration['is_active'] ?? false),
            'config' => [
                'phone_number_id' => (string) ($config['phone_number_id'] ?? ''),
                'waba_id' => (string) ($config['waba_id'] ?? ''),
            ],
        ];
    }

    /**
     * Save WhatsApp Meta Cloud configuration into xs_business_integrations (encrypted_config).
     * If access_token is omitted, it is preserved from previous config if present.
     */
    public function saveIntegration(int $businessId, array $input): array
    {
        $isActive = (bool) ($input['is_active'] ?? false);

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
            'provider' => self::PROVIDER,
            'phone_number_id' => $phoneNumberId,
            'waba_id' => $wabaId,
            'access_token' => $accessToken,
        ];

        try {
            $encryptedConfig = $this->encryptConfig($configToStore);
        } catch (\Throwable $e) {
            log_message('error', 'NotificationWhatsAppService: encrypt failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Encryption is not configured correctly. Please set an application encryption key.'];
        }

        $model = new BusinessIntegrationModel();
        $existing = $this->getIntegrationRow($businessId);

        $payload = [
            'business_id' => $businessId,
            'channel' => self::CHANNEL,
            'provider_name' => self::PROVIDER,
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

        // Allow clearing
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
            'provider' => self::PROVIDER,
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
        $model = new MessageTemplateModel();
        $row = $model
            ->where('business_id', $businessId)
            ->where('event_type', $eventType)
            ->where('channel', self::CHANNEL)
            ->where('is_active', 1)
            ->first();

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
     * Template-only enforcement: only sends WhatsApp template messages.
     *
     * @param array<int, string> $bodyParameters text params in template order
     */
    public function sendTemplateMessage(int $businessId, string $toPhone, string $eventType, array $bodyParameters = []): array
    {
        $toPhone = trim($toPhone);
        if (!$this->isValidE164($toPhone)) {
            return ['ok' => false, 'error' => 'Invalid recipient phone number (+E.164).'];
        }

        $integration = $this->getIntegrationRow($businessId);
        if (!$integration || empty($integration['encrypted_config'])) {
            return ['ok' => false, 'error' => 'WhatsApp integration is not configured.'];
        }
        if (empty($integration['is_active'])) {
            return ['ok' => false, 'error' => 'WhatsApp integration is not active.'];
        }

        $config = $this->decryptConfig($integration['encrypted_config']);
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

        return ['ok' => true];
    }

    /**
     * Sends a test WhatsApp message using the appointment_confirmed template.
     */
    public function sendTestMessage(int $businessId, string $toPhone): array
    {
        $toPhone = trim($toPhone);
        if (!$this->isValidE164($toPhone)) {
            return ['ok' => false, 'error' => 'Please provide a valid test recipient phone number in +E.164 format.'];
        }

        $integration = $this->getIntegrationRow($businessId);
        if (!$integration || empty($integration['encrypted_config'])) {
            return ['ok' => false, 'error' => 'WhatsApp integration is not configured yet.'];
        }

        $now = date('Y-m-d H:i:s');
        $model = new BusinessIntegrationModel();

        try {
            $send = $this->sendTemplateMessage($businessId, $toPhone, 'appointment_confirmed', ['Test', 'Service', 'Provider', $now]);
            if ($send['ok'] ?? false) {
                $this->updateHealth($model, $integration, 'healthy', $now);
                return ['ok' => true];
            }

            $this->updateHealth($model, $integration, 'unhealthy', $now);
            return ['ok' => false, 'error' => (string) ($send['error'] ?? 'WhatsApp test failed.')];
        } catch (\Throwable $e) {
            log_message('error', 'WhatsApp test exception: {msg}', ['msg' => $e->getMessage()]);
            $this->updateHealth($model, $integration, 'unhealthy', $now);
            return ['ok' => false, 'error' => 'WhatsApp test failed due to a server error.'];
        }
    }

    private function getIntegrationRow(int $businessId): ?array
    {
        $model = new BusinessIntegrationModel();
        $row = $model
            ->where('business_id', $businessId)
            ->where('channel', self::CHANNEL)
            ->first();

        return is_array($row) ? $row : null;
    }

    private function encryptConfig(array $config): string
    {
        $encrypter = service('encrypter');
        $json = json_encode($config);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode WhatsApp config');
        }
        return (string) $encrypter->encrypt($json);
    }

    private function decryptConfig($encrypted): array
    {
        if (!is_string($encrypted) || trim($encrypted) === '') {
            return [];
        }

        try {
            $encrypter = service('encrypter');
            $json = $encrypter->decrypt($encrypted);
            $decoded = json_decode((string) $json, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            log_message('error', 'NotificationWhatsAppService: decrypt failed: {msg}', ['msg' => $e->getMessage()]);
            return [];
        }
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
