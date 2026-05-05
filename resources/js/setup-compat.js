/**
 * setup-compat.js
 *
 * Hosting Compatibility Mode — UI behaviour module.
 *
 * Responsibilities
 * ─────────────────────────────────────────────────────────────────────────────
 *  1. Wire the compatibility_mode checkbox to pill / badge visibility.
 *  2. Intercept the "Test Connection" button click, inject the compat flag,
 *     and render a rich result card in #connection_result.
 *  3. Smart-detect hosting restrictions in the server response and surface
 *     the one-click "Enable" suggestion banner.
 *
 * Integration
 * ─────────────────────────────────────────────────────────────────────────────
 * Import this module at the top of your existing setup.js entry point:
 *
 *   import { initCompatibilityMode } from './setup-compat.js';
 *   // … existing setup.js code …
 *   initCompatibilityMode();
 *
 * The module is fully self-contained and adds no global state.
 */

// ─────────────────────────────────────────────────────────────────────────────
// DOM helpers
// ─────────────────────────────────────────────────────────────────────────────

/** @param {string} id @returns {HTMLElement|null} */
const el = (id) => document.getElementById(id);

/** Show an element by removing 'hidden' and optionally adding 'flex'. */
function show(element, display = 'flex') {
    if (!element) return;
    element.classList.remove('hidden');
    if (display === 'flex') element.classList.add('flex');
}

/** Hide an element by adding 'hidden' and removing display classes. */
function hide(element) {
    if (!element) return;
    element.classList.add('hidden');
    element.classList.remove('flex');
}

// ─────────────────────────────────────────────────────────────────────────────
// Result card renderer
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Render a styled result card inside #connection_result.
 *
 * @param {{
 *   success:       boolean,
 *   message:       string,
 *   suggestCompat: boolean,
 *   compatMode:    boolean
 * }} result
 */
function renderConnectionResult(result) {
    const container = el('connection_result');
    if (!container) return;

    const isSuccess = result.success === true;

    // Palette tokens (Tailwind class strings — no inline styles)
    const palette = isSuccess
        ? {
              wrap:   'bg-green-50 dark:bg-green-900/20 border border-green-400 dark:border-green-600',
              icon:   'text-green-500 dark:text-green-400',
              title:  'text-green-800 dark:text-green-300',
              body:   'text-green-700 dark:text-green-400',
              iconPath: 'M5 13l4 4L19 7',
          }
        : {
              wrap:   'bg-red-50 dark:bg-red-900/20 border border-red-400 dark:border-red-600',
              icon:   'text-red-500 dark:text-red-400',
              title:  'text-red-800 dark:text-red-300',
              body:   'text-red-700 dark:text-red-400',
              iconPath: 'M6 18L18 6M6 6l12 12',
          };

    const compatBadge = result.compatMode
        ? `<span class="inline-flex items-center gap-1 ml-2 px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-600 text-xs font-medium text-green-700 dark:text-green-400">
               <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                         d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                   </path>
               </svg>
               Compat mode
           </span>`
        : '';

    container.innerHTML = `
        <div class="rounded-lg p-3 flex items-start gap-3 ${palette.wrap} transition-colors duration-200">
            <div class="flex-shrink-0 mt-0.5">
                <svg class="w-5 h-5 ${palette.icon}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${palette.iconPath}"></path>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold flex items-center flex-wrap gap-1 ${palette.title}">
                    ${isSuccess ? 'Connection successful' : 'Connection failed'}
                    ${compatBadge}
                </p>
                <p class="mt-0.5 text-xs ${palette.body}">${escapeHtml(result.message)}</p>
            </div>
        </div>
    `.trim();

    show(container, 'block');
}

/** Minimal HTML escape — avoids importing a utility just for this. */
function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─────────────────────────────────────────────────────────────────────────────
// Smart detection
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Show the amber suggestion banner when the server signals a restricted host.
 * Wires the one-click "Enable" button to auto-check the compat checkbox.
 */
