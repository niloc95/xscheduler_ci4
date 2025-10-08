// (CoreUI components are no longer used for the sidebar. Keep charts init only.)

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';

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
            plugins: [dayGridPlugin],
            initialView: 'dayGridMonth',
            height: 'auto',
            nowIndicator: true,
            selectable: false,
            dayMaxEvents: true,
            headerToolbar: false,
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

        calendar.on('datesSet', updateCalendarTitle);

        calendar.render();
        updateCalendarTitle();

        const handleCalendarAction = (event) => {
            const actionTarget = event.target.closest('[data-calendar-action]');
            if (!actionTarget) {
                return;
            }

            const action = actionTarget.getAttribute('data-calendar-action');
            if (!action) {
                return;
            }

            if (action === 'prev') {
                event.preventDefault();
                calendar.prev();
            } else if (action === 'next') {
                event.preventDefault();
                calendar.next();
            } else if (action === 'today') {
                event.preventDefault();
                calendar.today();
            }
        };

        document.addEventListener('click', handleCalendarAction);
        window.addEventListener('beforeunload', () => {
            document.removeEventListener('click', handleCalendarAction);
        });
    }
});

console.log('Charts initialized');
