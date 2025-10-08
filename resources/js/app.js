// (CoreUI components are no longer used for the sidebar. Keep charts init only.)

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';

// Import charts functionality
import Charts from './charts.js';

// Store calendar instance globally to allow re-initialization
let calendarInstance = null;

// Calendar initialization function
function initializeCalendar() {
    const calendarEl = document.getElementById('appointments-inline-calendar');
    const titleEl = document.getElementById('appointments-inline-calendar-title');

    // Skip if element doesn't exist or calendar already initialized
    if (!calendarEl) {
        return;
    }

    // Destroy existing instance if it exists
    if (calendarInstance) {
        calendarInstance.destroy();
        calendarInstance = null;
    }

    const today = new Date();
    const initialDateAttr = calendarEl.dataset.initialDate;
    const initialDate = initialDateAttr ? new Date(initialDateAttr) : undefined;

    calendarInstance = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timeGridPlugin],
        initialView: 'dayGridMonth',
        height: 'auto',
        nowIndicator: true,
        selectable: false,
        dayMaxEvents: true,
        headerToolbar: false,
        views: {
            dayGridMonth: { buttonText: 'Month' },
            timeGridWeek: { buttonText: 'Week' },
            timeGridDay: { buttonText: 'Day' }
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

    document.addEventListener('click', handleCalendarAction);

    console.log('Calendar initialized successfully');
}

// Initialize charts and dashboard widgets when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Charts !== 'undefined') {
        Charts.initAllCharts();
    }

    initializeCalendar();
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
