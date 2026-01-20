/**
 * URL Helper Utilities
 * Centralized functions for URL handling across the application
 */

/**
 * Get the base URL of the application from the <base> tag or window.appBaseUrl
 * Falls back to empty string if neither is available
 * @returns {string} The base URL without trailing slash
 */
export function getBaseUrl() {
    // First check for window.appBaseUrl (set in layouts)
    if (typeof window !== 'undefined' && window.appBaseUrl) {
        return window.appBaseUrl.replace(/\/$/, '');
    }
    
    // Fall back to <base> tag
    const base = document.querySelector('base');
    if (base && base.href) {
        return base.href.replace(/\/$/, '');
    }
    
    // Last resort: empty string (relative URLs)
    return '';
}

/**
 * Construct a full URL with the base path
 * @param {string} path - The path to append to base URL (with or without leading slash)
 * @returns {string} The full URL
 */
export function withBaseUrl(path) {
    const base = getBaseUrl();
    // Ensure path starts with /
    const normalizedPath = path.startsWith('/') ? path : '/' + path;
    return base + normalizedPath;
}

/**
 * Build a URL with query parameters
 * @param {string} path - The base path
 * @param {Object} params - Query parameters as key-value pairs
 * @returns {string} The full URL with query string
 */
export function buildUrl(path, params = {}) {
    const url = new URL(withBaseUrl(path), window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            url.searchParams.set(key, value);
        }
    });
    return url.toString();
}

/**
 * Build an API URL
 * @param {string} endpoint - API endpoint (without /api prefix)
 * @returns {string} The full API URL
 */
export function apiUrl(endpoint) {
    const path = endpoint.startsWith('/') ? endpoint : '/' + endpoint;
    return withBaseUrl('/api' + path);
}
