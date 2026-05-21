<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Services\Concerns\HandlesNotificationIntegrations;

/**
 * Manages Zoom Server-to-Server OAuth integration per business.
 *
 * Credentials (account_id, client_id, client_secret) are stored encrypted in
 * xs_business_integrations with channel = 'zoom'. Access tokens are fetched
 * on-demand and cached in encrypted_config.
 *
 * No Composer package required — uses CI4 CURLRequest service.
 */
class ZoomIntegrationService
{
    use HandlesNotificationIntegrations;

    public const CHANNEL = 'zoom';
    public const PROVIDER = 'server_to_server';

    private const TOKEN_URL = 'https://zoom.us/oauth/token';
    private const API_BASE  = 'https://api.zoom.us/v2';

    protected function integrationEncryptionLabel(): string
    {
        return 'Zoom';
    }

    protected function integrationDecryptContext(): string
    {
        return 'ZoomIntegrationService';
    }

    private function getRow(int $businessId): ?array
    {
        try {
            $model = new BusinessIntegrationModel();
            $row = $model
                ->where('business_id', $businessId)
                ->where('channel', self::CHANNEL)
                ->first();
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            log_message('debug', 'ZoomIntegrationService::getRow - ' . $e->getMessage());
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
                'has_credentials' => false,
            ];
        }

        $decrypted    = $this->decryptConfig($row['encrypted_config'] ?? null, true);
        $config       = $decrypted['data'] ?? [];
        $decryptError = $decrypted['error'] ?? null;

        return [
            'is_active'       => (bool) ($row['is_active'] ?? false),
            'health_status'   => (string) ($row['health_status'] ?? 'unknown'),
            'last_tested_at'  => (string) ($row['last_tested_at'] ?? ''),
            'decrypt_error'   => $decryptError,
            'has_credentials' => !empty($config['account_id']) && !empty($config['client_id']),
            'account_id_hint' => !empty($config['account_id']) ? substr($config['account_id'], 0, 6) . '...' : '',
        ];
    }

    public function saveIntegration(int $businessId, array $input): array
    {
        $accountId    = trim((string) ($input['account_id'] ?? ''));
        $clientId     = trim((string) ($input['client_id'] ?? ''));
        $clientSecret = trim((string) ($input['client_secret'] ?? ''));

        if ($accountId === '') {
            return ['ok' => false, 'error' => 'Account ID is required.'];
        }
        if ($clientId === '') {
            return ['ok' => false, 'error' => 'Client ID is required.'];
        }

        // Preserve existing client secret if omitted
        if ($clientSecret === '') {
            $existing = $this->getRow($businessId);
            if ($existing) {
                $existingConfig = $this->decryptConfig($existing['encrypted_config'] ?? null);
                $clientSecret   = (string) ($existingConfig['client_secret'] ?? '');
            }
        }
        if ($clientSecret === '') {
            return ['ok' => false, 'error' => 'Client Secret is required.'];
        }

        try {
            $encryptedConfig = $this->encryptConfig([
                'account_id'    => $accountId,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Encryption error: ' . $e->getMessage()];
        }

        $model   = new BusinessIntegrationModel();
        $existing = $this->getRow($businessId);
        $payload = [
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

    /**
     * Fetch and cache a Server-to-Server OAuth access token.
     */
    private function getAccessToken(array $config): string
    {
        $accountId    = (string) ($config['account_id'] ?? '');
        $clientId     = (string) ($config['client_id'] ?? '');
        $clientSecret = (string) ($config['client_secret'] ?? '');

        if ($accountId === '' || $clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('Zoom credentials are incomplete.');
        }

        $curl = \Config\Services::curlrequest(['timeout' => 10]);
        $response = $curl->post(self::TOKEN_URL . '?grant_type=account_credentials&account_id=' . urlencode($accountId), [
            'auth'    => [$clientId, $clientSecret],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);

        $body = json_decode($response->getBody(), true);

        if (empty($body['access_token'])) {
            throw new \RuntimeException('Zoom token fetch failed: ' . ($body['reason'] ?? 'unknown'));
        }

        return (string) $body['access_token'];
    }

    public function testConnection(int $businessId): array
    {
        $row = $this->getRow($businessId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Zoom is not configured.'];
        }

        $config = $this->decryptConfig($row['encrypted_config'] ?? null);

        try {
            $token    = $this->getAccessToken($config);
            $curl     = \Config\Services::curlrequest(['timeout' => 10]);
            $response = $curl->get(self::API_BASE . '/users/me', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            $body  = json_decode($response->getBody(), true);
            $model = new BusinessIntegrationModel();

            if (!empty($body['id'])) {
                $this->updateHealth($model, $row, 'healthy', date('Y-m-d H:i:s'));
                return ['ok' => true, 'zoom_email' => $body['email'] ?? ''];
            }

            $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            return ['ok' => false, 'error' => 'Zoom API returned unexpected response.'];
        } catch (\Throwable $e) {
            $model = new BusinessIntegrationModel();
            $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            return ['ok' => false, 'error' => 'Zoom test failed: ' . $e->getMessage()];
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
     * Create a Zoom meeting for an appointment.
     * Returns ['ok' => bool, 'join_url' => string|null, 'meeting_id' => string|null, 'error' => string|null]
     */
    public function createMeeting(int $businessId, array $appointment): array
    {
        $row = $this->getRow($businessId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Zoom not connected.'];
        }

        try {
            $config   = $this->decryptConfig($row['encrypted_config'] ?? null);
            $token    = $this->getAccessToken($config);
            $duration = (int) ($appointment['duration_minutes'] ?? 60);

            $payload = json_encode([
                'topic'      => $appointment['title'] ?? 'Appointment',
                'type'       => 2,
                'start_time' => $appointment['start_datetime'] ?? date('Y-m-d\TH:i:s'),
                'duration'   => $duration,
                'timezone'   => $appointment['timezone'] ?? 'UTC',
                'settings'   => ['waiting_room' => false, 'join_before_host' => true],
            ]);

            $curl     = \Config\Services::curlrequest(['timeout' => 10]);
            $response = $curl->post(self::API_BASE . '/users/me/meetings', [
                'body'    => $payload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            if (!empty($body['join_url'])) {
                return [
                    'ok'         => true,
                    'join_url'   => $body['join_url'],
                    'meeting_id' => (string) ($body['id'] ?? ''),
                ];
            }

            return ['ok' => false, 'error' => 'Zoom meeting creation failed: ' . ($body['message'] ?? 'unknown')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Zoom meeting creation failed: ' . $e->getMessage()];
        }
    }
}
