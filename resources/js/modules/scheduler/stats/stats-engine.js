/**
 * Stats Engine - The Shared Engine
 * 
 * Pure, view-agnostic statistics calculation service.
 * All views consume from this engine - none compute their own stats.
 * 
 * Car Analogy: This is the ENGINE - same power plant in every car model.
 * The body (view) differs, but the engine logic is identical.
 * 
 * @module scheduler/stats-engine
 */

import { DateTime } from 'luxon';
import { STATUS_DEFINITIONS } from './stats-definitions.js';

/**
 * Core stats calculation - THE ONLY place stats are computed
 * 
 * @param {Array} appointments - Raw appointment data
 * @param {Object} dateRange - { start: DateTime, end: DateTime }
 * @returns {Object} Computed statistics
 */
function computeStats(appointments, dateRange) {
    const now = DateTime.now();
    const filtered = filterByDateRange(appointments, dateRange);
    
    // Initialize counters from STATUS_DEFINITIONS (single source of truth)
    const byStatus = {};
    Object.keys(STATUS_DEFINITIONS).forEach(status => {
        byStatus[status] = 0;
    });
    
    // Time-based categorization
    let upcoming = 0;
    let past = 0;
    let inProgress = 0;
    
    // Provider distribution
    const byProvider = {};
    
    // Hourly distribution (for utilization)
    const byHour = Array(24).fill(0);
    
    // Process each appointment ONCE
    filtered.forEach(apt => {
        const status = normalizeStatus(apt.status);
        const aptStart = parseAppointmentDateTime(apt);
        const aptEnd = aptStart.plus({ minutes: apt.duration || 30 });
        
        // Count by status
        if (byStatus.hasOwnProperty(status)) {
            byStatus[status]++;
        }
        
        // Time categorization
        if (aptStart > now) {
            upcoming++;
        } else if (aptEnd < now) {
            past++;
        } else {
            inProgress++;
        }
        
        // Provider distribution
        const providerId = apt.provider_id || apt.providerId || 'unassigned';
        byProvider[providerId] = (byProvider[providerId] || 0) + 1;
        
        // Hourly distribution
        const hour = aptStart.hour;
        byHour[hour]++;
    });
    
    const total = filtered.length;
    
    return {
        // Core counts
        total,
        byStatus,
        
        // Time-based
        upcoming,
        past,
        inProgress,
        
        // Distribution
        byProvider,
        byHour,
        
        // Computed rates
        completionRate: total > 0 ? (byStatus.completed / total) * 100 : 0,
        cancellationRate: total > 0 ? ((byStatus.cancelled + byStatus['no-show']) / total) * 100 : 0,
        confirmationRate: total > 0 ? (byStatus.confirmed / total) * 100 : 0,
        
        // Utilization (active vs cancelled/no-show)
        activeCount: total - byStatus.cancelled - byStatus['no-show'],
        
        // Peak hour
        peakHour: byHour.indexOf(Math.max(...byHour)),
        
        // Metadata
        dateRange: {
            start: dateRange.start.toISO(),
            end: dateRange.end.toISO()
        },
        computedAt: now.toISO()
    };
}

/**
 * Filter appointments by date range
 * @private
 */
function filterByDateRange(appointments, dateRange) {
    if (!appointments || !Array.isArray(appointments)) return [];
    if (!dateRange?.start || !dateRange?.end) return appointments;
    
    return appointments.filter(apt => {
        const aptDate = parseAppointmentDateTime(apt);
        return aptDate >= dateRange.start && aptDate <= dateRange.end;
    });
}

/**
 * Parse appointment to DateTime
 * @private
 */
function parseAppointmentDateTime(apt) {
    // Handle various date formats from API
    const dateStr = apt.appointment_date || apt.appointmentDate || apt.date;
    const timeStr = apt.start_time || apt.startTime || apt.time || '00:00';
    
    if (!dateStr) {
        return DateTime.now();
    }
    
    // Combine date and time
    const combined = `${dateStr}T${timeStr}`;
    const parsed = DateTime.fromISO(combined);
    
    return parsed.isValid ? parsed : DateTime.now();
}

/**
 * Normalize status to match STATUS_DEFINITIONS keys
 * @private
 */
function normalizeStatus(status) {
    if (!status) return 'pending';
    
    const normalized = String(status).toLowerCase().trim();
    
    // Map variations to canonical keys
    const statusMap = {
        'pending': 'pending',
        'confirmed': 'confirmed',
        'completed': 'completed',
        'cancelled': 'cancelled',
        'canceled': 'cancelled',
        'no-show': 'no-show',
        'noshow': 'no-show',
        'no_show': 'no-show'
    };
    
    return statusMap[normalized] || 'pending';
}

/**
 * Calculate date range for a given view type and reference date
 * 
 * @param {string} viewType - 'day' | 'week' | 'month'
 * @param {DateTime} referenceDate - The date to calculate range around
 * @returns {Object} { start: DateTime, end: DateTime }
 */
export function calculateDateRange(viewType, referenceDate) {
    const date = referenceDate || DateTime.now();
    
    switch (viewType) {
        case 'day':
            return {
                start: date.startOf('day'),
                end: date.endOf('day')
            };
            
        case 'week':
            // Week starts on Monday (ISO standard)
            return {
                start: date.startOf('week'),
                end: date.endOf('week')
            };
            
        case 'month':
            return {
                start: date.startOf('month'),
                end: date.endOf('month')
            };
            
        default:
            return {
                start: date.startOf('day'),
                end: date.endOf('day')
            };
    }
}

/**
 * Main entry point - Get stats for a specific view
 * 
 * Views call this, never compute their own stats.
 * 
 * @param {Array} appointments - Appointment data
 * @param {string} viewType - 'day' | 'week' | 'month'
 * @param {DateTime} referenceDate - Current date context
 * @returns {Object} View-ready statistics
 */
export function getStatsForView(appointments, viewType, referenceDate) {
    const dateRange = calculateDateRange(viewType, referenceDate);
    const stats = computeStats(appointments, dateRange);
    
    return {
        viewType,
        ...stats
    };
}

/**
 * Get stats for custom date range (for API/external use)
 * 
 * @param {Array} appointments - Appointment data
 * @param {DateTime} startDate - Range start
 * @param {DateTime} endDate - Range end
 * @returns {Object} Statistics for the range
 */
export function getStatsForRange(appointments, startDate, endDate) {
    const dateRange = { start: startDate, end: endDate };
    return computeStats(appointments, dateRange);
}

/**
 * Compare stats between two periods (for trends)
 * 
 * @param {Object} currentStats - Current period stats
 * @param {Object} previousStats - Previous period stats
 * @returns {Object} Comparison with deltas
 */
export function compareStats(currentStats, previousStats) {
    if (!previousStats) {
        return {
            current: currentStats,
            previous: null,
            deltas: null
        };
    }
    
    const deltas = {
        total: currentStats.total - previousStats.total,
        completionRate: currentStats.completionRate - previousStats.completionRate,
        cancellationRate: currentStats.cancellationRate - previousStats.cancellationRate
    };
    
    // Calculate percentage changes
    Object.keys(STATUS_DEFINITIONS).forEach(status => {
        const current = currentStats.byStatus[status] || 0;
        const previous = previousStats.byStatus[status] || 0;
        deltas[status] = current - previous;
    });
    
    return {
        current: currentStats,
        previous: previousStats,
        deltas
    };
}

// Export for testing
export { computeStats, filterByDateRange, normalizeStatus };
