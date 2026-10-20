/**
 * Dashboard landing live refresh: polls metrics + today's schedule, refreshes
 * immediately on `appointment:changed`, pauses while the tab is hidden, and tears
 * itself down when SPA navigation leaves the dashboard.
 */

import { getBaseUrl } from '../../utils/url-helpers.js';

export function initDashboardRefresh() {
    const container = document.getElementById('metrics-container');
    if (!container || container.dataset.refreshInitialized === 'true') return;
    container.dataset.refreshInitialized = 'true';

    const METRICS_INTERVAL  = 15000;
    const SCHEDULE_INTERVAL = 30000;
    const RETRY_DELAY       = 10000;
    const AUTH_RETRY_DELAY  = 3000;
    const MAX_AUTH_RETRIES  = 2;

    let metricsTimer   = null;
    let scheduleTimer  = null;
    let abortController = null;  // guards the in-flight schedule fetch
    let isRefreshingMetrics = false;
    let authFailureCount = 0;

    function handleUnauthorized(message) {
        authFailureCount += 1;

        if (authFailureCount < MAX_AUTH_RETRIES) {
            console.warn(`[Dashboard] ${message}; retrying shortly (${authFailureCount}/${MAX_AUTH_RETRIES - 1})`);
            metricsTimer = setTimeout(refreshMetrics, AUTH_RETRY_DELAY);
            scheduleTimer = setTimeout(refreshSchedule, AUTH_RETRY_DELAY);
            return;
        }

        window.location.href = `${getBaseUrl()}/auth/login`;
    }

    // ─── Unified cleanup (called on SPA nav away + tab hide) ────────────────
    function cleanup() {
        if (metricsTimer)  { clearTimeout(metricsTimer);  metricsTimer  = null; }
        if (scheduleTimer) { clearTimeout(scheduleTimer); scheduleTimer = null; }
        if (abortController) { abortController.abort(); abortController = null; }
        window.removeEventListener('appointment:changed', onAppointmentChanged);
    }

    // ─── Metrics polling ────────────────────────────────────────────────────
    function refreshMetrics() {
        if (document.hidden || isRefreshingMetrics) return;
        const metricsEl = document.getElementById('metrics-container');
        if (!metricsEl) { cleanup(); return; }

        isRefreshingMetrics = true;
        metricsEl.classList.add('loading-pulse');

        fetch(`${getBaseUrl()}/dashboard/api/metrics`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async r => {
            if (r.status === 401) {
                throw { status: 401, message: 'metrics endpoint returned 401' };
            }

            if (!r.ok) {
                throw { status: r.status, message: `metrics endpoint returned ${r.status}` };
            }

            return r.json();
        })
        .then(data => {
            authFailureCount = 0;
            const m = data.data || data;
            updateValue('metric-today',            m.total);
            updateValue('metric-customers',        m.customers);
            updateValue('metric-pending-payments', m.pending_payments);
            updateText('metric-revenue',           m.revenue_formatted);
            metricsEl.classList.remove('loading-pulse');
            isRefreshingMetrics = false;
            metricsTimer = setTimeout(refreshMetrics, METRICS_INTERVAL);
        })
        .catch(err => {
            if (err?.status === 401) {
                metricsEl.classList.remove('loading-pulse');
                isRefreshingMetrics = false;
                handleUnauthorized(err.message || 'metrics polling unauthorized');
                return;
            }

            console.error('[Dashboard] Metrics refresh failed:', err);
            metricsEl.classList.remove('loading-pulse');
            isRefreshingMetrics = false;
            metricsTimer = setTimeout(refreshMetrics, RETRY_DELAY);
        });
    }

    function updateValue(id, val) {
        if (val === undefined || val === null) return;
        const el = document.getElementById(id);
        if (el && parseInt(el.textContent) !== val) { el.textContent = val; }
    }

    // Text (non-numeric) update — used for the pre-formatted currency value.
    function updateText(id, val) {
        if (val === undefined || val === null) return;
        const el = document.getElementById(id);
        if (el && el.textContent !== String(val)) { el.textContent = String(val); }
    }

    // ─── Schedule polling ────────────────────────────────────────────────────
    function refreshSchedule() {
        const body = document.getElementById('dashboard-schedule-body');
        if (!body) { cleanup(); return; }

        // Abort any in-flight request before starting a new one
        if (abortController) { abortController.abort(); }
        abortController = new AbortController();

        body.classList.add('opacity-50', 'transition-opacity', 'duration-150');

        fetch(`${getBaseUrl()}/dashboard/api/schedule`, {
            signal: abortController.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async r => {
            if (r.status === 401) {
                throw { status: 401, message: 'schedule endpoint returned 401' };
            }

            if (!r.ok) {
                throw { status: r.status, message: `schedule endpoint returned ${r.status}` };
            }

            return r.text();
        })
        .then(html => {
            authFailureCount = 0;
            // Only swap if content changed — avoids unnecessary repaints
            if (body.innerHTML !== html) { body.innerHTML = html; }
            body.classList.remove('opacity-50');
            abortController = null;
            scheduleTimer = setTimeout(refreshSchedule, SCHEDULE_INTERVAL);
        })
        .catch(err => {
            if (err.name === 'AbortError') return; // intentional — ignore

            if (err?.status === 401) {
                body.classList.remove('opacity-50');
                abortController = null;
                handleUnauthorized(err.message || 'schedule polling unauthorized');
                return;
            }

            console.error('[Dashboard] Schedule refresh failed:', err);
            body.classList.remove('opacity-50');
            scheduleTimer = setTimeout(refreshSchedule, RETRY_DELAY);
        });
    }

    // ─── appointment:changed — immediate schedule refresh ───────────────────
    function onAppointmentChanged() {
        if (scheduleTimer) { clearTimeout(scheduleTimer); scheduleTimer = null; }
        refreshSchedule();
    }
    window.addEventListener('appointment:changed', onAppointmentChanged);

    // ─── Visibility change ───────────────────────────────────────────────────
    function onVisibilityChange() {
        if (!document.getElementById('metrics-container')) {
            // Edge case: metrics-container gone after SPA nav — full teardown
            cleanup();
            document.removeEventListener('visibilitychange', onVisibilityChange);
            return;
        }
        if (!document.hidden) {
            refreshMetrics();
            refreshSchedule();
        } else {
            // Tab hidden — pause timers and abort in-flight requests ONLY.
            // Do NOT remove appointment:changed listener — it must survive tab hide
            // so mutations made on return to tab still trigger immediate refresh.
            if (metricsTimer)    { clearTimeout(metricsTimer);  metricsTimer  = null; }
            if (scheduleTimer)   { clearTimeout(scheduleTimer); scheduleTimer = null; }
            if (abortController) { abortController.abort();     abortController = null; }
        }
    }

    // ─── SPA navigation away from dashboard ─────────────────────────────────
    function onSpaNavigated() {
        if (!document.getElementById('metrics-container')) {
            cleanup(); // full teardown including appointment:changed listener
            document.removeEventListener('spa:navigated', onSpaNavigated);
            document.removeEventListener('visibilitychange', onVisibilityChange);
        }
    }

    document.addEventListener('visibilitychange', onVisibilityChange);
    document.addEventListener('spa:navigated', onSpaNavigated);

    // ─── Bootstrap ──────────────────────────────────────────────────────────
    refreshMetrics();
    refreshSchedule();
}
