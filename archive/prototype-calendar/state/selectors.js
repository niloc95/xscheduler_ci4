import { DateTime } from 'luxon';

function selectMeta(state) {
  return state.meta;
}

function selectProviders(state) {
  return state.providerOrder.map(id => state.providers[id]).filter(Boolean);
}

function selectMonthMatrix(state) {
  const { meta } = state;
  const base = meta.rangeStart ? DateTime.fromISO(meta.rangeStart) : DateTime.now();
  const start = base.startOf('month').startOf('week');
  const visibleProviders = getVisibleProviderSet(state);
  const appts = getVisibleAppointments(state, visibleProviders);

  const matrix = [];
  for (let week = 0; week < 6; week += 1) {
    const row = [];
    for (let day = 0; day < 7; day += 1) {
      const cursor = start.plus({ days: week * 7 + day });
      const dayAppts = appts.filter(appt => DateTime.fromISO(appt.start).hasSame(cursor, 'day'));
      const chips = dayAppts.slice(0, 3).map(appt => ({
        appointmentId: appt.id,
        providerId: appt.providerId,
        label: appt.patientName,
        timeLabel: DateTime.fromISO(appt.start).toFormat('h:mma'),
      }));
      row.push({
        date: cursor.toISODate(),
        isCurrentMonth: cursor.month === base.month,
        isToday: cursor.hasSame(DateTime.now(), 'day'),
        isWeekend: cursor.weekday === 6 || cursor.weekday === 7,
        appointmentChips: chips,
        overflowCount: Math.max(0, dayAppts.length - chips.length),
      });
    }
    matrix.push(row);
  }
  return matrix;
}

function selectWeekColumns(state) {
  const start = resolveRangeStart(state, 'week');
  const visibleProviders = getVisibleProviderSet(state);
  const appts = getVisibleAppointments(state, visibleProviders);
  const now = DateTime.now();
  const columns = [];
  for (let day = 0; day < 7; day += 1) {
    const cursor = start.plus({ days: day });
    const dayAppts = appts.filter(appt => DateTime.fromISO(appt.start).hasSame(cursor, 'day'));
    columns.push({
      date: cursor.toISODate(),
      isToday: cursor.hasSame(now, 'day'),
      isWeekend: cursor.weekday === 6 || cursor.weekday === 7,
      appointments: buildBlocks(dayAppts),
    });
  }
  return columns;
}

function selectDayTimeline(state) {
  const start = resolveRangeStart(state, 'day');
  const visibleProviders = getVisibleProviderSet(state);
  const appts = getVisibleAppointments(state, visibleProviders);
  return state.providerOrder
    .filter(id => visibleProviders.has(id))
    .map(providerId => {
      const provider = state.providers[providerId];
      const providerAppts = appts.filter(appt => appt.providerId === providerId && DateTime.fromISO(appt.start).hasSame(start, 'day'));
      return {
        providerId,
        providerName: provider?.displayName ?? providerId,
        colorToken: provider?.colorToken ?? 'blue-500',
        blocks: buildBlocks(providerAppts),
      };
    });
}

function buildBlocks(appointments) {
  const normalized = appointments
    .map(appt => {
      const start = DateTime.fromISO(appt.start);
      const end = appt.end ? DateTime.fromISO(appt.end) : start.plus({ minutes: appt.durationMinutes ?? 30 });
      return { appt, start, end };
    })
    .sort((a, b) => a.start.toMillis() - b.start.toMillis() || a.end.toMillis() - b.end.toMillis());

  const active = [];
  const blocks = [];

  normalized.forEach(entry => {
    const startMillis = entry.start.toMillis();

    for (let i = active.length - 1; i >= 0; i -= 1) {
      if (active[i].endMillis <= startMillis) {
        active.splice(i, 1);
      }
    }

    const usedLanes = new Set(active.map(item => item.block.laneIndex));
    let laneIndex = 0;
    while (usedLanes.has(laneIndex)) laneIndex += 1;

    const startOffset = Math.max(0, Math.round(entry.start.diff(entry.start.startOf('day'), 'minutes').minutes));
    const heightMinutes = Math.max(15, entry.end.diff(entry.start, 'minutes').minutes);

    const block = {
      appointmentId: entry.appt.id,
      providerId: entry.appt.providerId,
      startIso: entry.appt.start,
      endIso: entry.appt.end ?? entry.end.toISO(),
      startOffsetMinutes: startOffset,
      heightMinutes,
      laneIndex,
      laneCount: Math.max(1, active.length + 1),
      summary: entry.appt.patientName,
      subtext: entry.appt.visitType ?? '',
      chipColor: entry.appt.colorOverride ?? 'blue-500',
    };

    blocks.push(block);
    active.push({ endMillis: entry.end.toMillis(), block });
    active.sort((a, b) => a.endMillis - b.endMillis);

    const activeLaneCount = active.length;
    active.forEach(item => {
      item.block.laneCount = Math.max(item.block.laneCount, activeLaneCount);
    });
  });

  return blocks;
}

function resolveRangeStart(state, granularity) {
  const base = state.meta.rangeStart ? DateTime.fromISO(state.meta.rangeStart) : DateTime.now();
  if (granularity === 'week') return base.startOf('week');
  if (granularity === 'day') return base.startOf('day');
  return base.startOf('month');
}

function getVisibleProviderSet(state) {
  if (state.filters.providerIds && state.filters.providerIds.size > 0) {
    return state.filters.providerIds;
  }
  return new Set(state.providerOrder.length ? state.providerOrder : Object.keys(state.providers));
}

function getVisibleAppointments(state, providerSet) {
  return Object.values(state.appointments).filter(appt => providerSet.has(appt.providerId));
}

export { selectMeta, selectProviders, selectMonthMatrix, selectWeekColumns, selectDayTimeline };
