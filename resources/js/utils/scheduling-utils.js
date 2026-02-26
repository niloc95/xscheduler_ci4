/**
 * Scheduling Utilities - Centralized time slot and overlap detection
 * 
 * This module provides consistent logic for:
 * - Appointment overlap detection
 * - Time slot availability checking
 * - Provider booking status calculation
 * 
 * Used by: scheduler-week-view.js, scheduler-day-view.js, public-booking.js
 * Backend equivalent: AvailabilityService.php
 * 
 * @module scheduling-utils
 */

/**
 * Check if two time ranges overlap
 * Uses the standard interval overlap formula: start1 < end2 AND start2 < end1
 * 
 * @param {Object} range1 - First time range with start/end DateTime or timestamps
 * @param {Object} range2 - Second time range with start/end DateTime or timestamps
 * @returns {boolean} True if ranges overlap
 */
export function doTimeRangesOverlap(range1, range2) {
    // Handle Luxon DateTime objects
    const start1 = range1.start?.toMillis ? range1.start.toMillis() : new Date(range1.start).getTime();
    const end1 = range1.end?.toMillis ? range1.end.toMillis() : new Date(range1.end).getTime();
    const start2 = range2.start?.toMillis ? range2.start.toMillis() : new Date(range2.start).getTime();
    const end2 = range2.end?.toMillis ? range2.end.toMillis() : new Date(range2.end).getTime();
    
    // Standard overlap check: ranges overlap if start1 < end2 AND start2 < end1
    return start1 < end2 && start2 < end1;
}

/**
 * Find all appointments that overlap with a given time slot
 * 
 * @param {Array} appointments - Array of appointment objects with startDateTime/endDateTime
 * @param {DateTime} slotStart - Start of the time slot (Luxon DateTime)
 * @param {DateTime} slotEnd - End of the time slot (Luxon DateTime)
 * @param {Object} options - Optional filters
 * @param {number} options.providerId - Filter by specific provider
 * @param {number} options.excludeAppointmentId - Exclude a specific appointment (for updates)
 * @returns {Array} Appointments that overlap with the slot
 */
export function findOverlappingAppointments(appointments, slotStart, slotEnd, options = {}) {
    const { providerId, excludeAppointmentId } = options;
    
    return appointments.filter(apt => {
        // Exclude specific appointment if requested (for update scenarios)
        if (excludeAppointmentId && apt.id === excludeAppointmentId) {
            return false;
        }
        
        // Filter by provider if specified
        if (providerId !== undefined && apt.providerId !== providerId) {
            return false;
        }
        
        // Check overlap using the appointment's start and end times
        const aptStart = apt.startDateTime;
        const aptEnd = apt.endDateTime;
        
        // Overlap check: appointment overlaps slot if apt.start < slot.end AND apt.end > slot.start
        return aptStart < slotEnd && aptEnd > slotStart;
    });
}

/**
 * Get provider booking status for a time slot
 * Returns which providers are booked vs available for a given time period
 * 
 * @param {Array} appointments - All appointments to check
 * @param {DateTime} slotStart - Start of the time slot
 * @param {DateTime} slotEnd - End of the time slot  
 * @param {Array} providers - Array of provider objects to check
 * @param {number} excludeAppointmentId - Optional appointment ID to exclude
 * @returns {Object} { bookedProviders: [], availableProviders: [], overlappingAppointments: [] }
 */
function getProviderScheduleFor(providerSchedules, providerId) {
    if (!providerSchedules) return null;
    if (providerSchedules instanceof Map) {
        return providerSchedules.get(providerId) || providerSchedules.get(String(providerId));
    }
    if (typeof providerSchedules === 'object') {
        return providerSchedules[providerId] || providerSchedules[String(providerId)];
    }
    return null;
}

function isProviderWorkingForSlot(schedule, slotStart, slotEnd) {
    if (!schedule) return true;
    const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    const dayName = dayNames[slotStart.weekday % 7];
    const daySchedule = schedule[dayName];

    if (!daySchedule || !daySchedule.enabled) return false;

    const [startHour, startMin] = daySchedule.start.split(':').map(Number);
    const [endHour, endMin] = daySchedule.end.split(':').map(Number);
    const startTime = slotStart.set({ hour: startHour, minute: startMin, second: 0, millisecond: 0 });
    const endTime = slotStart.set({ hour: endHour, minute: endMin, second: 0, millisecond: 0 });

    return slotStart >= startTime && slotEnd <= endTime;
}

