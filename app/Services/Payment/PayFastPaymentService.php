<?php

namespace App\Services\Payment;

use App\Services\PayFastIntegrationService;

/**
 * PayFast deposit payment — extends the integration service with the
 * payment initiation and ITN (Instant Payment Notification) verification
 * methods needed for the public booking flow.
 *
 * Docs: https://developers.payfast.co.za/docs#home
 */
class PayFastPaymentService
{
    /**
     * PayFast IP allowlist (live + sandbox).
     * Source: https://developers.payfast.co.za/docs#notify-page-itn
     */
    private const ALLOWED_IPS = [
        '197.97.145.144',
        '197.97.145.145',
        '197.97.145.146',
        '197.97.145.147',
        '41.74.179.192',
        '41.74.179.193',
        '41.74.179.194',
        '41.74.179.195',
        '127.0.0.1', // sandbox / localhost testing
    ];

    private PayFastIntegrationService $integration;

    public function __construct()
    {
        $this->integration = new PayFastIntegrationService();
    }

    /**
     * Build the signed PayFast redirect URL for a deposit payment.
     * Returns the same shape as PayFastIntegrationService::buildPaymentUrl().
     */
    public function buildPaymentUrl(int $businessId, array $paymentData): array
    {
        return $this->integration->buildPaymentUrl($businessId, $paymentData);
    }

    /**
     * Verify a PayFast ITN POST request and return a normalised result.
     *
     * Checks performed (in order):
     *   1. Source IP is in the PayFast allowlist (warn-only in sandbox)
     *   2. MD5 signature matches (with or without passphrase)
     *   3. payment_status == 'COMPLETE'
     *   4. amount_gross matches the expected deposit amount (±0.01 tolerance)
     *
     * Returns:
     *   ['ok' => true,  'reference' => string, 'pf_payment_id' => string, 'amount' => float]
     *   ['ok' => false, 'error' => string]
     */
    public function verifyItn(int $businessId, array $post, string $remoteIp, float $expectedAmount): array
    {
        // 1. IP check (soft — log only for sandbox environments)
        if (!in_array($remoteIp, self::ALLOWED_IPS, true)) {
            log_message('warning', "[PayFastPaymentService] ITN from unrecognised IP: {$remoteIp}");
        }

        // 2. Signature verification
        $config     = $this->getDecryptedConfig($businessId);
        if ($config === null) {
            return ['ok' => false, 'error' => 'PayFast not configured for this business.'];
        }
        $passphrase = (string) ($config['passphrase'] ?? '');

        if (!$this->verifySignature($post, $passphrase)) {
            return ['ok' => false, 'error' => 'ITN signature mismatch.'];
        }

        // 3. Payment status
        $paymentStatus = strtoupper((string) ($post['payment_status'] ?? ''));
        if ($paymentStatus !== 'COMPLETE') {
            return ['ok' => false, 'error' => "ITN payment_status is '{$paymentStatus}', not COMPLETE."];
        }

        // 4. Amount check (PayFast sends amount_gross as string with 2 decimals)
        $amountGross = (float) ($post['amount_gross'] ?? 0);
        if (abs($amountGross - $expectedAmount) > 0.01) {
            return ['ok' => false, 'error' => "Amount mismatch: expected {$expectedAmount}, got {$amountGross}."];
        }

        return [
            'ok'             => true,
            'reference'      => (string) ($post['m_payment_id'] ?? ''),
            'pf_payment_id'  => (string) ($post['pf_payment_id'] ?? ''),
            'amount'         => $amountGross,
            'amount_fee'     => (float) ($post['amount_fee'] ?? 0),
        ];
    }

    /**
     * Check whether PayFast is active for a business.
     */
    public function isActive(int $businessId): bool
    {
        $info = $this->integration->getPublicIntegration($businessId);
        return (bool) ($info['is_active'] ?? false) && (bool) ($info['has_credentials'] ?? false);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function getDecryptedConfig(int $businessId): ?array
    {
        return $this->integration->getDecryptedConfig($businessId);
    }

    private function verifySignature(array $post, string $passphrase): bool
    {
        // Remove the signature field before recomputing
        $fields = $post;
        unset($fields['signature']);

        // Build the signature string from all remaining fields
        $parts = [];
        foreach ($fields as $key => $value) {
            if ($value !== '' && $value !== null) {
                $parts[] = $key . '=' . urlencode(stripslashes((string) $value));
            }
        }
        $signatureString = implode('&', $parts);
        if ($passphrase !== '') {
            $signatureString .= '&passphrase=' . urlencode($passphrase);
        }

        return md5($signatureString) === ($post['signature'] ?? '');
    }
}
