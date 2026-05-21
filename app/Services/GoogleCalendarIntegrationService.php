<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Services\Concerns\HandlesNotificationIntegrations;

/**
 * Manages Google Calendar OAuth 2.0 integration per business.
 *
 * OAuth app credentials (client_id, client_secret) are stored encrypted in
 * xs_business_integrations alongside the per-business access tokens. Admins
 * configure credentials via the Settings > Integrations UI — no .env access
 * required. Environment variables GOOGLE_CALENDAR_CLIENT_ID/CLIENT_SECRET are
 * honoured as a fallback for existing deployments.
 *
 * Encrypted config layout:
 *   client_id      — Google OAuth app Client ID (admin-configured)
 *   client_secret  — Google OAuth app Client Secret (admin-configured)
 *   access_token   — Per-business OAuth access token (set after OAuth consent)
 *   refresh_token  — Per-business OAuth refresh token
 *   token_expiry   — Unix timestamp for access token expiry
 *   calendar_id    — Target calendar (default: 'primary')
 *
 * Requires composer package: google/apiclient:^2.0
 */
class GoogleCalendarIntegrationService
{
    use HandlesNotificationIntegrations;

    public const CHANNEL  = 'google_calendar';
    public const PROVIDER = 'oauth2';

    protected function integrationEncryptionLabel(): string
    {
        return 'GoogleCalendar';
    }

    protected function integrationDecryptContext(): string
    {
        return 'GoogleCalendarIntegrationService';
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
            log_message('debug', 'GoogleCalendarIntegrationService::getRow - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * The redirect URI that must be registered in Google Cloud Console.
     * Auto-generated from the application base URL so admins can copy it.
     */
    public static function getRedirectUri(): string
    {
        return base_url('oauth/google/callback');
    }

    /**
     * Build a Google_Client using credentials from the DB row (primary)
     * or environment variables (fallback for existing deployments).
     */
    private function buildClient(int $businessId): \Google_Client
    {
        $row    = $this->getRow($businessId);
        $config = $row ? $this->decryptConfig($row['encrypted_config'] ?? null) : [];

        $clientId     = (string) ($config['client_id']     ?? getenv('GOOGLE_CALENDAR_CLIENT_ID')     ?? '');
        $clientSecret = (string) ($config['client_secret'] ?? getenv('GOOGLE_CALENDAR_CLIENT_SECRET') ?? '');
        $redirectUri  = self::getRedirectUri();

        $client = new \Google_Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->addScope(\Google_Service_Calendar::CALENDAR_EVENTS);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        return $client;
    }

    /**
     * Returns true if OAuth app credentials are available (DB or env fallback).
     */
    public function isConfigured(int $businessId): bool
    {
        $row = $this->getRow($businessId);
        if ($row) {
            $config = $this->decryptConfig($row['encrypted_config'] ?? null);
            if (!empty($config['client_id']) && !empty($config['client_secret'])) {
                return true;
            }
        }
        // Env-var fallback — supports existing deployments
        return getenv('GOOGLE_CALENDAR_CLIENT_ID') !== false
            && getenv('GOOGLE_CALENDAR_CLIENT_ID') !== ''
            && getenv('GOOGLE_CALENDAR_CLIENT_SECRET') !== false
            && getenv('GOOGLE_CALENDAR_CLIENT_SECRET') !== '';
    }

    /**
     * Save Google OAuth app credentials (client_id + client_secret).
     * Merges with any existing config so previously-saved tokens are preserved.
     */
    public function saveAppCredentials(int $businessId, array $input): array
    {
        $clientId     = trim((string) ($input['client_id']     ?? ''));
        $clientSecret = trim((string) ($input['client_secret'] ?? ''));

        if ($clientId === '') {
            return ['ok' => false, 'error' => 'Client ID is required.'];
        }
        if ($clientSecret === '') {
            return ['ok' => false, 'error' => 'Client Secret is required.'];
        }

        // Preserve existing secret when the field is intentionally left blank on re-save
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

        // Merge into existing config — preserve access/refresh tokens if already connected
        $existing       = $this->getRow($businessId);
        $existingConfig = $existing ? $this->decryptConfig($existing['encrypted_config'] ?? null) : [];

        $newConfig = array_merge($existingConfig, [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ]);

        return $this->persistConfig($businessId, $newConfig, (bool) ($existing['is_active'] ?? false));
    }

    public function getAuthUrl(int $businessId): string
    {
        $client = $this->buildClient($businessId);
        $client->setState(base64_encode(json_encode(['business_id' => $businessId])));
        return $client->createAuthUrl();
    }

    public function handleCallback(int $businessId, string $code): array
    {
        try {
            $client = $this->buildClient($businessId);
            $token  = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                return ['ok' => false, 'error' => 'Google OAuth error: ' . ($token['error_description'] ?? $token['error'])];
            }

            // Merge tokens into existing config — preserves client_id/client_secret
            $existing       = $this->getRow($businessId);
            $existingConfig = $existing ? $this->decryptConfig($existing['encrypted_config'] ?? null) : [];

            $newConfig = array_merge($existingConfig, [
                'access_token'  => $token['access_token'] ?? '',
                'refresh_token' => $token['refresh_token'] ?? ($existingConfig['refresh_token'] ?? ''),
                'token_expiry'  => time() + (int) ($token['expires_in'] ?? 3600),
                'calendar_id'   => $existingConfig['calendar_id'] ?? 'primary',
            ]);

            return $this->persistConfig($businessId, $newConfig, true);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'OAuth callback failed: ' . $e->getMessage()];
        }
    }

