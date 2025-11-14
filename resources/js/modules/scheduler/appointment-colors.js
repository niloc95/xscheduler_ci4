/**
 * Appointment Color Utilities
 * 
 * Centralized color management for appointments based on status and provider.
 */

/**
 * Status color mapping
 * Background colors for appointment cards based on their status
 */
export const STATUS_COLORS = {
    pending: {
        bg: '#FEF3C7',      // amber-100
        border: '#F59E0B',  // amber-500
        text: '#78350F',    // amber-900
        dot: '#F59E0B'      // amber-500
    },
    confirmed: {
        bg: '#DBEAFE',      // blue-100
        border: '#3B82F6',  // blue-500
        text: '#1E3A8A',    // blue-900
        dot: '#3B82F6'      // blue-500
    },
    completed: {
        bg: '#D1FAE5',      // green-100
        border: '#10B981',  // green-500
        text: '#064E3B',    // green-900
        dot: '#10B981'      // green-500
    },
    cancelled: {
        bg: '#FEE2E2',      // red-100
        border: '#EF4444',  // red-500
        text: '#7F1D1D',    // red-900
        dot: '#EF4444'      // red-500
    },
    'no-show': {
        bg: '#F3F4F6',      // gray-100
        border: '#6B7280',  // gray-500
        text: '#1F2937',    // gray-800
        dot: '#6B7280'      // gray-500
    }
};

/**
 * Dark mode status colors
 */
export const STATUS_COLORS_DARK = {
    pending: {
        bg: '#78350F',      // amber-900 (dark)
        border: '#F59E0B',  // amber-500
        text: '#FEF3C7',    // amber-100 (light text)
        dot: '#F59E0B'      // amber-500
    },
    confirmed: {
        bg: '#1E3A8A',      // blue-900 (dark)
        border: '#3B82F6',  // blue-500
        text: '#DBEAFE',    // blue-100 (light text)
        dot: '#3B82F6'      // blue-500
    },
    completed: {
        bg: '#064E3B',      // green-900 (dark)
        border: '#10B981',  // green-500
        text: '#D1FAE5',    // green-100 (light text)
        dot: '#10B981'      // green-500
    },
    cancelled: {
        bg: '#7F1D1D',      // red-900 (dark)
        border: '#EF4444',  // red-500
        text: '#FEE2E2',    // red-100 (light text)
        dot: '#EF4444'      // red-500
    },
    'no-show': {
        bg: '#374151',      // gray-700 (dark)
        border: '#9CA3AF',  // gray-400
        text: '#F3F4F6',    // gray-100 (light text)
        dot: '#9CA3AF'      // gray-400
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
