/**
 * HTML Utility Functions
 * 
 * Common HTML manipulation and escaping utilities shared across the application.
 * 
 * @module html
 * @version 1.0.0
 */

/**
 * Escape HTML special characters to prevent XSS attacks.
 * 
 * Converts HTML special characters (&, <, >, ", ') to their HTML entity equivalents.
 * Safe for use in HTML content, attributes, and text nodes.
 * 
 * @param {string|null|undefined} value - The string to escape
 * @returns {string} - Escaped HTML-safe string
 * 
 * @example
 * escapeHtml('<script>alert("XSS")</script>');
 * // Returns: '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;'
 * 
 * @example
 * escapeHtml(null); // Returns: ''
 * escapeHtml(undefined); // Returns: ''
 */
export function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
