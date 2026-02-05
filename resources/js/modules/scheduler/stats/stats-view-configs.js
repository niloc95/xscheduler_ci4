/**
 * View Configurations - The Body Specifications
 * 
 * Each view (Day/Week/Month) has its own configuration that determines:
 * - Which stats to display
 * - Display order and emphasis
 * - Layout preferences
 * 
 * Car Analogy: These are the BODY SPECIFICATIONS - 
 * BMW interior differs from Volvo differs from Toyota,
 * but all use the same engine and sensors.
 * 
 * NO LOGIC HERE - only configuration.
 * Views never compute, only consume.
 * 
 * @module scheduler/stats-view-configs
 */

/**
 * Day View Configuration (BMW-like)
 * 
 * Characteristics:
 * - Operational, real-time, dense
 * - Focus on urgency and upcoming work
 * - Needs quick status counts for immediate action
 */
export const DAY_VIEW_CONFIG = {
    viewType: 'day',
    intent: 'operational',
    
    // Primary stats - shown prominently
    primaryStats: [
        { key: 'upcoming', emphasis: 'high', showIcon: true },
        { key: 'inProgress', emphasis: 'high', showIcon: true }
    ],
    
    // Status breakdown - shown as compact pills
    statusBreakdown: {
        show: true,
        layout: 'horizontal',
        showCounts: true,
        showLabels: 'short',  // Use shortLabel
        statuses: ['pending', 'confirmed', 'completed']
    },
    
    // Secondary stats - shown smaller
    secondaryStats: [
        { key: 'completionRate', emphasis: 'low', showDelta: false }
    ],
    
    // Visual preferences
    display: {
        showTotal: true,
        showTrends: false,
        compactMode: false,
        showChart: false
    }
};

/**
 * Week View Configuration (Volvo-like)
 * 
 * Characteristics:
 * - Balanced, planning-oriented
 * - Focus on workload and utilization
 * - Needs distribution and completion metrics
 */
export const WEEK_VIEW_CONFIG = {
    viewType: 'week',
    intent: 'planning',
    
    // Primary stats - shown prominently
    primaryStats: [
        { key: 'total', emphasis: 'high', showIcon: true },
        { key: 'activeCount', emphasis: 'medium', showIcon: true }
    ],
    
    // Status breakdown - shown as compact pills
    statusBreakdown: {
        show: true,
        layout: 'horizontal',
        showCounts: true,
        showLabels: 'short',
        statuses: ['pending', 'confirmed', 'completed', 'cancelled']
    },
    
    // Secondary stats - shown smaller
    secondaryStats: [
        { key: 'completionRate', emphasis: 'medium', showDelta: true },
        { key: 'cancellationRate', emphasis: 'low', showDelta: true }
    ],
    
    // Visual preferences
    display: {
        showTotal: true,
        showTrends: true,
        compactMode: false,
        showChart: false
    }
};

/**
 * Month View Configuration (Toyota-like)
 * 
 * Characteristics:
 * - Stable, summary-oriented
 * - Focus on trends and volume
 * - Needs aggregate metrics and comparisons
 */
export const MONTH_VIEW_CONFIG = {
    viewType: 'month',
    intent: 'summary',
    
    // Primary stats - shown prominently
    primaryStats: [
        { key: 'total', emphasis: 'high', showIcon: true }
    ],
    
    // Status breakdown - shown as compact pills
    statusBreakdown: {
        show: true,
        layout: 'horizontal',
        showCounts: true,
        showLabels: 'full',  // Use full label
        statuses: ['pending', 'confirmed', 'completed', 'cancelled', 'no-show']
    },
    
    // Secondary stats - shown smaller
    secondaryStats: [
        { key: 'completionRate', emphasis: 'medium', showDelta: true },
        { key: 'cancellationRate', emphasis: 'medium', showDelta: true }
    ],
    
    // Visual preferences
    display: {
        showTotal: true,
        showTrends: true,
        compactMode: false,
        showChart: true  // Only month view shows chart
    }
};

/**
 * Get configuration for a view type
 * 
 * @param {string} viewType - 'day' | 'week' | 'month'
 * @returns {Object} View configuration
 */
export function getViewConfig(viewType) {
    const configs = {
        day: DAY_VIEW_CONFIG,
        week: WEEK_VIEW_CONFIG,
        month: MONTH_VIEW_CONFIG
    };
    
    return configs[viewType] || DAY_VIEW_CONFIG;
}

/**
 * View title generators
 * Each view has its own way of describing the date range
 */
export const VIEW_TITLE_FORMATS = {
    day: (date) => date.toLocaleString({ weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }),
    week: (date) => {
        const start = date.startOf('week');
        const end = date.endOf('week');
        if (start.month === end.month) {
            return `${start.toLocaleString({ month: 'long' })} ${start.day}–${end.day}, ${start.year}`;
        }
        return `${start.toLocaleString({ month: 'short', day: 'numeric' })} – ${end.toLocaleString({ month: 'short', day: 'numeric', year: 'numeric' })}`;
    },
    month: (date) => date.toLocaleString({ month: 'long', year: 'numeric' })
};

/**
 * Get title for view and date
 * @param {string} viewType - 'day' | 'week' | 'month'
 * @param {DateTime} date - Reference date
 * @returns {string} Formatted title
 */
export function getViewTitle(viewType, date) {
    const formatter = VIEW_TITLE_FORMATS[viewType] || VIEW_TITLE_FORMATS.day;
    return formatter(date);
}
