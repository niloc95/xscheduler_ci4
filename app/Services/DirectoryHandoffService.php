<?php

namespace App\Services;

use App\Models\LocationModel;
use Config\Directory as DirectoryConfig;

/**
 * Builds the signed "List on our directory" handoff.
 *
 * Assembles the customer's BASIC business details from existing app data
 * (general settings, the acting user, their primary location) so an existing
 * WebScheduler customer never re-types anything when listing on the directory
 * SaaS. The result is a base64 JSON payload + HMAC signature that a view
 * browser-POSTs to the SaaS prefill endpoint.
 *
 * The payload is convenience only — the SaaS treats it as unverified user input
 * and gates publication behind email verification.
 */
class DirectoryHandoffService
{
    private DirectoryConfig $config;

    public function __construct(?DirectoryConfig $config = null)
    {
        $this->config = $config ?? config('Directory');
        helper('app'); // setting(), setting_url()
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled && $this->config->handoffUrl() !== '';
    }

    /**
     * Build the handoff descriptor for the given acting user.
     *
     * @param array<string,mixed> $user  The acting user (current_identity_user()).
     * @return array{url:string,payload:string,sig:string}
     */
    public function buildHandoff(array $user): array
    {
        $payload = $this->buildPayload($user);
        $json    = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encoded = base64_encode((string) $json);

        return [
            'url'     => $this->config->handoffUrl(),
            'payload' => $encoded,
            'sig'     => $this->sign($encoded),
        ];
    }

    /**
     * Assemble the basic-business-details payload from existing app data.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function buildPayload(array $user): array
    {
        $location = $this->primaryLocationFor($user);

        $phone = $this->firstNonEmpty([
            setting('general.company_phone'),
            setting('general.telephone_number'),
            setting('general.mobile_number'),
            $user['phone'] ?? null,
        ]);

        return [
            'version'        => 1,
            'business_name'  => $this->firstNonEmpty([
                setting('general.company_name'),
                setting('general.business_name'),
                $user['name'] ?? null,
            ]),
            'contact_person' => (string) ($user['name'] ?? ''),
            'title'          => (string) ($user['title'] ?? ''),
            'phone'          => $phone,
            'email'          => $this->firstNonEmpty([
                setting('general.company_email'),
                $user['email'] ?? null,
            ]),
            'website'        => (string) (setting('general.company_link') ?? ''),
            'address_line'   => $this->firstNonEmpty([
                $location['address'] ?? null,
                setting('general.business_address'),
            ]),
            'suburb'         => (string) ($location['area'] ?? ''),
            'city'           => $this->firstNonEmpty([
                $location['city'] ?? null,
                setting('general.city'),
            ]),
            'province'       => '', // not stored in this app — filled on the SaaS
            'postal_code'    => '',
            'country'        => 'South Africa',
            'logo_url'       => $this->absoluteLogoUrl(),
            'profession_hint' => '',
            'source_url'     => rtrim(base_url(), '/') . '/',
            'issued_at'      => date('c'),
        ];
    }

    /**
     * HMAC-SHA256 over the exact transmitted payload string. Empty when no
     * shared secret is configured.
     */
    public function sign(string $encodedPayload): string
    {
        $secret = $this->config->handoffSecret();
        if ($secret === '') {
            return '';
        }
        return hash_hmac('sha256', $encodedPayload, $secret);
    }

    /**
     * The acting user's primary location (providers only). Business-level
     * fallbacks live in general settings.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>|null
     */
    private function primaryLocationFor(array $user): ?array
    {
        $providerId = (int) ($user['provider_id'] ?? $user['id'] ?? 0);
        if ($providerId <= 0) {
            return null;
        }

        $model = new LocationModel();
        $row = $model->where('provider_id', $providerId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('id', 'ASC')
            ->first();

        return is_array($row) ? $row : null;
    }

    private function absoluteLogoUrl(): string
    {
        $url = setting_url('general.company_logo');
        if (! is_string($url) || $url === '') {
            return '';
        }
        // The SaaS lives on another host, so the logo must be absolute.
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        return rtrim(base_url(), '/') . '/' . ltrim($url, '/');
    }

    /**
     * First trimmed, non-empty string from the candidates.
     *
     * @param array<int,mixed> $candidates
     */
    private function firstNonEmpty(array $candidates): string
    {
        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        return '';
    }
}
