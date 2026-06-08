<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Services\Concerns\HandlesNotificationIntegrations;

/**
 * Manages Stripe payment integration per business.
 *
 * Stores secret_key, publishable_key, webhook_secret, and currency
 * encrypted in xs_business_integrations with channel = 'stripe'.
 *
 * Requires composer package: stripe/stripe-php:^12.0
 *
 * getPublicIntegration() never returns the secret key — only has_secret_key (bool).
 */
class StripeIntegrationService
{
    use HandlesNotificationIntegrations;

    public const CHANNEL = 'stripe';
    public const PROVIDER = 'stripe';

    protected function integrationEncryptionLabel(): string
    {
        return 'Stripe';
    }

    protected function integrationDecryptContext(): string
    {
        return 'StripeIntegrationService';
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
            log_message('debug', 'StripeIntegrationService::getRow - ' . $e->getMessage());
            return null;
        }
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
                'has_secret_key'  => false,
                'publishable_key' => '',
                'currency'        => 'usd',
            ];
        }

        $decrypted    = $this->decryptConfig($row['encrypted_config'] ?? null, true);
        $config       = $decrypted['data'] ?? [];
        $decryptError = $decrypted['error'] ?? null;

        $secretKey = (string) ($config['secret_key'] ?? '');
        $maskedKey = $secretKey !== '' ? substr($secretKey, 0, 8) . '...' : '';

        return [
            'is_active'       => (bool) ($row['is_active'] ?? false),
            'health_status'   => (string) ($row['health_status'] ?? 'unknown'),
            'last_tested_at'  => (string) ($row['last_tested_at'] ?? ''),
            'decrypt_error'   => $decryptError,
            'has_secret_key'  => $secretKey !== '',
            'secret_key_hint' => $maskedKey,
            'publishable_key' => (string) ($config['publishable_key'] ?? ''),
            'currency'        => (string) ($config['currency'] ?? 'usd'),
        ];
    }

    public function saveIntegration(int $businessId, array $input): array
    {
        $secretKey      = trim((string) ($input['secret_key'] ?? ''));
        $publishableKey = trim((string) ($input['publishable_key'] ?? ''));
        $webhookSecret  = trim((string) ($input['webhook_secret'] ?? ''));
        $currency       = strtolower(trim((string) ($input['currency'] ?? 'usd')));

        // Preserve existing secret key if omitted
        if ($secretKey === '') {
            $existing = $this->getRow($businessId);
            if ($existing) {
                $existingConfig = $this->decryptConfig($existing['encrypted_config'] ?? null);
                $secretKey      = (string) ($existingConfig['secret_key'] ?? '');
            }
        }

        if ($secretKey === '') {
            return ['ok' => false, 'error' => 'Secret key is required.'];
        }
        if (!str_starts_with($secretKey, 'sk_live_') && !str_starts_with($secretKey, 'sk_test_')) {
            return ['ok' => false, 'error' => 'Secret key must start with sk_live_ or sk_test_.'];
        }
        if ($publishableKey === '') {
            return ['ok' => false, 'error' => 'Publishable key is required.'];
        }
        if (!str_starts_with($publishableKey, 'pk_live_') && !str_starts_with($publishableKey, 'pk_test_')) {
            return ['ok' => false, 'error' => 'Publishable key must start with pk_live_ or pk_test_.'];
        }

        // Preserve existing webhook secret if omitted
        if ($webhookSecret === '') {
            $existing = $this->getRow($businessId);
            if ($existing) {
                $existingConfig = $this->decryptConfig($existing['encrypted_config'] ?? null);
                $webhookSecret  = (string) ($existingConfig['webhook_secret'] ?? '');
            }
        }

        try {
            $encryptedConfig = $this->encryptConfig([
                'secret_key'      => $secretKey,
                'publishable_key' => $publishableKey,
                'webhook_secret'  => $webhookSecret,
                'currency'        => $currency,
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

    public function testConnection(int $businessId): array
    {
        $row = $this->getRow($businessId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Stripe is not configured.'];
        }

        $config    = $this->decryptConfig($row['encrypted_config'] ?? null);
        $secretKey = trim((string) ($config['secret_key'] ?? ''));

        if ($secretKey === '') {
            return ['ok' => false, 'error' => 'Stripe secret key is missing.'];
        }

        try {
            \Stripe\Stripe::setApiKey($secretKey);
            $account = \Stripe\Account::retrieve();
            $model   = new BusinessIntegrationModel();
            $this->updateHealth($model, $row, 'healthy', date('Y-m-d H:i:s'));
            return ['ok' => true, 'account_id' => $account->id];
        } catch (\Stripe\Exception\AuthenticationException $e) {
            $model = new BusinessIntegrationModel();
            $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            return ['ok' => false, 'error' => 'Stripe authentication failed — check your secret key.'];
        } catch (\Throwable $e) {
            $model = new BusinessIntegrationModel();
            $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            return ['ok' => false, 'error' => 'Stripe test failed: ' . $e->getMessage()];
        }
    }

    /**
     * Retrieve and decrypt the stored Stripe credentials for a business.
     * Returns null when no row exists or decryption fails.
     * Used by StripePaymentService to avoid Reflection-based private access.
     */
    public function getDecryptedConfig(int $businessId): ?array
    {
        $row = $this->getRow($businessId);
        if (!$row) {
            return null;
        }
        return $this->decryptConfig($row['encrypted_config'] ?? null) ?: null;
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
}
