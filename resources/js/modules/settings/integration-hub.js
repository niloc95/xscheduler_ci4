import { apiRequest } from '../../core/api.js';

const API_BASE = '/api/v1/integrations';

function showToast(type, title, message) {
    if (window.XSNotify?.toast) {
        window.XSNotify.toast({ type, title, message, autoClose: type !== 'error', duration: type !== 'error' ? 4000 : undefined });
        return;
    }
    console.error(message);
}

function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('hidden');
    el.classList.add('flex');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('hidden');
    el.classList.remove('flex');
}

function setButtonLoading(btn, loading) {
    if (!btn) return;
    btn.disabled = loading;
    if (loading) {
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined text-base animate-spin">progress_activity</span>';
    } else if (btn.dataset.originalText) {
        btn.innerHTML = btn.dataset.originalText;
    }
}

async function callApi(intent, channel, body = {}) {
    const endpoints = { save: `${API_BASE}/save`, test: `${API_BASE}/test`, disconnect: `${API_BASE}/disconnect` };
    const { response, payload } = await apiRequest(endpoints[intent], {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...body, channel }),
    });
    return { ok: response.ok, message: payload?.data?.message || payload?.error?.message || 'Unknown error' };
}

function reloadPage() {
    // Preserve the integrations tab on reload via hash
    const url = new URL(window.location.href);
    url.hash = '#integrations';
    window.location.href = url.toString();
}

// ── Analytics ─────────────────────────────────────────────────────────────────

function wireAnalytics() {
    const saveBtn       = document.getElementById('analytics-save-btn');
    const providerSel   = document.getElementById('analytics-provider');
    const ga4Fields     = document.getElementById('analytics-ga4-fields');
    const matomoFields  = document.getElementById('analytics-matomo-fields');

    // Show/hide provider-specific fields when the select changes
    if (providerSel && ga4Fields && matomoFields) {
        const toggleFields = () => {
            const v = providerSel.value;
            ga4Fields.classList.toggle('hidden', v !== 'google');
            matomoFields.classList.toggle('hidden', v !== 'matomo');
        };
        providerSel.addEventListener('change', toggleFields);
    }

    if (!saveBtn) return;

    saveBtn.addEventListener('click', async () => {
        const provider  = providerSel?.value ?? 'none';
        const gaId      = document.getElementById('analytics-id')?.value.trim() ?? '';
        const matomoUrl = document.getElementById('analytics-matomo-url')?.value.trim() ?? '';
        const matomoSid = document.getElementById('analytics-matomo-site-id')?.value.trim() ?? '1';

        if (provider === 'google' && !gaId) {
            showToast('error', 'Validation', 'Measurement ID (G-XXXXXXXXXX) is required.');
            return;
        }
        if (provider === 'matomo' && !matomoUrl) {
            showToast('error', 'Validation', 'Matomo URL is required.');
            return;
        }

        // analytics_id stores G-XXXXXXXXXX for GA4, or the Matomo URL for Matomo
        const analyticsId = provider === 'google' ? gaId : (provider === 'matomo' ? matomoUrl : '');

        setButtonLoading(saveBtn, true);
        try {
            const { response, payload } = await apiRequest('/api/v1/settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    'integrations.analytics': provider,
                    'integrations.analytics_id': analyticsId,
                    'integrations.analytics_site_id': provider === 'matomo' ? matomoSid : '',
                }),
            });

            if (response.ok) {
                const label = { none: 'Disabled', google: 'Google Analytics', matomo: 'Matomo' }[provider] ?? provider;
                showToast('success', 'Analytics', `Analytics set to ${label}.`);
                closeModal('analytics-modal');
                reloadPage();
            } else {
                const msg = payload?.error?.message || 'Failed to save analytics setting.';
                showToast('error', 'Analytics', msg);
            }
        } finally {
            setButtonLoading(saveBtn, false);
        }
    });
}

// ── Webhook ──────────────────────────────────────────────────────────────────