function handleCompatSuggestion() {
    const banner  = el('compat_suggestion_banner');
    const enableBtn = el('compat_suggestion_btn');
    const checkbox  = el('compatibility_mode');

    if (!banner || !enableBtn || !checkbox) return;

    show(banner);

    // One-click enable: tick the checkbox, hide the banner, re-run the test
    enableBtn.addEventListener('click', () => {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change')); // trigger badge update
        hide(banner);
        el('test_connection_btn')?.click();          // re-run test immediately
    }, { once: true });
}

// ─────────────────────────────────────────────────────────────────────────────
// Checkbox state → UI sync
// ─────────────────────────────────────────────────────────────────────────────

function syncCompatUi(checked) {
    const pill  = el('compat_mode_pill');
    const badge = el('compat_active_badge');
    if (checked) {
        show(pill);
        show(badge);
    } else {
        hide(pill);
        hide(badge);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Test connection handler
// ─────────────────────────────────────────────────────────────────────────────

async function testConnection() {
    const btn = el('test_connection_btn');
    const result = el('connection_result');

    if (!btn) return;

    // Collect form values
    const host       = el('mysql_hostname')?.value?.trim()  ?? '';
    const db         = el('mysql_database')?.value?.trim()  ?? '';
    const user       = el('mysql_username')?.value?.trim()  ?? '';
    const pass       = el('mysql_password')?.value          ?? '';
    const port       = el('mysql_port')?.value              ?? '3306';
    const compatMode = el('compatibility_mode')?.checked    ?? false;

    // Loading state
    btn.disabled = true;
    btn.innerHTML = `
        <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        Testing…
    `;
    if (result) hide(result);

    try {
        let response;
        try {
            response = await fetch(`${window.appConfig.baseURL}setup/testConnection`, {
                method:  'POST',
                headers: {
                    'Content-Type':            'application/json',
                    'Accept':                  'application/json',
                    [window.appConfig.csrfHeaderName]: window.appConfig.csrfToken,
                },
                body: JSON.stringify({ host, db, user, pass, port, compatibility_mode: compatMode }),
            });
        } catch {
            // Network-level failure (DNS, connection refused, offline)
            renderConnectionResult({
                success:       false,
                message:       'Could not reach the server. Please check your network connection.',
                suggestCompat: false,
                compatMode,
            });
            return;
        }

        let data;
        try {
            data = await response.json();
        } catch {
            // Server responded but not with valid JSON (e.g. PHP error page, CSRF 403 HTML)
            renderConnectionResult({
                success:       false,
                message:       `Server returned an unexpected response (HTTP ${response.status}). Check server logs.`,
                suggestCompat: false,
                compatMode,
            });
            return;
        }

        // Normalise: CI4 exception handler returns {title, status} instead of {success, message}
        if (data.message == null && data.title != null) {
            data = { success: false, message: data.title, suggestCompat: false, compatMode };
        }
        // Final guard so escapeHtml never receives undefined
        if (data.message == null) {
            data.message = `Unexpected server error (HTTP ${response.status}).`;
        }

        renderConnectionResult(data);

        // Smart detection: server says this looks like a hosting restriction
        if (!data.success && data.suggestCompat && !compatMode) {
            handleCompatSuggestion();
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = `
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0">
                </path>
            </svg>
            Test Connection
        `;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Module entry point
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Initialise all compatibility-mode UI bindings.
 * Call once from setup.js after DOM is ready.
 */
export function initCompatibilityMode() {
    const checkbox = el('compatibility_mode');
    const testBtn  = el('test_connection_btn');

    if (!checkbox || !testBtn) return;

    // Sync UI to checkbox initial state (handles page reload with checked state)
    syncCompatUi(checkbox.checked);

    // Live sync on toggle
    checkbox.addEventListener('change', () => syncCompatUi(checkbox.checked));

    // Wire test connection button
    testBtn.addEventListener('click', testConnection);
}