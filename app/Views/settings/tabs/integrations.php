<?php
/**
 * Settings Tab: Integrations Hub
 *
 * Section A — simple settings form (analytics selector, preserved for initTabForm() wiring)
 * Section B — integration cards grid (Webhooks, Google Calendar, Stripe, Zoom)
 * Section C — modals (Webhook, Stripe, Zoom; Google Calendar uses OAuth redirect)
 */

$webhookIntegration        = $webhookIntegration        ?? ['is_active' => false, 'health_status' => 'unknown', 'last_tested_at' => '', 'config' => ['url' => '', 'events' => []]];
$googleCalendarIntegration = $googleCalendarIntegration ?? ['is_active' => false, 'health_status' => 'unknown', 'last_tested_at' => '', 'has_tokens' => false, 'calendar_id' => ''];
$googleCalendarConfigured  = $googleCalendarConfigured  ?? false;
$stripeIntegration         = $stripeIntegration         ?? ['is_active' => false, 'health_status' => 'unknown', 'last_tested_at' => '', 'has_secret_key' => false, 'publishable_key' => '', 'currency' => 'usd'];
$zoomIntegration           = $zoomIntegration           ?? ['is_active' => false, 'health_status' => 'unknown', 'last_tested_at' => '', 'has_credentials' => false];
$webhookEvents             = $webhookEvents             ?? [];

$activeWebhookEvents = $webhookIntegration['config']['events'] ?? [];

function integrationBadge(bool $active, string $health): string {
    if ($active && $health === 'healthy') {
        return '<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                    <span class="material-symbols-outlined text-xs">check_circle</span> Connected
                </span>';
    }
    if ($active && $health === 'unhealthy') {
        return '<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300">
                    <span class="material-symbols-outlined text-xs">warning</span> Degraded
                </span>';
    }
    if ($active) {
        return '<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                    <span class="material-symbols-outlined text-xs">link</span> Configured
                </span>';
    }
    return '<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                <span class="material-symbols-outlined text-xs">link_off</span> Not Connected
            </span>';
}
?>

<?php
$analyticsProvider = $settings['integrations.analytics'] ?? 'none';
$analyticsLabels   = ['none' => 'Disabled', 'google' => 'Google Analytics', 'matomo' => 'Matomo'];
$analyticsActive   = $analyticsProvider !== 'none';
?>

<section id="panel-integrations" class="tab-panel hidden">

