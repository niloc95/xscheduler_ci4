import { DateTime } from 'luxon';
import { buildCanonicalState } from './adapters.js';
import { recordTelemetry } from '../telemetry.js';

function hydrate(store, payload, meta = {}) {
  store.setState(prev => {
    const next = buildCanonicalState(prev, payload, meta);
    return { ...prev, ...next };
  }, 'hydrate');
}

function setActiveView(store, view) {
  store.setState(prev => {
    if (prev.meta.activeView === view) {
      return prev;
    }
    const { rangeStart, rangeEnd } = computeRangeForView(
      view,
      resolveBaseDate(prev.meta),
      prev.meta.timezone,
    );
    return {
      ...prev,
      meta: { ...prev.meta, activeView: view, rangeStart, rangeEnd },
    };
  }, 'setActiveView');
}

function setRange(store, { rangeStart, rangeEnd }) {
  store.setState(prev => ({
    ...prev,
    meta: { ...prev.meta, rangeStart, rangeEnd },
  }), 'setRange');
}

function shiftRange(store, direction = 1) {
  store.setState(prev => {
    const baseDate = resolveBaseDate(prev.meta);
    const nextBase = shiftDateByView(baseDate, prev.meta.activeView, direction);
    const { rangeStart, rangeEnd } = computeRangeForView(prev.meta.activeView, nextBase, prev.meta.timezone);
    return {
      ...prev,
      meta: { ...prev.meta, rangeStart, rangeEnd },
    };
  }, 'shiftRange');
}

function setToday(store) {
  store.setState(prev => {
    const today = DateTime.now().setZone(prev.meta.timezone);
    const { rangeStart, rangeEnd } = computeRangeForView(prev.meta.activeView, today, prev.meta.timezone);
    return {
      ...prev,
      meta: { ...prev.meta, rangeStart, rangeEnd },
    };
  }, 'setToday');
}

function toggleProvider(store, providerId) {
  store.setState(prev => {
    const nextSet = new Set(prev.filters.providerIds);
    if (nextSet.has(providerId)) {
      nextSet.delete(providerId);
    } else {
      nextSet.add(providerId);
    }
    return {
      ...prev,
      filters: { ...prev.filters, providerIds: nextSet },
    };
  }, 'toggleProvider');
}

async function fetchRange(store, { start, end } = {}) {
  const state = store.getState();
  const targetStart = start ?? state.meta.rangeStart;
  const targetEnd = end ?? state.meta.rangeEnd;

  if (!targetStart || !targetEnd) {
    console.warn('[calendar-prototype] Cannot fetch range without start and end.');
    return null;
  }

  const endpoint = resolveRangeEndpoint();
  if (!endpoint) {
    console.warn('[calendar-prototype] No prototype endpoint configured for range fetches.');
    return null;
  }

  store.setState(prev => ({
    ...prev,
    meta: { ...prev.meta, isFetching: true, error: null },
  }), 'fetchRange:pending');

  recordTelemetry('range_fetch', {
    requestStart: targetStart,
    requestEnd: targetEnd,
    source: 'auto',
    status: 'pending',
  });

  try {
    const url = new URL(endpoint, window.location.origin);
    url.searchParams.set('start', targetStart);
    url.searchParams.set('end', targetEnd);

    const response = await fetch(url.toString(), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error(`Range request failed with status ${response.status}`);
    }

    const payload = await response.json();
    if (payload?.data) {
      hydrate(store, payload.data, { source: 'lazy-range', requestedStart: targetStart, requestedEnd: targetEnd });
    }

    store.setState(prev => ({
      ...prev,
      meta: { ...prev.meta, isFetching: false, error: null },
    }), 'fetchRange:success');

    recordTelemetry('range_fetch', {
      requestStart: targetStart,
      requestEnd: targetEnd,
      status: 'success',
      resultCount: Array.isArray(payload?.data?.appointments) ? payload.data.appointments.length : undefined,
    });

    return payload?.data ?? null;
  } catch (error) {
    console.error('[calendar-prototype] Failed to fetch range', error);
    store.setState(prev => ({
      ...prev,
      meta: { ...prev.meta, isFetching: false, error: error?.message ?? 'Range fetch failed' },
    }), 'fetchRange:error');
    recordTelemetry('range_fetch', {
      requestStart: targetStart,
      requestEnd: targetEnd,
      status: 'error',
    });
    return null;
  }
}

function resolveRangeEndpoint() {
  if (typeof window === 'undefined') {
    return null;
  }
  const endpoints = window.__calendarPrototype?.endpoints ?? {};
  return endpoints.range || endpoints.bootstrap || null;
}

function resolveBaseDate(meta) {
  if (meta.rangeStart) {
    return DateTime.fromISO(meta.rangeStart, { zone: meta.timezone });
  }
  return DateTime.now().setZone(meta.timezone);
}

function shiftDateByView(date, view, direction) {
  if (view === 'month') return date.plus({ months: direction }).startOf('month');
  if (view === 'week') return date.plus({ weeks: direction }).startOf('week');
  return date.plus({ days: direction }).startOf('day');
}

function computeRangeForView(view, date, timezone) {
  const cursor = date.setZone(timezone);
  if (view === 'month') {
    const rangeStart = cursor.startOf('month');
    return {
      rangeStart: rangeStart.toISODate(),
      rangeEnd: rangeStart.plus({ months: 1 }).toISODate(),
    };
  }
  if (view === 'week') {
    const rangeStart = cursor.startOf('week');
    return {
      rangeStart: rangeStart.toISODate(),
      rangeEnd: rangeStart.plus({ days: 7 }).toISODate(),
    };
  }
  const rangeStart = cursor.startOf('day');
  return {
    rangeStart: rangeStart.toISO(),
    rangeEnd: rangeStart.plus({ days: 1 }).toISO(),
  };
}

export {
  hydrate,
  setActiveView,
  setRange,
  shiftRange,
  setToday,
  toggleProvider,
  fetchRange,
};
