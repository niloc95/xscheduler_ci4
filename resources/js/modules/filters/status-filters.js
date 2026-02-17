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
 * Get current scheduler view and date context
 * @returns {Object} { view: 'day'|'week'|'month', date: 'YYYY-MM-DD' }
 */
function getSchedulerContext() {
    // Try to get from global scheduler instance
    if (window.scheduler) {
        return {
            view: window.scheduler.currentView || 'day',
            date: window.scheduler.currentDate?.toISOString?.()?.split('T')[0] || new Date().toISOString().split('T')[0]
        };
    }
    
    // Fallback to calendar element data
    const calendar = document.getElementById('appointments-inline-calendar');
    if (calendar) {
        return {
            view: calendar.dataset.view || 'day',
            date: calendar.dataset.currentDate || new Date().toISOString().split('T')[0]
        };
    }
    
    return { view: 'day', date: new Date().toISOString().split('T')[0] };
}

/**
 * Refresh appointment statistics from API
 * Respects active status filter and current view context
 * 
 * @param {Object} options - Optional override parameters
 * @param {string} options.view - Override view type ('day'|'week'|'month')
 * @param {string} options.date - Override date (YYYY-MM-DD)
 */
export async function refreshAppointmentStats(options = {}) {
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
        const context = getSchedulerContext();
        const url = new URL(`${getBaseUrl()}/api/dashboard/appointment-stats`);
        
        // Add view and date context for date-range aware stats
        url.searchParams.set('view', options.view || context.view);
        url.searchParams.set('date', options.date || context.date);
        
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
        const meta = payload.meta || {};

        // Update summary counts (combined upcoming = pending + confirmed)
        const upcomingTotal = (stats.pending || 0) + (stats.confirmed || 0);
        updateCountElement('upcomingCount', upcomingTotal);
        updateCountElement('completedCount', stats.completed);
        
        // Update detailed status counts if elements exist
        updateCountElement('pendingCount', stats.pending);
        updateCountElement('confirmedCount', stats.confirmed);
        updateCountElement('cancelledCount', stats.cancelled);
        updateCountElement('noshowCount', stats.noshow);
        updateCountElement('totalCount', stats.total);
        
        // Update provider filter dropdown if active_providers data available
        if (stats.active_providers && Array.isArray(stats.active_providers)) {
            updateProviderFilterOptions(stats.active_providers);
        }
        
        // Dispatch event with full stats for other components
        window.dispatchEvent(new CustomEvent('stats-refreshed', { 
            detail: { stats, meta }
        }));
        
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
 * Update provider filter dropdown to only show providers with appointments
 * @param {Array} activeProviders - List of providers with appointments
 */
function updateProviderFilterOptions(activeProviders) {
    const providerSelect = document.getElementById('filter-provider');
    if (!providerSelect) return;
    
    const currentValue = providerSelect.value;
    const activeIds = new Set(activeProviders.map(p => String(p.id)));
    
    // Disable providers without appointments, keep "All Providers" option enabled
    Array.from(providerSelect.options).forEach(option => {
        if (option.value === '') {
            // "All Providers" option - always enabled
            return;
        }
        
        if (activeIds.has(option.value)) {
            option.disabled = false;
            option.classList.remove('text-gray-400', 'dark:text-gray-600');
        } else {
            option.disabled = true;
            option.classList.add('text-gray-400', 'dark:text-gray-600');
        }
    });
    
    // If current selection is now disabled, reset to "All"
    const currentOption = providerSelect.querySelector(`option[value="${currentValue}"]`);
    if (currentOption?.disabled) {
        providerSelect.value = '';
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

/**
 * Initialize clickable summary cards in the context summary section
 * These cards act as status filters when clicked
 */
export function initSummaryCardFilters() {
    const summaryCards = document.querySelectorAll('.status-summary-card[data-filter-status]');
    if (!summaryCards.length) return;
    
    const activeIndicator = document.getElementById('active-filter-indicator');
    const activeLabel = document.getElementById('active-filter-label');
    const clearBtn = document.getElementById('clear-status-filter');
    
    /**
     * Update UI to reflect active filter
     */
    const updateFilterIndicator = (status, label) => {
        if (!activeIndicator) return;
        
        if (status) {
            activeIndicator.classList.remove('hidden');
            if (activeLabel) activeLabel.textContent = label;
        } else {
            activeIndicator.classList.add('hidden');
            if (activeLabel) activeLabel.textContent = 'All appointments';
        }
    };
    
    /**
     * Apply filter from summary card
     */
    const applyFilter = async (status) => {
        // Update visual state of cards
        summaryCards.forEach(card => {
            if (card.dataset.filterStatus === status) {
                card.classList.add('ring-2', 'ring-blue-500');
            } else {
                card.classList.remove('ring-2', 'ring-blue-500');
            }
        });
        
        // Get label from clicked card
        const clickedCard = document.querySelector(`.status-summary-card[data-filter-status="${status}"]`);
        const label = clickedCard?.querySelector('.text-xs')?.textContent || status;
        
        updateFilterIndicator(status, label);
        
        // Update container data attribute
        const container = document.querySelector('[data-status-filter-container]');
        if (container) {
            container.dataset.activeStatus = status || '';
        }
        
        // Apply to scheduler
        await applySchedulerFilter(status || null);
        
        // Emit update
        emitAppointmentsUpdated({ source: 'summary-card', status: status || null });
    };
    
    // Bind click handlers to summary cards
    summaryCards.forEach(card => {
        if (card.dataset.summaryCardBound === 'true') return;
        card.dataset.summaryCardBound = 'true';
        
        card.addEventListener('click', () => {
            const status = card.dataset.filterStatus;
            const currentStatus = document.querySelector('[data-status-filter-container]')?.dataset.activeStatus;
            
            // Toggle off if same status clicked
            if (status === currentStatus) {
                applyFilter(null);
            } else {
                applyFilter(status);
            }
        });
    });
    
    // Bind clear filter button
    if (clearBtn && clearBtn.dataset.clearBound !== 'true') {
        clearBtn.dataset.clearBound = 'true';
        clearBtn.addEventListener('click', () => applyFilter(null));
    }
}

/**
 * Initialize view toggle buttons to refresh stats on view change
 */
export function initViewToggleHandlers() {
    const viewButtons = document.querySelectorAll('.view-toggle-btn[data-view]');
    if (!viewButtons.length) return;
    
    const summaryTitle = document.getElementById('summary-title');
    const summaryDateRange = document.getElementById('summary-date-range');
    const summaryTotal = document.getElementById('summary-total');
    
    /**
     * Update summary section header based on view
     */
    const updateSummaryHeader = (view, date) => {
        const dateObj = new Date(date + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        
        if (summaryTitle) {
            switch (view) {
                case 'day':
                    summaryTitle.textContent = "Today's Summary";
                    break;
                case 'week':
                    summaryTitle.textContent = "This Week's Summary";
                    break;
                case 'month':
                    summaryTitle.textContent = "This Month's Summary";
                    break;
            }
        }
        
        if (summaryDateRange) {
            switch (view) {
                case 'day':
                    summaryDateRange.textContent = dateObj.toLocaleDateString(undefined, options);
                    break;
                case 'week': {
                    // Calculate week range (Monday to Sunday)
                    const day = dateObj.getDay();
                    const mondayDiff = day === 0 ? -6 : 1 - day;
                    const monday = new Date(dateObj);
                    monday.setDate(dateObj.getDate() + mondayDiff);
                    const sunday = new Date(monday);
                    sunday.setDate(monday.getDate() + 6);
                    summaryDateRange.textContent = `${monday.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} - ${sunday.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}`;
                    break;
                }
                case 'month': {
                    summaryDateRange.textContent = dateObj.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
                    break;
                }
            }
        }
    };
    
    viewButtons.forEach(btn => {
        if (btn.dataset.viewToggleBound === 'true') return;
        btn.dataset.viewToggleBound = 'true';
        
        btn.addEventListener('click', () => {
            const view = btn.dataset.view;
            const currentDate = getSchedulerContext().date;
            
            // Update button styles
            viewButtons.forEach(b => {
                if (b === btn) {
                    b.classList.remove('bg-slate-100', 'dark:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
                    b.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
                } else {
                    b.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
                    b.classList.add('bg-slate-100', 'dark:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
                }
            });
            
            // Update summary header
            updateSummaryHeader(view, currentDate);
            
            // Refresh stats for new view
            refreshAppointmentStats({ view, date: currentDate });
        });
    });
    
    // Listen for scheduler date changes (guard against accumulation)
    if (!window.__xsDateChangeListenerBound) {
        window.__xsDateChangeListenerBound = true;
        window.addEventListener('scheduler:date-change', (event) => {
            const { view, date } = event.detail || {};
            if (view && date) {
                updateSummaryHeader(view, date);
                refreshAppointmentStats({ view, date });
            }
        });
    }
}

/**
 * Initialize all status filter components
 */
export function initAllFilters() {
    initStatusFilterControls();
    initSummaryCardFilters();
    initViewToggleHandlers();
}

