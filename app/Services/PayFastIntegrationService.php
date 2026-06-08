<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Services\Concerns\HandlesNotificationIntegrations;

/**
 * Manages PayFast payment integration per business.
 *
 * PayFast is the leading South African payment gateway. Supports both
 * sandbox (testing) and live environments.
 *
 * Encrypted config stored in xs_business_integrations with channel = 'payfast'.
 * No Composer package required — uses CI4 CURLRequest for API calls.
 *
 * Docs: https://developers.payfast.co.za/api
 */
class PayFastIntegrationService
{
    use HandlesNotificationIntegrations;

    public const CHANNEL  = 'payfast';
    public const PROVIDER = 'payfast';

    private const API_LIVE    = 'https://api.payfast.co.za';
    private const API_SANDBOX = 'https://api.payfast.co.za'; // sandbox uses ?testing=true param
    private const PING_PATH   = '/ping';

    protected function integrationEncryptionLabel(): string
    {
        return 'PayFast';
    }

    protected function integrationDecryptContext(): string
    {
        return 'PayFastIntegrationService';
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
            log_message('debug', 'PayFastIntegrationService::getRow - ' . $e->getMessage());
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
                'has_credentials' => false,
                'merchant_id_hint' => '',
                'sandbox'         => true,
            ];
        }

        $decrypted    = $this->decryptConfig($row['encrypted_config'] ?? null, true);
        $config       = $decrypted['data'] ?? [];
        $decryptError = $decrypted['error'] ?? null;

        $merchantId = (string) ($config['merchant_id'] ?? '');
        $passphrase = (string) ($config['passphrase'] ?? '');

        return [
            'is_active'        => (bool) ($row['is_active'] ?? false),
            'health_status'    => (string) ($row['health_status'] ?? 'unknown'),
            'last_tested_at'   => (string) ($row['last_tested_at'] ?? ''),
            'decrypt_error'    => $decryptError,
            'has_credentials'  => $merchantId !== '',
            'merchant_id'      => $merchantId,                                          // exposed — merchant_id is public (appears in payment forms)
            'merchant_id_hint' => $merchantId !== '' ? substr($merchantId, 0, 4) . '...' : '',
            'has_passphrase'   => $passphrase !== '',
            'sandbox'          => (bool) ($config['sandbox'] ?? true),
        ];
    }

    public function saveIntegration(int $businessId, array $input): array
    {
        $merchantId  = trim((string) ($input['merchant_id'] ?? ''));
        $merchantKey = trim((string) ($input['merchant_key'] ?? ''));
        $passphrase  = trim((string) ($input['passphrase'] ?? ''));
        $sandbox     = filter_var($input['sandbox'] ?? true, FILTER_VALIDATE_BOOLEAN);

        // Preserve existing merchant_key and passphrase when left blank (same pattern as Stripe).
        // Must happen before validation so "leave blank to keep existing" actually works.
        $existing       = null;
        $existingConfig = [];
        if ($merchantKey === '' || $passphrase === '') {
            $existing = $this->getRow($businessId);
            if ($existing) {
                $existingConfig = $this->decryptConfig($existing['encrypted_config'] ?? null);
            }
        }
        if ($merchantKey === '') {
            $merchantKey = (string) ($existingConfig['merchant_key'] ?? '');
        }
        if ($passphrase === '') {
            $passphrase = (string) ($existingConfig['passphrase'] ?? '');
        }

        if ($merchantId === '') {
            return ['ok' => false, 'error' => 'Merchant ID is required.'];
        }
        if (!ctype_digit($merchantId)) {
            return ['ok' => false, 'error' => 'Merchant ID must be numeric.'];
        }
        if ($merchantKey === '') {
            return ['ok' => false, 'error' => 'Merchant Key is required.'];
        }

        try {
            $encryptedConfig = $this->encryptConfig([
                'merchant_id'  => $merchantId,
                'merchant_key' => $merchantKey,
                'passphrase'   => $passphrase,
                'sandbox'      => $sandbox,
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
            return ['ok' => false, 'error' => 'PayFast is not configured.'];
        }

        $config     = $this->decryptConfig($row['encrypted_config'] ?? null);
        $merchantId = (string) ($config['merchant_id'] ?? '');
        $merchantKey = (string) ($config['merchant_key'] ?? '');
        $sandbox    = (bool) ($config['sandbox'] ?? true);

        if ($merchantId === '' || $merchantKey === '') {
            return ['ok' => false, 'error' => 'Merchant credentials are missing.'];
        }

        $passphrase = (string) ($config['passphrase'] ?? '');
        $timestamp  = date('Y-m-d\TH:i:s');
        $version    = 'v1';
        // PayFast requires header fields sorted ALPHABETICALLY by key before MD5.
        // Correct order: merchant-id < passphrase < timestamp < version
        $headerFields = [
            'merchant-id' => $merchantId,
            'timestamp'   => $timestamp,
            'version'     => $version,
        ];
        if ($passphrase !== '') {
            $headerFields['passphrase'] = $passphrase;
        }
        ksort($headerFields);
        $pfData    = http_build_query($headerFields);
        $signature = md5($pfData);

        try {
            $url  = self::API_LIVE . self::PING_PATH . ($sandbox ? '?testing=true' : '');
            $curl = \Config\Services::curlrequest(['timeout' => 10]);
            $response = $curl->get($url, [
                'headers' => [
                    'merchant-id' => $merchantId,
                    'version'     => $version,
                    'timestamp'   => $timestamp,
                    'signature'   => $signature,
                ],
            ]);

            $status = $response->getStatusCode();
            $model  = new BusinessIntegrationModel();

            if ($status === 200) {
                $this->updateHealth($model, $row, 'healthy', date('Y-m-d H:i:s'));
                return ['ok' => true, 'sandbox' => $sandbox];
            }

            $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            return ['ok' => false, 'error' => "PayFast returned HTTP {$status}. Check your merchant credentials."];
        } catch (\Throwable $e) {
            $model = new BusinessIntegrationModel();
            $this->updateHealth($model, $row, 'unhealthy', date('Y-m-d H:i:s'));
            return ['ok' => false, 'error' => 'PayFast connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Retrieve and decrypt the stored PayFast credentials for a business.
     * Returns null when no row exists or decryption fails.
     * Used by PayFastPaymentService to avoid Reflection-based private access.
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

    /**
     * Build the standard PayFast payment URL for an appointment deposit.
     * Returns the redirect URL the customer should be sent to for payment.
     */
    public function buildPaymentUrl(int $businessId, array $paymentData): array
    {
        $row = $this->getRow($businessId);
        if (!$row || !(bool) ($row['is_active'] ?? false)) {
            return ['ok' => false, 'error' => 'PayFast is not connected.'];
        }

        $config      = $this->decryptConfig($row['encrypted_config'] ?? null);
        $merchantId  = (string) ($config['merchant_id'] ?? '');
        $merchantKey = (string) ($config['merchant_key'] ?? '');
        $passphrase  = (string) ($config['passphrase'] ?? '');
        $sandbox     = (bool) ($config['sandbox'] ?? true);

        $baseUrl  = $sandbox ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process';

        $fields = array_filter([
            'merchant_id'   => $merchantId,
            'merchant_key'  => $merchantKey,
            'return_url'    => $paymentData['return_url'] ?? '',
            'cancel_url'    => $paymentData['cancel_url'] ?? '',
            'notify_url'    => $paymentData['notify_url'] ?? '',
            'name_first'    => $paymentData['first_name'] ?? '',
            'name_last'     => $paymentData['last_name'] ?? '',
            'email_address' => $paymentData['email'] ?? '',
            'm_payment_id'  => $paymentData['reference'] ?? '',
            'amount'        => number_format((float) ($paymentData['amount'] ?? 0), 2, '.', ''),
            'item_name'     => $paymentData['item_name'] ?? 'Appointment Deposit',
        ]);

        // Generate signature
        $signatureString = http_build_query($fields);
        if ($passphrase !== '') {
            $signatureString .= '&passphrase=' . urlencode($passphrase);
        }
        $fields['signature'] = md5($signatureString);

        return ['ok' => true, 'url' => $baseUrl . '?' . http_build_query($fields), 'sandbox' => $sandbox];
    }
}
