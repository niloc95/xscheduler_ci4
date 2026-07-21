import { DateTime } from 'luxon';
import { weekStart as getWeekStart } from './time-grid-utils.js';

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
        case 'agenda': {
            // Agenda is a single-day list; the toolbar is the ONLY date display
            // (no separate sticky heading), so show a compact day with a relative
            // hint. e.g. "Today · Mon, 20 Jul" or "Wed, 22 Jul".
            const today = DateTime.now().setZone(timezone).startOf('day');
            const diffDays = Math.round(currentDate.startOf('day').diff(today, 'days').days);
            const relative = diffDays === 0 ? 'Today'
                : diffDays === 1 ? 'Tomorrow'
                : diffDays === -1 ? 'Yesterday'
                : null;
            const dayLabel = currentDate.toFormat('EEE, d MMM');
            return relative ? `${relative} · ${dayLabel}` : dayLabel;
        }
        case 'day':
            return currentDate.toFormat('EEEE, MMMM d, yyyy');
        case 'week': {
            const weekStart = getWeekStart(currentDate, 1);
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
