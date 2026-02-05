/**
 * Stats Definitions - The Shared Sensors
 * 
 * Single source of truth for:
 * - Status definitions (labels, colors, icons)
 * - Stat type definitions
 * - Display tokens
 * 
 * Car Analogy: These are the SENSORS and GAUGES - 
 * same measurement standards across all car models.
 * 
 * @module scheduler/stats-definitions
 */

/**
 * Status Definitions - THE source of truth for all status-related display
 * 
 * Every component that needs status info reads from here.
 * NEVER duplicate these values in view code.
 */
export const STATUS_DEFINITIONS = {
    pending: {
        key: 'pending',
        label: 'Pending',
        shortLabel: 'Pend',
        icon: 'schedule',
        priority: 1,
        isActive: true,
        colors: {
            light: {
                bg: 'bg-amber-100',
                border: 'border-amber-500',
                text: 'text-amber-900',
                dot: 'bg-amber-500',
                badge: 'bg-amber-500 text-white'
            },
            dark: {
                bg: 'dark:bg-amber-900/30',
                border: 'dark:border-amber-400',
                text: 'dark:text-amber-100',
                dot: 'dark:bg-amber-400',
                badge: 'dark:bg-amber-600 dark:text-white'
            }
        },
        hex: {
            light: { bg: '#FEF3C7', border: '#F59E0B', text: '#78350F', dot: '#F59E0B' },
            dark: { bg: '#78350F', border: '#F59E0B', text: '#FEF3C7', dot: '#F59E0B' }
        }
    },
    
    confirmed: {
        key: 'confirmed',
        label: 'Confirmed',
        shortLabel: 'Conf',
        icon: 'check_circle',
        priority: 2,
        isActive: true,
        colors: {
            light: {
                bg: 'bg-blue-100',
                border: 'border-blue-500',
                text: 'text-blue-900',
                dot: 'bg-blue-500',
                badge: 'bg-blue-500 text-white'
            },
            dark: {
                bg: 'dark:bg-blue-900/30',
                border: 'dark:border-blue-400',
                text: 'dark:text-blue-100',
                dot: 'dark:bg-blue-400',
                badge: 'dark:bg-blue-600 dark:text-white'
            }
        },
        hex: {
            light: { bg: '#DBEAFE', border: '#3B82F6', text: '#1E3A8A', dot: '#3B82F6' },
            dark: { bg: '#1E3A8A', border: '#3B82F6', text: '#DBEAFE', dot: '#3B82F6' }
        }
    },
    
    completed: {
        key: 'completed',
        label: 'Completed',
        shortLabel: 'Done',
        icon: 'task_alt',
        priority: 3,
        isActive: false,
        colors: {
            light: {
                bg: 'bg-emerald-100',
                border: 'border-emerald-500',
                text: 'text-emerald-900',
                dot: 'bg-emerald-500',
                badge: 'bg-emerald-500 text-white'
            },
            dark: {
                bg: 'dark:bg-emerald-900/30',
                border: 'dark:border-emerald-400',
                text: 'dark:text-emerald-100',
                dot: 'dark:bg-emerald-400',
                badge: 'dark:bg-emerald-600 dark:text-white'
            }
        },
        hex: {
            light: { bg: '#D1FAE5', border: '#10B981', text: '#064E3B', dot: '#10B981' },
            dark: { bg: '#064E3B', border: '#10B981', text: '#D1FAE5', dot: '#10B981' }
        }
    },
    
    cancelled: {
        key: 'cancelled',
        label: 'Cancelled',
        shortLabel: 'Canc',
        icon: 'cancel',
        priority: 4,
        isActive: false,
        colors: {
            light: {
                bg: 'bg-red-100',
                border: 'border-red-500',
                text: 'text-red-900',
                dot: 'bg-red-500',
                badge: 'bg-red-500 text-white'
            },
            dark: {
                bg: 'dark:bg-red-900/30',
                border: 'dark:border-red-400',
                text: 'dark:text-red-100',
                dot: 'dark:bg-red-400',
                badge: 'dark:bg-red-600 dark:text-white'
            }
        },
        hex: {
            light: { bg: '#FEE2E2', border: '#EF4444', text: '#7F1D1D', dot: '#EF4444' },
            dark: { bg: '#7F1D1D', border: '#EF4444', text: '#FEE2E2', dot: '#EF4444' }
        }
    },
    
    'no-show': {
        key: 'no-show',
        label: 'No-Show',
        shortLabel: 'N/S',
        icon: 'person_off',
        priority: 5,
        isActive: false,
        colors: {
            light: {
                bg: 'bg-gray-100',
                border: 'border-gray-500',
                text: 'text-gray-900',
                dot: 'bg-gray-500',
                badge: 'bg-gray-500 text-white'
            },
            dark: {
                bg: 'dark:bg-gray-700/50',
                border: 'dark:border-gray-400',
                text: 'dark:text-gray-100',
                dot: 'dark:bg-gray-400',
                badge: 'dark:bg-gray-600 dark:text-white'
            }
        },
        hex: {
            light: { bg: '#F3F4F6', border: '#6B7280', text: '#1F2937', dot: '#6B7280' },
            dark: { bg: '#374151', border: '#6B7280', text: '#F3F4F6', dot: '#6B7280' }
        }
    }
};

