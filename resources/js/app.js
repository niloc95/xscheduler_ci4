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

import { getBaseUrl } from './utils/url-helpers.js';

// Import dynamic color utility (auto-initialises; side-effect import only)
import './utils/dynamic-colors.js';

// Import global search
import { initGlobalSearch } from './modules/search/global-search.js';

// Import status filters
import { initStatusFilterControls, initSummaryCardFilters, initViewToggleHandlers, emitAppointmentsUpdated, refreshAppointmentStats } from './modules/filters/status-filters.js';

// Import advanced filters
import { setupAdvancedFilterPanel } from './modules/filters/advanced-filters.js';

// Import scheduler UI module
import { setupSchedulerToolbar } from './modules/scheduler/scheduler-ui.js';
import { initSettingsPageEnhancements } from './modules/settings/settings-page.js';
import { initCustomerManagementSearch } from './modules/customer-management/customer-search.js';
import { initProviderSchedule } from './modules/user-management/provider-schedule.js';
import { bindAppLifecycleEvents } from './modules/app-lifecycle.js';
import { initPhoneCountrySelectors } from './utils/phone-country-selector.js';

// Import appointment navigation module
import { navigateToCreateAppointment, prefillAppointmentForm, handleAppointmentClick } from './modules/appointments/appointment-navigation.js';

// Import custom scheduler core (loads MonthView, WeekView, DayView, DragDropManager, SettingsManager internally)
import { SchedulerCore } from './modules/scheduler/scheduler-core.js';

// Import scheduler styles
import '../css/scheduler.css';

// Import currency formatter (was orphaned — now included in bundle)
import './currency.js';

// Define global escapeHtml utility — single source of truth
window.xsEscapeHtml = window.xsEscapeHtml || function(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
};

// Export shared time-slot UI initializer to window so PHP views can call it
import { initTimeSlotsUI } from './modules/appointments/time-slots-ui.js';
window.initTimeSlotsUI = initTimeSlotsUI;

// Export analytics chart functions for PHP views
window.initRevenueTrendChart = initRevenueTrendChart;
window.initTimeSlotChart = initTimeSlotChart;
window.initServiceDistributionChart = initServiceDistributionChart;

// Shared service provider picker used by create/edit service views.
window.initProviderPicker = window.initProviderPicker || function(root) {
    const picker = root?.querySelector('[data-provider-picker]');
    if (!picker) return;

    const checkboxes = Array.from(picker.querySelectorAll('input[name="provider_ids[]"]'));
    const countLabel = picker.querySelector('[data-provider-selection-count]');
    const selectAllBtn = picker.querySelector('[data-provider-picker-action="select-all"]');
    const clearAllBtn = picker.querySelector('[data-provider-picker-action="clear-all"]');

    const sync = () => {
        let count = 0;

        checkboxes.forEach((cb) => {
            const card = cb.closest('label');
            if (!card) return;

            if (cb.checked) {
                count += 1;
                card.classList.add('border-primary-300', 'bg-primary-50/70', 'dark:border-primary-700', 'dark:bg-primary-900/20');
                card.classList.remove('border-gray-200', 'dark:border-gray-700');
            } else {
                card.classList.remove('border-primary-300', 'bg-primary-50/70', 'dark:border-primary-700', 'dark:bg-primary-900/20');
                card.classList.add('border-gray-200', 'dark:border-gray-700');
            }
        });

        if (countLabel) {
            countLabel.textContent = String(count);
        }
    };

    checkboxes.forEach((cb) => cb.addEventListener('change', sync));

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', () => {
            checkboxes.forEach((cb) => {
                cb.checked = true;
            });
            sync();
        });
    }

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', () => {
            checkboxes.forEach((cb) => {
                cb.checked = false;
            });
            sync();
        });
    }

    sync();
};

export { 
    initRevenueTrendChart, 
    initTimeSlotChart, 
    initServiceDistributionChart 
};

// ============================================
// COMPONENT INITIALIZATION
// ============================================

/**
 * Initialize all components (charts, scheduler, forms)
 * Called on initial page load and after SPA navigation
 */
