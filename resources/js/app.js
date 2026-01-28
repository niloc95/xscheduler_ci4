// (CoreUI components are no longer used for the sidebar. Keep charts init only.)

// Import appointment booking form functionality
import { initAppointmentForm } from './modules/appointments/appointments-form.js';

// Import charts functionality
import Charts from './charts.js';

import { attachTimezoneHeaders } from './utils/timezone-helper.js';
import { getBaseUrl } from './utils/url-helpers.js';

// Import dynamic color utility
import { applyDynamicColors } from './utils/dynamic-colors.js';

// Import custom scheduler components
import { SchedulerCore } from './modules/scheduler/scheduler-core.js';
import { MonthView } from './modules/scheduler/scheduler-month-view.js';
import { WeekView } from './modules/scheduler/scheduler-week-view.js';
import { DayView } from './modules/scheduler/scheduler-day-view.js';
import { DragDropManager } from './modules/scheduler/scheduler-drag-drop.js';
import { SettingsManager } from './modules/scheduler/settings-manager.js';

// Import scheduler styles
import '../css/scheduler.css';
// Export shared time-slot UI initializer to window so PHP views can call it
import { initTimeSlotsUI } from './modules/appointments/time-slots-ui.js';
if (typeof window !== 'undefined') {
    window.initTimeSlotsUI = initTimeSlotsUI;
}

/**
 * Navigate to create appointment page with pre-filled slot data
 * @param {Object} slotInfo - Selected slot information from calendar
 */
function navigateToCreateAppointment(slotInfo) {
    const { start, end, resource } = slotInfo;
    
    // Format date and time from the selected slot
    const startDate = new Date(start);
    const appointmentDate = startDate.toISOString().split('T')[0]; // YYYY-MM-DD
    const appointmentTime = startDate.toTimeString().slice(0, 5); // HH:MM
    
    // Build URL with query parameters
    const params = new URLSearchParams({
        date: appointmentDate,
        time: appointmentTime
    });
    
    // Add provider ID if available (from resource or event)
    if (resource) {
        params.append('provider_id', resource.id);
    }
    
    // Navigate to create page
    const url = `/appointments/create?${params.toString()}`;
    window.location.href = url;
}

/**
 * Pre-fill appointment form with URL parameters
 * Supports: date, time, provider_id, available_providers (from scheduler slot booking)
 */
