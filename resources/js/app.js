// (CoreUI components are no longer used for the sidebar. Keep charts init only.)

// Import appointment booking form functionality
import { initAppointmentForm } from './modules/appointments/appointments-form.js';

// Import charts functionality
import Charts from './charts.js';

// Import analytics charts
import { 
    initRevenueTrendChart, 
    initTimeSlotChart, 
    initServiceDistributionChart 
} from './modules/analytics/analytics-charts.js';

import { attachTimezoneHeaders } from './utils/timezone-helper.js';
import { getBaseUrl } from './utils/url-helpers.js';

// Import dynamic color utility
import { applyDynamicColors } from './utils/dynamic-colors.js';

// Import global search
import { initGlobalSearch } from './modules/search/global-search.js';

// Import status filters
import { initStatusFilterControls, initSummaryCardFilters, initViewToggleHandlers, emitAppointmentsUpdated, refreshAppointmentStats } from './modules/filters/status-filters.js';

// Import advanced filters
import { setupAdvancedFilterPanel } from './modules/filters/advanced-filters.js';

// Import scheduler UI module
import { setupSchedulerToolbar } from './modules/scheduler/scheduler-ui.js';

// Import appointment navigation module
import { navigateToCreateAppointment, prefillAppointmentForm, handleAppointmentClick } from './modules/appointments/appointment-navigation.js';

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

// Export analytics chart functions to window for PHP views
if (typeof window !== 'undefined') {
    window.initRevenueTrendChart = initRevenueTrendChart;
    window.initTimeSlotChart = initTimeSlotChart;
    window.initServiceDistributionChart = initServiceDistributionChart;
}

// Also export as ES6 modules
export { 
    initRevenueTrendChart, 
    initTimeSlotChart, 
    initServiceDistributionChart 
};

// ============================================
// COMPONENT INITIALIZATION
// ============================================

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
    
    // Wire up summary card filters (clickable status cards)
    initSummaryCardFilters();
    
    // Wire up view toggle handlers (Day/Week/Month stats refresh)
    initViewToggleHandlers();

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
let schedulerInitAttempts = 0;
const MAX_SCHEDULER_INIT_ATTEMPTS = 10;

async function initScheduler() {
    const schedulerContainer = document.getElementById('appointments-inline-calendar');
    
    if (!schedulerContainer) {
        // SPA navigation may not have injected the appointments view yet
        if (window.location.pathname.includes('/appointments') && schedulerInitAttempts < MAX_SCHEDULER_INIT_ATTEMPTS) {
            schedulerInitAttempts += 1;
            setTimeout(() => initScheduler(), 200);
        }
        return;
    }

    // Reset attempts once container is found
    schedulerInitAttempts = 0;

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
        setupSchedulerToolbar(scheduler, { setupAdvancedFilterPanel });

        // Store scheduler instance globally for debugging
        window.scheduler = scheduler;
        window.dispatchEvent(new CustomEvent('scheduler:ready', { detail: { scheduler } }));

        // If URL includes ?open=..., open the appointment after scheduler is ready
        const openParam = new URLSearchParams(window.location.search).get('open');
        if (openParam && typeof scheduler.openAppointmentById === 'function') {
            scheduler.openAppointmentById(openParam, true);
        }

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

// ============================================
// PAGE INITIALIZATION
// ============================================

