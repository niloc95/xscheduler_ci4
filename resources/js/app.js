// (CoreUI components are no longer used for the sidebar. Keep charts init only.)

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';

// Import charts functionality
import Charts from './charts.js';

// Initialize charts and dashboard widgets when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Charts !== 'undefined') {
        Charts.initAllCharts();
    }

    const calendarEl = document.getElementById('appointments-inline-calendar');
    const titleEl = document.getElementById('appointments-inline-calendar-title');

    if (calendarEl) {
        const today = new Date();
        const initialDateAttr = calendarEl.dataset.initialDate;
        const initialDate = initialDateAttr ? new Date(initialDateAttr) : undefined;

        const calendar = new Calendar(calendarEl, {
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
                titleEl.textContent = calendar.view.title;
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

        calendar.on('datesSet', () => {
            updateCalendarTitle();
            setActiveButton(calendar.view.type);
        });

        calendar.render();
        updateCalendarTitle();
        setActiveButton(calendar.view.type);

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

            switch (action) {
                case 'prev':
                    calendar.prev();
                    break;
                case 'next':
                    calendar.next();
                    break;
                case 'today':
                    calendar.today();
                    break;
                case 'day':
                    calendar.changeView('timeGridDay');
                    calendar.today();
                    break;
                case 'week':
                    calendar.changeView('timeGridWeek');
                    break;
                case 'month':
                    calendar.changeView('dayGridMonth');
                    break;
                case 'all':
                    calendar.changeView('dayGridMonth');
                    break;
            }
        };

        document.addEventListener('click', handleCalendarAction);
        window.addEventListener('beforeunload', () => {
            document.removeEventListener('click', handleCalendarAction);
        });
    }
});

console.log('Charts initialized');
