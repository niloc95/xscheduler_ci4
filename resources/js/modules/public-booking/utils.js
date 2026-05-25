/**
 * Public Booking — Pure Utility Functions
 *
 * Stateless helpers used by both the orchestrator (public-booking.js)
 * and the render module. All functions are pure: they accept plain values
 * as parameters and have no side-effects or state access.
 */

/**
 * Parse the booking context from the page.
 * Reads from the <script id="public-booking-context"> JSON block first,
 * then falls back to root.dataset.context or window.__PUBLIC_BOOKING__.
 *
 * @param {HTMLElement} root - The #public-booking-root element
 * @returns {object}
 */
export function parseContext(root) {
  const scriptPayload = document.getElementById('public-booking-context');
  if (scriptPayload?.textContent) {
    try {
      return JSON.parse(scriptPayload.textContent) || {};
    } catch (error) {
      console.error('[public-booking] Failed to parse script context payload.', error);
    }
  }

  try {
    return JSON.parse(root.dataset.context ?? '{}') || window.__PUBLIC_BOOKING__ || {};
  } catch (error) {
    console.error('[public-booking] Failed to parse context payload.', error);
    return window.__PUBLIC_BOOKING__ || {};
  }
}

/**
 * Resolve the application base URL from context or window globals.
 * Intentionally standalone to avoid importing url-helpers.js.
 *
 * @param {object} ctx
 * @returns {string}
 */
export function resolveAppBaseUrl(ctx) {
  if (ctx?.appBaseUrl) {
    return String(ctx.appBaseUrl).replace(/\/+$/, '');
  }

  if (typeof window !== 'undefined' && window.__BASE_URL__) {
    return String(window.__BASE_URL__).replace(/\/+$/, '');
  }

  if (ctx?.bookingBaseUrl) {
    return String(ctx.bookingBaseUrl).replace(/\/booking\/?$/, '').replace(/\/+$/, '');
  }

  return '';
}

/**
 * Format a date string for display using Intl.DateTimeFormat.
 *
 * @param {string} dateStr - ISO date string (YYYY-MM-DD)
 * @param {Intl.DateTimeFormatOptions} [options]
 * @returns {string}
 */
export function formatDateDisplay(dateStr, options) {
  if (!dateStr) {
    return '';
  }
  const date = new Date(`${dateStr}T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    return dateStr;
  }
  try {
    return new Intl.DateTimeFormat(undefined, options).format(date);
  } catch (error) {
    return date.toLocaleDateString();
  }
}

/**
 * Short date label for date picker pills (e.g. "Mon, Jun 3").
 *
 * @param {string} dateStr - ISO date string
 * @returns {string}
 */
export function formatDateSelectLabel(dateStr) {
  return formatDateDisplay(dateStr, { weekday: 'short', month: 'short', day: 'numeric' });
}

/**
 * Filter delivery modes that are available for a service given the current context.
 * Removes online_zoom / online_jitsi if the respective integration is not connected.
 *
 * @param {string} serviceId
 * @param {Array} services
 * @param {object} ctx
 * @returns {string[]}
 */
export function getAvailableModes(serviceId, services, ctx) {
  const svc = (services || []).find(s => String(s.id) === String(serviceId));
  const modes = Array.isArray(svc?.deliveryModes) ? svc.deliveryModes : ['onsite'];
  const filtered = modes.filter(m =>
    m === 'onsite' ||
    (m === 'online_zoom'  && ctx.zoomConnected) ||
    (m === 'online_jitsi' && ctx.jitsiConnected)
  );
  return filtered.length > 0 ? filtered : ['onsite'];
}

/**
 * Deep-clone a value using structuredClone or JSON fallback.
 *
 * @param {*} value
 * @returns {*}
 */
export function cloneState(value) {
  if (typeof structuredClone === 'function') {
    return structuredClone(value);
  }
  return JSON.parse(JSON.stringify(value));
}

/**
 * Error class for expected submission failures (validation errors, policy rejections).
 * `details` is a flat object mapping field names to error messages.
 */
export class SubmissionError extends Error {
  constructor(message, details = {}) {
    super(message);
    this.details = details;
  }
}
