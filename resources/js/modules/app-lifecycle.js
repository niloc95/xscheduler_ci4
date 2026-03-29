export function bindAppLifecycleEvents({
    documentRef = document,
    initializeComponents,
    refreshAppointmentStats = () => {},
    resetSchedulerInitAttempts = () => {},
    hasDashboardStats = () => Boolean(
        documentRef.getElementById('upcomingCount') || documentRef.getElementById('completedCount')
    ),
} = {}) {
    if (typeof initializeComponents !== 'function') {
        throw new TypeError('initializeComponents is required');
    }

    documentRef.addEventListener('DOMContentLoaded', () => {
        initializeComponents();
        refreshAppointmentStats();
    });

    documentRef.addEventListener('spa:navigated', () => {
        resetSchedulerInitAttempts();
        initializeComponents();

        if (hasDashboardStats()) {
            refreshAppointmentStats();
        }
    });
}