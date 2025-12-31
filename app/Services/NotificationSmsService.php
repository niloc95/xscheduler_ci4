<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;

class NotificationSmsService
{
    public const CHANNEL = 'sms';

    public const PROVIDERS = ['clickatell', 'twilio'];

    /**
     * Returns a safe-to-render subset of the stored SMS integration.
     * Secrets are never returned.
     */
    /**
     * Returns a safe-to-render subset of the stored SMS integration.
     * Secrets are never returned. decrypt_error is set if key mismatch.
     */
    public function getPublicIntegration(int $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT): array
    {
        $integration = $this->getIntegrationRow($businessId);
        if (!$integration) {
            return [
                'provider' => 'clickatell',
                'is_active' => false,
                'config' => [
                    // clickatell
                    'clickatell_api_key' => '',
                    'clickatell_from' => '',
                    // twilio
                    'twilio_account_sid' => '',
                    'twilio_from_number' => '',
                ],
                'decrypt_error' => null,
            ];
        }

        $decrypted = $this->decryptConfig($integration['encrypted_config'] ?? null, true);
        $config = $decrypted['data'] ?? [];
        $decryptError = $decrypted['error'] ?? null;

        return [
            'provider' => (string) ($integration['provider_name'] ?? 'clickatell'),
            'is_active' => (bool) ($integration['is_active'] ?? false),
            'config' => [
                'clickatell_api_key' => (string) ($config['clickatell_api_key'] ?? ''),
                'clickatell_from' => (string) ($config['clickatell_from'] ?? ''),
                'twilio_account_sid' => (string) ($config['twilio_account_sid'] ?? ''),
                'twilio_from_number' => (string) ($config['twilio_from_number'] ?? ''),
            ],
            'decrypt_error' => $decryptError,
        ];
    }

