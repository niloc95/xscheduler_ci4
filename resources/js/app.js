// Import appointment booking form functionality
import { initAppointmentForm } from './modules/appointments/appointments-form.js';

// Import charts functionality
import Charts from './charts.js';

// Import analytics charts
import { 
    initRevenueTrendChart, 
    initTimeSlotChart, 
    initServiceDistributionChart,
    initNewVsReturningChart,
    initPeakHoursChart,
    initRevenueByProviderChart,
    initMoMComparisonChart,
    initAnalyticsDashboardPage
} from './modules/analytics/analytics-charts.js';

import { getBaseUrl } from './utils/url-helpers.js';
import { SEL } from './core/selectors.js';

// Import dynamic color utility (auto-initialises; side-effect import only)
import './utils/dynamic-colors.js';

// Import global search
import { initGlobalSearch } from './modules/search/global-search.js';
import { initDashboardProviderCards } from './modules/dashboard/provider-cards.js';

// Import status filters
import { initStatusFilterControls, initSummaryCardFilters, initViewToggleHandlers, refreshAppointmentStats } from './modules/filters/status-filters.js';

// Import advanced filters
import { setupAdvancedFilterPanel } from './modules/filters/advanced-filters.js';

// Import scheduler UI module
import { setupSchedulerToolbar } from './modules/scheduler/scheduler-ui.js';
import { initSettingsPageEnhancements } from './modules/settings/settings-page.js';
import { initIntegrationHub } from './modules/settings/integration-hub.js';
import { initCustomerManagementSearch } from './modules/customer-management/customer-search.js';
import { initProviderSchedule } from './modules/user-management/provider-schedule.js';
import { initProfilePage } from './modules/profile/profile-page.js';
import { bindAppLifecycleEvents } from './modules/app-lifecycle.js';
import { initPhoneCountrySelectors } from './utils/phone-country-selector.js';
import { initConfirmActions, initHelpFaq, initPasswordToggles, initProviderPicker, initServiceManagementForms, togglePassword } from './modules/app/shared-ui.js';
import { getAvatarInitials, getDisplayName } from './utils/avatar.js';
import { initInactivityMonitor } from './modules/auth/inactivity-monitor.js';

// Import appointment navigation module
import { prefillAppointmentForm, handleAppointmentClick } from './modules/appointments/appointment-navigation.js';

// Import custom scheduler core (loads MonthView, WeekView, DayView, DragDropManager, SettingsManager internally)
import { SchedulerCore } from './modules/scheduler/scheduler-core.js';

// Import scheduler styles
import '../css/scheduler.css';

// Import currency formatter (was orphaned — now included in bundle)
import './currency.js';

import { XSConfirm } from './utils/confirm.js';

