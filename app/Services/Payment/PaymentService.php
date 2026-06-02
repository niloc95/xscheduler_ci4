<?php

namespace App\Services\Payment;

use App\Models\AppointmentModel;
use App\Models\PaymentTransactionModel;
use App\Models\ServiceModel;
use App\Services\AppointmentEventService;
use App\Services\LocalizationSettingsService;

/**
 * Payment orchestrator for the public booking deposit flow.
 *
 * Responsibilities:
 * - Calculate deposit amounts from service configuration.
 * - Initiate payment with the chosen gateway (PayFast or Stripe).
 * - Record a pending transaction on initiation.
 * - On webhook confirmation: update transaction + appointment, dispatch notifications.
 *
 * This service does NOT touch SMTP or channels directly — all notification
 * delivery is deferred to AppointmentEventService → queue → cron worker.
 */
class PaymentService
{
    private PayFastPaymentService    $payfast;
    private StripePaymentService     $stripe;
    private PaymentTransactionModel  $txnModel;
    private AppointmentModel         $appointmentModel;
    private ServiceModel             $serviceModel;
    private AppointmentEventService  $eventService;
    private LocalizationSettingsService $localization;

    public function __construct()
    {
        $this->payfast          = new PayFastPaymentService();
        $this->stripe           = new StripePaymentService();
        $this->txnModel         = new PaymentTransactionModel();
        $this->appointmentModel = new AppointmentModel();
        $this->serviceModel     = new ServiceModel();
        $this->eventService     = new AppointmentEventService();
        $this->localization     = new LocalizationSettingsService();
    }

    // -------------------------------------------------------------------------
    // Deposit calculation
    // -------------------------------------------------------------------------

    /**
     * Return whether each gateway has live credentials configured for a business.
     * Single source of truth — call this instead of duplicating the isActive() check.
     *
     * @return array{payfast: bool, stripe: bool}
     */
    public function getGatewayAvailability(int $businessId): array
    {
        return [
            'payfast' => $this->payfast->isActive($businessId),
            'stripe'  => $this->stripe->isActive($businessId),
        ];
    }

    /**
     * Calculate the deposit amount for a service.
     * Returns 0.0 if the service has no deposit or payment is disabled.
     */
    public function calculateDeposit(array $service): float
    {
        if (!(bool) ($service['payment_enabled'] ?? false)) {
            return 0.0;
        }
        helper('currency');
        return calculate_deposit_amount(
            (float) ($service['price'] ?? 0),
            (float) ($service['deposit_percentage'] ?? 0)
        );
    }

    /**
     * Return payment metadata for a service to embed in the booking API response.
     * Used by the public booking JS to render the deposit badge and payment step.
     */
    public function servicePaymentMeta(array $service, int $businessId): array
    {
        if (!(bool) ($service['payment_enabled'] ?? false)) {
            return ['payment_required' => false];
        }

        $deposit      = $this->calculateDeposit($service);
        $currency     = $this->localization->getCurrency();
        $currencySymbol = $this->localization->getCurrencySymbol();

        return [
            'payment_required'   => $deposit > 0,
            'deposit_percentage' => (float) ($service['deposit_percentage'] ?? 0),
            'deposit_amount'     => $deposit,
            'full_price'         => (float) ($service['price'] ?? 0),
            'currency'           => $currency,
            'currency_symbol'    => $currencySymbol,
            'payfast_available'  => (bool) ($service['payfast_enabled'] ?? false) && $this->payfast->isActive($businessId),
            'stripe_available'   => (bool) ($service['stripe_enabled'] ?? false) && $this->stripe->isActive($businessId),
        ];
    }

    // -------------------------------------------------------------------------
    // Payment initiation
    // -------------------------------------------------------------------------

    /**
     * Initiate a PayFast payment for an appointment deposit.
     * Creates a pending transaction record and returns the redirect URL.
     *
     * @param int    $businessId
     * @param int    $appointmentId
     * @param array  $service        Service row including price + deposit_percentage.
     * @param array  $customer       ['first_name', 'last_name', 'email'].
     * @param string $returnUrl      URL PayFast redirects to on success.
     * @param string $cancelUrl      URL PayFast redirects to on cancel.
     * @param string $notifyUrl      URL PayFast posts the ITN to.
     *
     * @return array ['ok' => true, 'url' => string, 'reference' => string]
     *             | ['ok' => false, 'error' => string]
     */
    public function initiatePayFast(
        int    $businessId,
        int    $appointmentId,
        array  $service,
        array  $customer,
        string $returnUrl,
        string $cancelUrl,
        string $notifyUrl
    ): array {
        $deposit   = $this->calculateDeposit($service);
        $reference = $this->generateReference($appointmentId, 'pf');

        $result = $this->payfast->buildPaymentUrl($businessId, [
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'notify_url' => $notifyUrl,
            'first_name' => $customer['first_name'] ?? '',
            'last_name'  => $customer['last_name']  ?? '',
            'email'      => $customer['email']       ?? '',
            'reference'  => $reference,
            'amount'     => $deposit,
            'item_name'  => 'Deposit: ' . ($service['name'] ?? 'Appointment'),
        ]);

        if (!($result['ok'] ?? false)) {
            return $result;
        }

        // Record pending transaction
        $this->txnModel->insert([
            'business_id'       => $businessId,
            'appointment_id'    => $appointmentId,
            'gateway'           => 'payfast',
            'gateway_reference' => $reference,
            'amount'            => $deposit,
            'currency'          => $this->localization->getCurrency(),
            'status'            => 'pending',
        ]);

        // Store reference on appointment so webhook can look it up
        $this->appointmentModel->update($appointmentId, [
            'payment_status'    => 'pending',
            'payment_amount'    => $deposit,
            'payment_reference' => $reference,
        ]);

        return ['ok' => true, 'url' => $result['url'], 'reference' => $reference, 'sandbox' => $result['sandbox'] ?? false];
    }