    /**
     * Save SMS provider configuration into xs_business_integrations (encrypted_config).
     * If Twilio auth token is omitted, it is preserved from previous config if present.
     */
    public function saveIntegration(int $businessId, array $input): array
    {
        $provider = trim((string) ($input['provider'] ?? 'clickatell'));
        if (!in_array($provider, self::PROVIDERS, true)) {
            $provider = 'clickatell';
        }

        $isActive = (bool) ($input['is_active'] ?? false);

        $clickatellApiKey = trim((string) ($input['clickatell_api_key'] ?? ''));
        $clickatellFrom = trim((string) ($input['clickatell_from'] ?? ''));

        $twilioSid = trim((string) ($input['twilio_account_sid'] ?? ''));
        $twilioToken = (string) ($input['twilio_auth_token'] ?? '');
        $twilioFrom = trim((string) ($input['twilio_from_number'] ?? ''));

        $hasAny = ($provider !== '' || $clickatellApiKey !== '' || $clickatellFrom !== '' || $twilioSid !== '' || $twilioToken !== '' || $twilioFrom !== '' || $isActive);
        if (!$hasAny) {
            return ['ok' => true, 'cleared' => false];
        }

        // Validate based on provider
        if ($provider === 'clickatell') {
            if ($clickatellApiKey === '') {
                return ['ok' => false, 'error' => 'Clickatell API Key is required.'];
            }
            if ($clickatellFrom !== '' && !$this->isValidSender($clickatellFrom)) {
                return ['ok' => false, 'error' => 'Clickatell From/Sender ID must be a valid phone number (+E.164) or 3–11 alphanumeric characters.'];
            }
        }

        if ($provider === 'twilio') {
            if ($twilioSid === '') {
                return ['ok' => false, 'error' => 'Twilio Account SID is required.'];
            }
            if ($twilioFrom === '' || !$this->isValidE164($twilioFrom)) {
                return ['ok' => false, 'error' => 'Twilio From Number must be a valid +E.164 phone number.'];
            }

            if ($twilioToken === '') {
                // Preserve existing token
                $existing = $this->getIntegrationRow($businessId);
                $existingConfig = $this->decryptConfig($existing['encrypted_config'] ?? null);
                if (!empty($existingConfig['twilio_auth_token'])) {
                    $twilioToken = (string) $existingConfig['twilio_auth_token'];
                } else {
                    return ['ok' => false, 'error' => 'Twilio Auth Token is required.'];
                }
            }
        }

        $configToStore = [
            'provider' => $provider,
            'clickatell_api_key' => $clickatellApiKey,
            'clickatell_from' => $clickatellFrom,
            'twilio_account_sid' => $twilioSid,
            'twilio_auth_token' => $twilioToken,
            'twilio_from_number' => $twilioFrom,
        ];

        try {
            $encryptedConfig = $this->encryptConfig($configToStore);
        } catch (\Throwable $e) {
            log_message('error', 'NotificationSmsService: encrypt failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Encryption is not configured correctly. Please set an application encryption key.'];
        }

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
     * Sends a test SMS and updates integration health status.
     */
    public function sendTestSms(int $businessId, string $toPhone): array
    {
        $toPhone = trim($toPhone);
        if (!$this->isValidE164($toPhone)) {
            return ['ok' => false, 'error' => 'Please provide a valid test recipient phone number in +E.164 format.'];
        }

        $integration = $this->getIntegrationRow($businessId);
        if (!$integration || empty($integration['encrypted_config'])) {
            return ['ok' => false, 'error' => 'SMS integration is not configured yet.'];
        }

        $now = date('Y-m-d H:i:s');
        $model = new BusinessIntegrationModel();

        try {
            $send = $this->sendSms($businessId, $toPhone, 'WebSchedulr SMS Test: your SMS integration is working.');
            if ($send['ok'] ?? false) {
                $this->updateHealth($model, $integration, 'healthy', $now);
                return ['ok' => true];
            }

            $this->updateHealth($model, $integration, 'unhealthy', $now);
            return ['ok' => false, 'error' => (string) ($send['error'] ?? 'SMS test failed. Please verify your provider credentials.')];
        } catch (\Throwable $e) {
            log_message('error', 'SMS test exception: {msg}', ['msg' => $e->getMessage()]);
            $this->updateHealth($model, $integration, 'unhealthy', $now);
            return ['ok' => false, 'error' => 'SMS test failed due to a server error.'];
        }
    }

    /**
     * Send an SMS using the stored SMS integration.
     * Returns ['ok' => bool, 'error' => string?]
     */
    public function sendSms(int $businessId, string $toPhone, string $message): array
    {
        $toPhone = trim($toPhone);
        $message = trim($message);
        if (!$this->isValidE164($toPhone)) {
            return ['ok' => false, 'error' => 'Invalid recipient phone number.'];
        }
        if ($message === '') {
            return ['ok' => false, 'error' => 'Message cannot be empty.'];
        }

        $integration = $this->getIntegrationRow($businessId);
        if (!$integration || empty($integration['encrypted_config'])) {
            return ['ok' => false, 'error' => 'SMS integration is not configured.'];
        }
        if (empty($integration['is_active'])) {
            return ['ok' => false, 'error' => 'SMS integration is not active.'];
        }

        $config = $this->decryptConfig($integration['encrypted_config']);
        $provider = (string) ($config['provider'] ?? ($integration['provider_name'] ?? 'clickatell'));
        if (!in_array($provider, self::PROVIDERS, true)) {
            $provider = 'clickatell';
        }

        if ($provider === 'twilio') {
            return $this->sendTwilio($config, $toPhone, $message);
        }

        return $this->sendClickatell($config, $toPhone, $message);
    }

    private function sendClickatell(array $config, string $toPhone, string $message): array
    {
        $apiKey = (string) ($config['clickatell_api_key'] ?? '');
        $from = (string) ($config['clickatell_from'] ?? '');
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'Clickatell API Key missing.'];
        }
        if ($from !== '' && !$this->isValidSender($from)) {
            return ['ok' => false, 'error' => 'Invalid Clickatell sender ID/from.'];
        }