function wireWebhook() {
    const saveBtn = document.getElementById('webhook-save-btn');
    if (!saveBtn) return;

    saveBtn.addEventListener('click', async () => {
        const url    = document.getElementById('webhook-url')?.value.trim() ?? '';
        const secret = document.getElementById('webhook-secret')?.value ?? '';
        const events = [...document.querySelectorAll('.webhook-event-checkbox:checked')].map(el => el.value);

        if (!url) { showToast('error', 'Validation', 'Endpoint URL is required.'); return; }

        setButtonLoading(saveBtn, true);
        try {
            const result = await callApi('save', 'webhook', { url, secret, events });
            if (result.ok) {
                showToast('success', 'Webhooks', result.message);
                closeModal('webhook-modal');
                reloadPage();
            } else {
                showToast('error', 'Webhooks', result.message);
            }
        } finally {
            setButtonLoading(saveBtn, false);
        }
    });
}

// ── Stripe ────────────────────────────────────────────────────────────────────

function wireStripe() {
    const saveBtn = document.getElementById('stripe-save-btn');
    if (!saveBtn) return;

    saveBtn.addEventListener('click', async () => {
        const secretKey      = document.getElementById('stripe-secret-key')?.value ?? '';
        const publishableKey = document.getElementById('stripe-publishable-key')?.value.trim() ?? '';
        const webhookSecret  = document.getElementById('stripe-webhook-secret')?.value ?? '';
        const currency       = document.getElementById('stripe-currency')?.value ?? 'usd';

        setButtonLoading(saveBtn, true);
        try {
            const result = await callApi('save', 'stripe', { secret_key: secretKey, publishable_key: publishableKey, webhook_secret: webhookSecret, currency });
            if (result.ok) {
                showToast('success', 'Stripe', result.message);
                closeModal('stripe-modal');
                reloadPage();
            } else {
                showToast('error', 'Stripe', result.message);
            }
        } finally {
            setButtonLoading(saveBtn, false);
        }
    });
}

// ── Zoom ──────────────────────────────────────────────────────────────────────

function wireZoom() {
    const saveBtn = document.getElementById('zoom-save-btn');
    if (!saveBtn) return;

    saveBtn.addEventListener('click', async () => {
        const accountId    = document.getElementById('zoom-account-id')?.value.trim() ?? '';
        const clientId     = document.getElementById('zoom-client-id')?.value.trim() ?? '';
        const clientSecret = document.getElementById('zoom-client-secret')?.value ?? '';

        if (!accountId) { showToast('error', 'Validation', 'Account ID is required.'); return; }
        if (!clientId)  { showToast('error', 'Validation', 'Client ID is required.'); return; }

        setButtonLoading(saveBtn, true);
        try {
            const result = await callApi('save', 'zoom', { account_id: accountId, client_id: clientId, client_secret: clientSecret });
            if (result.ok) {
                showToast('success', 'Zoom', result.message);
                closeModal('zoom-modal');
                reloadPage();
            } else {
                showToast('error', 'Zoom', result.message);
            }
        } finally {
            setButtonLoading(saveBtn, false);
        }
    });
}

// ── Google Calendar ───────────────────────────────────────────────────────────

function wireGoogleCalendar() {
    const saveBtn  = document.getElementById('google-calendar-save-btn');
    const copyBtn  = document.getElementById('gc-copy-redirect-uri');

    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            const input = copyBtn.closest('.flex')?.querySelector('input');
            if (!input) return;
            navigator.clipboard?.writeText(input.value).then(() => {
                showToast('success', 'Copied', 'Redirect URI copied to clipboard.');
            }).catch(() => {
                input.select();
                document.execCommand('copy');
            });
        });
    }

    if (!saveBtn) return;

    saveBtn.addEventListener('click', async () => {
        const clientId     = document.getElementById('gc-client-id')?.value.trim() ?? '';
        const clientSecret = document.getElementById('gc-client-secret')?.value ?? '';

        if (!clientId) { showToast('error', 'Validation', 'Client ID is required.'); return; }

        setButtonLoading(saveBtn, true);
        try {
            const result = await callApi('save', 'google_calendar', { client_id: clientId, client_secret: clientSecret });
            if (result.ok) {
                showToast('success', 'Google Calendar', result.message);
                closeModal('google-calendar-modal');
                reloadPage();
            } else {
                showToast('error', 'Google Calendar', result.message);
            }
        } finally {
            setButtonLoading(saveBtn, false);
        }
    });
}