export function getProviderAvailabilityForSlot(appointments, slotStart, slotEnd, providers, excludeAppointmentId = null, providerSchedules = null) {
    const overlappingAppointments = findOverlappingAppointments(
        appointments, 
        slotStart, 
        slotEnd, 
        { excludeAppointmentId }
    );
    
    const bookedProviders = [];
    const availableProviders = [];
    
    providers.forEach(provider => {
        const schedule = getProviderScheduleFor(providerSchedules, provider.id);
        if (!isProviderWorkingForSlot(schedule, slotStart, slotEnd)) {
            return;
        }

        const isBooked = overlappingAppointments.some(apt => {
            const aptProviderId = parseInt(apt.providerId, 10);
            const provId = parseInt(provider.id, 10);
            return aptProviderId === provId;
        });
        
        if (isBooked) {
            bookedProviders.push(provider);
        } else {
            availableProviders.push(provider);
        }
    });
    
    return {
        bookedProviders,
        availableProviders,
        overlappingAppointments
    };
}

/**
 * Check if a specific time slot is available for a provider
 * 
 * @param {Array} appointments - All appointments to check
 * @param {DateTime} slotStart - Start of the time slot
 * @param {DateTime} slotEnd - End of the time slot
 * @param {number} providerId - Provider ID to check
 * @param {number} excludeAppointmentId - Optional appointment ID to exclude
 * @returns {Object} { available: boolean, conflicts: Array }
 */
export function isSlotAvailableForProvider(appointments, slotStart, slotEnd, providerId, excludeAppointmentId = null) {
    const conflicts = findOverlappingAppointments(
        appointments,
        slotStart,
        slotEnd,
        { providerId, excludeAppointmentId }
    );
    
    return {
        available: conflicts.length === 0,
        conflicts
    };
}

/**
 * Generate time slots for a date with availability information
 * 
 * @param {Object} options - Configuration options
 * @param {DateTime} options.date - The date to generate slots for
 * @param {Object} options.businessHours - { startTime: 'HH:mm', endTime: 'HH:mm' }
 * @param {number} options.slotDuration - Duration of each slot in minutes
 * @param {Array} options.appointments - Appointments to check against
 * @param {Array} options.providers - Providers to check availability for
 * @param {number} options.excludeAppointmentId - Optional appointment to exclude
 * @param {Map|Object} options.providerSchedules - Provider schedules keyed by provider id
 * @param {DateTime} options.minDateTime - Optional minimum DateTime (filter past slots)
 * @param {boolean} options.filterEmptyProviders - Remove slots with no working providers
 * @returns {Array} Array of slot objects with availability info
 *
 * @deprecated Phase 6 — Slot generation moved server-side.
 *   Use GET /api/calendar/{day|week|month}?date=... (CalendarController) to get
 *   pre-computed slots from CalendarRangeService + DayViewService/WeekViewService.
 *   This function is kept for backward-compat with client mode; it will be removed
 *   once all scheduler views consume the server-provided calendarModel.
 */
export function generateSlotsWithAvailability(options) {
    console.warn('generateSlotsWithAvailability is deprecated and should not be called in server mode.');
    return [];
}

/**
 * Check if an appointment would conflict with existing appointments
 * Used for validation before creating/updating appointments
 * 
 * @param {Array} appointments - Existing appointments
 * @param {DateTime} newStart - Proposed start time
 * @param {DateTime} newEnd - Proposed end time
 * @param {number} providerId - Provider for the new appointment
 * @param {number} excludeAppointmentId - Appointment ID to exclude (for updates)
 * @returns {Object} { hasConflict: boolean, conflicts: Array, message: string }
 */
export function checkForConflicts(appointments, newStart, newEnd, providerId, excludeAppointmentId = null) {
    const conflicts = findOverlappingAppointments(
        appointments,
        newStart,
        newEnd,
        { providerId, excludeAppointmentId }
    );
    
    if (conflicts.length === 0) {
        return {
            hasConflict: false,
            conflicts: [],
            message: 'Time slot is available'
        };
    }
    
    // Build a helpful conflict message
    const conflictTimes = conflicts.map(c => {
        const start = c.startDateTime?.toFormat?.('h:mm a') || c.start;
        const end = c.endDateTime?.toFormat?.('h:mm a') || c.end;
        return `${start} - ${end}`;
    }).join(', ');
    
    return {
        hasConflict: true,
        conflicts,
        message: `Conflicts with existing appointment(s): ${conflictTimes}`
    };
}

/**
 * Calculate appointment duration in minutes from start and end times
 * 
 * @param {DateTime} startDateTime - Start time
 * @param {DateTime} endDateTime - End time
 * @returns {number} Duration in minutes
 */
export function calculateDurationMinutes(startDateTime, endDateTime) {
    if (startDateTime.diff && endDateTime.diff) {
        // Luxon DateTime
        return Math.round(endDateTime.diff(startDateTime, 'minutes').minutes);
    }
    // Fallback for regular dates
    const startMs = startDateTime.getTime ? startDateTime.getTime() : new Date(startDateTime).getTime();
    const endMs = endDateTime.getTime ? endDateTime.getTime() : new Date(endDateTime).getTime();
    return Math.round((endMs - startMs) / 60000);
}
