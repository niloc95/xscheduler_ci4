import '../css/calendar/tailwind-prototype.css';
import { calendarStore, boundActions, selectors } from './modules/calendar/state/index.js';
import { initTelemetry, recordTelemetry, registerNavigationTelemetry } from './modules/calendar/telemetry.js';

const GLOBAL_FLAG = '__calendarPrototypeBundleLoaded';
const storeUnsubscribers = [];

if (!window[GLOBAL_FLAG]) {
  window[GLOBAL_FLAG] = true;
  window.addEventListener('beforeunload', cleanupStoreSubscriptions, { once: true });
  initializeCalendarPrototype();
}

function initializeCalendarPrototype() {
  const runtime = window.__calendarPrototype || {};
  if (!runtime.enabled) {
    console.info('[calendar-prototype] Feature flag disabled; bundle exiting.');
    return;
  }

  const telemetryActive = initTelemetry(runtime);

  const bootstrapPayload = window.__calendarBootstrap;
  if (bootstrapPayload) {
    try {
      boundActions.hydrate(bootstrapPayload, { source: 'bootstrap' });
      console.info('[calendar-prototype] Bootstrap payload hydrated.', {
        rangeStart: bootstrapPayload.rangeStart,
        rangeEnd: bootstrapPayload.rangeEnd,
        timezone: bootstrapPayload.timezone,
      });
      if (telemetryActive) {
        recordTelemetry('hydrate', {
          rangeStart: bootstrapPayload.rangeStart,
          rangeEnd: bootstrapPayload.rangeEnd,
          resultCount: Array.isArray(bootstrapPayload.appointments) ? bootstrapPayload.appointments.length : 0,
          source: 'bootstrap',
        });
      }
    } catch (error) {
      console.error('[calendar-prototype] Failed to hydrate bootstrap payload.', error);
    }
  } else {
    console.warn('[calendar-prototype] Missing window.__calendarBootstrap payload.');
  }

  exposeDebugHandles();
  initializeRangeCoverageWatcher();
  initializeTelemetryWatchers();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupBanner, { once: true });
  } else {
    setupBanner();
  }
}

function exposeDebugHandles() {
  window.__calendarPrototypeStore = calendarStore;
  window.__calendarPrototypeActions = boundActions;
  window.__calendarPrototypeSelectors = selectors;
}

function setupBanner() {
  const mountTarget = document.querySelector('[data-calendar-prototype-root]')
    || document.getElementById('appointments-inline-calendar');

  if (!mountTarget) {
    console.warn('[calendar-prototype] Unable to find a banner mount target.');
    return;
  }

  const banner = document.createElement('div');
  banner.className = 'rounded-2xl border border-dashed border-amber-300/80 bg-amber-50/80 dark:border-amber-400/50 dark:bg-amber-500/10 px-4 py-3 mb-4 text-sm text-amber-900 dark:text-amber-100 flex flex-col gap-1';
  banner.innerHTML = `
    <div class="flex items-center gap-2">
      <span class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-200">
        <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
        Prototype Mode
      </span>
      <button type="button" aria-label="Dismiss prototype banner" class="ml-auto text-xs text-amber-600 dark:text-amber-200 hover:underline" data-calendar-banner-dismiss>
        Dismiss
      </button>
    </div>
    <div class="text-xs text-amber-800 dark:text-amber-100" data-calendar-banner-range></div>
    <div class="text-[11px] text-amber-700/80 dark:text-amber-200/80" data-calendar-banner-meta></div>
  `;

  const dismissButton = banner.querySelector('[data-calendar-banner-dismiss]');
  dismissButton?.addEventListener('click', () => banner.remove());

  mountTarget.prepend(banner);
  const render = () => updateBanner(banner, calendarStore.getState());
  render();
  trackStoreSubscription(calendarStore.subscribe((state) => updateBanner(banner, state)));
}

function updateBanner(banner, state) {
  if (!banner?.isConnected || !state) {
    return;
  }

  const rangeLabel = banner.querySelector('[data-calendar-banner-range]');
  const metaLabel = banner.querySelector('[data-calendar-banner-meta]');
  if (!rangeLabel || !metaLabel) {
    return;
  }

  const rangeStart = state.meta?.rangeStart || '—';
  const rangeEnd = state.meta?.rangeEnd || '—';
  rangeLabel.textContent = `Hydrated range: ${rangeStart} → ${rangeEnd}`;

  const providerCount = Object.keys(state.providers || {}).length;
  const appointmentCount = Object.keys(state.appointments || {}).length;
  const activeView = state.meta?.activeView || 'month';
  metaLabel.textContent = `Providers: ${providerCount} · Appointments: ${appointmentCount} · View: ${activeView}`;
}

function initializeRangeCoverageWatcher() {
  const handler = (state) => maybeFetchAdditionalRanges(state);
  handler(calendarStore.getState());
  trackStoreSubscription(calendarStore.subscribe(handler));
}

function initializeTelemetryWatchers() {
  const unsubscribe = registerNavigationTelemetry(calendarStore);
  if (unsubscribe) {
    trackStoreSubscription(unsubscribe);
  }
}

let pendingRangeRequest = null;
let queuedRange = null;

function maybeFetchAdditionalRanges(state) {
  if (!state?.meta) {
    return;
  }

  const {
    rangeStart,
    rangeEnd,
    loadedRangeStart,
    loadedRangeEnd,
    isFetching,
  } = state.meta;

  if (!rangeStart || !rangeEnd || isFetching) {
    return;
  }

  if (!requiresRangeFetch(rangeStart, rangeEnd, loadedRangeStart, loadedRangeEnd)) {
    return;
  }

  enqueueRangeFetch(rangeStart, rangeEnd);
}

function requiresRangeFetch(rangeStart, rangeEnd, loadedStart, loadedEnd) {
  if (!loadedStart || !loadedEnd) {
    return true;
  }

  const requestedStart = Date.parse(rangeStart);
  const requestedEnd = Date.parse(rangeEnd);
  const loadedStartValue = Date.parse(loadedStart);
  const loadedEndValue = Date.parse(loadedEnd);

  if (Number.isNaN(requestedStart) || Number.isNaN(requestedEnd)) {
    return false;
  }
  if (Number.isNaN(loadedStartValue) || Number.isNaN(loadedEndValue)) {
    return true;
  }

  return requestedStart < loadedStartValue || requestedEnd > loadedEndValue;
}

function enqueueRangeFetch(start, end) {
  if (pendingRangeRequest) {
    queuedRange = { start, end };
    return;
  }

  pendingRangeRequest = Promise.resolve(boundActions.fetchRange({ start, end }))
    .catch((error) => {
      console.error('[calendar-prototype] Range fetch promise rejected', error);
    })
    .finally(() => {
      pendingRangeRequest = null;
      if (queuedRange) {
        const next = queuedRange;
        queuedRange = null;
        enqueueRangeFetch(next.start, next.end);
      }
    });
}

function trackStoreSubscription(unsubscribe) {
  if (typeof unsubscribe === 'function') {
    storeUnsubscribers.push(unsubscribe);
  }
}

function cleanupStoreSubscriptions() {
  while (storeUnsubscribers.length > 0) {
    const unsubscribe = storeUnsubscribers.pop();
    try {
      unsubscribe?.();
    } catch (error) {
      console.error('[calendar-prototype] Failed to clean up store subscription', error);
    }
  }
}