    /**
     * Initiate a Stripe Checkout Session for an appointment deposit.
     *
     * @return array ['ok' => true, 'checkout_url' => string, 'session_id' => string]
     *             | ['ok' => false, 'error' => string]
     */
    public function initiateStripe(
        int    $businessId,
        int    $appointmentId,
        array  $service,
        string $successUrl,
        string $cancelUrl
    ): array {
        $deposit  = $this->calculateDeposit($service);
        $currency = $this->localization->getCurrency();

        $result = $this->stripe->createCheckoutSession(
            $businessId,
            $appointmentId,
            $deposit,
            $currency,
            'Deposit: ' . ($service['name'] ?? 'Appointment'),
            $successUrl,
            $cancelUrl
        );

        if (!($result['ok'] ?? false)) {
            return $result;
        }

        // Record pending transaction
        $this->txnModel->insert([
            'business_id'       => $businessId,
            'appointment_id'    => $appointmentId,
            'gateway'           => 'stripe',
            'gateway_reference' => $result['session_id'],
            'amount'            => $deposit,
            'currency'          => $currency,
            'status'            => 'pending',
        ]);

        $this->appointmentModel->update($appointmentId, [
            'payment_status'    => 'pending',
            'payment_amount'    => $deposit,
            'payment_reference' => $result['session_id'],
        ]);

        return ['ok' => true, 'checkout_url' => $result['checkout_url'], 'session_id' => $result['session_id']];
    }

    // -------------------------------------------------------------------------
    // Webhook / ITN confirmation
    // -------------------------------------------------------------------------

    /**
     * Handle a confirmed payment (called from webhook controller after signature verification).
     *
     * - Idempotent: if the transaction is already 'complete', returns ok without re-processing.
     * - Updates transaction status to 'complete'.
     * - Updates appointment payment_status to 'paid'.
     * - If appointment is 'pending', transitions to 'confirmed' and dispatches notification.
     *
     * @param string $gateway          'payfast' | 'stripe'
     * @param string $gatewayReference m_payment_id (PayFast) | session.id (Stripe)
     * @param int    $appointmentId    Resolved appointment ID.
     * @param float  $amount           Actual amount paid.
     * @param string $rawPayload       Raw webhook body for audit.
     * @param int    $businessId
     */
    public function confirmPayment(
        string $gateway,
        string $gatewayReference,
        int    $appointmentId,
        float  $amount,
        string $rawPayload,
        int    $businessId = 1
    ): array {
        // Idempotency: skip if already confirmed
        $existing = $this->txnModel->findByReference($gateway, $gatewayReference);
        if ($existing && ($existing['status'] ?? '') === 'complete') {
            return ['ok' => true, 'skipped' => true];
        }

        // Update transaction
        if ($existing) {
            $this->txnModel->markComplete((int) $existing['id'], $rawPayload);
        } else {
            // Transaction was not pre-created (edge case: webhook arrived before initiation response)
            $this->txnModel->insert([
                'business_id'       => $businessId,
                'appointment_id'    => $appointmentId,
                'gateway'           => $gateway,
                'gateway_reference' => $gatewayReference,
                'amount'            => $amount,
                'currency'          => $this->localization->getCurrency(),
                'status'            => 'complete',
                'raw_payload'       => $rawPayload,
            ]);
        }

        // Update appointment payment fields
        $this->appointmentModel->update($appointmentId, [
            'payment_status'    => 'paid',
            'payment_amount'    => $amount,
            'payment_reference' => $gatewayReference,
        ]);

        // Transition pending → confirmed and dispatch notifications (customer + provider)
        $appointment = $this->appointmentModel->find($appointmentId);
        if ($appointment && ($appointment['status'] ?? '') === 'pending') {
            $this->appointmentModel->update($appointmentId, ['status' => 'confirmed']);

            // Customer: standard booking confirmation (appointment_confirmed)
            $this->eventService->dispatch('appointment_confirmed', $appointmentId, ['email', 'whatsapp'], $businessId);

            // Customer: dedicated payment receipt (payment_confirmed)
            $this->eventService->dispatch('payment_confirmed', $appointmentId, ['email', 'whatsapp'], $businessId);

            // Provider/internal notification — mirrors what AppointmentBookingService does
            // on normal booking creation, but is not triggered by the event listener
            $providerUserId = (int) ($appointment['provider_id'] ?? 0);
            if ($providerUserId > 0) {
                $queue = new \App\Services\NotificationQueueService();
                foreach (['email'] as $channel) {
                    $queue->enqueueInternalEvent(
                        $businessId,
                        $channel,
                        'appointment_confirmed',
                        $appointmentId,
                        $providerUserId
                    );
                }
            }
        }

        return ['ok' => true, 'skipped' => false];
    }

    /**
     * Mark a transaction as failed (cancelled payment or ITN failure).
     */
    public function failPayment(string $gateway, string $gatewayReference, string $rawPayload = ''): void
    {
        $existing = $this->txnModel->findByReference($gateway, $gatewayReference);
        if ($existing) {
            $this->txnModel->markFailed((int) $existing['id'], $rawPayload);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function generateReference(int $appointmentId, string $prefix = 'pmt'): string
    {
        return $prefix . '_' . $appointmentId . '_' . time();
    }
}
