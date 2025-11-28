import { describe, it, expect } from 'vitest';
import { createCalendarStore } from '../../../resources/js/modules/calendar/state/store.js';
import { hydrate } from '../../../resources/js/modules/calendar/state/actions.js';
import { selectMonthMatrix, selectWeekColumns, selectDayTimeline } from '../../../resources/js/modules/calendar/state/selectors.js';
import { samplePayload } from '../../../resources/js/modules/calendar/state/fixtures/samplePayload.js';

function cloneSample(overrides = {}) {
  const base = JSON.parse(JSON.stringify(samplePayload));
  return {
    ...base,
    ...overrides,
    providers: overrides.providers ?? base.providers,
    appointments: overrides.appointments ?? base.appointments,
    providerOrder: overrides.providerOrder ?? base.providerOrder,
  };
}

function hydrateState(overrides = {}) {
  const store = createCalendarStore();
  hydrate(store, cloneSample(overrides), { source: 'test' });
  return store;
}

describe('selectMonthMatrix', () => {
  it('builds a 6x7 grid and surfaces appointment chips', () => {
    const store = hydrateState();
    const matrix = selectMonthMatrix(store.getState());
    expect(matrix).toHaveLength(6);
    matrix.forEach(row => expect(row).toHaveLength(7));
    const chipCell = matrix.flat().find(cell => cell.appointmentChips.length > 0);
    expect(chipCell?.appointmentChips[0]).toMatchObject({ providerId: expect.any(String), timeLabel: expect.any(String) });
  });
});

describe('selectWeekColumns', () => {
  it('assigns lane metadata for overlapping appointments', () => {
    const overlapping = {
      rangeStart: '2026-01-23',
      appointments: [
        {
          id: 'over-1',
          providerId: 'p-smith',
          patientName: 'Overlap A',
          start: '2026-01-23T10:00:00-05:00',
          end: '2026-01-23T10:45:00-05:00',
          durationMinutes: 45,
        },
        {
          id: 'over-2',
          providerId: 'p-smith',
          patientName: 'Overlap B',
          start: '2026-01-23T10:15:00-05:00',
          end: '2026-01-23T11:00:00-05:00',
          durationMinutes: 45,
        },
        {
          id: 'over-3',
          providerId: 'p-smith',
          patientName: 'Overlap C',
          start: '2026-01-23T10:30:00-05:00',
          end: '2026-01-23T11:30:00-05:00',
          durationMinutes: 60,
        },
      ],
    };
    const store = hydrateState(overlapping);
    const friday = selectWeekColumns(store.getState()).find(col => col.date === '2026-01-23');
    expect(friday).toBeDefined();
    const laneCounts = friday.appointments.map(block => block.laneCount);
    expect(Math.max(...laneCounts)).toBe(3);
    expect(new Set(friday.appointments.map(block => block.laneIndex)).size).toBe(3);
  });

  it('respects provider filters when deriving appointments', () => {
    const store = hydrateState();
    store.setState(prev => ({
      ...prev,
      filters: { ...prev.filters, providerIds: new Set(['p-lee']) },
    }));
    const state = store.getState();
    const saturday = selectWeekColumns(state).find(col => col.date === '2026-01-24');
    expect(saturday.appointments.length).toBeGreaterThan(0);
    expect(saturday.appointments.every(block => block.providerId === 'p-lee')).toBe(true);
  });
});

describe('selectDayTimeline', () => {
  it('filters providers and keeps lane info for overlaps', () => {
    const store = hydrateState({ rangeStart: '2026-01-23' });
    store.setState(prev => ({
      ...prev,
      filters: { ...prev.filters, providerIds: new Set(['p-smith']) },
    }));
    const columns = selectDayTimeline(store.getState());
    expect(columns).toHaveLength(1);
    expect(columns[0].providerId).toBe('p-smith');
    const maxLane = Math.max(...columns[0].blocks.map(block => block.laneCount));
    expect(maxLane).toBeGreaterThan(1);
  });
});
