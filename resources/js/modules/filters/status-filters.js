/**
 * Status Filters Module
 * 
 * Handles dashboard status filter buttons and appointment statistics refresh.
 * Integrates with scheduler component to filter appointments by status.
 * 
 * Features:
 * - Status filter UI management (active states, loading)
 * - Scheduler integration for filtering
 * - Appointment stats refresh with abort controller
 * - Query string synchronization
 * 
 * @module filters/status-filters
 */

import { getBaseUrl } from '../../utils/url-helpers.js';

let statsRefreshAbortController = null;

/**
 * Get the currently active status filter
 * Checks multiple sources in priority order
 * 
 * @returns {string} Active status filter or empty string
 */
export function getActiveStatusFilter() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return '';
    }

    // Priority 1: Check filter container data attribute
    const container = document.querySelector('[data-status-filter-container]');
    if (container) {
        const containerStatus = container.dataset.activeStatus;
        if (typeof containerStatus === 'string' && containerStatus !== '') {
            return containerStatus;
        }
    }

    // Priority 2: Check calendar data attribute
    const calendar = document.getElementById('appointments-inline-calendar');
    if (calendar) {
        const calendarStatus = calendar.dataset.activeStatus;
        if (typeof calendarStatus === 'string' && calendarStatus !== '') {
            return calendarStatus;
        }
    }

    // Priority 3: Check scheduler instance
    if (window.scheduler && typeof window.scheduler.statusFilter !== 'undefined' && window.scheduler.statusFilter !== null) {
        return window.scheduler.statusFilter;
    }

    return '';
}

/**
 * Update count element with formatted number
 * @param {string} elementId - Element ID to update
 * @param {number|string} value - Count value
 */
function updateCountElement(elementId, value) {
    const el = document.getElementById(elementId);
    if (!el) {
        return;
    }

    const formatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
    const numericValue = typeof value === 'number' ? value : parseInt(value ?? 0, 10) || 0;
    el.textContent = formatter.format(numericValue);
}

/**
 * Refresh appointment statistics from API
 * Respects active status filter if set
 */
export async function refreshAppointmentStats() {
    if (typeof window === 'undefined') {
        return;
    }

    // Cancel any pending refresh
    if (statsRefreshAbortController) {
        statsRefreshAbortController.abort();
    }

    statsRefreshAbortController = new AbortController();

    try {
        const activeStatus = getActiveStatusFilter();
        const url = new URL(`${getBaseUrl()}/api/dashboard/appointment-stats`);
        
        // Add status filter to request if active
        if (activeStatus) {
            url.searchParams.set('status', activeStatus);
        }

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            },
            cache: 'no-store',
            signal: statsRefreshAbortController.signal
        });

        if (!response.ok) {
            throw new Error(`Failed to refresh stats: HTTP ${response.status}`);
        }

        const payload = await response.json();
        const stats = payload.data || payload;

        // Update count elements
        updateCountElement('upcomingCount', stats.upcoming);
        updateCountElement('completedCount', stats.completed);
        updateCountElement('pendingCount', stats.pending);
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        console.error('[status-filters] Failed to refresh appointment stats', error);
    } finally {
        statsRefreshAbortController = null;
    }
}

/**
 * Emit appointments updated event
 * Refreshes stats and dispatches custom event
 * 
 * @param {object} detail - Event detail data
 */
export function emitAppointmentsUpdated(detail = {}) {
    if (typeof window === 'undefined') {
        return;
    }

    refreshAppointmentStats();
    window.dispatchEvent(new CustomEvent('appointments-updated', { detail }));
}

/**
 * Apply status filter to scheduler instance
 * @param {string} status - Status to filter by (or null for all)
 * @returns {Promise} Resolves when filter is applied
 */
function applySchedulerFilter(status) {
    const calendar = document.getElementById('appointments-inline-calendar');
    
    if (!calendar) {
        return Promise.resolve();
    }

    const normalizedStatus = status || null;
    const schedulerInstance = window.scheduler;

    // If scheduler is already initialized, apply filter immediately
    if (schedulerInstance && typeof schedulerInstance.setStatusFilter === 'function') {
        return schedulerInstance.setStatusFilter(normalizedStatus);
    }

    // Wait for scheduler to be ready
    return new Promise(resolve => {
        const readyHandler = (event) => {
            const instance = event?.detail?.scheduler || window.scheduler;
            if (instance && typeof instance.setStatusFilter === 'function') {
                Promise.resolve(instance.setStatusFilter(normalizedStatus)).finally(resolve);
            } else {
                resolve();
            }
        };

        window.addEventListener('scheduler:ready', readyHandler, { once: true });
    });
}

/**
 * Initialize status filter controls
 * Sets up click handlers and state management for filter buttons
 */
export function initStatusFilterControls() {
    const container = document.querySelector('[data-status-filter-container]');
    if (!container) {
        return;
    }

    const buttons = Array.from(container.querySelectorAll('.status-filter-btn'));
    if (!buttons.length) {
        return;
    }

    const calendar = document.getElementById('appointments-inline-calendar');

    /**
     * Update active state of filter buttons
     * @param {string} nextStatus - Status to mark as active
     */
    const setActiveState = (nextStatus) => {
        buttons.forEach(button => {
            if (button.dataset.status === nextStatus && nextStatus !== '') {
                button.classList.add('is-active');
                button.setAttribute('aria-pressed', 'true');
            } else {
                button.classList.remove('is-active');
                button.setAttribute('aria-pressed', 'false');
            }
        });
        
        container.dataset.activeStatus = nextStatus;
        
        if (calendar) {
            calendar.dataset.activeStatus = nextStatus;
        }
    };

    /**
     * Update loading state of filter buttons
     * @param {boolean} isLoading - Loading state
     */
    const setLoadingState = (isLoading) => {
        buttons.forEach(button => {
            if (isLoading) {
                button.classList.add('is-loading');
            } else {
                button.classList.remove('is-loading');
            }
        });
    };

    /**
     * Update URL query string with current filter
     * @param {string} nextStatus - Status for query string
     */
    const updateQueryString = (nextStatus) => {
        const url = new URL(window.location.href);
        if (nextStatus) {
            url.searchParams.set('status', nextStatus);
        } else {
            url.searchParams.delete('status');
        }
        window.history.replaceState({}, '', `${url.pathname}${url.search}`);
    };

    // Set initial state from container
    const initialStatus = container.dataset.activeStatus || '';
    setActiveState(initialStatus);

    // Bind click handlers to buttons
    buttons.forEach(button => {
        // Prevent double binding
        if (button.dataset.statusFilterBound === 'true') {
            return;
        }
        button.dataset.statusFilterBound = 'true';

        button.addEventListener('click', () => {
            const clickedStatus = button.dataset.status || '';
            const currentStatus = container.dataset.activeStatus || '';
            const toggledOff = clickedStatus === currentStatus;
            const nextStatus = toggledOff ? '' : clickedStatus;

            // Update UI state
            setActiveState(nextStatus);
            updateQueryString(nextStatus);
            setLoadingState(true);

            // Apply filter to scheduler
            applySchedulerFilter(nextStatus)
                .catch(error => {
                    console.error('[status-filters] Failed to apply scheduler status filter', error);
                })
                .finally(() => {
                    setLoadingState(false);
                });

            // Emit update event
            emitAppointmentsUpdated({ source: 'status-filter', status: nextStatus || null });
        });
    });
}
