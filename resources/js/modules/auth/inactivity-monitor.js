import { withBaseUrl } from '../../utils/url-helpers.js';

// Aligned with app/Config/Session.php $expiration = 7200
const SESSION_MS  = 7200 * 1000;
const WARNING_MS  = 5 * 60 * 1000; // show modal 5 min before expiry
const PING_MAX_FAILURES = 2;
const PING_RETRY_DELAY_MS = 3000;

// Wall-clock evaluation cadence. setTimeout/setInterval are throttled or
// suspended in background tabs and across machine sleep, so the monitor never
// trusts tick counts — every check re-derives elapsed time from Date.now().
const CHECK_INTERVAL_MS = 30 * 1000;

// While the user is active, slide the server session forward periodically.
// The server expires the session 2h after the last HTTP request — client-only
// activity (mousemove/scroll) sends no requests, so without a keepalive the
// server clock and the client clock drift apart.
const KEEPALIVE_MS = 15 * 60 * 1000;

// Shared across tabs so activity in one tab prevents a stale warning in another.
const ACTIVITY_STORAGE_KEY  = 'xs-last-activity-at';
const KEEPALIVE_STORAGE_KEY = 'xs-last-keepalive-at';
const STORAGE_WRITE_THROTTLE_MS = 5000;

let checkTimer        = null;
let countdownTimer    = null;
let pingRetryTimer    = null;
let warningVisible    = false;
let warningDeadlineAt = 0;
let extendInFlight    = false;
let keepaliveInFlight = false;
let monitorEpoch      = 0;
let lastActivityAt    = 0;
let lastKeepaliveAt   = 0;
let lastStorageWriteAt = 0;

function clearCheckTimer() {
    clearInterval(checkTimer);
    checkTimer = null;
}

function clearCountdownTimer() {
    clearInterval(countdownTimer);
    countdownTimer = null;
}

function clearPingRetryTimer() {
    clearTimeout(pingRetryTimer);
    pingRetryTimer = null;
}

function redirectToLogin() {
    window.location.href = withBaseUrl('/auth/login');
}

// ── Shared timestamps (cross-tab) ──────────────────────────────────────────

function readSharedTimestamp(key) {
    try {
        const value = Number(window.localStorage.getItem(key));
        return Number.isFinite(value) ? value : 0;
    } catch (error) {
        return 0;
    }
}

function writeSharedTimestamp(key, value) {
    try {
        window.localStorage.setItem(key, String(value));
    } catch (error) {
        // Storage unavailable (privacy mode) — single-tab tracking still works.
    }
}

function effectiveLastActivity() {
    return Math.max(lastActivityAt, readSharedTimestamp(ACTIVITY_STORAGE_KEY));
}

function effectiveLastKeepalive() {
    return Math.max(lastKeepaliveAt, readSharedTimestamp(KEEPALIVE_STORAGE_KEY));
}

function markActivityNow({ forceStorageWrite = false } = {}) {
    const now = Date.now();
    lastActivityAt = now;
    if (forceStorageWrite || now - lastStorageWriteAt >= STORAGE_WRITE_THROTTLE_MS) {
        lastStorageWriteAt = now;
        writeSharedTimestamp(ACTIVITY_STORAGE_KEY, now);
    }
}

function markKeepaliveNow() {
    lastKeepaliveAt = Date.now();
    writeSharedTimestamp(KEEPALIVE_STORAGE_KEY, lastKeepaliveAt);
}

// ── DOM helpers ────────────────────────────────────────────────────────────

