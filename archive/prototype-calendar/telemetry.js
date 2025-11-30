const ALLOWED_META_KEYS = new Set([
  'rangeStart',
  'rangeEnd',
  'loadedRangeStart',
  'loadedRangeEnd',
  'activeView',
  'source',
  'status',
  'durationMs',
  'requestStart',
  'requestEnd',
  'resultCount',
]);

const TRACKED_NAV_ACTIONS = new Set(['setActiveView', 'setRange', 'shiftRange', 'setToday']);

let telemetryEndpoint = null;
let telemetryEnabled = false;

function initTelemetry(runtime = {}) {
  telemetryEndpoint = runtime?.endpoints?.telemetry || null;
  telemetryEnabled = Boolean(runtime?.enabled && telemetryEndpoint);
  return telemetryEnabled;
}

function recordTelemetry(event, meta = {}) {
  if (!telemetryEnabled || !event || !telemetryEndpoint) {
    return;
  }

  const payload = JSON.stringify({
    event,
    meta: sanitizeMeta(meta),
    timestamp: new Date().toISOString(),
  });

  const hasNavigator = typeof window !== 'undefined' && window.navigator;
  if (hasNavigator && typeof window.navigator.sendBeacon === 'function' && typeof Blob !== 'undefined') {
    try {
      const blob = new Blob([payload], { type: 'application/json' });
      const accepted = window.navigator.sendBeacon(telemetryEndpoint, blob);
      if (accepted) {
        return;
      }
    } catch (error) {
      console.debug('[calendar-prototype] Telemetry beacon failed', error);
    }
  }

  const fetcher = hasNavigator && typeof window.fetch === 'function'
    ? window.fetch.bind(window)
    : (typeof fetch === 'function' ? fetch : null);

  if (!fetcher) {
    return;
  }

  fetcher(telemetryEndpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: payload,
    credentials: 'same-origin',
    keepalive: true,
  }).catch((error) => {
    console.debug('[calendar-prototype] Telemetry fetch failed', error);
  });
}

function registerNavigationTelemetry(store) {
  if (!telemetryEnabled || !store) {
    return null;
  }

  let previousSignature = null;
  const handler = (state, description) => {
    if (description && !TRACKED_NAV_ACTIONS.has(description)) {
      return;
    }
    const meta = state?.meta;
    if (!meta) {
      return;
    }

    const signature = `${meta.rangeStart || ''}|${meta.rangeEnd || ''}|${meta.activeView || ''}`;
    if (signature === previousSignature) {
      return;
    }
    previousSignature = signature;

    recordTelemetry('navigation', {
      rangeStart: meta.rangeStart,
      rangeEnd: meta.rangeEnd,
      loadedRangeStart: meta.loadedRangeStart,
      loadedRangeEnd: meta.loadedRangeEnd,
      activeView: meta.activeView,
      source: description || 'unknown',
    });
  };

  return store.subscribe(handler);
}

function sanitizeMeta(meta) {
  if (!meta || typeof meta !== 'object') {
    return {};
  }

  return Object.keys(meta).reduce((acc, key) => {
    if (!ALLOWED_META_KEYS.has(key)) {
      return acc;
    }
    const value = meta[key];
    if (value === undefined) {
      return acc;
    }
    if (typeof value === 'number' || typeof value === 'string' || typeof value === 'boolean') {
      acc[key] = value;
      return acc;
    }
    if (value === null) {
      acc[key] = null;
      return acc;
    }
    acc[key] = String(value);
    return acc;
  }, {});
}

export { initTelemetry, recordTelemetry, registerNavigationTelemetry };