<!-- Integration Cards Grid -->
<div id="integration-cards-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">

    <!-- Analytics card -->
    <div class="xs-card" data-integration-card="analytics">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-orange-500">analytics</span>
                    <div>
                        <h3 class="xs-card-title">Analytics</h3>
                        <p class="xs-card-subtitle">Track visitor and booking activity</p>
                    </div>
                </div>
            </div>
            <div class="xs-card-actions">
                <?php if ($analyticsActive): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                        <span class="material-symbols-outlined text-xs">bar_chart</span>
                        <?= esc($analyticsLabels[$analyticsProvider] ?? $analyticsProvider) ?>
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                        <span class="material-symbols-outlined text-xs">link_off</span>
                        Disabled
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="xs-card-body">
            <p class="text-sm text-gray-600 dark:text-gray-400">Connect a web analytics provider to track public booking page activity.</p>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-start gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-primary" data-open-modal="analytics-modal">
                    <span class="material-symbols-outlined text-base">settings</span>
                    Configure
                </button>
            </div>
        </div>
    </div>

    <!-- Webhooks card -->
    <div class="xs-card" data-integration-card="webhook">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-indigo-500">webhook</span>
                    <div>
                        <h3 class="xs-card-title">Webhooks</h3>
                        <p class="xs-card-subtitle">Trigger Zapier, Make, n8n automations</p>
                    </div>
                </div>
            </div>
            <div class="xs-card-actions">
                <?= integrationBadge((bool)($webhookIntegration['is_active'] ?? false), $webhookIntegration['health_status'] ?? 'unknown') ?>
            </div>
        </div>
        <div class="xs-card-body">
            <p class="text-sm text-gray-600 dark:text-gray-400">Send appointment event data to any external URL when events occur.</p>
            <?php if (!empty($webhookIntegration['config']['url'])): ?>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500 font-mono truncate" title="<?= esc($webhookIntegration['config']['url']) ?>">
                    <?= esc($webhookIntegration['config']['url']) ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-start gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-primary" data-open-modal="webhook-modal">
                    <span class="material-symbols-outlined text-base">settings</span>
                    Configure
                </button>
                <?php if ($webhookIntegration['is_active']): ?>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-integration-action="test" data-channel="webhook">
                    <span class="material-symbols-outlined text-base">wifi_tethering</span>
                    Test
                </button>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost text-red-600 dark:text-red-400 hover:text-red-700" data-integration-action="disconnect" data-channel="webhook">
                    <span class="material-symbols-outlined text-base">link_off</span>
                    Disconnect
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Google Calendar card -->
    <?php
    $gcHasCredentials = (bool) ($googleCalendarIntegration['has_credentials'] ?? false);
    $gcHasTokens      = (bool) ($googleCalendarIntegration['has_tokens'] ?? false);
    $gcIsActive       = (bool) ($googleCalendarIntegration['is_active'] ?? false);
    ?>
    <div class="xs-card" data-integration-card="google_calendar">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-red-500">calendar_month</span>
                    <div>
                        <h3 class="xs-card-title">Google Calendar</h3>
                        <p class="xs-card-subtitle">Two-way appointment sync</p>
                    </div>
                </div>
            </div>
            <div class="xs-card-actions">
                <?= integrationBadge($gcIsActive, $googleCalendarIntegration['health_status'] ?? 'unknown') ?>
            </div>
        </div>
        <div class="xs-card-body">
            <p class="text-sm text-gray-600 dark:text-gray-400">Sync appointments to Google Calendar and prevent double-bookings.</p>
            <?php if ($gcHasCredentials): ?>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                    Client ID: <span class="font-mono"><?= esc($googleCalendarIntegration['client_id_hint'] ?? '') ?></span>
                    <?php if ($gcHasTokens): ?>
                        &nbsp;&middot;&nbsp;Calendar: <span class="font-mono"><?= esc($googleCalendarIntegration['calendar_id'] ?? 'primary') ?></span>
                    <?php else: ?>
                        &nbsp;&middot;&nbsp;<span class="text-amber-600 dark:text-amber-400">OAuth not yet authorised</span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-start gap-2">
                <!-- Always show Configure to allow updating credentials -->
                <button type="button" class="xs-btn xs-btn-sm xs-btn-primary" data-open-modal="google-calendar-modal">
                    <span class="material-symbols-outlined text-base">settings</span>
                    Configure
                </button>
                <?php if ($gcHasCredentials && !$gcIsActive): ?>
                <a href="<?= base_url('oauth/google/authorize') ?>" class="xs-btn xs-btn-sm xs-btn-ghost">
                    <span class="material-symbols-outlined text-base">login</span>
                    Connect with Google
                </a>
                <?php endif; ?>
                <?php if ($gcIsActive): ?>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-integration-action="test" data-channel="google_calendar">
                    <span class="material-symbols-outlined text-base">wifi_tethering</span>
                    Test
                </button>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost text-red-600 dark:text-red-400 hover:text-red-700" data-integration-action="disconnect" data-channel="google_calendar">
                    <span class="material-symbols-outlined text-base">link_off</span>
                    Disconnect
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stripe card -->
    <div class="xs-card" data-integration-card="stripe">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-purple-500">credit_card</span>
                    <div>
                        <h3 class="xs-card-title">Stripe</h3>
                        <p class="xs-card-subtitle">Deposits &amp; no-show fee collection</p>
                    </div>
                </div>
            </div>
            <div class="xs-card-actions">
                <?= integrationBadge((bool)($stripeIntegration['is_active'] ?? false), $stripeIntegration['health_status'] ?? 'unknown') ?>
            </div>
        </div>
        <div class="xs-card-body">
            <p class="text-sm text-gray-600 dark:text-gray-400">Accept deposits and enforce no-show fees at booking.</p>
            <?php if (!empty($stripeIntegration['secret_key_hint'])): ?>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500 font-mono">Key: <?= esc($stripeIntegration['secret_key_hint']) ?></p>
            <?php endif; ?>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-start gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-primary" data-open-modal="stripe-modal">
                    <span class="material-symbols-outlined text-base">settings</span>
                    Configure
                </button>
                <?php if ($stripeIntegration['is_active']): ?>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-integration-action="test" data-channel="stripe">
                    <span class="material-symbols-outlined text-base">wifi_tethering</span>
                    Test
                </button>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost text-red-600 dark:text-red-400 hover:text-red-700" data-integration-action="disconnect" data-channel="stripe">
                    <span class="material-symbols-outlined text-base">link_off</span>
                    Disconnect
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Zoom card -->
    <div class="xs-card" data-integration-card="zoom">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-blue-500">video_call</span>
                    <div>
                        <h3 class="xs-card-title">Zoom</h3>
                        <p class="xs-card-subtitle">Auto-generate meeting links</p>
                    </div>
                </div>
            </div>
            <div class="xs-card-actions">
                <?= integrationBadge((bool)($zoomIntegration['is_active'] ?? false), $zoomIntegration['health_status'] ?? 'unknown') ?>
            </div>
        </div>
        <div class="xs-card-body">
            <p class="text-sm text-gray-600 dark:text-gray-400">Automatically create Zoom meeting links for virtual appointments.</p>
            <?php if (!empty($zoomIntegration['account_id_hint'])): ?>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500 font-mono">Account: <?= esc($zoomIntegration['account_id_hint']) ?></p>
            <?php endif; ?>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-start gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-primary" data-open-modal="zoom-modal">
                    <span class="material-symbols-outlined text-base">settings</span>
                    Configure
                </button>
                <?php if ($zoomIntegration['is_active']): ?>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-integration-action="test" data-channel="zoom">
                    <span class="material-symbols-outlined text-base">wifi_tethering</span>
                    Test
                </button>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost text-red-600 dark:text-red-400 hover:text-red-700" data-integration-action="disconnect" data-channel="zoom">
                    <span class="material-symbols-outlined text-base">link_off</span>
                    Disconnect
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <!-- Jitsi card -->
    <div class="xs-card" data-integration-card="jitsi">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-teal-500">videocam</span>
                    <div>
                        <h3 class="xs-card-title">Jitsi Meet</h3>
                        <p class="xs-card-subtitle">Open-source video meetings</p>
                    </div>
                </div>
            </div>
            <div class="xs-card-actions">
                <?= integrationBadge((bool)($jitsiIntegration['is_active'] ?? false), $jitsiIntegration['health_status'] ?? 'unknown') ?>
            </div>
        </div>
        <div class="xs-card-body">
            <p class="text-sm text-gray-600 dark:text-gray-400">Self-hosted or public Jitsi Meet for virtual appointments. No per-meeting fees.</p>
            <?php if (!empty($jitsiIntegration['server_url'])): ?>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500 font-mono truncate" title="<?= esc($jitsiIntegration['server_url']) ?>"><?= esc($jitsiIntegration['server_url']) ?></p>
            <?php endif; ?>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-start gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-primary" data-open-modal="jitsi-modal">
                    <span class="material-symbols-outlined text-base">settings</span>
                    Configure
                </button>
                <?php if ($jitsiIntegration['is_active'] ?? false): ?>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-integration-action="test" data-channel="jitsi">
                    <span class="material-symbols-outlined text-base">wifi_tethering</span>
                    Test
                </button>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost text-red-600 dark:text-red-400 hover:text-red-700" data-integration-action="disconnect" data-channel="jitsi">
                    <span class="material-symbols-outlined text-base">link_off</span>
                    Disconnect
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PayFast card -->
    <div class="xs-card" data-integration-card="payfast">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl text-green-600">payments</span>
                    <div>
                        <h3 class="xs-card-title">PayFast</h3>
                        <p class="xs-card-subtitle">South African payment gateway</p>
                    </div>
                </div>
            </div>
            <div class="xs-card-actions">
                <?= integrationBadge((bool)($payfastIntegration['is_active'] ?? false), $payfastIntegration['health_status'] ?? 'unknown') ?>
            </div>
        </div>
        <div class="xs-card-body">
            <p class="text-sm text-gray-600 dark:text-gray-400">Accept deposits and booking fees via PayFast — supports EFT, credit card, and Instant EFT.</p>
            <?php if (!empty($payfastIntegration['merchant_id_hint'])): ?>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                    Merchant: <span class="font-mono"><?= esc($payfastIntegration['merchant_id_hint']) ?></span>
                    <?php if ($payfastIntegration['sandbox'] ?? true): ?>
                        <span class="ml-2 inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Sandbox</span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-start gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-primary" data-open-modal="payfast-modal">
                    <span class="material-symbols-outlined text-base">settings</span>
                    Configure
                </button>
                <?php if ($payfastIntegration['is_active'] ?? false): ?>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-integration-action="test" data-channel="payfast">
                    <span class="material-symbols-outlined text-base">wifi_tethering</span>
                    Test
                </button>
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost text-red-600 dark:text-red-400 hover:text-red-700" data-integration-action="disconnect" data-channel="payfast">
                    <span class="material-symbols-outlined text-base">link_off</span>
                    Disconnect
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /integration-cards-grid -->

