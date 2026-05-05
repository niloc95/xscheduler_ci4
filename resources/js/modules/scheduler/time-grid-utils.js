/**
 * Time Grid Utilities — Shared Functions
 * 
 * Common utilities for time-grid-based views (Week, Day).
 * Handles:
 * - Shared constants (business hours)
 * 
 * Overlap resolution is performed server-side by EventLayoutService.
 * Frontend consumes backend layout metadata (_widthPct, _leftPct, _colIndex).
 */

/**
 * Calculate the week start date (Monday-first, ISO 8601).
 * 
 * @param {DateTime} date - Any date within the week
 * @param {number} firstDayOfWeek - Day of week (0=Sun, 1=Mon, ..., 6=Sat)
 *                                  Default: 1 (Monday)
 * @returns {DateTime} - Start of week (Monday at 00:00:00)
 */
export function weekStart(date, firstDayOfWeek = 1) {
    // Convert firstDayOfWeek (0=Sun, 1=Mon) to Luxon weekday (1=Mon, 7=Sun)
    const luxonFirstDay = firstDayOfWeek === 0 ? 7 : firstDayOfWeek;
    const currentWeekday = date.weekday;

    let daysToSubtract = currentWeekday - luxonFirstDay;
    if (daysToSubtract < 0) {
        daysToSubtract += 7;
    }

    return date.minus({ days: daysToSubtract }).startOf('day');
}

/**
 * Get business hours from config with defaults.
 * 
 * @param {Object} config - Configuration object with businessHours
 * @returns {Object} - { startHour, endHour, startTime, endTime }
 */
export function getBusinessHours(config = {}) {
    let startTime = config?.businessHours?.startTime || config?.slotMinTime || '08:00';
    let endTime = config?.businessHours?.endTime || config?.slotMaxTime || '17:00';

    // Parse time strings to hours
    const parseTime = (str) => {
        if (typeof str === 'number') return str;
        const [h] = String(str).split(':').map(Number);
        return h;
    };

    const startHour = parseTime(startTime);
    const endHour = parseTime(endTime);

    return {
        startHour,
        endHour,
        startTime,
        endTime,
        hoursPerDay: endHour - startHour,
    };
}

/**
 * Check if an appointment is visible in a given time range.
 * 
 * @param {Object} appointment - Appointment with startDateTime, endDateTime
 * @returns {boolean} - True if visible in time grid
 */
export function isAppointmentVisible(appointment) {
    return !!(appointment?.startDateTime && appointment?.endDateTime);
}
