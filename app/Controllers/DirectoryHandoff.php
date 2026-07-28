<?php

namespace App\Controllers;

use App\Services\DirectoryHandoffService;

/**
 * "List on our directory" handoff.
 *
 * Thin controller: assembles the signed basic-business-details payload (via
 * DirectoryHandoffService) and renders an interstitial that browser-POSTs it to
 * the directory SaaS signup, so an existing WebScheduler customer never re-types
 * their details. No data is persisted here.
 */
class DirectoryHandoff extends BaseController
{
    public function start()
    {
        helper(['app', 'permissions']);

        $service = new DirectoryHandoffService();
        if (! $service->isEnabled()) {
            return redirect()->to(base_url('settings'))
                ->with('error', 'The directory listing service is not configured for this installation.');
        }

        $user = current_identity_user();
        if (! is_array($user) || $user === []) {
            return redirect()->to(base_url('auth/login'));
        }

        $handoff = $service->buildHandoff($user);

        // The interstitial form POSTs cross-origin to the directory SaaS. The app
        // CSP sets form-action 'self', so permit the directory origin for THIS
        // response only (does not loosen CSP anywhere else).
        $origin = $this->originOf($handoff['url']);
        if ($origin !== '') {
            $this->response->getCSP()->addFormAction($origin);
        }

        return view('directory-handoff/post', [
            'handoffUrl'   => $handoff['url'],
            'payload'      => $handoff['payload'],
            'sig'          => $handoff['sig'],
            'businessName' => setting('general.company_name') ?: 'WebScheduler',
        ]);
    }

    /**
     * Scheme+host(+port) origin of a URL, or '' if unparseable.
     */
    private function originOf(string $url): string
    {
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host'])) {
            return '';
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return $origin;
    }
}
