// (CoreUI components are no longer used for the sidebar. Keep charts init only.)

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import esLocale from '@fullcalendar/core/locales/es';
import ptBrLocale from '@fullcalendar/core/locales/pt-br';

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
    const titleEl = document.getElementById('appointments-inline-calendar-title');

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

    const today = new Date();
    const initialDateAttr = calendarEl.dataset.initialDate;
    const initialDate = initialDateAttr ? new Date(initialDateAttr) : undefined;

    // Configure time format settings
    const hour12 = calendarSettings.timeFormat === '12h';
    const hourFormat = hour12 ? 'numeric' : '2-digit';
    const meridiem = hour12 ? 'short' : false;

    console.log('[calendar] Applying time format configuration:', {
        timeFormat: calendarSettings.timeFormat,
        hour12,
        hourFormat,
        meridiem,
        workStart: calendarSettings.workStart,
        workEnd: calendarSettings.workEnd
    });

    calendarInstance = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timeGridPlugin],
        initialView: 'dayGridMonth',
        height: 'auto',
        nowIndicator: true,
        selectable: false,
        dayMaxEvents: true,
        headerToolbar: false,
        locales: [esLocale, ptBrLocale],
        locale: calendarSettings.locale,
        firstDay: calendarSettings.firstDay,
        timeZone: calendarSettings.timezone,
        
        // Time slot configuration for Day/Week views
        slotMinTime: calendarSettings.workStart,
        slotMaxTime: calendarSettings.workEnd,
        slotDuration: '00:30:00',
        slotLabelInterval: '01:00:00',
        slotLabelFormat: {
            hour: hourFormat,
            minute: '2-digit',
            omitZeroMinute: false,
            meridiem: meridiem,
            hour12: hour12
        },
        
        // Event time format
        eventTimeFormat: {
            hour: hourFormat,
            minute: '2-digit',
            meridiem: meridiem,
            hour12: hour12
        },
        
        views: {
            dayGridMonth: { buttonText: 'Month' },
            timeGridWeek: { 
                buttonText: 'Week',
                slotLabelFormat: {
                    hour: hourFormat,
                    minute: '2-digit',
                    omitZeroMinute: false,
                    meridiem: meridiem,
                    hour12: hour12
                }
            },
            timeGridDay: { 
                buttonText: 'Day',
                slotLabelFormat: {
                    hour: hourFormat,
                    minute: '2-digit',
                    omitZeroMinute: false,
                    meridiem: meridiem,
                    hour12: hour12
                }
            }
        },
        initialDate: initialDate instanceof Date && !Number.isNaN(initialDate.valueOf()) ? initialDate : undefined,
        dayCellDidMount: (arg) => {
            const dayNumberEl = arg.el.querySelector('.fc-daygrid-day-number');
            if (!dayNumberEl) {
                return;
            }

            dayNumberEl.classList.remove('bg-blue-600', 'text-white', 'dark:bg-blue-500', 'dark:text-white');
            dayNumberEl.classList.add('font-medium', 'text-gray-700', 'dark:text-gray-200');

            if (arg.date.toDateString() === today.toDateString()) {
                dayNumberEl.classList.remove('text-gray-700', 'dark:text-gray-200');
                dayNumberEl.classList.add('bg-blue-600', 'text-white', 'dark:bg-blue-500', 'dark:text-white');
            }
        },
    });

    const updateCalendarTitle = () => {
        if (titleEl) {
            titleEl.textContent = calendarInstance.view.title;
        }
    };

    const setActiveButton = (viewType) => {
        // Map view types to button actions
        const viewToActionMap = {
            'dayGridMonth': 'month',
            'timeGridWeek': 'week',
            'timeGridDay': 'day'
        };
        
        const actionName = viewToActionMap[viewType] || 'month';
        
        document.querySelectorAll('[data-calendar-action]').forEach(btn => {
            const action = btn.getAttribute('data-calendar-action');
            if (['day', 'week', 'month', 'all'].includes(action)) {
                btn.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
                btn.classList.add('bg-gray-100', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            }
        });
        
        const activeBtn = document.querySelector(`[data-calendar-action="${actionName}"]`);
        if (activeBtn) {
            activeBtn.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            activeBtn.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
        }
    };

    calendarInstance.on('datesSet', () => {
        updateCalendarTitle();
        setActiveButton(calendarInstance.view.type);
    });

    calendarInstance.render();
    updateCalendarTitle();
    setActiveButton(calendarInstance.view.type);

    const handleCalendarAction = (event) => {
        const actionTarget = event.target.closest('[data-calendar-action]');
        if (!actionTarget) {
            return;
        }

        const action = actionTarget.getAttribute('data-calendar-action');
        if (!action) {
            return;
        }

        event.preventDefault();

        if (!calendarInstance) {
            return;
        }

        switch (action) {
            case 'prev':
                calendarInstance.prev();
                break;
            case 'next':
                calendarInstance.next();
                break;
            case 'today':
                calendarInstance.today();
                break;
            case 'day':
                calendarInstance.changeView('timeGridDay');
                calendarInstance.today();
                break;
            case 'week':
                calendarInstance.changeView('timeGridWeek');
                break;
            case 'month':
                calendarInstance.changeView('dayGridMonth');
                break;
            case 'all':
                calendarInstance.changeView('dayGridMonth');
                break;
        }
    };

    if (!initializeCalendar._actionHandlerBound) {
        document.addEventListener('click', handleCalendarAction);
        initializeCalendar._actionHandlerBound = true;
    }

    console.log('Calendar initialized successfully');
}

// Initialize charts and dashboard widgets when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Charts !== 'undefined') {
        Charts.initAllCharts();
    }

    initializeCalendar();
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