        $payload = [
            'messages' => [
                [
                    'to' => [$toPhone],
                    'content' => $message,
                ],
            ],
        ];
        if ($from !== '') {
            $payload['messages'][0]['from'] = $from;
        }

        $ch = curl_init('https://platform.clickatell.com/v1/message');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $apiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $resp = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo) {
            log_message('error', 'Clickatell SMS curl error: {err}', ['err' => $err]);
            return ['ok' => false, 'error' => 'Clickatell request failed.'];
        }

        if ($http < 200 || $http >= 300) {
            log_message('error', 'Clickatell SMS HTTP {code}: {resp}', ['code' => $http, 'resp' => (string) $resp]);
            return ['ok' => false, 'error' => 'Clickatell request was rejected.'];
        }

        return ['ok' => true];
    }

    private function sendTwilio(array $config, string $toPhone, string $message): array
    {
        $sid = (string) ($config['twilio_account_sid'] ?? '');
        $token = (string) ($config['twilio_auth_token'] ?? '');
        $from = (string) ($config['twilio_from_number'] ?? '');

        if ($sid === '' || $token === '') {
            return ['ok' => false, 'error' => 'Twilio credentials missing.'];
        }
        if ($from === '' || !$this->isValidE164($from)) {
            return ['ok' => false, 'error' => 'Twilio From Number missing/invalid.'];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $post = http_build_query([
            'To' => $toPhone,
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
            log_message('error', 'Twilio SMS curl error: {err}', ['err' => $err]);
            return ['ok' => false, 'error' => 'Twilio request failed.'];
        }

        if ($http < 200 || $http >= 300) {
            log_message('error', 'Twilio SMS HTTP {code}: {resp}', ['code' => $http, 'resp' => (string) $resp]);
            return ['ok' => false, 'error' => 'Twilio request was rejected.'];
        }

        return ['ok' => true];
    }

    /**
     * Get full config including secrets (for internal use by WhatsApp Twilio provider)
     * WARNING: Contains sensitive data - never expose to frontend
     */
    public function getFullConfig(int $businessId): array
    {
        $integration = $this->getIntegrationRow($businessId);
        if (!$integration || empty($integration['encrypted_config'])) {
            return [];
        }
        return $this->decryptConfig($integration['encrypted_config']);
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
            throw new \RuntimeException('Failed to encode SMS config');
        }
        // Base64 encode the binary encrypted data for safe storage in TEXT column
        return base64_encode((string) $encrypter->encrypt($json));
    }

    /**
     * Decrypt config, returning ['data' => array, 'error' => string|null].
     */
    private function decryptConfig($encrypted, bool $returnError = false): array
    {
        if (!is_string($encrypted) || trim($encrypted) === '') {
            return $returnError ? ['data' => [], 'error' => null] : [];
        }

        try {
            $encrypter = service('encrypter');
            // Base64 decode before decrypting
            $decoded = base64_decode($encrypted, true);
            if ($decoded === false) {
                throw new \RuntimeException('Failed to base64 decode encrypted config');
            }
            $json = $encrypter->decrypt($decoded);
            $data = json_decode((string) $json, true);
            $result = is_array($data) ? $data : [];
            return $returnError ? ['data' => $result, 'error' => null] : $result;
        } catch (\Throwable $e) {
            log_message('error', 'NotificationSmsService: decrypt failed: {msg}', ['msg' => $e->getMessage()]);
            $errMsg = 'encryption_key_mismatch';
            if (stripos($e->getMessage(), 'authentication failed') !== false) {
                $errMsg = 'encryption_key_mismatch';
            }
            return $returnError ? ['data' => [], 'error' => $errMsg] : [];
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

    private function isValidSender(string $sender): bool
    {
        $sender = trim($sender);
        if ($sender === '') {
            return false;
        }
        if ($this->isValidE164($sender)) {
            return true;
        }
        return (bool) preg_match('/^[A-Za-z0-9]{3,11}$/', $sender);
    }
}
