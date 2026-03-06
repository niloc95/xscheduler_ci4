import { DateTime } from 'luxon';

export function normalizeFirstDayOfWeek(firstDayOfWeek) {
    const parsed = Number(firstDayOfWeek);
    if (!Number.isInteger(parsed) || parsed < 0 || parsed > 6) {
        return 0;
    }

    return parsed;
}

export function buildMonthGridDays(displayDate, firstDayOfWeek = 0, totalDays = 42) {
    const normalizedFirstDay = normalizeFirstDayOfWeek(firstDayOfWeek);
    const monthStart = displayDate.startOf('month');

    const luxonFirstDay = normalizedFirstDay === 0 ? 7 : normalizedFirstDay;
    const monthStartWeekday = monthStart.weekday;
    let daysBack = monthStartWeekday - luxonFirstDay;
    if (daysBack < 0) daysBack += 7;

    const gridStart = monthStart.minus({ days: daysBack });

    const days = [];
    let current = gridStart;
    for (let i = 0; i < totalDays; i++) {
        days.push(current);
        current = current.plus({ days: 1 });
    }

    return days;
}

export function buildMonthGridWeeks(displayDate, firstDayOfWeek = 0, weekCount = 6) {
    const days = buildMonthGridDays(displayDate, firstDayOfWeek, weekCount * 7);
    const weeks = [];

    for (let i = 0; i < days.length; i += 7) {
        weeks.push(days.slice(i, i + 7));
    }

    return weeks;
}

export function getRotatedWeekdayInitials(firstDayOfWeek = 0) {
    const normalizedFirstDay = normalizeFirstDayOfWeek(firstDayOfWeek);
    const dayNames = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    return [...dayNames.slice(normalizedFirstDay), ...dayNames.slice(0, normalizedFirstDay)];
}

export function getRotatedWeekdayShortNames(referenceDate, firstDayOfWeek = 0) {
    const normalizedFirstDay = normalizeFirstDayOfWeek(firstDayOfWeek);
    const firstWeekday = normalizedFirstDay === 0 ? 7 : normalizedFirstDay;
    const anchor = (referenceDate || DateTime.now()).startOf('week').set({ weekday: firstWeekday });

    return Array.from({ length: 7 }, (_, index) => anchor.plus({ days: index }).toFormat('ccc'));
}

export function buildAppointmentCountsByDate(appointments = []) {
    const counts = {};

    appointments.forEach((appointment) => {
        const dateKey = appointment?.startDateTime?.toISODate?.();
        if (!dateKey) return;

        counts[dateKey] = (counts[dateKey] || 0) + 1;
    });

    return counts;
}
