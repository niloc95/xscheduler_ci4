/**
 * =============================================================================
 * TIME RANGE GENERATOR — SINGLE SOURCE OF TRUTH
 * =============================================================================
 * 
 * @file        resources/js/modules/scheduler/utils/timeRangeGenerator.js
 * @description Unified time slot generation for timelines and appointment grids.
 *              Ensures timeline rows and grid rows are perfectly aligned.
 * 
 * KEY PRINCIPLE:
 * -------------
 * There must be ONE single source of truth for time ranges.
 * Both timeline rendering and appointment grid MUST consume this same function.
 * 
 * USAGE:
 * ------
 * import { generateTimeRange, generateTimeSlots } from './utils/timeRangeGenerator.js';
 * 
 * const slots = generateTimeRange({
 *     startTime: '08:00',
 *     endTime: '17:00',
 *     interval: 15  // minutes
 * });
 * 
 * // Returns: ['08:00', '08:15', '08:30', ..., '17:00']
 * 
 * @package     WebScheduler
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * @version     1.0.0
 * =============================================================================
 */

/**
 * Generate an array of time slots between start and end times.
 * 
 * @param {Object} options - Generation options
 * @param {string} options.startTime - Start time in 'HH:MM' or 'HH:MM:SS' format
 * @param {string} options.endTime - End time in 'HH:MM' or 'HH:MM:SS' format
 * @param {number} [options.interval=60] - Interval in minutes (default: 60)
 * @param {boolean} [options.includeEnd=false] - Include end time in the range (default: false)
 * 
 * @returns {string[]} Array of time strings in 'HH:MM' format
 * 
 * @example
 * generateTimeRange({ startTime: '08:00', endTime: '17:00', interval: 60 })
 * // Returns: ['08:00', '09:00', '10:00', ..., '16:00']
 * 
 * @example
 * generateTimeRange({ startTime: '08:00', endTime: '09:00', interval: 15, includeEnd: true })
 * // Returns: ['08:00', '08:15', '08:30', '08:45', '09:00']
 */
export function generateTimeRange({ startTime, endTime, interval = 60, includeEnd = false }) {
    // Validate inputs
    if (!startTime || !endTime) {
        console.warn('[timeRangeGenerator] Missing startTime or endTime');
        return[];
    }

    if (interval <= 0) {
        console.warn('[timeRangeGenerator] Invalid interval, must be > 0');
        return [];
    }

    // Parse time strings to minutes since midnight
    const startMinutes = timeToMinutes(startTime);
    const endMinutes = timeToMinutes(endTime);

    if (startMinutes === null || endMinutes === null) {
        console.warn('[timeRangeGenerator] Invalid time format');
        return [];
    }

    if (startMinutes >= endMinutes) {
        console.warn('[timeRangeGenerator] startTime must be before endTime');
        return [];
    }

    // Generate time slots
    const slots = [];
    let current = startMinutes;
    const limit = includeEnd ? endMinutes : endMinutes - 1;

    while (current <= limit) {
        slots.push(minutesToTime(current));
        current += interval;
    }

    return slots;
}

/**
 * Generate detailed time slot objects with metadata.
 * Useful for rendering UI components with additional context.
 * 
 * @param {Object} options - Generation options
 * @param {string} options.startTime - Start time in 'HH:MM' or 'HH:MM:SS' format
 * @param {string} options.endTime - End time in 'HH:MM' or 'HH:MM:SS' format
 * @param {number} [options.interval=60] - Interval in minutes (default: 60)
 * @param {boolean} [options.includeEnd=false] - Include end time in the range
 * 
 * @returns {Object[]} Array of slot objects with time, index, and metadata
 * 
 * @example
 * generateTimeSlots({ startTime: '08:00', endTime: '10:00', interval: 30 })
 * // Returns: [
 * //   { time: '08:00', index: 0, minutes: 480, hour: 8, minute: 0, isHourMark: true },
 * //   { time: '08:30', index: 1, minutes: 510, hour: 8, minute: 30, isHourMark: false },
 * //   { time: '09:00', index: 2, minutes: 540, hour: 9, minute: 0, isHourMark: true },
 * //   { time: '09:30', index: 3, minutes: 570, hour: 9, minute: 30, isHourMark: false }
 * // ]
 */
export function generateTimeSlots({ startTime, endTime, interval = 60, includeEnd = false }) {
    const timeStrings = generateTimeRange({ startTime, endTime, interval, includeEnd });
    
    return timeStrings.map((time, index) => {
        const minutes = timeToMinutes(time);
        const hour = Math.floor(minutes / 60);
        const minute = minutes % 60;
        
        return {
            time,           // 'HH:MM'
            index,          // Position in array
            minutes,        // Minutes since midnight
            hour,           // Hour (0-23)
            minute,         // Minute (0-59)
            isHourMark: minute === 0  // True if on the hour (e.g., 08:00, 09:00)
        };
    });
}

/**
 * Parse time string to minutes since midnight.
 * Supports 'HH:MM' and 'HH:MM:SS' formats.
 * 
 * @param {string} timeString - Time string
 * @returns {number|null} Minutes since midnight, or null if invalid
 * 
 * @example
 * timeToMinutes('08:30')    // Returns: 510
 * timeToMinutes('14:45:00') // Returns: 885
 * timeToMinutes('invalid')  // Returns: null
 */