<!-- Section C: Modals -->

<!-- Webhook Modal -->
<div id="webhook-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="webhook-modal-title">
    <div class="xs-card max-w-lg w-full mx-4 shadow-xl">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <h3 id="webhook-modal-title" class="xs-card-title">Configure Webhooks</h3>
                <p class="xs-card-subtitle">Receive real-time appointment events</p>
            </div>
            <div class="xs-card-actions">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" data-close-modal="webhook-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        <div class="xs-card-body space-y-4">
            <div class="form-field">
                <label class="form-label" for="webhook-url">Endpoint URL <span class="text-red-500">*</span></label>
                <input id="webhook-url" type="url" class="form-input" placeholder="https://hooks.zapier.com/..." value="<?= esc($webhookIntegration['config']['url'] ?? '') ?>">
            </div>
            <div class="form-field">
                <label class="form-label" for="webhook-secret">Signing Secret <span class="text-xs text-gray-400">(optional)</span></label>
                <input id="webhook-secret" type="password" class="form-input" placeholder="Leave blank to keep existing" autocomplete="new-password">
                <p class="form-help">Used to sign payloads with HMAC-SHA256 via <code class="font-mono text-xs">X-Webhook-Signature</code> header.</p>
            </div>
            <div class="form-field">
                <label class="form-label">Events to send</label>
                <div class="space-y-1.5 mt-1">
                    <?php foreach ($webhookEvents as $eventKey => $eventLabel): ?>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" class="form-checkbox webhook-event-checkbox" value="<?= esc($eventKey) ?>"
                            <?= in_array($eventKey, $activeWebhookEvents, true) || empty($activeWebhookEvents) ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-700 dark:text-gray-300"><?= esc($eventLabel) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-end gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-close-modal="webhook-modal">Cancel</button>
                <button type="button" id="webhook-save-btn" class="xs-btn xs-btn-sm xs-btn-primary">
                    <span class="material-symbols-outlined text-base">save</span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Stripe Modal -->
