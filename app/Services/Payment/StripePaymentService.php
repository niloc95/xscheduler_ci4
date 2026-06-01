<?php

namespace App\Services\Payment;

use App\Services\StripeIntegrationService;

/**
 * Stripe deposit payment — extends the integration service with Checkout
 * Session creation and webhook event handling for the public booking flow.
 *
 * Uses Stripe Checkout (redirect-based) — simpler than Payment Intents
 * for a hosted-page deposit flow; no frontend JS SDK required.
 *
 * Docs: https://docs.stripe.com/payments/checkout
 */
class StripePaymentService
{
    private StripeIntegrationService $integration;

    public function __construct()
    {
        $this->integration = new StripeIntegrationService();
    }

    /**
     * Create a Stripe Checkout Session for an appointment deposit.
     *
     * @param int    $businessId
     * @param int    $appointmentId   Stored in session metadata for webhook lookup.
     * @param float  $amount          Deposit amount in major currency units (e.g. 30.00).
     * @param string $currency        ISO 4217 code (e.g. 'zar', 'usd').
     * @param string $itemName        Line-item description shown in Checkout UI.
     * @param string $successUrl      Full URL to redirect on payment success.
     * @param string $cancelUrl       Full URL to redirect on cancellation.
     *
     * @return array ['ok' => true, 'session_id' => string, 'checkout_url' => string]
     *             | ['ok' => false, 'error' => string]
     */
    public function createCheckoutSession(
        int    $businessId,
        int    $appointmentId,
        float  $amount,
        string $currency,
        string $itemName,
        string $successUrl,
        string $cancelUrl
    ): array {
        $config = $this->getDecryptedConfig($businessId);
        if ($config === null) {
            return ['ok' => false, 'error' => 'Stripe is not configured for this business.'];
        }

        $secretKey = (string) ($config['secret_key'] ?? '');
        if ($secretKey === '') {
            return ['ok' => false, 'error' => 'Stripe secret key is missing.'];
        }

        // Convert to smallest currency unit (cents). Stripe requires integers.
        $amountInCents = (int) round($amount * 100);
        if ($amountInCents < 50) {
            return ['ok' => false, 'error' => "Stripe minimum charge is 0.50 {$currency}."];
        }

        try {
            \Stripe\Stripe::setApiKey($secretKey);

            $session = \Stripe\Checkout\Session::create([
                'mode'                => 'payment',
                'payment_method_types' => ['card'],
                'line_items'          => [[
                    'price_data' => [
                        'currency'     => strtolower($currency),
                        'unit_amount'  => $amountInCents,
                        'product_data' => ['name' => $itemName],
                    ],
                    'quantity' => 1,
                ]],
                'metadata'      => ['appointment_id' => (string) $appointmentId],
                'success_url'   => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'    => $cancelUrl,
            ]);

            return [
                'ok'           => true,
                'session_id'   => $session->id,
                'checkout_url' => $session->url,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            log_message('error', '[StripePaymentService] Checkout creation failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Stripe error: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            log_message('error', '[StripePaymentService] Unexpected error: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Payment initiation failed.'];
        }
    }

    /**
     * Verify and parse a Stripe webhook event from the raw request body.
     *
     * Returns a normalised result for checkout.session.completed events.
     *
     * @param string $payload   Raw request body (before JSON decode).
     * @param string $sigHeader Value of the Stripe-Signature header.
     * @param int    $businessId
     *
     * @return array ['ok' => true, 'event_type' => string, 'session_id' => string,
     *                'appointment_id' => int, 'amount' => float, 'currency' => string]
     *             | ['ok' => false, 'error' => string, 'skip' => bool]
     */
    public function verifyWebhookEvent(string $payload, string $sigHeader, int $businessId): array
    {
        $config        = $this->getDecryptedConfig($businessId);
        $webhookSecret = (string) ($config['webhook_secret'] ?? '');

        if ($webhookSecret === '') {
            return ['ok' => false, 'error' => 'Stripe webhook secret not configured.', 'skip' => true];
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return ['ok' => false, 'error' => 'Stripe signature verification failed.', 'skip' => false];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Stripe event parse error: ' . $e->getMessage(), 'skip' => false];
        }

        $eventType = $event->type;

        // Only process completed checkout sessions
        if ($eventType !== 'checkout.session.completed') {
            return ['ok' => true, 'event_type' => $eventType, 'session_id' => '', 'appointment_id' => 0, 'amount' => 0.0, 'currency' => ''];
        }

        $session       = $event->data->object;
        $appointmentId = (int) ($session->metadata->appointment_id ?? 0);
        $sessionId     = (string) ($session->id ?? '');
        $amountTotal   = (float) ($session->amount_total ?? 0) / 100; // convert from cents
        $currency      = strtoupper((string) ($session->currency ?? ''));

        if ($appointmentId === 0 || $sessionId === '') {
            return ['ok' => false, 'error' => 'Checkout session missing appointment metadata.', 'skip' => false];
        }

        if (($session->payment_status ?? '') !== 'paid') {
            return ['ok' => false, 'error' => 'Checkout session not paid.', 'skip' => true];
        }

        return [
            'ok'             => true,
            'event_type'     => $eventType,
            'session_id'     => $sessionId,
            'appointment_id' => $appointmentId,
            'amount'         => $amountTotal,
            'currency'       => $currency,
        ];
    }

    /**
     * Check whether Stripe is active for a business.
     */
    public function isActive(int $businessId): bool
    {
        $info = $this->integration->getPublicIntegration($businessId);
        return (bool) ($info['is_active'] ?? false) && (bool) ($info['has_secret_key'] ?? false);
    }

    /**
     * Return only the publishable key (safe for frontend use).
     */
    public function getPublishableKey(int $businessId): string
    {
        $info = $this->integration->getPublicIntegration($businessId);
        return (string) ($info['publishable_key'] ?? '');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function getDecryptedConfig(int $businessId): ?array
    {
        try {
            $reflection = new \ReflectionClass($this->integration);
            $getRow     = $reflection->getMethod('getRow');
            $getRow->setAccessible(true);
            $row = $getRow->invoke($this->integration, $businessId);
            if (!$row) {
                return null;
            }
            $decrypt = $reflection->getMethod('decryptConfig');
            $decrypt->setAccessible(true);
            return $decrypt->invoke($this->integration, $row['encrypted_config'] ?? null);
        } catch (\Throwable $e) {
            log_message('error', '[StripePaymentService] Cannot decrypt config: ' . $e->getMessage());
            return null;
        }
    }
}
