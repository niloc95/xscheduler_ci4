<?php

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use App\Models\AppointmentModel;
use App\Services\Payment\PayFastPaymentService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\StripePaymentService;

/**
 * Handles inbound payment webhooks from PayFast and Stripe.
 *
 * Both endpoints are public (no auth) and excluded from CSRF protection.
 * They MUST validate the payload cryptographically before acting on it.
 *
 * PayFast: POST /public/payments/payfast/notify  (ITN — Instant Payment Notification)
 * Stripe:  POST /public/payments/stripe/webhook
 */
class PaymentWebhookController extends BaseController
{
    private PaymentService     $paymentService;
    private AppointmentModel   $appointmentModel;

    public function __construct()
    {
        $this->paymentService   = new PaymentService();
        $this->appointmentModel = new AppointmentModel();
    }

    // -------------------------------------------------------------------------
    // PayFast ITN
    // -------------------------------------------------------------------------

    /**
     * POST /public/payments/payfast/notify
     *
     * PayFast requires an HTTP 200 response with an empty body to acknowledge
     * receipt. Any non-200 response causes PayFast to retry the ITN.
     */
    public function payfastItn(): \CodeIgniter\HTTP\ResponseInterface
    {
        $post      = $this->request->getPost() ?? [];
        $remoteIp  = $this->request->getIPAddress();
        $rawPayload = json_encode($post);

        // Resolve appointment and business from m_payment_id (format: pf_{appointmentId}_{ts})
        $reference = (string) ($post['m_payment_id'] ?? '');
        [$appointmentId, $businessId] = $this->resolveFromReference($reference);

        if ($appointmentId === 0) {
            log_message('warning', "[PaymentWebhookController] PayFast ITN: cannot resolve appointment from reference '{$reference}'");
            return $this->response->setStatusCode(200)->setBody('');
        }

        // Load appointment to get the expected deposit amount
        $appointment = $this->appointmentModel->find($appointmentId);
        if (!$appointment) {
            log_message('warning', "[PaymentWebhookController] PayFast ITN: appointment {$appointmentId} not found");
            return $this->response->setStatusCode(200)->setBody('');
        }
        $expectedAmount = (float) ($appointment['payment_amount'] ?? 0);

        // Verify signature and payment status
        $pfService = new PayFastPaymentService();
        $result    = $pfService->verifyItn($businessId, $post, $remoteIp, $expectedAmount);

        if (!($result['ok'] ?? false)) {
            log_message('warning', '[PaymentWebhookController] PayFast ITN rejected: ' . ($result['error'] ?? 'unknown'));
            return $this->response->setStatusCode(200)->setBody('');
        }

        // Confirm payment
        $confirm = $this->paymentService->confirmPayment(
            'payfast',
            $reference,
            $appointmentId,
            $result['amount'],
            $rawPayload,
            $businessId
        );

        if (!($confirm['ok'] ?? false)) {
            log_message('error', '[PaymentWebhookController] PayFast confirmPayment failed: ' . ($confirm['error'] ?? ''));
        }

        return $this->response->setStatusCode(200)->setBody('');
    }

    // -------------------------------------------------------------------------
    // Stripe Webhook
    // -------------------------------------------------------------------------

    /**
     * POST /public/payments/stripe/webhook
     *
     * Stripe expects an HTTP 200 response. It retries webhooks on non-2xx
     * responses with exponential backoff up to 3 days.
     */
    public function stripeWebhook(): \CodeIgniter\HTTP\ResponseInterface
    {
        $rawPayload = $this->request->getBody();
        $sigHeader  = $this->request->getHeaderLine('Stripe-Signature');

        // Business ID: single-tenant default (extend for multi-tenant via metadata)
        $businessId = 1;

        $stripeService = new StripePaymentService();
        $result        = $stripeService->verifyWebhookEvent($rawPayload, $sigHeader, $businessId);

        if (!($result['ok'] ?? false)) {
            $skip = (bool) ($result['skip'] ?? false);
            $msg  = $result['error'] ?? 'unknown';
            log_message($skip ? 'info' : 'warning', "[PaymentWebhookController] Stripe webhook: {$msg}");
            // Return 200 for skippable events (not our event type) so Stripe stops retrying
            return $this->response->setStatusCode($skip ? 200 : 400)->setBody('');
        }

        // Only act on checkout.session.completed
        if ($result['event_type'] !== 'checkout.session.completed') {
            return $this->response->setStatusCode(200)->setBody('');
        }

        $appointmentId = (int) ($result['appointment_id'] ?? 0);
        if ($appointmentId === 0) {
            return $this->response->setStatusCode(200)->setBody('');
        }

        $confirm = $this->paymentService->confirmPayment(
            'stripe',
            $result['session_id'],
            $appointmentId,
            $result['amount'],
            $rawPayload,
            $businessId
        );

        if (!($confirm['ok'] ?? false)) {
            log_message('error', '[PaymentWebhookController] Stripe confirmPayment failed: ' . ($confirm['error'] ?? ''));
        }

        return $this->response->setStatusCode(200)->setBody('');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Parse appointmentId and businessId from a payment reference.
     * Format: {prefix}_{appointmentId}_{timestamp}
     * Returns [appointmentId, businessId] — businessId defaults to 1 (single-tenant).
     */
    private function resolveFromReference(string $reference): array
    {
        // Strict format: pf_{appointmentId}_{unixTimestamp}
        if (!preg_match('/^pf_(\d+)_(\d+)$/', $reference, $m)) {
            return [0, 1];
        }
        $appointmentId = (int) $m[1];
        return [$appointmentId > 0 ? $appointmentId : 0, 1];
    }
}
