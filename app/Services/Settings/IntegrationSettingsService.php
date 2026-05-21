<?php

namespace App\Services\Settings;

use App\Services\NotificationCatalog;
use App\Services\GoogleCalendarIntegrationService;
use App\Services\JitsiIntegrationService;
use App\Services\PayFastIntegrationService;
use App\Services\StripeIntegrationService;
use App\Services\WebhookIntegrationService;
use App\Services\ZoomIntegrationService;

class IntegrationSettingsService
{
    private ?WebhookIntegrationService $webhookService = null;
    private ?GoogleCalendarIntegrationService $googleCalendarService = null;
    private ?StripeIntegrationService $stripeService = null;
    private ?ZoomIntegrationService $zoomService = null;
    private ?JitsiIntegrationService $jitsiService = null;
    private ?PayFastIntegrationService $payfastService = null;

    protected function resolveBusinessId(): int
    {
        $sessionUser = session()->get('user');
        $sessionUser = is_array($sessionUser) ? $sessionUser : [];

        $candidates = [
            session()->get('business_id'),
            session()->get('active_business_id'),
            $sessionUser['business_id'] ?? null,
            $sessionUser['active_business_id'] ?? null,
            $sessionUser['businessId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return NotificationCatalog::BUSINESS_ID_DEFAULT;
    }

    private function successResult(string $message): array
    {
        return ['type' => 'success', 'message' => $message];
    }

    private function errorResult(string $message): array
    {
        return ['type' => 'error', 'message' => $message];
    }

    public function getIndexData(): array
    {
        $businessId = $this->resolveBusinessId();

        try {
            $webhookIntegration        = $this->getWebhookService()->getPublicIntegration($businessId);
            $googleCalendarIntegration = $this->getGoogleCalendarService()->getPublicIntegration($businessId);
            $stripeIntegration         = $this->getStripeService()->getPublicIntegration($businessId);
            $zoomIntegration           = $this->getZoomService()->getPublicIntegration($businessId);
            $jitsiIntegration          = $this->getJitsiService()->getPublicIntegration($businessId);
            $payfastIntegration        = $this->getPayFastService()->getPublicIntegration($businessId);
            $googleCalendarConfigured  = $this->getGoogleCalendarService()->isConfigured($businessId);
        } catch (\Throwable $e) {
            log_message('error', 'IntegrationSettingsService::getIndexData - ' . $e->getMessage());
            $webhookIntegration        = ['is_active' => false, 'health_status' => 'unknown', 'last_tested_at' => '', 'decrypt_error' => null, 'config' => ['url' => '', 'events' => []]];
            $googleCalendarIntegration = ['is_active' => false, 'health_status' => 'unknown', 'last_tested_at' => '', 'decrypt_error' => null, 'calendar_id' => '', 'has_tokens' => false];
            $stripeIntegration         = ['is_active' => false, 'health_status' => 'unknown', 'last_tested_at' => '', 'decrypt_error' => null, 'has_secret_key' => false, 'publishable_key' => '', 'currency' => 'usd'];
            $zoomIntegration           = ['is_active' => false, 'health_status' => 'unknown', 'last_tested_at' => '', 'decrypt_error' => null, 'has_credentials' => false];
            $jitsiIntegration          = ['is_active' => false, 'health_status' => 'unknown', 'last_tested_at' => '', 'decrypt_error' => null, 'server_url' => JitsiIntegrationService::DEFAULT_SERVER, 'has_api_key' => false, 'mode' => 'public'];
            $payfastIntegration        = ['is_active' => false, 'health_status' => 'unknown', 'last_tested_at' => '', 'decrypt_error' => null, 'has_credentials' => false, 'merchant_id_hint' => '', 'sandbox' => true];
            $googleCalendarConfigured  = false;
        }

        return [
            'webhookIntegration'        => $webhookIntegration,
            'googleCalendarIntegration' => $googleCalendarIntegration,
            'googleCalendarConfigured'  => $googleCalendarConfigured,
            'webhookEvents'             => WebhookIntegrationService::EVENTS,
            'stripeIntegration'         => $stripeIntegration,
            'zoomIntegration'           => $zoomIntegration,
            'jitsiIntegration'          => $jitsiIntegration,
            'payfastIntegration'        => $payfastIntegration,
        ];
    }

    public function save(array $post, ?int $userId): array
    {
        $businessId = $this->resolveBusinessId();
        $intent     = trim((string) ($post['intent'] ?? 'save'));
        $channel    = trim((string) ($post['channel'] ?? ''));

        $result = match (true) {
            $intent === 'save'       && $channel === 'webhook'         => $this->getWebhookService()->saveIntegration($businessId, $post),
            $intent === 'test'       && $channel === 'webhook'         => $this->getWebhookService()->testConnection($businessId),
            $intent === 'disconnect' && $channel === 'webhook'         => $this->getWebhookService()->disconnect($businessId),

            $intent === 'save'       && $channel === 'google_calendar' => $this->getGoogleCalendarService()->saveAppCredentials($businessId, $post),
            $intent === 'test'       && $channel === 'google_calendar' => $this->getGoogleCalendarService()->testConnection($businessId),
            $intent === 'disconnect' && $channel === 'google_calendar' => $this->getGoogleCalendarService()->disconnect($businessId),

            $intent === 'save'       && $channel === 'stripe'          => $this->getStripeService()->saveIntegration($businessId, $post),
            $intent === 'test'       && $channel === 'stripe'          => $this->getStripeService()->testConnection($businessId),
            $intent === 'disconnect' && $channel === 'stripe'          => $this->getStripeService()->disconnect($businessId),

            $intent === 'save'       && $channel === 'zoom'            => $this->getZoomService()->saveIntegration($businessId, $post),
            $intent === 'test'       && $channel === 'zoom'            => $this->getZoomService()->testConnection($businessId),
            $intent === 'disconnect' && $channel === 'zoom'            => $this->getZoomService()->disconnect($businessId),

            $intent === 'save'       && $channel === 'jitsi'           => $this->getJitsiService()->saveIntegration($businessId, $post),
            $intent === 'test'       && $channel === 'jitsi'           => $this->getJitsiService()->testConnection($businessId),
            $intent === 'disconnect' && $channel === 'jitsi'           => $this->getJitsiService()->disconnect($businessId),

            $intent === 'save'       && $channel === 'payfast'         => $this->getPayFastService()->saveIntegration($businessId, $post),
            $intent === 'test'       && $channel === 'payfast'         => $this->getPayFastService()->testConnection($businessId),
            $intent === 'disconnect' && $channel === 'payfast'         => $this->getPayFastService()->disconnect($businessId),

            default => ['ok' => false, 'error' => 'Unknown intent or channel.'],
        };

        if (!$result['ok']) {
            return $this->errorResult($result['error'] ?? 'Operation failed.');
        }

        $successMessages = [
            'save_webhook'              => 'Webhook endpoint saved.',
            'test_webhook'              => 'Webhook test succeeded.',
            'disconnect_webhook'        => 'Webhook disconnected.',
            'save_google_calendar'       => 'Google Calendar credentials saved. Click Connect to authorise.',
            'test_google_calendar'       => 'Google Calendar connection verified.',
            'disconnect_google_calendar' => 'Google Calendar disconnected.',
            'save_stripe'               => 'Stripe credentials saved.',
            'test_stripe'               => 'Stripe connection verified.',
            'disconnect_stripe'         => 'Stripe disconnected.',
            'save_zoom'                 => 'Zoom credentials saved.',
            'test_zoom'                 => 'Zoom connection verified.',
            'disconnect_zoom'           => 'Zoom disconnected.',
            'save_jitsi'                => 'Jitsi server saved.',
            'test_jitsi'                => 'Jitsi server reachable.',
            'disconnect_jitsi'          => 'Jitsi disconnected.',
            'save_payfast'              => 'PayFast credentials saved.',
            'test_payfast'              => 'PayFast connection verified.',
            'disconnect_payfast'        => 'PayFast disconnected.',
        ];

        $key = "{$intent}_{$channel}";
        $message = $successMessages[$key] ?? 'Operation completed.';

        return $this->successResult($message);
    }

    private function getWebhookService(): WebhookIntegrationService
    {
        if ($this->webhookService === null) {
            $this->webhookService = new WebhookIntegrationService();
        }
        return $this->webhookService;
    }

    private function getGoogleCalendarService(): GoogleCalendarIntegrationService
    {
        if ($this->googleCalendarService === null) {
            $this->googleCalendarService = new GoogleCalendarIntegrationService();
        }
        return $this->googleCalendarService;
    }

    private function getStripeService(): StripeIntegrationService
    {
        if ($this->stripeService === null) {
            $this->stripeService = new StripeIntegrationService();
        }
        return $this->stripeService;
    }

    private function getZoomService(): ZoomIntegrationService
    {
        if ($this->zoomService === null) {
            $this->zoomService = new ZoomIntegrationService();
        }
        return $this->zoomService;
    }

    private function getJitsiService(): JitsiIntegrationService
    {
        if ($this->jitsiService === null) {
            $this->jitsiService = new JitsiIntegrationService();
        }
        return $this->jitsiService;
    }

    private function getPayFastService(): PayFastIntegrationService
    {
        if ($this->payfastService === null) {
            $this->payfastService = new PayFastIntegrationService();
        }
        return $this->payfastService;
    }
}
