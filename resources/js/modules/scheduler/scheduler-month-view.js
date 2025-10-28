/**
 * Custom Scheduler - Month View
 * 
 * Renders a traditional month calendar grid with appointments.
 * Displays appointments as colored blocks within date cells.
 */

import { DateTime } from 'luxon';

export class MonthView {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.appointmentsByDate = {};
    }
    
    render(container, data) {
        const { currentDate, appointments, providers, config, settings } = data;
        
        console.log('🗓️ MonthView.render called');
        console.log('   Current date:', currentDate.toISO());
        console.log('   Appointments received:', appointments.length);
        console.log('   Appointments data:', appointments);
        console.log('   Providers:', providers.length);
        
        // Store data for use in other methods
        this.appointments = appointments;
        this.providers = providers;
        this.settings = settings;
        this.blockedPeriods = config?.blockedPeriods || [];
        
        // Get calendar grid data
        const monthStart = currentDate.startOf('month');
        const monthEnd = currentDate.endOf('month');
        
        // Use first day of week from settings
        const firstDayOfWeek = settings?.getFirstDayOfWeek() || 0;
        const gridStart = monthStart.startOf('week').minus({ days: (7 - firstDayOfWeek) % 7 });
        const gridEnd = monthEnd.endOf('week').minus({ days: (7 - firstDayOfWeek) % 7 });
        
        // Generate weeks
        const weeks = [];
        let current = gridStart;
        
        while (current <= gridEnd) {
            const week = [];
            for (let i = 0; i < 7; i++) {
                week.push(current);
                current = current.plus({ days: 1 });
            }
            weeks.push(week);
        }

        // Group appointments by date
        this.appointmentsByDate = this.groupAppointmentsByDate(appointments);
        console.log('📅 Appointments grouped by date:', this.appointmentsByDate);

        // Render HTML
        container.innerHTML = `
            <div class="scheduler-month-view bg-white dark:bg-gray-800">
                <!-- Month Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        ${currentDate.toFormat('MMMM yyyy')}
                    </h2>
                </div>

                <!-- Day Headers -->
                <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
                    ${this.renderDayHeaders(config, settings)}
                </div>

                <!-- Calendar Grid -->
                <div class="grid grid-cols-7 auto-rows-fr divide-x divide-y divide-gray-200 dark:divide-gray-700">
                    ${weeks.map(week => week.map(day => 
                        this.renderDayCell(day, monthStart.month, settings)
                    ).join('')).join('')}
                </div>
                
                ${appointments.length === 0 ? `
                <div class="px-6 py-8 text-center border-t border-gray-200 dark:border-gray-700">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Appointments</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Click on any day to create a new appointment
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">
                        💡 Backend API endpoints need to be implemented to load and save appointments
                    </p>
                </div>
                ` : ''}
            </div>
        `;

        // Add event listeners
        this.attachEventListeners(container, data);
    }

    renderDayHeaders(config, settings) {
        const firstDay = settings?.getFirstDayOfWeek() || config?.firstDayOfWeek || 0;
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        // Rotate array to start with firstDay
        const rotatedDays = [...days.slice(firstDay), ...days.slice(0, firstDay)];
        
        return rotatedDays.map(day => `
            <div class="px-4 py-3 text-center">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">${day}</span>
            </div>
        `).join('');
    }

    renderDayCell(day, currentMonth, settings) {
        const isToday = day.hasSame(DateTime.now(), 'day');
        const isCurrentMonth = day.month === currentMonth;
        const isPast = day < DateTime.now().startOf('day');
        const dayAppointments = this.getAppointmentsForDay(day);
        const isWorkingDay = settings?.isWorkingDay ? settings.isWorkingDay(day) : true;
        const isBlocked = this.isDateBlocked(day);
        const blockedInfo = isBlocked ? this.getBlockedPeriodInfo(day) : null;
        
        const cellClasses = [
            'scheduler-day-cell',
            'min-h-[100px]',
            'p-2',
            'relative',
            'overflow-hidden',
            isToday ? 'today' : '',
            !isCurrentMonth ? 'other-month' : '',
            isPast ? 'past' : '',
            !isWorkingDay ? 'non-working-day' : '',
            isBlocked ? 'bg-red-50 dark:bg-red-900/10' : ''
        ].filter(Boolean).join(' ');
        
        return `
            <div class="${cellClasses}" data-date="${day.toISODate()}" data-click-create="day">
                <div class="day-number text-sm font-medium mb-1 ${isCurrentMonth ? isBlocked ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-600'}">
                    ${day.day}
                    ${isBlocked ? '<span class="text-xs ml-1">🚫</span>' : ''}
                </div>
                ${isBlocked && blockedInfo ? `
                    <div class="text-[10px] text-red-600 dark:text-red-400 font-medium mb-1 truncate" title="${this.escapeHtml(blockedInfo.notes || 'Blocked')}">
                        ${this.escapeHtml(blockedInfo.notes || 'Blocked')}
                    </div>
                ` : ''}
                <div class="day-appointments space-y-1">
                    ${dayAppointments.slice(0, 3).map(apt => this.renderAppointmentBlock(apt)).join('')}
                    ${dayAppointments.length > 3 ? `<div class="text-xs text-gray-500 dark:text-gray-400 font-medium cursor-pointer hover:text-blue-600" data-show-more="${day.toISODate()}">+${dayAppointments.length - 3} more</div>` : ''}
                </div>
            </div>
        `;
    }

    renderAppointmentBlock(appointment) {
        const provider = this.providers.find(p => p.id === appointment.providerId);
        const color = provider?.color || '#3B82F6';
        const textColor = this.getContrastColor(color);
        
        // Use settings to format time
        const time = this.settings?.formatTime ? this.settings.formatTime(appointment.startDateTime) : appointment.startDateTime.toFormat('h:mm a');
        const title = appointment.title || appointment.customerName || 'Appointment';

        return `
            <div class="scheduler-appointment text-xs px-2 py-1 rounded cursor-pointer hover:opacity-80 transition-opacity truncate"
                 style="background-color: ${color}; color: ${textColor};"
                 data-appointment-id="${appointment.id}"
                 title="${title} at ${time}">
                <span class="font-medium">${time}</span> ${this.escapeHtml(title)}
            </div>
        `;
    }
    
    getAppointmentsForDay(day) {
        const dateKey = day.toISODate();
        return this.appointmentsByDate[dateKey] || [];
    }

    groupAppointmentsByDate(appointments) {
        const grouped = {};
        
        appointments.forEach(apt => {
            const dateKey = apt.startDateTime.toISODate();
            if (!grouped[dateKey]) {
                grouped[dateKey] = [];
            }
            grouped[dateKey].push(apt);
        });

        // Sort appointments by start time within each day
        Object.keys(grouped).forEach(dateKey => {
            grouped[dateKey].sort((a, b) => 
                a.startDateTime.toMillis() - b.startDateTime.toMillis()
            );
        });

        return grouped;
    }

    attachEventListeners(container, data) {
        // Appointment click handlers
        container.querySelectorAll('.scheduler-appointment').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('[MonthView] Appointment clicked, prevented default');
                const aptId = parseInt(el.dataset.appointmentId, 10);
                const appointment = data.appointments.find(a => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    console.log('[MonthView] Calling onAppointmentClick');
                    data.onAppointmentClick(appointment);
                } else {
                    console.warn('[MonthView] No appointment found or no callback');
                }
            });
        });

        // "Show more" handlers
        container.querySelectorAll('[data-show-more]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const date = el.dataset.showMore;
                // TODO: Open modal or expand to show all appointments for this date
                console.log('Show more appointments for', date);
            });
        });

        // Day cell click handlers (for creating new appointments)
        container.querySelectorAll('[data-date]').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target === el || e.target.closest('.flex')) {
                    const date = el.dataset.date;
                    console.log('Day clicked:', date);
                    this.scheduler.openCreateModal({ date });
                }
            });
        });
    }

    getContrastColor(hexColor) {
        // Convert hex to RGB
        const hex = hexColor.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        
        // Calculate luminance
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        
        return luminance > 0.5 ? '#000000' : '#FFFFFF';
    }

    /**
     * Check if a date falls within a blocked period
     */
    isDateBlocked(date) {
        if (!this.blockedPeriods || this.blockedPeriods.length === 0) return false;
        
        const checkDate = date.toISODate();
        
        return this.blockedPeriods.some(period => {
            const start = period.start;
            const end = period.end;
            return checkDate >= start && checkDate <= end;
        });
    }

    /**
     * Get blocked period information for a date
     */
    getBlockedPeriodInfo(date) {
        if (!this.blockedPeriods || this.blockedPeriods.length === 0) return null;
        
        const checkDate = date.toISODate();
        
        const period = this.blockedPeriods.find(p => {
            return checkDate >= p.start && checkDate <= p.end;
        });
        
        return period || null;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
