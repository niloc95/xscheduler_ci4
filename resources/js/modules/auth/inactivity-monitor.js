import { withBaseUrl } from '../../utils/url-helpers.js';

// Aligned with app/Config/Session.php $expiration = 7200
const SESSION_MS  = 7200 * 1000;
const WARNING_MS  = 5   * 60  * 1000; // show modal 5 min before expiry
const COUNTDOWN_S = 5   * 60;         // 300 s countdown

let activityTimer    = null;
let countdownTimer   = null;
let warningVisible   = false;
let secondsRemaining = COUNTDOWN_S;

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
                               hover:bg-primary-700 transition-colors focus:ring-2 focus:ring-primary-500">
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

    document.getElementById('xs-session-stay').addEventListener('click', extendSession);
    document.getElementById('xs-session-logout').addEventListener('click', () => {
        window.location.href = withBaseUrl('/auth/logout');
    });

    return modal;
}

// ── Countdown ──────────────────────────────────────────────────────────────

function formatSeconds(s) {
    const m   = Math.floor(s / 60);
    const sec = s % 60;
    return `${m}:${String(sec).padStart(2, '0')}`;
}

function showWarning() {
    if (warningVisible) return;
    warningVisible   = true;
    secondsRemaining = COUNTDOWN_S;

    const modal = getOrCreateModal();
    modal.classList.remove('hidden');
    document.getElementById('xs-session-countdown').textContent = formatSeconds(secondsRemaining);

    countdownTimer = setInterval(() => {
        secondsRemaining -= 1;
        const el = document.getElementById('xs-session-countdown');
        if (el) el.textContent = formatSeconds(secondsRemaining);

        if (secondsRemaining <= 0) {
            clearInterval(countdownTimer);
            window.location.href = withBaseUrl('/auth/login');
        }
    }, 1000);
}

function hideWarning() {
    if (!warningVisible) return;
    warningVisible = false;
    clearInterval(countdownTimer);
    countdownTimer = null;
    const modal = document.getElementById('xs-session-warning-modal');
    if (modal) modal.classList.add('hidden');
}

// ── Session ping ───────────────────────────────────────────────────────────

async function extendSession() {
    try {
        const res = await fetch(withBaseUrl('/auth/ping'), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            hideWarning();
            resetTimer();
        } else {
            window.location.href = withBaseUrl('/auth/login');
        }
    } catch {
        window.location.href = withBaseUrl('/auth/login');
    }
}

// ── Activity tracking ──────────────────────────────────────────────────────

function resetTimer() {
    clearTimeout(activityTimer);
    activityTimer = setTimeout(showWarning, SESSION_MS - WARNING_MS); // 115 min
}

const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'touchstart', 'scroll'];

export function initInactivityMonitor() {
    // Only run on authenticated pages
    if (!document.getElementById('spa-content')) return;

    // Avoid double-binding if called again by SPA navigation
    ACTIVITY_EVENTS.forEach(evt => {
        document.removeEventListener(evt, resetTimer);
        document.addEventListener(evt, resetTimer, { passive: true });
    });

    resetTimer();

    // Clean up on SPA navigation — re-init happens on spa:navigated
    document.addEventListener('spa:leaving', () => {
        clearTimeout(activityTimer);
        clearInterval(countdownTimer);
        hideWarning();
        ACTIVITY_EVENTS.forEach(evt => document.removeEventListener(evt, resetTimer));
    }, { once: true });
}
