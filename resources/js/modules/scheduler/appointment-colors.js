/**
 * Appointment Color Utilities
 * 
 * Centralized color management for appointments based on status and provider.
 * 
 * Phase 1 Prototype Integration:
 * - Added prototype-style tokens: chipBg, chipText, chipBorder for pill styling
 * - Added badge colors for duration/room tags
 * - Maintains backwards compatibility with existing bg/border/text/dot
 */

/**
 * Status color mapping
 * Background colors for appointment cards based on their status
 * Enhanced with prototype-style chip tokens
 * 
 * LIGHT MODE: Uses solid opaque backgrounds with dark text for readability
 */
export const STATUS_COLORS = {
    pending: {
        bg: '#FEF3C7',          // amber-100
        border: '#F59E0B',      // amber-500
        text: '#78350F',        // amber-900 - DARK text for light bg
        dot: '#F59E0B',         // amber-500
        // Prototype-style tokens - solid backgrounds for readability
        chipBg: '#FEF3C7',                       // amber-100 solid
        chipBorder: '#FCD34D',                   // amber-300 visible border
        badgeBg: 'rgba(255, 255, 255, 0.8)'      // white badge background
    },
    confirmed: {
        bg: '#DBEAFE',          // blue-100
        border: '#3B82F6',      // blue-500
        text: '#1E3A8A',        // blue-900 - DARK text for light bg
        dot: '#3B82F6',         // blue-500
        // Prototype-style tokens - solid backgrounds for readability
        chipBg: '#DBEAFE',                       // blue-100 solid
        chipBorder: '#93C5FD',                   // blue-300 visible border
        badgeBg: 'rgba(255, 255, 255, 0.8)'      // white badge background
    },
    completed: {
        bg: '#D1FAE5',          // emerald-100
        border: '#10B981',      // emerald-500
        text: '#064E3B',        // emerald-900 - DARK text for light bg
        dot: '#10B981',         // emerald-500
        // Prototype-style tokens - solid backgrounds for readability
        chipBg: '#D1FAE5',                       // emerald-100 solid
        chipBorder: '#6EE7B7',                   // emerald-300 visible border
        badgeBg: 'rgba(255, 255, 255, 0.8)'      // white badge background
    },
    cancelled: {
        bg: '#FEE2E2',          // red-100
        border: '#EF4444',      // red-500
        text: '#7F1D1D',        // red-900 - DARK text for light bg
        dot: '#EF4444',         // red-500
        // Prototype-style tokens - solid backgrounds for readability
        chipBg: '#FEE2E2',                       // red-100 solid
        chipBorder: '#FCA5A5',                   // red-300 visible border
        badgeBg: 'rgba(255, 255, 255, 0.8)'      // white badge background
    },
    'no-show': {
        bg: '#F3F4F6',          // gray-100
        border: '#6B7280',      // gray-500
        text: '#1F2937',        // gray-800 - DARK text for light bg
        dot: '#6B7280',         // gray-500
        // Prototype-style tokens - solid backgrounds for readability
        chipBg: '#F3F4F6',                       // gray-100 solid
        chipBorder: '#D1D5DB',                   // gray-300 visible border
        badgeBg: 'rgba(255, 255, 255, 0.8)'      // white badge background
    }
};

/**
 * Dark mode status colors
 * Enhanced with prototype-style chip tokens for dark mode
 * 
 * DARK MODE: Uses semi-transparent colored backgrounds with LIGHT text
 */
export const STATUS_COLORS_DARK = {
    pending: {
        bg: '#78350F',          // amber-900 (dark)
        border: '#F59E0B',      // amber-500
        text: '#FEF3C7',        // amber-100 - LIGHT text for dark bg
        dot: '#F59E0B',         // amber-500
        // Prototype-style tokens for dark mode - more opaque for visibility
        chipBg: 'rgba(120, 53, 15, 0.8)',        // amber-900/80
        chipBorder: 'rgba(245, 158, 11, 0.5)',   // amber-500/50
        badgeBg: 'rgba(255, 255, 255, 0.15)'     // white/15
    },
    confirmed: {
        bg: '#1E3A8A',          // blue-900 (dark)
        border: '#3B82F6',      // blue-500
        text: '#DBEAFE',        // blue-100 - LIGHT text for dark bg
        dot: '#3B82F6',         // blue-500
        // Prototype-style tokens for dark mode - more opaque for visibility
        chipBg: 'rgba(30, 58, 138, 0.8)',        // blue-900/80
        chipBorder: 'rgba(59, 130, 246, 0.5)',   // blue-500/50
        badgeBg: 'rgba(255, 255, 255, 0.15)'     // white/15
    },
    completed: {
        bg: '#064E3B',          // emerald-900 (dark)
        border: '#10B981',      // emerald-500
        text: '#D1FAE5',        // emerald-100 - LIGHT text for dark bg
        dot: '#10B981',         // emerald-500
        // Prototype-style tokens for dark mode - more opaque for visibility
        chipBg: 'rgba(6, 78, 59, 0.8)',          // emerald-900/80
        chipBorder: 'rgba(16, 185, 129, 0.5)',   // emerald-500/50
        badgeBg: 'rgba(255, 255, 255, 0.15)'     // white/15
    },
    cancelled: {
        bg: '#7F1D1D',          // red-900 (dark)
        border: '#EF4444',      // red-500
        text: '#FEE2E2',        // red-100 - LIGHT text for dark bg
        dot: '#EF4444',         // red-500
        // Prototype-style tokens for dark mode - more opaque for visibility
        chipBg: 'rgba(127, 29, 29, 0.8)',        // red-900/80
        chipBorder: 'rgba(239, 68, 68, 0.5)',    // red-500/50
        badgeBg: 'rgba(255, 255, 255, 0.15)'     // white/15
    },
    'no-show': {
        bg: '#374151',          // gray-700 (dark)
        border: '#9CA3AF',      // gray-400
        text: '#F3F4F6',        // gray-100 - LIGHT text for dark bg
        dot: '#9CA3AF',         // gray-400
        // Prototype-style tokens for dark mode - more opaque for visibility
        chipBg: 'rgba(55, 65, 81, 0.8)',         // gray-700/80
        chipBorder: 'rgba(156, 163, 175, 0.5)',  // gray-400/50
        badgeBg: 'rgba(255, 255, 255, 0.15)'     // white/15
    }
};

