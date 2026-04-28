/**
 * Shared lifecycle helpers for DOM-ready bootstrapping.
 */

export function onDomReady(callback) {
    if (typeof callback !== 'function') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
        return;
    }

    callback();
}
