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
export function getProviderAvailabilityForSlot(appointments, slotStart, slotEnd, providers, excludeAppointmentId = null) {
    const overlappingAppointments = findOverlappingAppointments(
        appointments, 
        slotStart, 
        slotEnd, 
        { excludeAppointmentId }
    );
    
    const bookedProviders = [];
    const availableProviders = [];
    
    providers.forEach(provider => {
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
 * @returns {Array} Array of slot objects with availability info
 */
export function generateSlotsWithAvailability(options) {
    const {
        date,
        businessHours,
        slotDuration = 30,
        appointments = [],
        providers = [],
        excludeAppointmentId = null
    } = options;
    
    const slots = [];
    
    // Parse business hours
    const parseTime = (timeStr) => {
        if (!timeStr) return { hour: 9, minute: 0 };
        const parts = timeStr.split(':');
        return { 
            hour: parseInt(parts[0], 10), 
            minute: parseInt(parts[1] || '0', 10) 
        };
    };
    
    const start = parseTime(businessHours?.startTime);
    const end = parseTime(businessHours?.endTime);
    
    // Validate business hours
    if (start.hour > end.hour || (start.hour === end.hour && start.minute >= end.minute)) {
        console.warn('[scheduling-utils] Invalid business hours, using defaults 09:00-17:00');
        start.hour = 9;
        start.minute = 0;
        end.hour = 17;
        end.minute = 0;
    }
    
    // Generate slots
    let currentHour = start.hour;
    let currentMinute = start.minute;
    
    while (currentHour < end.hour || (currentHour === end.hour && currentMinute < end.minute)) {
        const timeStr = `${currentHour.toString().padStart(2, '0')}:${currentMinute.toString().padStart(2, '0')}`;
        
        // Create slot start/end DateTime objects
        const slotStart = date.set({ hour: currentHour, minute: currentMinute, second: 0, millisecond: 0 });
        const slotEnd = slotStart.plus({ minutes: slotDuration });
        
        // Get provider availability for this slot
        const availability = getProviderAvailabilityForSlot(
            appointments,
            slotStart,
            slotEnd,
            providers,
            excludeAppointmentId
        );
        
        slots.push({
            time: timeStr,
            slotStart,
            slotEnd,
            isBlocked: false,
            ...availability
        });
        
        // Increment to next slot
        currentMinute += slotDuration;
        if (currentMinute >= 60) {
            currentHour += Math.floor(currentMinute / 60);
            currentMinute = currentMinute % 60;
        }
    }
    
    return slots;
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
