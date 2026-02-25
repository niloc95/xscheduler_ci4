import { DateTime } from 'luxon';

function toSchedulerDateTime(value, timezone) {
    if (DateTime.isDateTime(value)) {
        return value.setZone(timezone);
    }

    if (value instanceof Date) {
        return DateTime.fromJSDate(value, { zone: timezone });
    }

    const fromIso = DateTime.fromISO(String(value), { zone: timezone });
    return fromIso.isValid ? fromIso : DateTime.now().setZone(timezone);
}

export function formatDateNavLabel({ date, view = 'month', timezone = 'UTC' } = {}) {
    const currentDate = toSchedulerDateTime(date, timezone);

    switch (view) {
        case 'day':
            return currentDate.toFormat('EEEE, MMMM d, yyyy');
        case 'week': {
            const weekStart = currentDate.startOf('week');
            const weekEnd = weekStart.plus({ days: 6 });

            if (weekStart.month === weekEnd.month) {
                return `${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('d, yyyy')}`;
            }

            if (weekStart.year === weekEnd.year) {
                return `${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('MMM d, yyyy')}`;
            }

            return `${weekStart.toFormat('MMM d, yyyy')} - ${weekEnd.toFormat('MMM d, yyyy')}`;
        }
        case 'month':
        default:
            return currentDate.toFormat('MMMM yyyy');
    }
}

export function syncDateNavLabel(element, options = {}) {
    if (!element) {
        return;
    }

    element.textContent = formatDateNavLabel(options);
}