function initializeComponents() {
    // Initialize charts and dashboard widgets
    if (Charts) {
        Charts.initAllCharts();
        Charts.setupDarkModeListener();
        Charts.initChartFilters();
    }

    // Initialize custom scheduler
    initScheduler();

    // Wire up dashboard filters (with deduplication guards)
    initStatusFilterControls();
    
    // Wire up summary card filters (clickable status cards)
    initSummaryCardFilters();
    
    // Wire up view toggle handlers (Day/Week/Month stats refresh)
    initViewToggleHandlers();

    // Initialize global header search
    initGlobalSearch();
    
    // Initialize appointment booking form if present
    initAppointmentForm();

    // Initialize extracted settings page workflows when present.
    initSettingsPageEnhancements();

    // Initialize customer management live search when that view is present.
    initCustomerManagementSearch();

    // Initialize extracted provider schedule UI when user-management forms are present.
    initProviderSchedule();

    // Initialize service create/edit UI when those forms are present
    initServiceManagementForms();

    // Add country-code selectors to all canonical phone fields.
    initPhoneCountrySelectors(document);
    
    // Pre-fill appointment form if URL parameters exist
    prefillAppointmentForm();
}

function getAppRelativePathname() {
    try {
        const currentPath = window.location.pathname;
        const basePath = new URL(getBaseUrl(), window.location.origin).pathname.replace(/\/+$/, '');

        if (!basePath || basePath === '/') {
            return currentPath;
        }

        if (currentPath === basePath) {
            return '/';
        }

        if (currentPath.startsWith(`${basePath}/`)) {
            return currentPath.slice(basePath.length);
        }

        return currentPath;
    } catch {
        return window.location.pathname;
    }
}

function initServiceManagementForms() {
    const form = document.getElementById('createServiceForm') || document.getElementById('editServiceForm');
    if (!form) {
        return;
    }

    if (typeof window.initProviderPicker === 'function' && form.dataset.providerPickerInitialized !== 'true') {
        form.dataset.providerPickerInitialized = 'true';
        window.initProviderPicker(form);
    }

    form.dataset.serviceViewInitialized = 'true';
}

/**
 * Global password-visibility toggle.
 * Used by user-management create/edit forms.
 */
window.togglePassword = function(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    if (!field || !icon) return;
    if (field.type === 'password') {
        field.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        field.type = 'password';
        icon.textContent = 'visibility';
    }
};

bindAppLifecycleEvents({
    documentRef: document,
    initializeComponents,
    refreshAppointmentStats,
    resetSchedulerInitAttempts: () => {
        schedulerInitAttempts = 0;
    },
    hasDashboardStats: () => Boolean(
        document.getElementById('upcomingCount') || document.getElementById('completedCount')
    ),
});

// Listen for settings changes and refresh scheduler
document.addEventListener('settingsSaved', async () => {
    const schedulerContainer = document.getElementById('appointments-inline-calendar');
    if (!schedulerContainer) {
        teardownScheduler();
        return;
    }

    if (!window.scheduler || !window.scheduler.settingsManager) return;
    await window.scheduler.settingsManager.refresh();
    await window.scheduler.loadAppointments();
    window.scheduler.render();
}, { once: false });

window.refreshAppointmentStats = refreshAppointmentStats;
window.emitAppointmentsUpdated = emitAppointmentsUpdated;

/**
 * Initialize custom scheduler
 */
let schedulerInitAttempts = 0;
const MAX_SCHEDULER_INIT_ATTEMPTS = 10;

function teardownScheduler() {
    if (window.scheduler && typeof window.scheduler.destroy === 'function') {
        window.scheduler.destroy();
    }
    window.scheduler = null;
}

async function initScheduler() {
    const schedulerContainer = document.getElementById('appointments-inline-calendar');
    
    if (!schedulerContainer) {
        teardownScheduler();

        // SPA navigation may not have injected the appointments view yet
        if (getAppRelativePathname().includes('/appointments') && schedulerInitAttempts < MAX_SCHEDULER_INIT_ATTEMPTS) {
            schedulerInitAttempts += 1;
            setTimeout(() => initScheduler(), 200);
        }
        return;
    }

    // Reset attempts once container is found
    schedulerInitAttempts = 0;

    try {
        // Destroy existing scheduler instance if it exists
        teardownScheduler();
        
        // Get initial date and active status from data attributes
        const initialDate = schedulerContainer.dataset.initialDate || new Date().toISOString().split('T')[0];
        const activeStatusFilter = schedulerContainer.dataset.activeStatus || '';
        
        // Create scheduler instance
        const scheduler = new SchedulerCore('appointments-inline-calendar', {
            mode: 'server',
            initialView: 'day',
            initialDate: initialDate,
            timezone: window.appTimezone,
            apiBaseUrl: `${getBaseUrl()}/api/appointments`,
            apiCalendarBaseUrl: `${getBaseUrl()}/api/calendar`,
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

        // NOTE: ?open= handling is in scheduler-core.js → checkUrlForAppointment()

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
