<?php

namespace App\Controllers;

use App\Services\GoogleCalendarIntegrationService;
use App\Services\NotificationCatalog;

/**
 * Handles OAuth 2.0 callback flows for third-party integrations.
 *
 * Routes (admin-only, see Routes.php):
 *   GET /oauth/google/authorize  — start Google Calendar OAuth flow
 *   GET /oauth/google/callback   — exchange code, store tokens, redirect back
 */
class OAuthCallback extends BaseController
{
    public function googleAuthorize()
    {
        $businessId = $this->resolveBusinessId();
        $service    = new GoogleCalendarIntegrationService();

        if (!$service->isConfigured($businessId)) {
            return redirect()->to(base_url('settings') . '#integrations')
                ->with('error', 'Google Calendar credentials are not configured. Click Configure on the Google Calendar card to enter your OAuth app credentials.');
        }

        return redirect()->to($service->getAuthUrl($businessId));
    }

    public function googleCallback()
    {
        $code  = $this->request->getGet('code');
        $state = $this->request->getGet('state');
        $error = $this->request->getGet('error');

        if ($error) {
            return redirect()->to(base_url('settings') . '#integrations')
                ->with('error', 'Google Calendar authorization was denied: ' . esc($error));
        }

        if (empty($code)) {
            return redirect()->to(base_url('settings') . '#integrations')
                ->with('error', 'No authorization code received from Google.');
        }

        // Decode business_id from state parameter
        $businessId = $this->resolveBusinessId();
        if (!empty($state)) {
            $decoded = json_decode(base64_decode($state), true);
            if (isset($decoded['business_id']) && is_numeric($decoded['business_id'])) {
                $businessId = (int) $decoded['business_id'];
            }
        }

        $service = new GoogleCalendarIntegrationService();
        $result  = $service->handleCallback($businessId, $code);

        if (!$result['ok']) {
            return redirect()->to(base_url('settings') . '#integrations')
                ->with('error', $result['error'] ?? 'Failed to connect Google Calendar.');
        }

        return redirect()->to(base_url('settings') . '#integrations')
            ->with('success', 'Google Calendar connected successfully.');
    }

    private function resolveBusinessId(): int
    {
        $sessionUser = session()->get('user');
        $sessionUser = is_array($sessionUser) ? $sessionUser : [];

        $candidates = [
            session()->get('business_id'),
            session()->get('active_business_id'),
            $sessionUser['business_id'] ?? null,
            $sessionUser['active_business_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return NotificationCatalog::BUSINESS_ID_DEFAULT;
    }
}
