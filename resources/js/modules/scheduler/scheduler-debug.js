export function createDebugLogger(schedulerAccessor) {
    return (...args) => {
        const scheduler = typeof schedulerAccessor === 'function' ? schedulerAccessor() : null;

        if (scheduler?.debugLog && scheduler.debugLog !== createDebugLogger) {
            scheduler.debugLog(...args);
            return;
        }

        if (typeof window !== 'undefined' && (scheduler?.options?.debug || window.appConfig?.debug)) {
            console.log(...args);
        }
    };
}
