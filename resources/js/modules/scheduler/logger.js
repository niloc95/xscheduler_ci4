/**
 * Scheduler Logger
 *
 * Lightweight logger with a global debug toggle.
 * - Enable via: window.__SCHEDULER_DEBUG__ = true
 * - Or persist via: localStorage.setItem('scheduler:debug', '1')
 */

const isDebugEnabled = () => {
    try {
        if (typeof window !== 'undefined' && typeof window.__SCHEDULER_DEBUG__ !== 'undefined') {
            return !!window.__SCHEDULER_DEBUG__;
        }
        if (typeof localStorage !== 'undefined') {
            const v = localStorage.getItem('scheduler:debug');
            return v === '1' || v === 'true';
        }
    } catch (_) {}
    return false;
};

const prefix = '[Scheduler]';

export const logger = {
    debug: (...args) => { if (isDebugEnabled()) console.debug(prefix, ...args); },
    info: (...args) => { if (isDebugEnabled()) console.info(prefix, ...args); },
    warn: (...args) => console.warn(prefix, ...args),
    error: (...args) => console.error(prefix, ...args),
    enable: (on = true) => { try { if (typeof window !== 'undefined') window.__SCHEDULER_DEBUG__ = !!on; } catch (_) {} },
};

export default logger;