function prefillAppointmentForm() {
    // Only run on create appointment page
    const form = document.querySelector('form[action*="/appointments/store"]');
    if (!form) return;
    
    // Parse URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const date = urlParams.get('date');
    const time = urlParams.get('time');
    const providerId = urlParams.get('provider_id');
    const availableProviders = urlParams.get('available_providers'); // Comma-separated IDs from scheduler
    
    // Pre-fill date field
    if (date) {
        const dateInput = document.getElementById('appointment_date');
        if (dateInput) {
            dateInput.value = date;
            
            // Trigger change event to update form state
            dateInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    
    // Pre-fill time field
    if (time) {
        const timeInput = document.getElementById('appointment_time');
        if (timeInput) {
            timeInput.value = time;
            
            // Trigger change event to update form state
            timeInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    
    // Pre-select provider if specified
    if (providerId) {
        const providerSelect = document.getElementById('provider_id');
        if (providerSelect) {
            providerSelect.value = providerId;
            
            // Trigger change event to load services
            providerSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    
    // If available_providers is specified (from scheduler slot), filter provider dropdown
    // and auto-select the first available provider if no specific provider was chosen
    if (availableProviders && !providerId) {
        const availableIds = availableProviders.split(',').map(id => parseInt(id.trim(), 10));
        const providerSelect = document.getElementById('provider_id');
        
        if (providerSelect && availableIds.length > 0) {
            // Mark unavailable providers in the dropdown (optional visual indicator)
            Array.from(providerSelect.options).forEach(option => {
                if (option.value && !availableIds.includes(parseInt(option.value, 10))) {
                    // Add visual indicator that this provider is busy at this time
                    if (!option.text.includes('(busy)')) {
                        option.text += ' (busy at this time)';
                        option.classList.add('text-gray-400');
                    }
                }
            });
            
            // Auto-select the first available provider
            const firstAvailable = availableIds[0];
            if (firstAvailable) {
                providerSelect.value = firstAvailable.toString();
                
                // Trigger change event to load services
                providerSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }
}

/**
 * Wire up dashboard status filter buttons
 */
function initStatusFilterControls() {
    const container = document.querySelector('[data-status-filter-container]');
    if (!container) {
        return;
    }

    const buttons = Array.from(container.querySelectorAll('.status-filter-btn'));
    if (!buttons.length) {
        return;
    }

    const calendar = document.getElementById('appointments-inline-calendar');

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

    const setLoadingState = (isLoading) => {
        buttons.forEach(button => {
            if (isLoading) {
                button.classList.add('is-loading');
            } else {
                button.classList.remove('is-loading');
            }
        });
    };

    const updateQueryString = (nextStatus) => {
        const url = new URL(window.location.href);
        if (nextStatus) {
            url.searchParams.set('status', nextStatus);
        } else {
            url.searchParams.delete('status');
        }
        window.history.replaceState({}, '', `${url.pathname}${url.search}`);
    };

    const applySchedulerFilter = (nextStatus) => {
        if (!calendar) {
            return Promise.resolve();
        }

        const normalizedStatus = nextStatus || null;
        const schedulerInstance = window.scheduler;

        if (schedulerInstance && typeof schedulerInstance.setStatusFilter === 'function') {
            return schedulerInstance.setStatusFilter(normalizedStatus);
        }

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
    };

    const initialStatus = container.dataset.activeStatus || '';
    setActiveState(initialStatus);

    buttons.forEach(button => {
        if (button.dataset.statusFilterBound === 'true') {
            return;
        }
        button.dataset.statusFilterBound = 'true';

        button.addEventListener('click', () => {
            const clickedStatus = button.dataset.status || '';
            const currentStatus = container.dataset.activeStatus || '';
            const toggledOff = clickedStatus === currentStatus;
            const nextStatus = toggledOff ? '' : clickedStatus;

            setActiveState(nextStatus);
            updateQueryString(nextStatus);
            setLoadingState(true);

            applySchedulerFilter(nextStatus)
                .catch(error => {
                    console.error('[app.js] Failed to apply scheduler status filter', error);
                })
                .finally(() => {
                    setLoadingState(false);
                });

            emitAppointmentsUpdated({ source: 'status-filter', status: nextStatus || null });
        });
    });
}

let statsRefreshAbortController = null;

function getActiveStatusFilter() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return '';
    }

    const container = document.querySelector('[data-status-filter-container]');
    if (container) {
        const containerStatus = container.dataset.activeStatus;
        if (typeof containerStatus === 'string' && containerStatus !== '') {
            return containerStatus;
        }
    }

    const calendar = document.getElementById('appointments-inline-calendar');
    if (calendar) {
        const calendarStatus = calendar.dataset.activeStatus;
        if (typeof calendarStatus === 'string' && calendarStatus !== '') {
            return calendarStatus;
        }
    }

    if (window.scheduler && typeof window.scheduler.statusFilter !== 'undefined' && window.scheduler.statusFilter !== null) {
        return window.scheduler.statusFilter;
    }

    return '';
}

async function refreshAppointmentStats() {
    if (typeof window === 'undefined') {
        return;
    }

    if (statsRefreshAbortController) {
        statsRefreshAbortController.abort();
    }

    statsRefreshAbortController = new AbortController();

    try {
        const activeStatus = getActiveStatusFilter();
        const url = new URL(`${getBaseUrl()}/api/dashboard/appointment-stats`);
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

        updateCountElement('upcomingCount', stats.upcoming);
        updateCountElement('completedCount', stats.completed);
        updateCountElement('pendingCount', stats.pending);
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        console.error('[app.js] Failed to refresh appointment stats', error);
    } finally {
        statsRefreshAbortController = null;
    }
}

function updateCountElement(elementId, value) {
    const el = document.getElementById(elementId);
    if (!el) {
        return;
    }

    const formatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
    const numericValue = typeof value === 'number' ? value : parseInt(value ?? 0, 10) || 0;
    el.textContent = formatter.format(numericValue);
}

function emitAppointmentsUpdated(detail = {}) {
    if (typeof window === 'undefined') {
        return;
    }

    refreshAppointmentStats();
    window.dispatchEvent(new CustomEvent('appointments-updated', { detail }));
}

// =============================================================================
// GLOBAL HEADER SEARCH
// =============================================================================

function initGlobalSearch() {
    const searchInput = document.getElementById('global-search');
    const searchResults = document.getElementById('global-search-results');
    const resultsContent = document.getElementById('global-search-results-content');

    const searchInputMobile = document.getElementById('global-search-mobile');
    const searchResultsMobile = document.getElementById('global-search-results-mobile');
    const resultsContentMobile = document.getElementById('global-search-results-content-mobile');

    const inputs = [
        { input: searchInput, results: searchResults, content: resultsContent },
        { input: searchInputMobile, results: searchResultsMobile, content: resultsContentMobile }
    ].filter(item => item.input && item.results && item.content);

    if (inputs.length === 0) return;

    inputs.forEach(({ input }) => {
        if (input.dataset.searchInitialized === 'true') return;
        input.dataset.searchInitialized = 'true';
    });

    let searchTimeout = null;
    let activeController = null;

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDateTime(value) {
        if (!value) return 'Unknown time';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function hideResults() {
        inputs.forEach(({ results }) => {
            results.classList.add('hidden');
        });
    }

    function showResults(targetResults) {
        if (targetResults) {
            targetResults.classList.remove('hidden');
        }
    }

    function renderResults(container, data, query) {
        if (!container) return;
        const customers = data.customers || [];
        const appointments = data.appointments || [];

        if (customers.length === 0 && appointments.length === 0) {
            container.innerHTML = `
                <div class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">
                    No results for "${escapeHtml(query)}"
                </div>
            `;
            return;
        }

        const baseUrl = getBaseUrl();
        let html = '';

        if (customers.length > 0) {
            html += `
                <div class="px-3 py-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Customers</div>
            `;
            customers.forEach(customer => {
                const name = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || customer.name || 'Customer';
                const email = customer.email || 'No email';
                const hash = customer.hash || customer.id;
                const url = `${baseUrl}/customer-management/history/${hash}`;

                html += `
                    <a href="${url}" class="block px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">${escapeHtml(name)}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(email)}</div>
                    </a>
                `;
            });
        }

        if (appointments.length > 0) {
            html += `
                <div class="px-3 py-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Appointments</div>
            `;
            appointments.forEach(appt => {
                const customerName = appt.customer_name || 'Unknown customer';
                const serviceName = appt.service_name || 'Appointment';
                const startTime = formatDateTime(appt.start_time);
                const hash = appt.hash || appt.id;
                const url = `${baseUrl}/appointments/edit/${hash}`;

                html += `
                    <a href="${url}" class="block px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">${escapeHtml(customerName)}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(serviceName)} • ${escapeHtml(startTime)}</div>
                    </a>
                `;
            });
        }

        container.innerHTML = html;
    }

    async function performSearch(query, target) {
        const { results, content } = target;
        if (!results || !content) return;

        if (!query || query.trim().length < 2) {
            hideResults();
            return;
        }

        if (activeController) {
            activeController.abort();
        }

        activeController = new AbortController();

        try {
            const baseUrl = getBaseUrl();
            const response = await fetch(`${baseUrl}/dashboard/search?q=${encodeURIComponent(query.trim())}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                signal: activeController.signal
            });

            if (!response.ok) {
                throw new Error(`Search failed: ${response.status}`);
            }

            // Get response as text first (in case debug toolbar is present)
            const text = await response.text();
            
            // Try to extract JSON from response (handles debug toolbar contamination)
            let data;
            try {
                // First try parsing as-is
                data = JSON.parse(text);
            } catch (e) {
                // Strategy 1: Look for JSON object pattern with success field
                const jsonMatch = text.match(/\{["']success["']:\s*(?:true|false)[\s\S]*?\}(?=\s*<|$)/);
                if (jsonMatch) {
                    try {
                        data = JSON.parse(jsonMatch[0]);
                    } catch (e2) {
                        // Strategy 1 failed
                    }
                }
                
                // Strategy 2: Find last complete JSON object
                if (!data) {
                    const lastBrace = text.lastIndexOf('}');
                    if (lastBrace > 0) {
                        let depth = 1;
                        let i = lastBrace - 1;
                        while (i >= 0 && depth > 0) {
                            if (text[i] === '}') depth++;
                            if (text[i] === '{') depth--;
                            i--;
                        }
                        if (depth === 0) {
                            try {
                                data = JSON.parse(text.substring(i + 1, lastBrace + 1));
                            } catch (e3) {
                                // Strategy 2 failed
                            }
                        }
                    }
                }
                
                if (!data) {
                    console.error('Global search: Could not extract JSON from response');
                    throw new Error('Invalid JSON response');
                }
            }

            if (!data || data.success === false) {
                throw new Error(data?.error || 'Search failed');
            }

            renderResults(content, data, query);
            showResults(results);
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Global search error:', error);
            content.innerHTML = `
                <div class="px-3 py-3 text-sm text-red-600 dark:text-red-400">
                    Search failed. Try again.
                </div>
            `;
            showResults(results);
        }
    }

    function bindInput(target) {
        const { input } = target;
        if (!input) return;

        input.addEventListener('input', (e) => {
            const query = e.target.value;
            if (searchTimeout) clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => performSearch(query, target), 300);
        });

        input.addEventListener('focus', (e) => {
            const query = e.target.value;
            if (query && query.trim().length >= 2) {
                performSearch(query, target);
            }
        });
    }

    inputs.forEach(bindInput);

    document.addEventListener('click', (e) => {
        const wrappers = [
            document.getElementById('global-search-wrapper'),
            document.getElementById('global-search-wrapper-mobile')
        ].filter(Boolean);

        if (!wrappers.some(wrapper => wrapper.contains(e.target))) {
            hideResults();
        }
    });
}

// ⚠️ DEPRECATED: Calendar initialization removed
// Custom scheduler placeholder will be implemented

/**
 * Initialize all components (charts, scheduler, forms)
 * Called on initial page load and after SPA navigation
 */
function initializeComponents() {
    // Initialize charts and dashboard widgets
    if (typeof Charts !== 'undefined') {
        Charts.initAllCharts();
        Charts.setupDarkModeListener();
        Charts.initChartFilters();
    }

    // Initialize custom scheduler
    initScheduler();

    // Wire up dashboard filters
    initStatusFilterControls();

    // Initialize global header search
    initGlobalSearch();
    
    // Initialize appointment booking form if present
    initAppointmentForm();
    
    // Pre-fill appointment form if URL parameters exist
    prefillAppointmentForm();
}

// Initialize on DOM ready (initial page load)
document.addEventListener('DOMContentLoaded', function() {
    initializeComponents();
    refreshAppointmentStats();
});

// Re-initialize after SPA navigation
document.addEventListener('spa:navigated', function(e) {
    initializeComponents();
    refreshAppointmentStats();
});

if (typeof window !== 'undefined') {
    window.refreshAppointmentStats = refreshAppointmentStats;
    window.emitAppointmentsUpdated = emitAppointmentsUpdated;
}

/**
 * Initialize custom scheduler
 */
async function initScheduler() {
    const schedulerContainer = document.getElementById('appointments-inline-calendar');
    
    if (!schedulerContainer) {
        return;
    }

    try {
        // Destroy existing scheduler instance if it exists
        if (window.scheduler && typeof window.scheduler.destroy === 'function') {
            window.scheduler.destroy();
            window.scheduler = null;
        }
        
        // Get initial date and active status from data attributes
        const initialDate = schedulerContainer.dataset.initialDate || new Date().toISOString().split('T')[0];
        const activeStatusFilter = schedulerContainer.dataset.activeStatus || '';
        
        // Create scheduler instance
        const scheduler = new SchedulerCore('appointments-inline-calendar', {
            initialView: 'month',
            initialDate: initialDate,
            timezone: window.appTimezone || 'America/New_York',
            apiBaseUrl: `${getBaseUrl()}/api/appointments`,
            statusFilter: activeStatusFilter || null,
            onAppointmentClick: handleAppointmentClick
        });

        // Initialize the scheduler (loads data and renders)
        await scheduler.init();

        // Wire up toolbar navigation buttons
        setupSchedulerToolbar(scheduler);

        // Store scheduler instance globally for debugging
        window.scheduler = scheduler;
        window.dispatchEvent(new CustomEvent('scheduler:ready', { detail: { scheduler } }));

        // Check if we need to refresh (e.g., after creating an appointment)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('refresh')) {
            // Remove the refresh parameter from URL without reload
            window.history.replaceState({}, document.title, window.location.pathname);
            // Force a calendar refresh by reloading appointments and re-rendering
            await scheduler.loadAppointments();
            scheduler.render();
        }
    } catch (error) {
        console.error('❌ Failed to initialize scheduler:', error);
        // Show fallback placeholder
        schedulerContainer.innerHTML = `
            <div class="flex flex-col items-center justify-center p-12">
                <span class="material-symbols-outlined text-red-500 text-6xl mb-4">error</span>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    Scheduler Error
                </h3>
                <p class="text-gray-600 dark:text-gray-400 text-center max-w-md">
                    Failed to load scheduler. Please refresh the page.
                </p>
            </div>
        `;
    }
}

/**
 * Setup toolbar button event handlers
 */
function setupSchedulerToolbar(scheduler) {
    // View buttons
    document.querySelectorAll('[data-calendar-action="day"], [data-calendar-action="week"], [data-calendar-action="month"]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const view = btn.dataset.calendarAction;
            try {
                await scheduler.changeView(view);
                
                // Update active state
                document.querySelectorAll('[data-calendar-action]').forEach(b => {
                    if (b.dataset.calendarAction === view) {
                        b.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
                        b.classList.remove('bg-slate-100', 'dark:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
                    } else if (['day', 'week', 'month'].includes(b.dataset.calendarAction)) {
                        b.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
                        b.classList.add('bg-slate-100', 'dark:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
                    }
                });

                updateDateDisplay(scheduler);
            } catch (error) {
                console.error('Failed to change view:', error);
            }
        });
    });

    // Today button
    const todayBtn = document.querySelector('[data-calendar-action="today"]');
    if (todayBtn) {
        todayBtn.addEventListener('click', async () => {
            try {
                await scheduler.navigateToToday();
                updateDateDisplay(scheduler);
            } catch (error) {
                console.error('Failed to navigate to today:', error);
            }
        });
    }

    // Previous button
    const prevBtn = document.querySelector('[data-calendar-action="prev"]');
    if (prevBtn) {
        prevBtn.addEventListener('click', async () => {
            try {
                await scheduler.navigatePrev();
                updateDateDisplay(scheduler);
            } catch (error) {
                console.error('Failed to navigate to previous:', error);
            }
        });
    }

    // Next button
    const nextBtn = document.querySelector('[data-calendar-action="next"]');
    if (nextBtn) {
        nextBtn.addEventListener('click', async () => {
            try {
                await scheduler.navigateNext();
                updateDateDisplay(scheduler);
            } catch (error) {
                console.error('Failed to navigate to next:', error);
            }
        });
    }

    // Render provider legend
    renderProviderLegend(scheduler);
    
    // Setup advanced filter panel
    setupAdvancedFilterPanel(scheduler);
    
    // Initial date display update
    updateDateDisplay(scheduler);
}

/**
 * Update the date display in the toolbar
 */
function updateDateDisplay(scheduler) {
    const displayEl = document.getElementById('scheduler-date-display');
    if (!displayEl) return;

    const { currentDate, currentView } = scheduler;
    let displayText = '';

    switch (currentView) {
        case 'day':
            displayText = currentDate.toFormat('EEEE, MMMM d, yyyy');
            break;
        case 'week':
            const weekStart = currentDate.startOf('week');
            const weekEnd = currentDate.endOf('week');
            displayText = `${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('MMM d, yyyy')}`;
            break;
        case 'month':
        default:
            displayText = currentDate.toFormat('MMMM yyyy');
            break;
    }

    displayEl.textContent = displayText;
}

/**
 * Setup the advanced filter panel toggle and apply/clear handlers
 */
function setupAdvancedFilterPanel(scheduler) {
    const toggleBtn = document.getElementById('advanced-filter-toggle');
    const filterPanel = document.getElementById('advanced-filter-panel');
    const toggleIcon = document.getElementById('filter-toggle-icon');
    const applyBtn = document.getElementById('apply-filters-btn');
    const clearBtn = document.getElementById('clear-filters-btn');
    
    // Filter dropdowns
    const statusSelect = document.getElementById('filter-status');
    const providerSelect = document.getElementById('filter-provider');
    const serviceSelect = document.getElementById('filter-service');
    
    // Store all services for "All Providers" view
    const allServicesOptions = serviceSelect ? serviceSelect.innerHTML : '';
    
    if (!toggleBtn || !filterPanel) {
        return; // Panel elements not present
    }
    
    // Dynamic service loading when provider changes
    if (providerSelect && serviceSelect) {
        providerSelect.addEventListener('change', async () => {
            const providerId = providerSelect.value;
            
            if (!providerId) {
                // No provider selected - show all services
                serviceSelect.innerHTML = allServicesOptions;
                serviceSelect.disabled = false;
                return;
            }
            
            // Show loading state
            serviceSelect.innerHTML = '<option value="">Loading services...</option>';
            serviceSelect.disabled = true;
            
            try {
                const response = await fetch(`${getBaseUrl()}/api/v1/providers/${providerId}/services`);
                if (!response.ok) throw new Error('Failed to load services');
                
                const result = await response.json();
                const services = result.data || [];
                
                // Rebuild service dropdown with provider-specific services
                let optionsHtml = '<option value="">All Services</option>';
                services.forEach(service => {
                    optionsHtml += `<option value="${service.id}">${service.name}</option>`;
                });
                
                serviceSelect.innerHTML = optionsHtml;
                serviceSelect.disabled = false;
            } catch (error) {
                console.error('Failed to load provider services:', error);
                // Fallback to all services on error
                serviceSelect.innerHTML = allServicesOptions;
                serviceSelect.disabled = false;
            }
        });
    }
    
    // Toggle panel visibility
    toggleBtn.addEventListener('click', () => {
        const isHidden = filterPanel.classList.toggle('hidden');
        
        // Rotate icon
        if (toggleIcon) {
            toggleIcon.style.transform = isHidden ? '' : 'rotate(180deg)';
        }
        
        // Update toggle button styling to indicate active state
        if (!isHidden) {
            toggleBtn.classList.add('bg-blue-100', 'dark:bg-blue-900/30', 'text-blue-700', 'dark:text-blue-300');
            toggleBtn.classList.remove('bg-slate-100', 'dark:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
        } else {
            toggleBtn.classList.remove('bg-blue-100', 'dark:bg-blue-900/30', 'text-blue-700', 'dark:text-blue-300');
            toggleBtn.classList.add('bg-slate-100', 'dark:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
        }
    });
    
    // Apply filters
    if (applyBtn) {
        applyBtn.addEventListener('click', async () => {
            const status = statusSelect?.value || '';
            const providerId = providerSelect?.value || '';
            const serviceId = serviceSelect?.value || '';
            
            try {
                await scheduler.setFilters({ status, providerId, serviceId });
                
                // Re-render provider legend to reflect filter state
                renderProviderLegend(scheduler);
                
                // Show success feedback
                applyBtn.textContent = 'Applied!';
                setTimeout(() => {
                    applyBtn.innerHTML = '<span class="material-symbols-outlined text-base">filter_alt</span> Apply';
                }, 1000);
                
                // Update filter indicator on toggle button
                const hasActiveFilters = status || providerId || serviceId;
                updateFilterIndicator(toggleBtn, hasActiveFilters);
            } catch (error) {
                console.error('Failed to apply filters:', error);
            }
        });
    }
    
    // Clear filters
    if (clearBtn) {
        clearBtn.addEventListener('click', async () => {
            // Reset all dropdowns
            if (statusSelect) statusSelect.value = '';
            if (providerSelect) providerSelect.value = '';
            if (serviceSelect) {
                // Restore all services when clearing filters
                serviceSelect.innerHTML = allServicesOptions;
                serviceSelect.value = '';
                serviceSelect.disabled = false;
            }
            
            try {
                await scheduler.setFilters({ status: '', providerId: '', serviceId: '' });
                
                // Re-render provider legend to reflect cleared state
                renderProviderLegend(scheduler);
                
                updateFilterIndicator(toggleBtn, false);
            } catch (error) {
                console.error('Failed to clear filters:', error);
            }
        });
    }
    
    // Check for initial active filters (from URL or server-side)
    const hasActiveFilters = 
        (statusSelect?.value && statusSelect.value !== '') ||
        (providerSelect?.value && providerSelect.value !== '') ||
        (serviceSelect?.value && serviceSelect.value !== '');
    
    if (hasActiveFilters) {
        updateFilterIndicator(toggleBtn, true);
    }
}

/**
 * Update the filter indicator badge on the toggle button
 */
function updateFilterIndicator(toggleBtn, hasActiveFilters) {
    // Remove existing indicator
    const existingIndicator = toggleBtn.querySelector('.filter-active-indicator');
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    if (hasActiveFilters) {
        const indicator = document.createElement('span');
        indicator.className = 'filter-active-indicator absolute -top-1 -right-1 w-3 h-3 bg-blue-500 rounded-full border-2 border-white dark:border-gray-800';
        toggleBtn.style.position = 'relative';
        toggleBtn.appendChild(indicator);
    }
}

/**
 * Render provider legend with color indicators
 */
function renderProviderLegend(scheduler) {
    const legendEl = document.getElementById('provider-legend');
    if (!legendEl || !scheduler.providers || scheduler.providers.length === 0) return;

    legendEl.innerHTML = scheduler.providers.map(provider => {
        const color = provider.color || '#3B82F6';
        const isVisible = scheduler.visibleProviders.has(provider.id);
        
        return `
            <button type="button" 
                    class="provider-legend-item flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-medium transition-all duration-200 ${
                        isVisible 
                            ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white' 
                            : 'bg-gray-50 dark:bg-gray-800 text-gray-400 dark:text-gray-500 opacity-50'
                    } hover:bg-gray-200 dark:hover:bg-gray-600"
                    data-provider-id="${provider.id}"
                    title="Toggle ${provider.name}">
                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: ${color};"></span>
                <span class="truncate max-w-[120px]">${provider.name}</span>
            </button>
        `;
    }).join('');

    // Add click handlers for toggling providers
    legendEl.querySelectorAll('.provider-legend-item').forEach(btn => {
        btn.addEventListener('click', () => {
            const providerId = parseInt(btn.dataset.providerId);
            scheduler.toggleProvider(providerId);
            renderProviderLegend(scheduler); // Re-render to update styles
        });
    });
}

/**
 * Handle appointment click - open details modal
 */
function handleAppointmentClick(appointment) {
    // Open the appointment details modal
    if (window.scheduler?.appointmentDetailsModal) {
        window.scheduler.appointmentDetailsModal.open(appointment);
    } else {
        console.error('[app.js] Appointment details modal not available');
    }
}
