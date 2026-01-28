/**
 * Dynamic Color Utility
 * 
 * Applies background colors from data-color attributes to elements.
 * This replaces inline styles while maintaining runtime color flexibility.
 * 
 * Usage in PHP views:
 *   <div class="provider-color-dot" data-color="<?= $color ?>"></div>
 * 
 * This script automatically applies the color on page load and after SPA navigation.
 */

/**
 * Apply data-color attributes to element backgrounds
 */
function applyDynamicColors() {
    // Select all elements with data-color attribute
    const elements = document.querySelectorAll('[data-color]');
    
    elements.forEach(element => {
        const color = element.getAttribute('data-color');
        if (color) {
            element.style.backgroundColor = color;
        }
    });
}

// Apply colors on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyDynamicColors);
} else {
    applyDynamicColors();
}

// Re-apply colors after SPA navigation
document.addEventListener('spa:navigated', applyDynamicColors);

// Re-apply colors after dynamic content is added
document.addEventListener('xs:content-updated', applyDynamicColors);

// Export for manual triggering
export { applyDynamicColors };
