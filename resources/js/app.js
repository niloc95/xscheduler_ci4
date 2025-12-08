// (CoreUI components are no longer used for the sidebar. Keep charts init only.)

// Import appointment booking form functionality
import { initAppointmentForm } from './modules/appointments/appointments-form.js';

// Import charts functionality
import Charts from './charts.js';

import { attachTimezoneHeaders } from './utils/timezone-helper.js';

// Import custom scheduler components
import { SchedulerCore } from './modules/scheduler/scheduler-core.js';
import { MonthView } from './modules/scheduler/scheduler-month-view.js';
import { WeekView } from './modules/scheduler/scheduler-week-view.js';
import { DayView } from './modules/scheduler/scheduler-day-view.js';
import { DragDropManager } from './modules/scheduler/scheduler-drag-drop.js';
import { SettingsManager } from './modules/scheduler/settings-manager.js';

// Import scheduler styles
import '../css/scheduler.css';

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
    console.log('[app] Navigating to create appointment:', url);
    window.location.href = url;
}

/**
 * Pre-fill appointment form with URL parameters
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
    
    // Pre-fill date field
    if (date) {
        const dateInput = document.getElementById('appointment_date');
        if (dateInput) {
            dateInput.value = date;
            console.log('[app] Pre-filled appointment date:', date);
            
            // Trigger change event to update form state
            dateInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    
    // Pre-fill time field
    if (time) {
        const timeInput = document.getElementById('appointment_time');
        if (timeInput) {
            timeInput.value = time;
            console.log('[app] Pre-filled appointment time:', time);
            
            // Trigger change event to update form state
            timeInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    
    // Pre-select provider if specified
    if (providerId) {
        const providerSelect = document.getElementById('provider_id');
        if (providerSelect) {
            providerSelect.value = providerId;
            console.log('[app] Pre-selected provider:', providerId);
            
            // Trigger change event to load services
            providerSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
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
    
    // Initialize appointment booking form if present
    initAppointmentForm();
    
    // Pre-fill appointment form if URL parameters exist
    prefillAppointmentForm();
}

// Initialize on DOM ready (initial page load)
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Initial page load - initializing components');
    initializeComponents();
});

// Re-initialize after SPA navigation
document.addEventListener('spa:navigated', function(e) {
    console.log('🔄 SPA navigated to:', e.detail.url);
    console.log('🔄 Re-initializing components...');
    initializeComponents();
});

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
            console.log('🧹 Cleaning up existing scheduler instance');
            window.scheduler.destroy();
            window.scheduler = null;
        }
        
        // Get initial date from data attribute
        const initialDate = schedulerContainer.dataset.initialDate || new Date().toISOString().split('T')[0];
        
        // Create scheduler instance
        const scheduler = new SchedulerCore('appointments-inline-calendar', {
            initialView: 'month',
            initialDate: initialDate,
            timezone: window.appTimezone || 'America/New_York',
            apiBaseUrl: '/api/appointments',
            onAppointmentClick: handleAppointmentClick
        });

        // Initialize the scheduler (loads data and renders)
        await scheduler.init();

        // Wire up toolbar navigation buttons
        setupSchedulerToolbar(scheduler);

        // Store scheduler instance globally for debugging
        window.scheduler = scheduler;

        console.log('✅ Custom scheduler initialized');
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
    console.log('[app.js] Appointment clicked:', appointment);
    
    // Open the appointment details modal
    if (window.scheduler?.appointmentDetailsModal) {
        console.log('[app.js] Opening appointment details modal');
        window.scheduler.appointmentDetailsModal.open(appointment);
    } else {
        console.error('[app.js] Appointment details modal not available');
    }
}

console.log('Charts initialized');
