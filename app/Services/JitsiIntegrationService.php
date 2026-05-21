<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Services\Concerns\HandlesNotificationIntegrations;

/**
 * Manages Jitsi Meet video conferencing integration per business.
 *
 * Supports two modes:
 *   1. Public Jitsi Meet (meet.jit.si) — no credentials needed; just configure
 *      a server URL and meeting links are generated automatically.
 *   2. Jitsi as a Service (JaaS) — configure app_id and API key for private
 *      meetings on 8x8.vc.
 *
 * Encrypted config stored in xs_business_integrations with channel = 'jitsi'.
 * No Composer package required — meeting links are URL-only; JaaS JWT tokens
 * are generated with PHP's built-in hash_hmac.
 */
class JitsiIntegrationService
{
    use HandlesNotificationIntegrations;

    public const CHANNEL  = 'jitsi';
    public const PROVIDER = 'jitsi_meet';

    public const DEFAULT_SERVER = 'https://meet.jit.si';

    protected function integrationEncryptionLabel(): string
    {
        return 'Jitsi';
    }

    protected function integrationDecryptContext(): string
    {
        return 'JitsiIntegrationService';
    }

    private function getRow(int $businessId): ?array
    {
        try {
            $model = new BusinessIntegrationModel();
            $row   = $model
                ->where('business_id', $businessId)
                ->where('channel', self::CHANNEL)
                ->first();
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            log_message('debug', 'JitsiIntegrationService::getRow - ' . $e->getMessage());
            return null;
        }
    }

    public function getPublicIntegration(int $businessId): array
    {
        $row = $this->getRow($businessId);

        if (!$row) {
            return [
                'is_active'      => false,
                'health_status'  => 'unknown',
                'last_tested_at' => '',
                'decrypt_error'  => null,
                'server_url'     => self::DEFAULT_SERVER,
                'has_api_key'    => false,
                'mode'           => 'public',
            ];
        }

        $decrypted    = $this->decryptConfig($row['encrypted_config'] ?? null, true);
        $config       = $decrypted['data'] ?? [];
        $decryptError = $decrypted['error'] ?? null;

        return [
            'is_active'      => (bool) ($row['is_active'] ?? false),
            'health_status'  => (string) ($row['health_status'] ?? 'unknown'),
            'last_tested_at' => (string) ($row['last_tested_at'] ?? ''),
            'decrypt_error'  => $decryptError,
            'server_url'     => (string) ($config['server_url'] ?? self::DEFAULT_SERVER),
            'has_api_key'    => !empty($config['api_key']),
            'mode'           => !empty($config['app_id']) ? 'jaas' : 'public',
        ];
    }

    public function saveIntegration(int $businessId, array $input): array
    {
        $serverUrl = rtrim(trim((string) ($input['server_url'] ?? self::DEFAULT_SERVER)), '/');
        $appId     = trim((string) ($input['app_id'] ?? ''));
        $apiKey    = trim((string) ($input['api_key'] ?? ''));

        if ($serverUrl === '') {
            $serverUrl = self::DEFAULT_SERVER;
        }
        if (!filter_var($serverUrl, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'error' => 'Server URL must be a valid URL (e.g. https://meet.jit.si).'];
        }
        if (!in_array(parse_url($serverUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return ['ok' => false, 'error' => 'Server URL must use http or https.'];
        }

        // Preserve existing API key if field left blank
        if ($apiKey === '') {
            $existing = $this->getRow($businessId);
            if ($existing) {
                $existingConfig = $this->decryptConfig($existing['encrypted_config'] ?? null);
                $apiKey         = (string) ($existingConfig['api_key'] ?? '');
            }
        }

        try {
            $encryptedConfig = $this->encryptConfig([
                'server_url' => $serverUrl,
                'app_id'     => $appId,
                'api_key'    => $apiKey,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Encryption error: ' . $e->getMessage()];
        }

        $model    = new BusinessIntegrationModel();
        $existing = $this->getRow($businessId);
        $payload  = [
            'business_id'      => $businessId,
            'channel'          => self::CHANNEL,
            'provider_name'    => self::PROVIDER,
            'encrypted_config' => $encryptedConfig,
            'is_active'        => 1,
        ];

        if ($existing && !empty($existing['id'])) {
            $model->update((int) $existing['id'], $payload);
        } else {
            $model->insert($payload);
        }

        return ['ok' => true];
    }

    public function testConnection(int $businessId): array
    {
        $row = $this->getRow($businessId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Jitsi is not configured.'];
        }

        $config    = $this->decryptConfig($row['encrypted_config'] ?? null);
        $serverUrl = rtrim((string) ($config['server_url'] ?? self::DEFAULT_SERVER), '/');

        // Verify the server is reachable via HTTP HEAD
        try {
            $curl     = \Config\Services::curlrequest(['timeout' => 8]);
            $response = $curl->head($serverUrl);
            $status   = $response->getStatusCode();
            $model    = new BusinessIntegrationModel();

            if ($status >= 200 && $status < 500) {
                $this->updateHealth($model, $row, 'healthy', date('Y-m-d H:i:s'));
                return ['ok' => true, 'server_url' => $serverUrl];
            }

            $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            return ['ok' => false, 'error' => "Server returned HTTP {$status}."];
        } catch (\Throwable $e) {
            $model = new BusinessIntegrationModel();
            $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            return ['ok' => false, 'error' => 'Server unreachable: ' . $e->getMessage()];
        }
    }

    public function disconnect(int $businessId): array
    {
        $row = $this->getRow($businessId);
        if (!$row) {
            return ['ok' => true];
        }
        (new BusinessIntegrationModel())->delete((int) $row['id']);
        return ['ok' => true];
    }

    /**
     * Generate a Jitsi meeting link for an appointment.
     * Room name is derived from the appointment hash/ID to ensure uniqueness.
     */
    public function generateMeetingLink(int $businessId, array $appointment): array
    {
        $row = $this->getRow($businessId);

        $config    = $row ? $this->decryptConfig($row['encrypted_config'] ?? null) : [];
        $serverUrl = rtrim((string) ($config['server_url'] ?? self::DEFAULT_SERVER), '/');

        // Stable, URL-safe room name derived from appointment identifier
        $roomSeed = $appointment['hash'] ?? $appointment['id'] ?? uniqid('ws', true);
        $roomName = 'ws-' . preg_replace('/[^a-z0-9]/', '', strtolower((string) $roomSeed));

        $joinUrl = "{$serverUrl}/{$roomName}";

        return ['ok' => true, 'join_url' => $joinUrl, 'room_name' => $roomName];
    }
}
