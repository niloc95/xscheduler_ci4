const defaultState = {
  meta: {
    activeView: 'month',
    rangeStart: null,
    rangeEnd: null,
    nowTimestamp: Date.now(),
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    hydrationSource: null,
    lastUpdatedAt: null,
      loadedRangeStart: null,
      loadedRangeEnd: null,
    featureFlags: {},
    error: null,
    isFetching: false,
  },
  providers: {},
  appointments: {},
  providerOrder: [],
  filters: {
    providerIds: new Set(),
    visitTypes: new Set(),
    locations: new Set(),
    showCanceled: false,
  },
  derived: {
    monthMatrix: [],
    weekColumns: [],
    dayTimeline: [],
  },
};

function createCalendarStore(initialState = defaultState) {
  let state = structuredClone(initialState);
  const listeners = new Set();

  const getState = () => state;

  const setState = (updater, description = 'anonymous') => {
    const nextState = typeof updater === 'function' ? updater(state) : updater;
    if (!nextState || nextState === state) return;
    state = nextState;
    listeners.forEach(listener => listener(state, description));
  };

  const subscribe = listener => {
    listeners.add(listener);
    return () => listeners.delete(listener);
  };

  const reset = () => {
    state = structuredClone(initialState);
    listeners.forEach(listener => listener(state, 'reset'));
  };

  return { getState, setState, subscribe, reset };
}

export { createCalendarStore, defaultState };