<div id="stripe-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="stripe-modal-title">
    <div class="xs-card max-w-lg w-full mx-4 shadow-xl">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <h3 id="stripe-modal-title" class="xs-card-title">Configure Stripe</h3>
                <p class="xs-card-subtitle">Enter your Stripe API keys</p>
            </div>
            <div class="xs-card-actions">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" data-close-modal="stripe-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        <div class="xs-card-body space-y-4">
            <div class="form-field">
                <label class="form-label" for="stripe-secret-key">Secret Key <span class="text-red-500">*</span></label>
                <input id="stripe-secret-key" type="password" class="form-input font-mono" placeholder="<?= !empty($stripeIntegration['secret_key_hint']) ? esc($stripeIntegration['secret_key_hint']) : 'sk_live_... or sk_test_...' ?>" autocomplete="new-password">
                <p class="form-help">Keep this secret. Starts with <code class="font-mono text-xs">sk_live_</code> or <code class="font-mono text-xs">sk_test_</code>.</p>
            </div>
            <div class="form-field">
                <label class="form-label" for="stripe-publishable-key">Publishable Key <span class="text-red-500">*</span></label>
                <input id="stripe-publishable-key" type="text" class="form-input font-mono" placeholder="pk_live_... or pk_test_..." value="<?= esc($stripeIntegration['publishable_key'] ?? '') ?>">
            </div>
            <div class="form-field">
                <label class="form-label" for="stripe-webhook-secret">Webhook Secret <span class="text-xs text-gray-400">(optional)</span></label>
                <input id="stripe-webhook-secret" type="password" class="form-input font-mono" placeholder="whsec_... (leave blank to keep existing)" autocomplete="new-password">
            </div>
            <div class="form-field">
                <label class="form-label" for="stripe-currency">Currency</label>
                <select id="stripe-currency" class="form-input">
                    <?php foreach (['usd' => 'USD', 'eur' => 'EUR', 'gbp' => 'GBP', 'aud' => 'AUD', 'cad' => 'CAD', 'zar' => 'ZAR'] as $code => $label): ?>
                    <option value="<?= $code ?>" <?= ($stripeIntegration['currency'] ?? 'usd') === $code ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-end gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-close-modal="stripe-modal">Cancel</button>
                <button type="button" id="stripe-save-btn" class="xs-btn xs-btn-sm xs-btn-primary">
                    <span class="material-symbols-outlined text-base">save</span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Zoom Modal -->