/**
 * Stat Types - Definition of available statistics
 * 
 * Views use these to specify which stats they want displayed.
 */
export const STAT_TYPES = {
    // Count-based stats
    total: {
        key: 'total',
        label: 'Total',
        shortLabel: 'Total',
        icon: 'calendar_month',
        format: 'number'
    },
    upcoming: {
        key: 'upcoming',
        label: 'Upcoming',
        shortLabel: 'Up',
        icon: 'upcoming',
        format: 'number'
    },
    inProgress: {
        key: 'inProgress',
        label: 'In Progress',
        shortLabel: 'Now',
        icon: 'play_circle',
        format: 'number'
    },
    activeCount: {
        key: 'activeCount',
        label: 'Active',
        shortLabel: 'Active',
        icon: 'event_available',
        format: 'number'
    },
    
    // Rate-based stats
    completionRate: {
        key: 'completionRate',
        label: 'Completion Rate',
        shortLabel: 'Comp%',
        icon: 'percent',
        format: 'percent'
    },
    cancellationRate: {
        key: 'cancellationRate',
        label: 'Cancellation Rate',
        shortLabel: 'Canc%',
        icon: 'trending_down',
        format: 'percent'
    },
    confirmationRate: {
        key: 'confirmationRate',
        label: 'Confirmation Rate',
        shortLabel: 'Conf%',
        icon: 'trending_up',
        format: 'percent'
    }
};

/**
 * Get status definition by key
 * @param {string} statusKey - Status identifier
 * @returns {Object} Status definition
 */
export function getStatusDef(statusKey) {
    return STATUS_DEFINITIONS[statusKey] || STATUS_DEFINITIONS.pending;
}

/**
 * Get all active statuses (pending, confirmed)
 * @returns {Array} Active status definitions
 */
export function getActiveStatuses() {
    return Object.values(STATUS_DEFINITIONS).filter(s => s.isActive);
}

/**
 * Get all statuses sorted by priority
 * @returns {Array} Sorted status definitions
 */
export function getStatusesByPriority() {
    return Object.values(STATUS_DEFINITIONS).sort((a, b) => a.priority - b.priority);
}

/**
 * Get hex color for status (respects dark mode)
 * @param {string} statusKey - Status identifier
 * @param {string} colorType - 'bg' | 'border' | 'text' | 'dot'
 * @param {boolean} isDark - Dark mode flag
 * @returns {string} Hex color value
 */
export function getStatusHex(statusKey, colorType = 'dot', isDark = false) {
    const def = getStatusDef(statusKey);
    const mode = isDark ? 'dark' : 'light';
    return def.hex[mode][colorType] || def.hex.light[colorType];
}

/**
 * Get Tailwind classes for status
 * @param {string} statusKey - Status identifier
 * @param {string} element - 'bg' | 'border' | 'text' | 'dot' | 'badge'
 * @returns {string} Combined light + dark Tailwind classes
 */
export function getStatusClasses(statusKey, element = 'bg') {
    const def = getStatusDef(statusKey);
    return `${def.colors.light[element]} ${def.colors.dark[element]}`;
}