export function timeToMinutes(timeString) {
    if (!timeString || typeof timeString !== 'string') {
        return null;
    }

    const parts = timeString.split(':');
    if (parts.length < 2) {
        return null;
    }

    const hours = parseInt(parts[0], 10);
    const minutes = parseInt(parts[1], 10);

    if (isNaN(hours) || isNaN(minutes)) {
        return null;
    }

    if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
        return null;
    }

    return hours * 60 + minutes;
}

/**
 * Convert minutes since midnight to time string.
 * 
 * @param {number} totalMinutes - Minutes since midnight (0-1439)
 * @returns {string} Time string in 'HH:MM' format
 * 
 * @example
 * minutesToTime(510)  // Returns: '08:30'
 * minutesToTime(885)  // Returns: '14:45'
 * minutesToTime(0)    // Returns: '00:00'
 */
export function minutesToTime(totalMinutes) {
    if (typeof totalMinutes !== 'number' || totalMinutes < 0) {
        return '00:00';
    }

    const hours = Math.floor(totalMinutes / 60) % 24;
    const minutes = Math.floor(totalMinutes % 60);

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

/**
 * Get the number of slots between two times.
 * 
 * @param {string} startTime - Start time in 'HH:MM' format
 * @param {string} endTime - End time in 'HH:MM' format
 * @param {number} interval - Interval in minutes
 * @returns {number} Number of slots (0 if invalid)
 * 
 * @example
 * getSlotCount('08:00', '17:00', 60)  // Returns: 9
 * getSlotCount('08:00', '09:00', 15)  // Returns: 4
 */
export function getSlotCount(startTime, endTime, interval) {
    const startMinutes = timeToMinutes(startTime);
    const endMinutes = timeToMinutes(endTime);

    if (startMinutes === null || endMinutes === null || interval <= 0) {
        return 0;
    }

    const totalMinutes = endMinutes - startMinutes;
    return Math.floor(totalMinutes / interval);
}

/**
 * Calculate total height in pixels for a time range.
 * Uses the standard hour height of 60px.
 * 
 * @param {string} startTime - Start time in 'HH:MM' format
 * @param {string} endTime - End time in 'HH:MM' format
 * @param {number} [hourHeightPx=60] - Height of one hour in pixels (default: 60)
 * @returns {number} Total height in pixels
 * 
 * @example
 * calculateRangeHeight('08:00', '17:00')      // Returns: 540 (9 hours × 60px)
 * calculateRangeHeight('08:00', '13:30', 60)  // Returns: 330 (5.5 hours × 60px)
 */
export function calculateRangeHeight(startTime, endTime, hourHeightPx = 60) {
    const startMinutes = timeToMinutes(startTime);
    const endMinutes = timeToMinutes(endTime);

    if (startMinutes === null || endMinutes === null) {
        return 0;
    }

    const totalMinutes = endMinutes - startMinutes;
    return (totalMinutes / 60) * hourHeightPx;
}

/**
 * Find the slot index for a given time.
 * Returns -1 if time is outside the range.
 * 
 * @param {string} time - Time to find in 'HH:MM' format
 * @param {string[]} slots - Array of time slot strings
 * @returns {number} Index of the slot, or -1 if not found
 * 
 * @example
 * const slots = ['08:00', '08:15', '08:30', '08:45'];
 * findSlotIndex('08:30', slots)  // Returns: 2
 * findSlotIndex('10:00', slots)  // Returns: -1
 */
export function findSlotIndex(time, slots) {
    return slots.indexOf(time);
}

/**
 * Calculate slot index for an appointment start time.
 * Useful for grid positioning.
 * 
 * @param {string} appointmentTime - Appointment start time in 'HH:MM' format
 * @param {string} rangeStartTime - Range start time in 'HH:MM' format
 * @param {number} interval - Interval in minutes
 * @returns {number} Slot index (0-based), or -1 if before range start
 * 
 * @example
 * calculateSlotIndex('09:30', '08:00', 15)  // Returns: 6 (90 minutes / 15)
 * calculateSlotIndex('08:00', '08:00', 30)  // Returns: 0
 * calculateSlotIndex('07:00', '08:00', 15)  // Returns: -1 (before range)
 */
export function calculateSlotIndex(appointmentTime, rangeStartTime, interval) {
    const appointmentMinutes = timeToMinutes(appointmentTime);
    const startMinutes = timeToMinutes(rangeStartTime);

    if (appointmentMinutes === null || startMinutes === null || interval <= 0) {
        return -1;
    }

    if (appointmentMinutes < startMinutes) {
        return -1; // Before range start
    }

    const minutesSinceStart = appointmentMinutes - startMinutes;
    return Math.floor(minutesSinceStart / interval);
}

/**
 * Calculate row span for an appointment.
 * Determines how many grid rows the appointment should occupy.
 * 
 * @param {number} durationMinutes - Appointment duration in minutes
 * @param {number} interval - Grid interval in minutes
 * @returns {number} Number of rows to span (minimum 1)
 * 
 * @example
 * calculateRowSpan(30, 15)  // Returns: 2 (30 minutes / 15 minutes per slot)
 * calculateRowSpan(60, 30)  // Returns: 2
 * calculateRowSpan(15, 30)  // Returns: 1 (rounds up to minimum)
 */
export function calculateRowSpan(durationMinutes, interval) {
    if (durationMinutes <= 0 || interval <= 0) {
        return 1;
    }

    return Math.max(1, Math.ceil(durationMinutes / interval));
}

/**
 * Default export with all utilities bundled.
 */
export default {
    generateTimeRange,
    generateTimeSlots,
    timeToMinutes,
    minutesToTime,
    getSlotCount,
    calculateRangeHeight,
    findSlotIndex,
    calculateSlotIndex,
    calculateRowSpan,
};