<div id="zoom-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="zoom-modal-title">
    <div class="xs-card max-w-lg w-full mx-4 shadow-xl">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <h3 id="zoom-modal-title" class="xs-card-title">Configure Zoom</h3>
                <p class="xs-card-subtitle">Server-to-Server OAuth credentials</p>
            </div>
            <div class="xs-card-actions">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" data-close-modal="zoom-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        <div class="xs-card-body space-y-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">Create a Server-to-Server OAuth app in the <a href="https://marketplace.zoom.us/develop/create" target="_blank" rel="noopener" class="text-blue-600 hover:underline dark:text-blue-400">Zoom App Marketplace</a> and copy the credentials below.</p>
            <div class="form-field">
                <label class="form-label" for="zoom-account-id">Account ID <span class="text-red-500">*</span></label>
                <input id="zoom-account-id" type="text" class="form-input font-mono" placeholder="<?= !empty($zoomIntegration['account_id_hint']) ? esc($zoomIntegration['account_id_hint']) : 'Your Zoom Account ID' ?>">
            </div>
            <div class="form-field">
                <label class="form-label" for="zoom-client-id">Client ID <span class="text-red-500">*</span></label>
                <input id="zoom-client-id" type="text" class="form-input font-mono" placeholder="Your Zoom Client ID">
            </div>
            <div class="form-field">
                <label class="form-label" for="zoom-client-secret">Client Secret <span class="text-red-500">*</span></label>
                <input id="zoom-client-secret" type="password" class="form-input font-mono" placeholder="Leave blank to keep existing" autocomplete="new-password">
            </div>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-end gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-close-modal="zoom-modal">Cancel</button>
                <button type="button" id="zoom-save-btn" class="xs-btn xs-btn-sm xs-btn-primary">
                    <span class="material-symbols-outlined text-base">save</span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Analytics Modal -->
<div id="analytics-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="analytics-modal-title">
    <div class="xs-card max-w-md w-full mx-4 shadow-xl">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <h3 id="analytics-modal-title" class="xs-card-title">Configure Analytics</h3>
                <p class="xs-card-subtitle">Choose your analytics provider</p>
            </div>
            <div class="xs-card-actions">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" data-close-modal="analytics-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        <div class="xs-card-body space-y-4">
            <div class="form-field">
                <label class="form-label" for="analytics-provider">Provider</label>
                <select id="analytics-provider" class="form-input">
                    <option value="none"   <?= $analyticsProvider === 'none'   ? 'selected' : '' ?>>None (disabled)</option>
                    <option value="google" <?= $analyticsProvider === 'google' ? 'selected' : '' ?>>Google Analytics (GA4)</option>
                    <option value="matomo" <?= $analyticsProvider === 'matomo' ? 'selected' : '' ?>>Matomo</option>
                </select>
            </div>
            <div id="analytics-ga4-fields" class="<?= $analyticsProvider === 'google' ? '' : 'hidden' ?> space-y-3">
                <div class="form-field">
                    <label class="form-label" for="analytics-id">Measurement ID <span class="text-red-500">*</span></label>
                    <input id="analytics-id" type="text" class="form-input font-mono" placeholder="G-XXXXXXXXXX" value="<?= esc($settings['integrations.analytics_id'] ?? '') ?>">
                    <p class="form-help">Found in Google Analytics → Admin → Data Streams → your stream → Measurement ID.</p>
                </div>
            </div>
            <div id="analytics-matomo-fields" class="<?= $analyticsProvider === 'matomo' ? '' : 'hidden' ?> space-y-3">
                <div class="form-field">
                    <label class="form-label" for="analytics-matomo-url">Matomo URL <span class="text-red-500">*</span></label>
                    <input id="analytics-matomo-url" type="url" class="form-input" placeholder="https://analytics.example.com" value="<?= esc($settings['integrations.analytics_id'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label class="form-label" for="analytics-matomo-site-id">Site ID <span class="text-red-500">*</span></label>
                    <input id="analytics-matomo-site-id" type="number" class="form-input" placeholder="1" min="1" value="<?= esc($settings['integrations.analytics_site_id'] ?? '1') ?>">
                </div>
            </div>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-end gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-close-modal="analytics-modal">Cancel</button>
                <button type="button" id="analytics-save-btn" class="xs-btn xs-btn-sm xs-btn-primary">
                    <span class="material-symbols-outlined text-base">save</span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Google Calendar Modal -->