// Define global escapeHtml utility — single source of truth
window.xsEscapeHtml = function(str) {
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
window.initNewVsReturningChart = initNewVsReturningChart;
window.initPeakHoursChart = initPeakHoursChart;
window.initRevenueByProviderChart = initRevenueByProviderChart;
window.initMoMComparisonChart = initMoMComparisonChart;

window.initProviderPicker = initProviderPicker;
window.xsGetAvatarInitials = window.xsGetAvatarInitials || getAvatarInitials;
window.xsGetDisplayName = window.xsGetDisplayName || getDisplayName;
window.XSConfirm = XSConfirm;

export { 
    initRevenueTrendChart, 
    initTimeSlotChart, 
    initServiceDistributionChart,
    initNewVsReturningChart,
    initPeakHoursChart,
    initRevenueByProviderChart,
    initMoMComparisonChart,
    initAnalyticsDashboardPage
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

    // Initialize provider-card slot filters on dashboard landing.
    initDashboardProviderCards();
    
    // Initialize appointment booking form if present
    initAppointmentForm();

    // Initialize extracted settings page workflows when present.
    initSettingsPageEnhancements();
    initIntegrationHub();

    // Initialize customer management live search when that view is present.
    initCustomerManagementSearch();

    // Initialize extracted provider schedule UI when user-management forms are present.
    initProviderSchedule();

    // Initialize service create/edit UI when those forms are present
    initServiceManagementForms();

    // Initialize shared view interactions extracted from inline handlers.
    initHelpFaq(document);
    initConfirmActions(document);
    initPasswordToggles(document);

    // Add country-code selectors to all canonical phone fields.
    initPhoneCountrySelectors(document);

    // Initialize the live profile page tab and avatar interactions.
    initProfilePage(document);

    // Start inactivity monitor — shows warning modal 5 min before session expiry.
    initInactivityMonitor();
    
    // Pre-fill appointment form if URL parameters exist
    prefillAppointmentForm();

    // Initialize analytics dashboard tab and chart interactions when present.
    initAnalyticsDashboardPage();
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

/**
 * Global password-visibility toggle.
 * Used by user-management create/edit forms.
 */
window.togglePassword = togglePassword;

bindAppLifecycleEvents({
    documentRef: document,
    initializeComponents,
    refreshAppointmentStats,
    resetSchedulerInitAttempts,
    hasDashboardStats: () => Boolean(document.querySelector('[data-dashboard-summary]')),
});

// Listen for settings changes and refresh cached singletons + scheduler
document.addEventListener('settingsSaved', async () => {
    // Currency is a load-once singleton consumed across many views; refresh it
    // regardless of the current page so subsequent renders use the new symbol.
    if (window.currencyFormatter && typeof window.currencyFormatter.refresh === 'function') {
        try {
            await window.currencyFormatter.refresh();
        } catch (error) {
            console.warn('Currency refresh after settings save failed:', error);
        }
    }

    const schedulerContainer = document.querySelector(SEL.SCHEDULER_CONTAINER);
    if (!schedulerContainer) {
        teardownScheduler();
        return;
    }

    if (!window.scheduler || !window.scheduler.settingsManager) return;
    await window.scheduler.settingsManager.refresh();

    if (typeof window.scheduler.refreshAndRender === 'function') {
        await window.scheduler.refreshAndRender({ reason: 'settings-saved' });
        return;
    }

    await window.scheduler.loadData();
    window.scheduler.render();
}, { once: false });

window.refreshAppointmentStats = refreshAppointmentStats;
let schedulerInitAttempts = 0;
const MAX_SCHEDULER_INIT_ATTEMPTS = 10;

function resetSchedulerInitAttempts() {
    schedulerInitAttempts = 0;
}

function teardownScheduler() {
    if (window.scheduler && typeof window.scheduler.stopBackgroundPolling === 'function') {
        window.scheduler.stopBackgroundPolling();
    }

    if (window.scheduler && typeof window.scheduler.destroy === 'function') {
        window.scheduler.destroy();
    }
    window.scheduler = null;
}

async function initScheduler() {
    const schedulerContainer = document.querySelector(SEL.SCHEDULER_CONTAINER);
    const relativePath = getAppRelativePathname();
    
    if (!schedulerContainer) {
        teardownScheduler();

        // SPA navigation may not have injected the appointments view yet
        if (relativePath.includes('/appointments') && schedulerInitAttempts < MAX_SCHEDULER_INIT_ATTEMPTS) {
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
            // Force a calendar refresh through the canonical server-mode path
            if (typeof scheduler.refreshAndRender === 'function') {
                await scheduler.refreshAndRender({ reason: 'url-refresh' });
            } else {
                await scheduler.loadData();
                scheduler.render();
            }
        }

        if (typeof scheduler.startBackgroundPolling === 'function') {
            scheduler.startBackgroundPolling();
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

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        return;
    }

    if (!getAppRelativePathname().includes('/appointments')) {
        return;
    }

    if (window.scheduler && typeof window.scheduler.triggerBackgroundRefresh === 'function') {
        window.scheduler.triggerBackgroundRefresh();
    }
}, { once: false });
