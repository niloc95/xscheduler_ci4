<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Stores the default settings for the ContentSecurityPolicy, if you
 * choose to use it. The values here will be read in and set as defaults
 * for the site. If needed, they can be overridden on a page-by-page basis.
 *
 * Suggested reference for explanations:
 *
 * @see https://www.html5rocks.com/en/tutorials/security/content-security-policy/
 */
class ContentSecurityPolicy extends BaseConfig
{
    // =========================================================================
    // PHASE 1 — Permissive enforced CSP (safe production baseline)
    //
    // 'unsafe-inline' is intentionally kept here while inline <script>/<style>
    // blocks across views are migrated to nonce attributes (Phase 2).
    // 'unsafe-eval' is allowed only for legacy scheduler tooling that relies on
    // dynamic evaluation; remove once confirmed no longer needed.
    //
    // Phase 2 will:
    //   - Remove 'unsafe-inline' from scriptSrc / styleSrc
    //   - Replace it with nonce-{csp-script-nonce} / nonce-{csp-style-nonce}
    //
    // Phase 3 will:
    //   - Set reportOnly = true with reportURI for ~1 week in production
    //   - Flip back to reportOnly = false after violations trend to zero
    // =========================================================================

    /**
     * Phase 3: set to true to send Content-Security-Policy-Report-Only instead
     * of enforcing. Flip false once violation rate stabilises.
     */
    public bool $reportOnly = false;

    /**
     * Phase 3: set to the violation-ingestion endpoint once the API endpoint
     * at /api/v1/csp-reports is deployed.
     */
    public ?string $reportURI = null;

    /**
     * Upgrade http:// sub-resource requests to https:// automatically.
     */
    public bool $upgradeInsecureRequests = false;

    // -------------------------------------------------------------------------
    // Sources allowed
    // NOTE: once you set a policy to 'none', it cannot be further restricted
    // -------------------------------------------------------------------------

    /**
     * Fallback for directive types not listed explicitly.
     * Set to 'self' so unlisted directives are restricted by default.
     *
     * @var list<string>|string|null
     */
    public $defaultSrc = 'self';

    /**
     * Phase 2: 'unsafe-inline' removed — all inline scripts now carry nonce="{csp-script-nonce}".
     * 'unsafe-eval' kept; remove in Phase 3 once scheduler tooling is audited.
     * cdn.jsdelivr.net required for Alpine.js used in setup wizard.
     *
     * @var list<string>|string
     */
    public $scriptSrc = ["'self'", "'unsafe-eval'",
                          'https://cdnjs.cloudflare.com',
                          'https://cdn.jsdelivr.net'];

    /**
     * Phase 2: 'unsafe-inline' retained in styleSrc — error pages (error_400/403/404/production.php)
     * render outside the CI4 response pipeline so nonces are never substituted.
     * Phase 3 target: remove 'unsafe-inline' once error pages are migrated to hash-based or external CSS.
     *
     * @var list<string>|string
     */
    public $styleSrc = ["'self'", "'unsafe-inline'",
                        'https://fonts.googleapis.com',
                        'https://cdnjs.cloudflare.com'];

    /**
     * Allow images from self, data: URIs (icons/avatars), and any HTTPS host
     * (for uploaded profile images served from configured storage URLs).
     *
     * @var list<string>|string
     */
    public $imageSrc = ["'self'", 'data:', 'https:'];

    /**
     * Restrict <base href> to same origin to prevent base-tag injection.
     *
     * @var list<string>|string|null
     */
    public $baseURI = "'self'";

    /**
     * @var list<string>|string
     */
    public $childSrc = "'self'";

    /**
     * XHR / fetch / WebSocket / EventSource origins.
     *
     * @var list<string>|string
     */
    public $connectSrc = "'self'";

    /**
     * Google Fonts files are loaded from fonts.gstatic.com.
     * data: allows inline font-face src used by some icon fonts.
     *
     * @var list<string>|string
     */
    public $fontSrc = ["'self'", 'https://fonts.gstatic.com', 'data:'];

    /**
     * Restrict form submissions to same origin.
     *
     * @var list<string>|string
     */
    public $formAction = "'self'";

    /**
     * Prevent this app from being embedded in frames on other origins.
     * 'none' is stronger than DENY and is the CSP-native equivalent.
     *
     * @var list<string>|string|null
     */
    public $frameAncestors = "'none'";

    /**
     * @var list<string>|string|null
     */
    public $frameSrc = "'none'";

    /**
     * @var list<string>|string|null
     */
    public $mediaSrc = "'self'";

    /**
     * Block all plugin content (Flash etc.).
     *
     * @var list<string>|string
     */
    public $objectSrc = "'none'";

    /**
     * @var list<string>|string|null
     */
    public $manifestSrc = "'self'";

    /**
     * Limits the kinds of plugins a page may invoke.
     *
     * @var list<string>|string|null
     */
    public $pluginTypes;

    /**
     * List of actions allowed.
     *
     * @var list<string>|string|null
     */
    public $sandbox;

    /**
     * Nonce placeholder substituted into every view response by CI4's CSP engine.
     * Usage in PHP views:  <script nonce="{csp-script-nonce}">
     */
    public string $styleNonceTag = '{csp-style-nonce}';

    /**
     * Usage in PHP views:  <style nonce="{csp-style-nonce}">
     */
    public string $scriptNonceTag = '{csp-script-nonce}';

    /**
     * Automatically replace nonce placeholders in rendered output.
     * Must remain true so Phase 2 nonce attributes work without controller changes.
     */
    public bool $autoNonce = true;
}
