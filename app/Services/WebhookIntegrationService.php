<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Services\Concerns\HandlesNotificationIntegrations;

class WebhookIntegrationService
{
    use HandlesNotificationIntegrations;

    public const CHANNEL = 'webhook';
    public const PROVIDER = 'custom';

    public const EVENTS = [
        'appointment.created'   => 'Appointment Created',
        'appointment.updated'   => 'Appointment Updated',
        'appointment.cancelled' => 'Appointment Cancelled',
        'appointment.completed' => 'Appointment Completed',
        'appointment.no_show'   => 'Appointment No-Show',
    ];

    protected function integrationEncryptionLabel(): string
    {
        return 'Webhook';
    }

    protected function integrationDecryptContext(): string
    {
        return 'WebhookIntegrationService';
    }

    private function getRow(int $businessId): ?array
    {
        try {
            $model = new BusinessIntegrationModel();
            $row = $model
                ->where('business_id', $businessId)
                ->where('channel', self::CHANNEL)
                ->where('provider_name', self::PROVIDER)
                ->first();
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            log_message('debug', 'WebhookIntegrationService::getRow - ' . $e->getMessage());
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
                'config'         => ['url' => '', 'events' => []],
            ];
        }

        $decrypted   = $this->decryptConfig($row['encrypted_config'] ?? null, true);
        $config      = $decrypted['data'] ?? [];
        $decryptError = $decrypted['error'] ?? null;

        return [
            'is_active'      => (bool) ($row['is_active'] ?? false),
            'health_status'  => (string) ($row['health_status'] ?? 'unknown'),
            'last_tested_at' => (string) ($row['last_tested_at'] ?? ''),
            'decrypt_error'  => $decryptError,
            'config'         => [
                'url'    => (string) ($config['url'] ?? ''),
                'events' => (array) ($config['events'] ?? []),
            ],
        ];
    }

    public function saveIntegration(int $businessId, array $input): array
    {
        $url    = trim((string) ($input['url'] ?? ''));
        $secret = trim((string) ($input['secret'] ?? ''));
        $events = array_values(array_filter((array) ($input['events'] ?? []), static fn($e) => isset(self::EVENTS[$e])));

        if ($url === '') {
            return ['ok' => false, 'error' => 'Endpoint URL is required.'];
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return ['ok' => false, 'error' => 'Endpoint URL must be a valid http or https URL.'];
        }

        $existing = $this->getRow($businessId);
        // Preserve existing secret if omitted
        if ($secret === '' && $existing) {
            $existingConfig = $this->decryptConfig($existing['encrypted_config'] ?? null);
            $secret = (string) ($existingConfig['secret'] ?? '');
        }

        try {
            $encryptedConfig = $this->encryptConfig([
                'url'    => $url,
                'secret' => $secret,
                'events' => $events ?: array_keys(self::EVENTS),
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Encryption error: ' . $e->getMessage()];
        }

        $model   = new BusinessIntegrationModel();
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

    public function testConnection(int $businessId): array
    {
        $row = $this->getRow($businessId);
        if (!$row) {
            return ['ok' => false, 'error' => 'No webhook configured.'];
        }

        $config = $this->decryptConfig($row['encrypted_config'] ?? null);
        $url    = trim((string) ($config['url'] ?? ''));
        $secret = trim((string) ($config['secret'] ?? ''));

        if ($url === '') {
            return ['ok' => false, 'error' => 'Webhook URL is not configured.'];
        }

        $payload = json_encode([
            'event'       => 'test',
            'timestamp'   => date('c'),
            'business_id' => $businessId,
        ]);

        $signature = $secret !== '' ? hash_hmac('sha256', $payload, $secret) : '';

        try {
            $curl = \Config\Services::curlrequest(['timeout' => 10]);
            $response = $curl->post($url, [
                'body'    => $payload,
                'headers' => array_filter([
                    'Content-Type'       => 'application/json',
                    'X-Webhook-Event'    => 'test',
                    'X-Webhook-Signature' => $signature ?: null,
                ]),
            ]);

            $statusCode = $response->getStatusCode();
            $model = new BusinessIntegrationModel();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->updateHealth($model, $row, 'healthy', date('Y-m-d H:i:s'));
                return ['ok' => true, 'status_code' => $statusCode];
            }

            $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            return ['ok' => false, 'error' => "Endpoint returned HTTP {$statusCode}."];
        } catch (\Throwable $e) {
            $model = new BusinessIntegrationModel();
            $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            return ['ok' => false, 'error' => 'Connection failed: ' . $e->getMessage()];
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
     * Fire-and-forget dispatch to all active webhook endpoints for this business.
     * Runs synchronously but ignores failures — must not block the HTTP request path.
     */
    public function dispatch(int $businessId, string $event, array $payload): void
    {
        $row = $this->getRow($businessId);
        if (!$row || !(bool) ($row['is_active'] ?? false)) {
            return;
        }

        $config = $this->decryptConfig($row['encrypted_config'] ?? null);
        $url    = trim((string) ($config['url'] ?? ''));
        $secret = trim((string) ($config['secret'] ?? ''));
        $events = (array) ($config['events'] ?? array_keys(self::EVENTS));

        if ($url === '' || !in_array($event, $events, true)) {
            return;
        }

        $body      = json_encode(array_merge(['event' => $event, 'timestamp' => date('c'), 'business_id' => $businessId], $payload));
        $signature = $secret !== '' ? hash_hmac('sha256', $body, $secret) : '';

        try {
            $curl = \Config\Services::curlrequest(['timeout' => 5]);
            $curl->post($url, [
                'body'    => $body,
                'headers' => array_filter([
                    'Content-Type'        => 'application/json',
                    'X-Webhook-Event'     => $event,
                    'X-Webhook-Signature' => $signature ?: null,
                ]),
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'WebhookIntegrationService::dispatch failed for event {event}: {msg}', [
                'event' => $event,
                'msg'   => $e->getMessage(),
            ]);
        }
    }
}
