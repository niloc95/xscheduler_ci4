/**
 * Time Grid Utilities — Shared Functions
 * 
 * Common utilities for time-grid-based views (Week, Day).
 * Handles:
 * - Overlap resolution (sweep-line algorithm)
 * - Position & size calculations (topPx, heightPx)
 * - Shared constants (business hours, hour height)
 */

// Shared Constants
export const HOUR_HEIGHT_PX = 60;           // Height of 1 hour row in pixels
export const MIN_APPOINTMENT_HEIGHT_PX = 20; // Minimum height for very short appointments
export const HOUR_TO_MIN_MARGIN_PX = 3;    // Margin between appointment and grid line

/**
 * Resolves overlapping appointments using a sweep-line algorithm.
 * Adds `_col` (column index) and `_colCount` (total columns needed) to each appointment.
 * 
 * @param {Array} appointments - Array of appointment objects with startDateTime, endDateTime
 * @returns {Array} - Same appointments array with overlap metadata added
 */
export function resolveOverlaps(appointments) {
    if (!appointments || appointments.length === 0) {
        return appointments;
    }

    // Sort by start time, then by end time (ascending)
    const sorted = [...appointments].sort((a, b) => {
        const startCmp = a.startDateTime.toMillis() - b.startDateTime.toMillis();
        if (startCmp !== 0) return startCmp;
        return a.endDateTime.toMillis() - b.endDateTime.toMillis();
    });

    // Sweep-line: track active intervals at each position
    const events = [];
    sorted.forEach((apt, idx) => {
        events.push({
            time: apt.startDateTime.toMillis(),
            type: 'start',
            aptIndex: idx,
            apt,
        });
        events.push({
            time: apt.endDateTime.toMillis(),
            type: 'end',
            aptIndex: idx,
            apt,
        });
    });

    // Sort events by time, with 'end' events before 'start' events at same time
    events.sort((a, b) => {
        const timeCmp = a.time - b.time;
        if (timeCmp !== 0) return timeCmp;
        return a.type === 'end' ? -1 : 1;
    });

    // Process events and assign columns
    const activeIntervals = [];
    const appointmentColumns = new Map(); // aptIndex -> column

    events.forEach((event) => {
        if (event.type === 'start') {
            // Find the lowest available column
            let col = 0;
            outer: while (true) {
                for (const active of activeIntervals) {
                    if (appointmentColumns.get(active) === col) {
                        col++;
                        continue outer;
                    }
                }
                break; // Found an available column
            }
            appointmentColumns.set(event.aptIndex, col);
            activeIntervals.push(event.aptIndex);
        } else {
            // Remove from active intervals
            const idx = activeIntervals.indexOf(event.aptIndex);
            if (idx >= 0) {
                activeIntervals.splice(idx, 1);
            }
        }
    });

    // Find max column count (total number of overlapping columns needed)
    let maxCol = 0;
    appointmentColumns.forEach((col) => {
        maxCol = Math.max(maxCol, col);
    });
    const totalCols = maxCol + 1;

    // Calculate column width and position for each appointment
    const colWidth = 100 / totalCols; // Percentage width per column

    sorted.forEach((apt, idx) => {
        apt._col = appointmentColumns.get(idx) || 0;
        apt._colCount = totalCols;
        apt._colWidth = colWidth; // % width
        apt._colLeft = apt._col * colWidth; // % left position
    });

    return sorted;
}

/**
 * Calculate top position (px) for an appointment based on start time.
 * 
 * @param {DateTime} startDateTime - Luxon DateTime of appointment start
 * @param {number} businessHourStart - Hour when business starts (e.g., 8 for 8 AM)
 * @param {number} hourHeightPx - Height of one hour in pixels (default: 60)
 * @returns {number} - Top position in pixels
 */
export function topPx(startDateTime, businessHourStart = 8, hourHeightPx = HOUR_HEIGHT_PX) {
    const hoursSinceStart = startDateTime.hour - businessHourStart + startDateTime.minute / 60;
    return Math.max(0, hoursSinceStart * hourHeightPx);
}

/**
 * Calculate height (px) for an appointment based on duration.
 * Ensures minimum height for visibility.
 * 
 * @param {number} durationMinutes - Duration of the appointment in minutes
 * @param {number} hourHeightPx - Height of one hour in pixels (default: 60)
 * @returns {number} - Height in pixels
 */
export function heightPx(durationMinutes, hourHeightPx = HOUR_HEIGHT_PX) {
    const calculatedHeight = (durationMinutes / 60) * hourHeightPx - HOUR_TO_MIN_MARGIN_PX;
    return Math.max(MIN_APPOINTMENT_HEIGHT_PX, calculatedHeight);
}

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
