<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * WebScheduler Directory (SaaS) handoff configuration.
 *
 * This standalone WebScheduler app is NOT the directory. The directory is a
 * separate SaaS (see the "Provider Directory" plan). This config only powers the
 * one-way "List on our directory" handoff: we build the customer's basic business
 * details, sign them, and browser-POST them to the SaaS signup so the customer
 * does not have to retype anything.
 *
 * Override per install via env (keys mirror the property names):
 *   directory.handoffUrl    = https://directory.webscheduler.co.za/list-your-practice/prefill
 *   directory.handoffSecret = <shared HMAC secret, also set on the SaaS>
 *
 * The signed payload is treated as UNVERIFIED input by the SaaS — email
 * verification is the real gate. The HMAC only provides tamper-evidence in
 * transit, so a low-stakes shared secret is acceptable here (this is NOT API
 * auth; API auth remains per-user rows in xs_api_keys — see Config\Api).
 */
class Directory extends BaseConfig
{
    /**
     * The SaaS prefill endpoint that receives the browser POST handoff.
     */
    public string $handoffUrl = 'https://directory.webscheduler.co.za/list-your-practice/prefill';

    /**
     * Shared HMAC-SHA256 secret. Empty by default — set it in .env (and match it
     * on the SaaS). When empty, the handoff still works but sends no signature.
     */
    public string $handoffSecret = '';

    /**
     * Whether the "List on our directory" entry point is shown in the app.
     */
    public bool $enabled = true;

    /**
     * Resolved handoff URL (property, then env fallback).
     */
    public function handoffUrl(): string
    {
        $env = env('directory.handoffUrl');
        return is_string($env) && trim($env) !== '' ? trim($env) : $this->handoffUrl;
    }

    /**
     * Resolved shared secret (property, then env fallback).
     */
    public function handoffSecret(): string
    {
        $env = env('directory.handoffSecret');
        return is_string($env) && trim($env) !== '' ? trim($env) : $this->handoffSecret;
    }
}
