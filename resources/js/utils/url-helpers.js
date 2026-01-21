/**
 * URL Helper Utilities
 * Centralized functions for URL handling across the application
 */

/**
 * Get the base URL of the application from window.__BASE_URL__ or the <base> tag
 * Falls back to window.location.origin if neither is available
 * @returns {string} The base URL without trailing slash
 */
export function getBaseUrl() {
    // First check for window.__BASE_URL__ (set in layouts)
    if (typeof window !== 'undefined' && window.__BASE_URL__) {
        return String(window.__BASE_URL__).replace(/\/+$/, '');
    }
    
    // Fall back to window.appBaseUrl (legacy)
    if (typeof window !== 'undefined' && window.appBaseUrl) {
        return String(window.appBaseUrl).replace(/\/+$/, '');
    }
    
    // Fall back to <base> tag
    const base = document.querySelector('base');
    if (base && base.href) {
        return base.href.replace(/\/+$/, '');
    }
    
    // Last resort: use current origin (ensures valid URL construction)
    return window.location.origin;
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
