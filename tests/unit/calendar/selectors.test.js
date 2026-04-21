import { describe, it, expect } from 'vitest';
import {
  normalizeCalendarPayload,
  getSlotsForDate,
  selectAvailableDate,
  buildCalendarCacheKey,
  slotTimeValue,
  slotLabel,
} from '../../../resources/js/modules/calendar/calendar-utils.js';

describe('normalizeCalendarPayload', () => {
  it('normalizes snake_case response fields', () => {
    const payload = normalizeCalendarPayload({
      availableDates: ['2026-01-23'],
      slotsByDate: {
        '2026-01-23': [{ start: '2026-01-23T08:00:00Z', label: '08:00 - 08:30' }],
      },
      start_date: '2026-01-23',
      end_date: '2026-01-30',
      timezone: 'Africa/Johannesburg',
      generated_at: '2026-01-20T10:00:00Z',
      default_date: '2026-01-23',
    });

    expect(payload.startDate).toBe('2026-01-23');
    expect(payload.endDate).toBe('2026-01-30');
    expect(payload.generatedAt).toBe('2026-01-20T10:00:00Z');
    expect(payload.defaultDate).toBe('2026-01-23');
    expect(payload.availableDates).toEqual(['2026-01-23']);
    expect(payload.slotsByDate['2026-01-23']).toHaveLength(1);
  });

  it('defaults empty source safely', () => {
    const payload = normalizeCalendarPayload(null);
    expect(payload.availableDates).toEqual([]);
    expect(payload.slotsByDate).toEqual({});
    expect(payload.defaultDate).toBeNull();
  });
});

describe('date and slot selectors', () => {
  it('returns slots for an available date and empty for missing date', () => {
    const calendar = normalizeCalendarPayload({
      availableDates: ['2026-01-23'],
      slotsByDate: {
        '2026-01-23': [{ startFormatted: '10:30', label: '10:30 - 11:00' }],
      },
    });

    expect(getSlotsForDate(calendar, '2026-01-23')).toHaveLength(1);
    expect(getSlotsForDate(calendar, '2026-01-24')).toEqual([]);
  });

  it('selects requested date when present, else falls back', () => {
    const calendar = normalizeCalendarPayload({
      availableDates: ['2026-01-23', '2026-01-24'],
      defaultDate: '2026-01-24',
    });

    expect(selectAvailableDate(calendar, '2026-01-23')).toEqual({ date: '2026-01-23', autoSelected: false });
    expect(selectAvailableDate(calendar, '2026-01-25')).toEqual({ date: '2026-01-24', autoSelected: true });
  });
});

describe('slot helpers and cache key', () => {
  it('derives slot value/label and builds stable cache keys', () => {
    const slot = { startFormatted: '09:00', endFormatted: '09:30' };
    expect(slotTimeValue(slot)).toBe('09:00');
    expect(slotLabel(slot)).toBe('09:00 - 09:30');

    const key = buildCalendarCacheKey(2, 9, '2026-01-23', 44);
    expect(key).toBe('2|9|2026-01-23|exclude:44');
  });
});
