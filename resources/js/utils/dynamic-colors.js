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

let observerInitialized = false;

/**
 * Apply dynamic color attributes to a single element
 *
 * Supported attributes:
 * - data-color / data-bg-color
 * - data-text-color
 * - data-border-color
 * - data-border-left-color
 * - data-provider-color (maps to --provider-color)
 * - data-pill-color (maps to --pill-color)
 */
function applyDynamicColorsToElement(element) {
    if (!element || element.nodeType !== Node.ELEMENT_NODE) {
        return;
    }

    const backgroundColor = element.getAttribute('data-bg-color') || element.getAttribute('data-color');
    const textColor = element.getAttribute('data-text-color');
    const borderColor = element.getAttribute('data-border-color');
    const borderLeftColor = element.getAttribute('data-border-left-color');
    const providerColor = element.getAttribute('data-provider-color');
    const pillColor = element.getAttribute('data-pill-color');

    if (backgroundColor) {
        element.style.backgroundColor = backgroundColor;
    }
    if (textColor) {
        element.style.color = textColor;
    }
    if (borderColor) {
        element.style.borderColor = borderColor;
    }
    if (borderLeftColor) {
        element.style.borderLeftColor = borderLeftColor;
    }
    if (providerColor) {
        element.style.setProperty('--provider-color', providerColor);
    }
    if (pillColor) {
        element.style.setProperty('--pill-color', pillColor);
    }
}

/**
 * Apply dynamic colors to all supported elements
 */
function applyDynamicColors() {
    const selector = [
        '[data-color]',
        '[data-bg-color]',
        '[data-text-color]',
        '[data-border-color]',
        '[data-border-left-color]',
        '[data-provider-color]',
        '[data-pill-color]',
    ].join(',');

    document.querySelectorAll(selector).forEach(applyDynamicColorsToElement);
}

function observeDynamicColorNodes() {
    if (observerInitialized || typeof MutationObserver === 'undefined') {
        return;
    }

    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType !== Node.ELEMENT_NODE) {
                    return;
                }

                applyDynamicColorsToElement(node);
                if (typeof node.querySelectorAll === 'function') {
                    node.querySelectorAll([
                        '[data-color]',
                        '[data-bg-color]',
                        '[data-text-color]',
                        '[data-border-color]',
                        '[data-border-left-color]',
                        '[data-provider-color]',
                        '[data-pill-color]',
                    ].join(',')).forEach(applyDynamicColorsToElement);
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
    observerInitialized = true;
}

// Apply colors on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        applyDynamicColors();
        observeDynamicColorNodes();
    });
} else {
    applyDynamicColors();
    observeDynamicColorNodes();
}

// Re-apply colors after SPA navigation
document.addEventListener('spa:navigated', applyDynamicColors);

// Re-apply colors after dynamic content is added
document.addEventListener('xs:content-updated', applyDynamicColors);

// Export for manual triggering
export { applyDynamicColors };