<div id="google-calendar-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="google-calendar-modal-title">
    <div class="xs-card max-w-lg w-full mx-4 shadow-xl">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <h3 id="google-calendar-modal-title" class="xs-card-title">Configure Google Calendar</h3>
                <p class="xs-card-subtitle">Enter your Google Cloud OAuth credentials</p>
            </div>
            <div class="xs-card-actions">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" data-close-modal="google-calendar-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        <div class="xs-card-body space-y-4">
            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 text-xs text-blue-800 dark:text-blue-200 space-y-1">
                <p class="font-medium">Setup instructions</p>
                <ol class="list-decimal list-inside space-y-0.5 text-blue-700 dark:text-blue-300">
                    <li>Open <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" class="underline">Google Cloud Console → Credentials</a></li>
                    <li>Create an OAuth 2.0 Client ID (type: Web application)</li>
                    <li>Add the Redirect URI below to Authorised redirect URIs</li>
                    <li>Paste Client ID and Client Secret here and save</li>
                    <li>Click <strong>Connect with Google</strong> on the card</li>
                </ol>
            </div>
            <div class="form-field">
                <label class="form-label">Redirect URI <span class="text-xs text-gray-400">(copy to Google Cloud Console)</span></label>
                <div class="flex items-center gap-2">
                    <input type="text" class="form-input font-mono text-xs bg-gray-50 dark:bg-gray-800" value="<?= esc(\App\Services\GoogleCalendarIntegrationService::getRedirectUri()) ?>" readonly>
                    <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon flex-shrink-0" id="gc-copy-redirect-uri" title="Copy to clipboard">
                        <span class="material-symbols-outlined text-base">content_copy</span>
                    </button>
                </div>
            </div>
            <div class="form-field">
                <label class="form-label" for="gc-client-id">Client ID <span class="text-red-500">*</span></label>
                <input id="gc-client-id" type="text" class="form-input font-mono" placeholder="<?= !empty($googleCalendarIntegration['client_id_hint']) ? esc($googleCalendarIntegration['client_id_hint']) : '123456789-abc....apps.googleusercontent.com' ?>">
            </div>
            <div class="form-field">
                <label class="form-label" for="gc-client-secret">Client Secret <span class="text-red-500">*</span></label>
                <input id="gc-client-secret" type="password" class="form-input font-mono" placeholder="<?= ($googleCalendarIntegration['has_credentials'] ?? false) ? 'Leave blank to keep existing' : 'GOCSPX-...' ?>" autocomplete="new-password">
            </div>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-end gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-close-modal="google-calendar-modal">Cancel</button>
                <button type="button" id="google-calendar-save-btn" class="xs-btn xs-btn-sm xs-btn-primary">
                    <span class="material-symbols-outlined text-base">save</span>
                    Save Credentials
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Jitsi Modal -->
<div id="jitsi-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="jitsi-modal-title">
    <div class="xs-card max-w-lg w-full mx-4 shadow-xl">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <h3 id="jitsi-modal-title" class="xs-card-title">Configure Jitsi Meet</h3>
                <p class="xs-card-subtitle">Public instance or self-hosted server</p>
            </div>
            <div class="xs-card-actions">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" data-close-modal="jitsi-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        <div class="xs-card-body space-y-4">
            <div class="form-field">
                <label class="form-label" for="jitsi-server-url">Server URL</label>
                <input id="jitsi-server-url" type="url" class="form-input" placeholder="https://meet.jit.si" value="<?= esc($jitsiIntegration['server_url'] ?? 'https://meet.jit.si') ?>">
                <p class="form-help">Leave as <code class="font-mono text-xs">https://meet.jit.si</code> to use the free public instance, or enter your self-hosted server URL.</p>
            </div>
            <div class="form-field">
                <label class="form-label" for="jitsi-app-id">App ID <span class="text-xs text-gray-400">(JaaS only)</span></label>
                <input id="jitsi-app-id" type="text" class="form-input font-mono" placeholder="vpaas-magic-cookie-...">
                <p class="form-help">Only required for <a href="https://jaas.8x8.vc" target="_blank" rel="noopener" class="text-blue-600 hover:underline dark:text-blue-400">Jitsi as a Service (JaaS)</a> on 8x8.vc.</p>
            </div>
            <div class="form-field">
                <label class="form-label" for="jitsi-api-key">API Key <span class="text-xs text-gray-400">(JaaS only, optional)</span></label>
                <input id="jitsi-api-key" type="password" class="form-input font-mono" placeholder="Leave blank to keep existing" autocomplete="new-password">
            </div>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-end gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-close-modal="jitsi-modal">Cancel</button>
                <button type="button" id="jitsi-save-btn" class="xs-btn xs-btn-sm xs-btn-primary">
                    <span class="material-symbols-outlined text-base">save</span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PayFast Modal -->