/**
 * Get status colors for an appointment
 * @param {string} status - Appointment status
 * @param {boolean} darkMode - Whether dark mode is active
 * @returns {object} Color scheme for the status
 */
export function getStatusColors(status, darkMode = false) {
    const normalizedStatus = status?.toLowerCase() || 'pending';
    const colors = darkMode ? STATUS_COLORS_DARK : STATUS_COLORS;
    return colors[normalizedStatus] || colors.pending;
}

/**
 * Get provider color with fallback
 * @param {object} provider - Provider object
 * @returns {string} Hex color code
 */
export function getProviderColor(provider) {
    return provider?.color || '#3B82F6'; // Default to blue-500
}

/**
 * Calculate contrasting text color for a background
 * @param {string} hexColor - Hex color code
 * @returns {string} 'white' or 'black'
 */
export function getContrastColor(hexColor) {
    // Remove # if present
    const hex = hexColor.replace('#', '');
    
    // Convert to RGB
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    
    // Calculate luminance
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    
    return luminance > 0.5 ? '#000000' : '#FFFFFF';
}

/**
 * Detect if dark mode is currently active
 * @returns {boolean}
 */
export function isDarkMode() {
    return document.documentElement.classList.contains('dark') ||
           window.matchMedia('(prefers-color-scheme: dark)').matches;
}

/**
 * Get CSS styles for appointment card
 * @param {object} appointment - Appointment object
 * @param {object} provider - Provider object
 * @returns {string} CSS style string
 */
export function getAppointmentStyles(appointment, provider) {
    const darkMode = isDarkMode();
    const statusColors = getStatusColors(appointment.status, darkMode);
    
    return `
        background-color: ${statusColors.bg};
        border-color: ${statusColors.border};
        color: ${statusColors.text};
    `.trim();
}

/**
 * Get provider dot HTML
 * @param {object} provider - Provider object
 * @param {string} size - Size class (e.g., 'w-3 h-3')
 * @returns {string} HTML for provider color dot
 */
export function getProviderDotHtml(provider, size = 'w-3 h-3') {
    const color = getProviderColor(provider);
    return `<span class="inline-block ${size} rounded-full" style="background-color: ${color};" title="${provider?.name || 'Provider'}"></span>`;
}

/**
 * Status badge labels
 */
export const STATUS_LABELS = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled',
    'no-show': 'No Show'
};

/**
 * Get status label
 * @param {string} status
 * @returns {string}
 */
export function getStatusLabel(status) {
    return STATUS_LABELS[status?.toLowerCase()] || 'Unknown';
}

/**
 * Format appointment duration for display
 * @param {number} minutes - Duration in minutes
 * @returns {string} Formatted duration (e.g., "30m", "1h", "1h 30m")
 */
export function formatDuration(minutes) {
    if (!minutes || minutes <= 0) return '';
    
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    
    if (hours === 0) return `${mins}m`;
    if (mins === 0) return `${hours}h`;
    return `${hours}h ${mins}m`;
}

/**
 * Provider color theme map (matches prototype design)
 * Maps provider colors to consistent light/dark mode styling
 */
export const PROVIDER_THEMES = {
    // Blue providers
    '#3B82F6': { // blue-500
        dot: 'bg-blue-500',
        chipBg: 'bg-blue-50 dark:bg-blue-500/20',
        chipText: 'text-blue-900 dark:text-blue-100',
        chipBorder: 'border-blue-100 dark:border-blue-500/30',
    },
    // Emerald/Green providers
    '#10B981': { // emerald-500
        dot: 'bg-emerald-500',
        chipBg: 'bg-emerald-50 dark:bg-emerald-500/20',
        chipText: 'text-emerald-900 dark:text-emerald-100',
        chipBorder: 'border-emerald-100 dark:border-emerald-500/30',
    },
    // Amber/Orange providers  
    '#F59E0B': { // amber-500
        dot: 'bg-amber-500',
        chipBg: 'bg-amber-50 dark:bg-amber-500/20',
        chipText: 'text-amber-900 dark:text-amber-100',
        chipBorder: 'border-amber-100 dark:border-amber-500/30',
    },
    // Purple providers
    '#8B5CF6': { // violet-500
        dot: 'bg-violet-500',
        chipBg: 'bg-violet-50 dark:bg-violet-500/20',
        chipText: 'text-violet-900 dark:text-violet-100',
        chipBorder: 'border-violet-100 dark:border-violet-500/30',
    },
    // Rose/Pink providers
    '#F43F5E': { // rose-500
        dot: 'bg-rose-500',
        chipBg: 'bg-rose-50 dark:bg-rose-500/20',
        chipText: 'text-rose-900 dark:text-rose-100',
        chipBorder: 'border-rose-100 dark:border-rose-500/30',
    },
};

/**
 * Get Tailwind classes for provider-themed chip (prototype style)
 * @param {object} provider - Provider object
 * @returns {object} Object with Tailwind class strings
 */
export function getProviderTheme(provider) {
    const color = provider?.color || '#3B82F6';
    return PROVIDER_THEMES[color] || PROVIDER_THEMES['#3B82F6']; // Default to blue
}