// ── Jitsi ─────────────────────────────────────────────────────────────────────

function wireJitsi() {
    const saveBtn = document.getElementById('jitsi-save-btn');
    if (!saveBtn) return;

    saveBtn.addEventListener('click', async () => {
        const serverUrl = document.getElementById('jitsi-server-url')?.value.trim() ?? '';
        const appId     = document.getElementById('jitsi-app-id')?.value.trim() ?? '';
        const apiKey    = document.getElementById('jitsi-api-key')?.value ?? '';

        setButtonLoading(saveBtn, true);
        try {
            const result = await callApi('save', 'jitsi', { server_url: serverUrl, app_id: appId, api_key: apiKey });
            if (result.ok) {
                showToast('success', 'Jitsi Meet', result.message);
                closeModal('jitsi-modal');
                reloadPage();
            } else {
                showToast('error', 'Jitsi Meet', result.message);
            }
        } finally {
            setButtonLoading(saveBtn, false);
        }
    });
}

// ── PayFast ───────────────────────────────────────────────────────────────────

function wirePayFast() {
    const saveBtn = document.getElementById('payfast-save-btn');
    if (!saveBtn) return;

    saveBtn.addEventListener('click', async () => {
        const merchantId  = document.getElementById('payfast-merchant-id')?.value.trim() ?? '';
        const merchantKey = document.getElementById('payfast-merchant-key')?.value ?? '';
        const passphrase  = document.getElementById('payfast-passphrase')?.value ?? '';
        const sandbox     = document.getElementById('payfast-sandbox')?.checked ?? true;

        if (!merchantId) { showToast('error', 'Validation', 'Merchant ID is required.'); return; }

        setButtonLoading(saveBtn, true);
        try {
            const result = await callApi('save', 'payfast', { merchant_id: merchantId, merchant_key: merchantKey, passphrase, sandbox });
            if (result.ok) {
                showToast('success', 'PayFast', result.message);
                closeModal('payfast-modal');
                reloadPage();
            } else {
                showToast('error', 'PayFast', result.message);
            }
        } finally {
            setButtonLoading(saveBtn, false);
        }
    });
}

// ── Global test / disconnect buttons ─────────────────────────────────────────

function wireActionButtons() {
    document.querySelectorAll('[data-integration-action]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const intent  = btn.dataset.integrationAction;
            const channel = btn.dataset.channel;
            if (!intent || !channel) return;

            const label = channel.replace('_', ' ');

            if (intent === 'disconnect') {
                const ok = await window.XSConfirm.show({
                    title: 'Disconnect Integration',
                    message: `Disconnect ${label}? This cannot be undone.`,
                    confirmText: 'Disconnect',
                    danger: true,
                });
                if (!ok) return;
            }

            setButtonLoading(btn, true);
            try {
                const result = await callApi(intent, channel);
                if (result.ok) {
                    showToast('success', label, result.message);
                    reloadPage();
                } else {
                    showToast('error', label, result.message);
                }
            } finally {
                setButtonLoading(btn, false);
            }
        });
    });
}

// ── Modal open/close wiring ───────────────────────────────────────────────────

function wireModals() {
    document.querySelectorAll('[data-open-modal]').forEach(btn => {
        btn.addEventListener('click', () => openModal(btn.dataset.openModal));
    });
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.dataset.closeModal));
    });
    // Close on backdrop click
    ['analytics-modal', 'webhook-modal', 'google-calendar-modal', 'stripe-modal', 'zoom-modal', 'jitsi-modal', 'payfast-modal'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', e => { if (e.target === el) closeModal(id); });
    });
    // Close on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            ['analytics-modal', 'webhook-modal', 'google-calendar-modal', 'stripe-modal', 'zoom-modal', 'jitsi-modal', 'payfast-modal'].forEach(closeModal);
        }
    });
}

// ── Entry point ───────────────────────────────────────────────────────────────

export function initIntegrationHub() {
    if (!document.getElementById('integration-cards-grid')) return;

    wireModals();
    wireAnalytics();
    wireWebhook();
    wireGoogleCalendar();
    wireStripe();
    wireZoom();
    wireJitsi();
    wirePayFast();
    wireActionButtons();
}
