// (CoreUI components are no longer used for the sidebar. Keep charts init only.)

// Import appointment calendar functionality
import { initAppointmentsCalendar, setupViewButtons, refreshCalendar, showAppointmentModal } from './modules/appointments/appointments-calendar.js';

// Import appointment booking form functionality
import { initAppointmentForm } from './modules/appointments/appointments-form.js';

// Import charts functionality
import Charts from './charts.js';

// Store calendar instance globally to allow re-initialization
let calendarInstance = null;
let calendarSettings = {
    timeFormat: '24h',
    workStart: '08:00:00',
    workEnd: '17:00:00',
    firstDay: 1,
    locale: 'en',
    timezone: 'local',
    lastFetchTime: 0
};

const LOCALE_MAP = {
    english: 'en',
    'portuguese-br': 'pt-br',
    portuguese: 'pt-br',
    spanish: 'es',
};

function normalizeTimeValue(value, fallback = '00:00:00') {
    if (!value) return fallback;
    const trimmed = value.toString().trim();
    if (!trimmed) return fallback;
    if (/^\d{2}:\d{2}:\d{2}$/.test(trimmed)) return trimmed;
    if (/^\d{2}:\d{2}$/.test(trimmed)) return `${trimmed}:00`;
    return fallback;
}

// Fetch calendar settings from API
async function fetchCalendarSettings(forceRefresh = false) {
    // Cache settings for 1 second to avoid excessive API calls
    const now = Date.now();
    if (!forceRefresh && calendarSettings.lastFetchTime && (now - calendarSettings.lastFetchTime) < 1000) {
        console.log('[calendar] Using cached settings');
        return calendarSettings;
    }
    
    try {
        const response = await fetch('/api/v1/settings');
        if (!response.ok) {
            throw new Error(`Failed to fetch settings: ${response.status}`);
        }
        const payload = await response.json();
        const settings = payload?.data || {};
        
        const newSettings = {
            timeFormat: settings['localization.time_format'] || '24h',
            workStart: normalizeTimeValue(settings['business.work_start'], '08:00:00'),
            workEnd: normalizeTimeValue(settings['business.work_end'], '17:00:00'),
            firstDay: (settings['localization.first_day'] || 'Monday') === 'Sunday' ? 0 : 1,
            locale: LOCALE_MAP[(settings['localization.language'] || 'english').toString().trim().toLowerCase()] || 'en',
            timezone: (settings['localization.timezone'] && settings['localization.timezone'] !== 'Automatic')
                ? settings['localization.timezone']
                : 'local',
            lastFetchTime: now
        };
        
        // Check if settings actually changed
        const hasChanged = 
            calendarSettings.timeFormat !== newSettings.timeFormat ||
            calendarSettings.workStart !== newSettings.workStart ||
            calendarSettings.workEnd !== newSettings.workEnd ||
            calendarSettings.firstDay !== newSettings.firstDay ||
            calendarSettings.locale !== newSettings.locale ||
            calendarSettings.timezone !== newSettings.timezone;
        
        calendarSettings = newSettings;
        
        console.log('[calendar] Settings loaded:', calendarSettings, hasChanged ? '(CHANGED)' : '(no change)');
        return calendarSettings;
    } catch (error) {
        console.warn('[calendar] Failed to fetch settings, using defaults:', error);
        return calendarSettings;
    }
}

// Calendar initialization function
async function initializeCalendar(forceRefresh = false) {
    const calendarEl = document.getElementById('appointments-inline-calendar');

    // Skip if element doesn't exist
    if (!calendarEl) {
        return;
    }

    // Fetch latest settings before initializing
    await fetchCalendarSettings(forceRefresh);

    // Destroy existing instance if it exists
    if (calendarInstance) {
        console.log('[calendar] Destroying existing calendar instance');
        calendarInstance.destroy();
        calendarInstance = null;
    }

    // Get user role from session/page data
    const userRole = document.body.dataset.userRole || 'customer';

    // Initialize appointments calendar with settings (async)
    calendarInstance = await initAppointmentsCalendar(calendarEl, {
        settings: {
            'business.work_start': calendarSettings.workStart,
            'business.work_end': calendarSettings.workEnd,
            'localization.time_format': calendarSettings.timeFormat === '12h' ? '12' : '24',
            'localization.first_day_of_week': calendarSettings.firstDay.toString(),
            'localization.language': calendarSettings.locale,
            'localization.timezone': calendarSettings.timezone
        },
        filters: {},
        userRole: userRole,
        onEventClick: (event, info) => {
            console.log('[app] Appointment clicked:', event.id);
            showAppointmentModal(event.id, userRole, calendarInstance);
        },
        onDateSelect: (info) => {
            console.log('[app] Date selected:', info.startStr, 'to', info.endStr);
            // TODO: Show create appointment modal with pre-filled date/time
        },
        onEventDrop: (info) => {
            console.log('[app] Appointment rescheduled:', info.event.id);
            // TODO: Update appointment via API
        }
    });

    // Setup view buttons after calendar is initialized
    if (calendarInstance) {
        setupViewButtons(calendarInstance);
        console.log('[calendar] View buttons setup complete');
    }

    console.log('[calendar] Appointments calendar initialized successfully');
}

// Initialize charts and dashboard widgets when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Charts !== 'undefined') {
        Charts.initAllCharts();
    }

    initializeCalendar();
    
    // Initialize appointment booking form if present
    initAppointmentForm();
});

// Listen for settings changes and refresh calendar (same page)
document.addEventListener('settingsSaved', async function(event) {
    console.log('[calendar] Settings changed, refreshing calendar:', event.detail);
    
    // Check if localization or business hours were updated
    const changedKeys = event.detail || [];
    const shouldRefresh = changedKeys.some(key => 
        key.startsWith('localization.time_format') || 
        key.startsWith('business.work_start') || 
        key.startsWith('business.work_end')
    );
    
    if (shouldRefresh && calendarInstance) {
        console.log('[calendar] Detected relevant settings change, reinitializing...');
        await initializeCalendar(true); // Force refresh
    }
});

// Listen for SPA navigation to appointments page (settings changed in different page)
document.addEventListener('spa:navigated', async function(event) {
    console.log('[calendar] SPA navigation detected, checking if calendar needs refresh');
    
    const calendarEl = document.getElementById('appointments-inline-calendar');
    if (calendarEl) {
        // We're on the appointments page, reinitialize to get latest settings
        console.log('[calendar] On appointments page, reinitializing with latest settings...');
        await initializeCalendar(true); // Force refresh to pick up any settings changes
    }
});

// Re-initialize calendar when visibility changes or page becomes visible
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        // Small delay to ensure DOM is ready
        setTimeout(() => {
            const calendarEl = document.getElementById('appointments-inline-calendar');
            if (calendarEl && !calendarInstance) {
                initializeCalendar();
            }
        }, 100);
    }
});

// Re-initialize calendar when window gains focus
window.addEventListener('focus', function() {
    setTimeout(() => {
        const calendarEl = document.getElementById('appointments-inline-calendar');
        if (calendarEl && !calendarInstance) {
            initializeCalendar();
        }
    }, 100);
});

// Expose initialization function globally for SPA compatibility
window.reinitializeCalendar = initializeCalendar;

console.log('Charts initialized');