<div id="payfast-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="payfast-modal-title">
    <div class="xs-card max-w-lg w-full mx-4 shadow-xl">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <h3 id="payfast-modal-title" class="xs-card-title">Configure PayFast</h3>
                <p class="xs-card-subtitle">Enter your PayFast merchant credentials</p>
            </div>
            <div class="xs-card-actions">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" data-close-modal="payfast-modal" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        <div class="xs-card-body space-y-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">Find your credentials in the <a href="https://www.payfast.co.za/account/integration" target="_blank" rel="noopener" class="text-blue-600 hover:underline dark:text-blue-400">PayFast Merchant Account</a> under Integration Settings.</p>
            <div class="form-field">
                <label class="form-label" for="payfast-merchant-id">Merchant ID <span class="text-red-500">*</span></label>
                <input id="payfast-merchant-id" type="text" class="form-input font-mono" placeholder="<?= !empty($payfastIntegration['merchant_id_hint']) ? esc($payfastIntegration['merchant_id_hint']) : '10000100' ?>">
            </div>
            <div class="form-field">
                <label class="form-label" for="payfast-merchant-key">Merchant Key <span class="text-red-500">*</span></label>
                <input id="payfast-merchant-key" type="password" class="form-input font-mono" placeholder="Leave blank to keep existing" autocomplete="new-password">
            </div>
            <div class="form-field">
                <label class="form-label" for="payfast-passphrase">Passphrase <span class="text-xs text-gray-400">(optional)</span></label>
                <input id="payfast-passphrase" type="password" class="form-input font-mono" placeholder="Leave blank to keep existing" autocomplete="new-password">
                <p class="form-help">Set in your PayFast account under Security. Strengthens payment signature verification.</p>
            </div>
            <div class="form-field">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input id="payfast-sandbox" type="checkbox" class="form-checkbox" <?= ($payfastIntegration['sandbox'] ?? true) ? 'checked' : '' ?>>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Use sandbox (testing mode)</span>
                </label>
                <p class="form-help mt-1">Disable this when going live. Sandbox uses test credentials from <span class="font-mono text-xs">sandbox.payfast.co.za</span>.</p>
            </div>
        </div>
        <div class="xs-card-body border-t border-gray-100 dark:border-gray-700 pt-3">
            <div class="xs-actions-container justify-end gap-2">
                <button type="button" class="xs-btn xs-btn-sm xs-btn-ghost" data-close-modal="payfast-modal">Cancel</button>
                <button type="button" id="payfast-save-btn" class="xs-btn xs-btn-sm xs-btn-primary">
                    <span class="material-symbols-outlined text-base">save</span>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

</section><!-- /panel-integrations -->
