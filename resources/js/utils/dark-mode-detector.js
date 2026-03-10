/**
 * Dark Mode Detection Utility
 * 
 * Provides a single source of truth for detecting dark mode state
 * across the application.
 * 
 * @module utils/dark-mode-detector
 */

/**
 * Detect if dark mode is currently enabled
 * @returns {boolean} True if dark mode is active
 */
export function isDarkMode() {
    return document.documentElement.classList.contains('dark');
}

/**
 * Listen for dark mode changes
 * @param {Function} callback - Callback fired when dark mode toggles
 * @param {Object} options - Listener options
 * @returns {Function} Unsubscribe function
 */
export function onDarkModeChange(callback, { once = false } = {}) {
    const observer = new MutationObserver(() => {
        callback(isDarkMode());
    });
    
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    });
    
    // Return unsubscribe function
    return () => observer.disconnect();
}