function getOrCreateModal() {
    let modal = document.getElementById('xs-session-warning-modal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'xs-session-warning-modal';
    modal.setAttribute('role', 'alertdialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'xs-session-title');
    modal.className = [
        'fixed inset-0 z-[9999] flex items-center justify-center p-4',
        'bg-black/50 backdrop-blur-sm',
        'hidden',
    ].join(' ');

    modal.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-sm w-full p-6 text-center">
            <span class="material-symbols-outlined text-4xl text-amber-500 mb-3 block">timer</span>
            <h2 id="xs-session-title" class="text-lg font-bold text-gray-900 dark:text-white mb-2">
                Session Expiring Soon
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                You'll be logged out in
            </p>
            <p id="xs-session-countdown"
               class="text-3xl font-mono font-bold text-amber-600 dark:text-amber-400 mb-4">
                5:00
            </p>
            <div class="flex gap-3 justify-center">
                <button id="xs-session-stay"
                        class="px-5 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium
                               hover:bg-primary-700 transition-colors focus:ring-2 focus:ring-primary-500
                               disabled:cursor-wait disabled:opacity-60">
                    Stay Logged In
                </button>
                <button id="xs-session-logout"
                        class="px-5 py-2 rounded-lg border border-gray-300 dark:border-gray-600
                               text-gray-700 dark:text-gray-300 text-sm font-medium
                               hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Log Out
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    modal.querySelector('#xs-session-stay').addEventListener('click', extendSession);
    modal.querySelector('#xs-session-logout').addEventListener('click', () => {
        window.location.href = withBaseUrl('/auth/logout');
    });

    return modal;
}

function setStayButtonPendingState(isPending) {
    const stayButton = document.getElementById('xs-session-stay');
    if (!stayButton) {
        return;
    }

    if (!stayButton.dataset.defaultLabel) {
        stayButton.dataset.defaultLabel = stayButton.textContent.trim();
    }

    stayButton.disabled = isPending;
    stayButton.setAttribute('aria-busy', isPending ? 'true' : 'false');
    stayButton.textContent = isPending
        ? 'Keeping Session Active...'
        : stayButton.dataset.defaultLabel;
}

// ── Countdown ──────────────────────────────────────────────────────────────

function formatSeconds(s) {
    const m   = Math.floor(s / 60);
    const sec = s % 60;
    return `${m}:${String(sec).padStart(2, '0')}`;
}

/**
 * Render the remaining time from the wall-clock deadline. Returns false once
 * the deadline has passed (after redirecting), so callers can stop ticking.
 */
function updateCountdownDisplay() {
    const remaining = Math.max(0, Math.ceil((warningDeadlineAt - Date.now()) / 1000));
    const el = document.getElementById('xs-session-countdown');
    if (el) el.textContent = formatSeconds(remaining);

    if (remaining <= 0) {
        clearCountdownTimer();
        redirectToLogin();
        return false;
    }
    return true;
}

function showWarning(deadlineAt) {
    if (warningVisible) return;
    warningVisible    = true;
    warningDeadlineAt = deadlineAt;
    extendInFlight    = false;
    clearPingRetryTimer();

    const modal = getOrCreateModal();
    modal.classList.remove('hidden');
    setStayButtonPendingState(false);

    if (!updateCountdownDisplay()) {
        return;
    }

    // The interval only refreshes the display; expiry is judged against the
    // deadline timestamp, so throttled ticks cannot stretch the countdown.
    countdownTimer = setInterval(updateCountdownDisplay, 1000);
}

function hideWarning({ removeModal = false } = {}) {
    warningVisible    = false;
    warningDeadlineAt = 0;
    extendInFlight    = false;
    clearCountdownTimer();
    clearPingRetryTimer();
    setStayButtonPendingState(false);

    const modal = document.getElementById('xs-session-warning-modal');
    if (!modal) {
        return;
    }

    if (removeModal) {
        modal.remove();
        return;
    }

    modal.classList.add('hidden');
}

// ── Session ping ───────────────────────────────────────────────────────────

function pauseWarningCountdown() {
    clearCountdownTimer();
}

function isRetriablePingStatus(statusCode) {
    return statusCode === 401 || statusCode === 429 || statusCode >= 500;
}

function waitForPingRetry(requestEpoch) {
    return new Promise((resolve) => {
        if (requestEpoch !== monitorEpoch) {
            resolve(false);
            return;
        }

        pingRetryTimer = setTimeout(() => {
            pingRetryTimer = null;
            resolve(requestEpoch === monitorEpoch);
        }, PING_RETRY_DELAY_MS);
    });
}

async function pingSession(requestEpoch) {
    let failureCount = 0;

    while (true) {
        try {
            const res = await fetch(withBaseUrl('/auth/ping'), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (requestEpoch !== monitorEpoch) {
                return null;
            }

            if (res.ok) {
                return true;
            }

            if (!isRetriablePingStatus(res.status)) {
                return false;
            }

            failureCount += 1;
            if (failureCount >= PING_MAX_FAILURES) {
                return false;
            }

            console.warn(
                `[InactivityMonitor] Ping returned ${res.status}; retrying shortly (${failureCount}/${PING_MAX_FAILURES - 1})`
            );
        } catch (error) {
            if (requestEpoch !== monitorEpoch) {
                return null;
            }

            failureCount += 1;
            if (failureCount >= PING_MAX_FAILURES) {
                return false;
            }

            console.warn(
                `[InactivityMonitor] Ping failed; retrying shortly (${failureCount}/${PING_MAX_FAILURES - 1})`,
                error
            );
        }

        const canRetry = await waitForPingRetry(requestEpoch);
        if (!canRetry) {
            return null;
        }
    }
}

async function extendSession() {
    if (extendInFlight) {
        return;
    }

    const requestEpoch = monitorEpoch;
    extendInFlight = true;
    pauseWarningCountdown();
    setStayButtonPendingState(true);

    const didExtendSession = await pingSession(requestEpoch);

    if (requestEpoch !== monitorEpoch || didExtendSession === null) {
        return;
    }

    if (didExtendSession) {
        hideWarning();
        markActivityNow({ forceStorageWrite: true });
        markKeepaliveNow();
        return;
    }

    extendInFlight = false;
    setStayButtonPendingState(false);
    redirectToLogin();
}

// ── Keepalive ──────────────────────────────────────────────────────────────

function maybeKeepalive(now, lastActive) {
    if (keepaliveInFlight) return;
    if (now - effectiveLastKeepalive() < KEEPALIVE_MS) return;
    // Idle user: let the session approach expiry naturally (that's the point
    // of an inactivity logout). Only active users slide the server window.
    if (now - lastActive >= KEEPALIVE_MS) return;

    keepaliveInFlight = true;
    const requestEpoch = monitorEpoch;
    pingSession(requestEpoch).then((didPing) => {
        if (requestEpoch !== monitorEpoch || didPing === null) {
            return;
        }
        keepaliveInFlight = false;
        if (didPing) {
            markKeepaliveNow();
        } else {
            // Session is already dead server-side despite local activity.
            redirectToLogin();
        }
    });
}

// ── Wall-clock idle evaluation ─────────────────────────────────────────────

function checkIdleState() {
    if (extendInFlight) return;

    const now        = Date.now();
    const lastActive = effectiveLastActivity();
    const elapsed    = now - lastActive;

    if (warningVisible) {
        // Another tab may have extended the session (its "Stay Logged In"
        // writes fresh shared timestamps) — retract the stale warning.
        if (elapsed < SESSION_MS - WARNING_MS) {
            hideWarning();
            return;
        }
        updateCountdownDisplay();
        return;
    }

    if (elapsed >= SESSION_MS) {
        // Woke from sleep / long-throttled tab: the session window has fully
        // passed. A fake 5-minute countdown would be a lie — go to login.
        redirectToLogin();
        return;
    }

    if (elapsed >= SESSION_MS - WARNING_MS) {
        showWarning(lastActive + SESSION_MS);
        return;
    }

    maybeKeepalive(now, lastActive);
}

// ── Activity tracking ──────────────────────────────────────────────────────

const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'touchstart', 'scroll'];

function handleActivity() {
    // While the warning is up, only an explicit "Stay Logged In" (server ping)
    // may extend the session — mouse movement toward the button must not.
    if (warningVisible || extendInFlight) return;
    markActivityNow();
}

function handleWake() {
    // visibilitychange / focus / pageshow: timers may not have fired for
    // hours — re-derive the true state from timestamps immediately.
    if (document.visibilityState === 'hidden') return;
    checkIdleState();
}

export function initInactivityMonitor() {
    // Only run on authenticated pages
    if (!document.getElementById('spa-content')) return;

    monitorEpoch += 1;
    keepaliveInFlight = false;
    clearCheckTimer();
    hideWarning({ removeModal: true });

    // Page load / SPA navigation was itself a server request, so the server
    // session window just slid — treat now as both activity and keepalive.
    markActivityNow({ forceStorageWrite: true });
    markKeepaliveNow();

    // Avoid double-binding if called again by SPA navigation
    ACTIVITY_EVENTS.forEach(evt => {
        document.removeEventListener(evt, handleActivity);
        document.addEventListener(evt, handleActivity, { passive: true });
    });
    document.removeEventListener('visibilitychange', handleWake);
    document.addEventListener('visibilitychange', handleWake);
    window.removeEventListener('focus', handleWake);
    window.addEventListener('focus', handleWake);
    window.removeEventListener('pageshow', handleWake);
    window.addEventListener('pageshow', handleWake);

    checkTimer = setInterval(checkIdleState, CHECK_INTERVAL_MS);

    // Clean up on SPA navigation — re-init happens on spa:navigated
    const lifecycleEpoch = monitorEpoch;
    document.addEventListener('spa:leaving', () => {
        if (lifecycleEpoch !== monitorEpoch) {
            return;
        }

        monitorEpoch += 1;
        clearCheckTimer();
        hideWarning({ removeModal: true });
        ACTIVITY_EVENTS.forEach(evt => document.removeEventListener(evt, handleActivity));
        document.removeEventListener('visibilitychange', handleWake);
        window.removeEventListener('focus', handleWake);
        window.removeEventListener('pageshow', handleWake);
    }, { once: true });
}