    private function persistConfig(int $businessId, array $config, bool $isActive): array
    {
        try {
            $encryptedConfig = $this->encryptConfig($config);
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
            'is_active'        => $isActive ? 1 : 0,
        ];

        if ($existing && !empty($existing['id'])) {
            $model->update((int) $existing['id'], $payload);
        } else {
            $model->insert($payload);
        }

        return ['ok' => true];
    }

    public function getPublicIntegration(int $businessId): array
    {
        $row = $this->getRow($businessId);

        if (!$row) {
            return [
                'is_active'       => false,
                'health_status'   => 'unknown',
                'last_tested_at'  => '',
                'decrypt_error'   => null,
                'has_credentials' => false,
                'client_id_hint'  => '',
                'has_tokens'      => false,
                'calendar_id'     => '',
            ];
        }

        $decrypted    = $this->decryptConfig($row['encrypted_config'] ?? null, true);
        $config       = $decrypted['data'] ?? [];
        $decryptError = $decrypted['error'] ?? null;

        $clientId = (string) ($config['client_id'] ?? '');

        return [
            'is_active'       => (bool) ($row['is_active'] ?? false),
            'health_status'   => (string) ($row['health_status'] ?? 'unknown'),
            'last_tested_at'  => (string) ($row['last_tested_at'] ?? ''),
            'decrypt_error'   => $decryptError,
            'has_credentials' => $clientId !== '',
            'client_id_hint'  => $clientId !== '' ? substr($clientId, 0, 12) . '...' : '',
            'has_tokens'      => !empty($config['access_token']),
            'calendar_id'     => (string) ($config['calendar_id'] ?? 'primary'),
        ];
    }

    private function getRefreshedClient(int $businessId): ?\Google_Client
    {
        $row = $this->getRow($businessId);
        if (!$row) {
            return null;
        }

        $config = $this->decryptConfig($row['encrypted_config'] ?? null);
        if (empty($config['access_token'])) {
            return null;
        }

        $client = $this->buildClient($businessId);
        $client->setAccessToken([
            'access_token'  => $config['access_token'],
            'refresh_token' => $config['refresh_token'] ?? '',
            'expires_in'    => max(0, (int) ($config['token_expiry'] ?? 0) - time()),
        ]);

        if ($client->isAccessTokenExpired() && !empty($config['refresh_token'])) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($config['refresh_token']);
            if (!isset($newToken['error'])) {
                $existing       = $this->getRow($businessId);
                $existingConfig = $existing ? $this->decryptConfig($existing['encrypted_config'] ?? null) : $config;
                $existingConfig['access_token'] = $newToken['access_token'];
                $existingConfig['token_expiry']  = time() + (int) ($newToken['expires_in'] ?? 3600);
                $this->persistConfig($businessId, $existingConfig, true);
                $client->setAccessToken($newToken);
            }
        }

        return $client;
    }

    public function testConnection(int $businessId): array
    {
        try {
            $client = $this->getRefreshedClient($businessId);
            if (!$client) {
                return ['ok' => false, 'error' => 'Google Calendar is not connected. Complete the OAuth flow first.'];
            }

            $service  = new \Google_Service_Calendar($client);
            $calendar = $service->calendars->get('primary');
            $model    = new BusinessIntegrationModel();
            $row      = $this->getRow($businessId);

            $this->updateHealth($model, $row, 'healthy', date('Y-m-d H:i:s'));
            return ['ok' => true, 'calendar_summary' => $calendar->getSummary()];
        } catch (\Throwable $e) {
            $model = new BusinessIntegrationModel();
            $row   = $this->getRow($businessId);
            if ($row) {
                $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            }
            return ['ok' => false, 'error' => 'Calendar test failed: ' . $e->getMessage()];
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

    public function createEvent(int $businessId, array $appointment): array
    {
        try {
            $client = $this->getRefreshedClient($businessId);
            if (!$client) {
                return ['ok' => false, 'error' => 'Google Calendar not connected.'];
            }

            $row        = $this->getRow($businessId);
            $config     = $this->decryptConfig($row['encrypted_config'] ?? null);
            $calendarId = (string) ($config['calendar_id'] ?? 'primary');

            $event = new \Google_Service_Calendar_Event([
                'summary'     => $appointment['title'] ?? 'Appointment',
                'description' => $appointment['notes'] ?? '',
                'start'       => ['dateTime' => $appointment['start_datetime'] ?? '', 'timeZone' => $appointment['timezone'] ?? 'UTC'],
                'end'         => ['dateTime' => $appointment['end_datetime']   ?? '', 'timeZone' => $appointment['timezone'] ?? 'UTC'],
            ]);

            $service = new \Google_Service_Calendar($client);
            $created = $service->events->insert($calendarId, $event);

            return ['ok' => true, 'event_id' => $created->getId()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Failed to create calendar event: ' . $e->getMessage()];
        }
    }

    public function deleteEvent(int $businessId, string $eventId): array
    {
        try {
            $client = $this->getRefreshedClient($businessId);
            if (!$client) {
                return ['ok' => false, 'error' => 'Google Calendar not connected.'];
            }

            $row        = $this->getRow($businessId);
            $config     = $this->decryptConfig($row['encrypted_config'] ?? null);
            $calendarId = (string) ($config['calendar_id'] ?? 'primary');

            $service = new \Google_Service_Calendar($client);
            $service->events->delete($calendarId, $eventId);

            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Failed to delete calendar event: ' . $e->getMessage()];
        }
    }
}
