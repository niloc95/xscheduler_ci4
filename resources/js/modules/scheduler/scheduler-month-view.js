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
        this.selectedDate = null; // Track selected date for daily view
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
        this.currentDate = currentDate;
        
        // Initialize selected date to today if not set
        if (!this.selectedDate) {
            this.selectedDate = DateTime.now().setZone(this.scheduler.options.timezone);
        }
        
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
        
        // Render daily appointments section for the selected day
        this.renderDailySection(data);
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
        const isSelected = this.selectedDate && day.hasSame(this.selectedDate, 'day');
        
        const cellClasses = [
            'scheduler-day-cell',
            'min-h-[100px]',
            'p-2',
            'relative',
            'overflow-hidden',
            'cursor-pointer',
            'hover:bg-gray-50',
            'dark:hover:bg-gray-700/50',
            'transition-colors',
            isToday ? 'today' : '',
            !isCurrentMonth ? 'other-month' : '',
            isPast ? 'past' : '',
            !isWorkingDay ? 'non-working-day' : '',
            isBlocked ? 'bg-red-50 dark:bg-red-900/10' : '',
            isSelected ? 'ring-2 ring-blue-500 ring-inset bg-blue-50 dark:bg-blue-900/20' : ''
        ].filter(Boolean).join(' ');
        
        return `
            <div class="${cellClasses}" data-date="${day.toISODate()}" data-click-create="day" data-select-day="${day.toISODate()}">
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

    /**
     * Render daily appointments section showing provider columns for selected day
     */
    renderDailyAppointments() {
        // For month view, show all appointments for the entire month grouped by provider
        const monthStart = this.currentDate.startOf('month');
        const monthEnd = this.currentDate.endOf('month');
        
        // Get all appointments for the month
        const monthAppointments = this.appointments.filter(apt => 
            apt.startDateTime >= monthStart && apt.startDateTime <= monthEnd
        );
        
        // Group appointments by provider
        const appointmentsByProvider = {};
        this.providers.forEach(provider => {
            appointmentsByProvider[provider.id] = [];
        });
        
        monthAppointments.forEach(apt => {
            if (appointmentsByProvider[apt.providerId]) {
                appointmentsByProvider[apt.providerId].push(apt);
            }
        });
        
        // Filter to only show providers with appointments
        const activeProviders = this.providers.filter(p => 
            appointmentsByProvider[p.id].length > 0
        );
        
        const timeFormat = this.settings?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        
        // Build the HTML
        let html = `
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Monthly Schedule
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            ${this.currentDate.toFormat('MMMM yyyy')}
                        </p>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        ${monthAppointments.length} ${monthAppointments.length === 1 ? 'appointment' : 'appointments'} this month
                    </span>
                </div>
            </div>
        `;
        
        if (activeProviders.length > 0) {
            html += `
                <!-- Provider Columns -->
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${Math.min(activeProviders.length, 3)} gap-4">
            `;
            
            activeProviders.forEach(provider => {
                const providerAppointments = appointmentsByProvider[provider.id] || [];
                const color = provider.color || '#3B82F6';
                
                // Sort appointments by date
                providerAppointments.sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());
                
                html += `
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <!-- Provider Header -->
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                             style="border-left: 4px solid ${color};">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                                     style="background-color: ${color};">
                                    ${provider.name.charAt(0).toUpperCase()}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 dark:text-white truncate">
                                        ${this.escapeHtml(provider.name)}
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        ${providerAppointments.length} ${providerAppointments.length === 1 ? 'appointment' : 'appointments'} this month
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Appointments List -->
                        <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
                `;
                
                if (providerAppointments.length > 0) {
                    // Show up to 10 appointments, then "+N more"
                    const displayedAppointments = providerAppointments.slice(0, 10);
                    
                    displayedAppointments.forEach(apt => {
                        const date = apt.startDateTime.toFormat('MMM d');
                        const time = apt.startDateTime.toFormat(timeFormat);
                        const customerName = apt.name || apt.customerName || apt.title || 'Unknown';
                        const serviceName = apt.serviceName || 'Appointment';
                        
                        const statusColors = {
                            confirmed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                            completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                            cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                            'no-show': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                        };
                        const statusClass = statusColors[apt.status] || statusColors.pending;
                        
                        html += `
                            <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
                                 data-appointment-id="${apt.id}">
                                <div class="flex items-start justify-between gap-2 mb-1">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                            ${date} • ${time}
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full ${statusClass} flex-shrink-0">
                                        ${apt.status}
                                    </span>
                                </div>
                                <h5 class="font-semibold text-sm text-gray-900 dark:text-white mb-1 truncate">
                                    ${this.escapeHtml(customerName)}
                                </h5>
                                <p class="text-xs text-gray-600 dark:text-gray-400 truncate">
                                    ${this.escapeHtml(serviceName)}
                                </p>
                            </div>
                        `;
                    });
                    
                    if (providerAppointments.length > 10) {
                        html += `
                            <div class="p-3 text-center text-sm text-gray-500 dark:text-gray-400 font-medium">
                                +${providerAppointments.length - 10} more appointments
                            </div>
                        `;
                    }
                } else {
                    html += `
                        <div class="p-8 text-center">
                            <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-4xl mb-2">event_available</span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No appointments</p>
                        </div>
                    `;
                }
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        } else {
            html += `
                <!-- Empty State -->
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                    <p class="text-sm text-gray-600 dark:text-gray-400">No appointments scheduled for ${this.currentDate.toFormat('MMMM yyyy')}</p>
                </div>
            `;
        }
        
        return html;
    }
    
    /**
     * Render daily section to the separate container outside the calendar
     */
    renderDailySection(data) {
        const dailyContainer = document.getElementById('daily-provider-appointments');
        if (!dailyContainer) {
            console.log('[MonthView] Daily provider appointments container not found');
            return;
        }
        
        console.log('[MonthView] Rendering daily section to separate container');
        dailyContainer.innerHTML = this.renderDailyAppointments();
        
        // Attach event listeners to the daily section
        this.attachDailySectionListeners(dailyContainer, data);
    }
    
    /**
     * Attach event listeners to daily section content
     */
    attachDailySectionListeners(container, data) {
        if (!container) return;
        
        // Use passed data or fallback to instance data
        const appointments = data?.appointments || this.appointments;
        const onAppointmentClick = data?.onAppointmentClick || this.scheduler.handleAppointmentClick.bind(this.scheduler);
        
        // Action buttons (view/edit)
        container.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const action = btn.dataset.action;
                const aptId = parseInt(btn.dataset.appointmentId, 10);
                const appointment = appointments.find(a => a.id === aptId);
                
                if (appointment && (action === 'view' || action === 'edit')) {
                    onAppointmentClick(appointment);
                }
            });
        });
        
        // Appointment cards (entire card clickable)
        container.querySelectorAll('[data-appointment-id]:not([data-action])').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('[data-action]')) return;
                
                const aptId = parseInt(card.dataset.appointmentId, 10);
                const appointment = appointments.find(a => a.id === aptId);
                
                if (appointment) {
                    onAppointmentClick(appointment);
                }
            });
        });
    }

    attachEventListeners(container, data) {
        // Appointment click handlers (in calendar grid)
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
                // Update selected date and refresh daily section
                this.selectedDate = DateTime.fromISO(date, { zone: this.scheduler.options.timezone });
                this.updateDailySection(container);
                console.log('Show more appointments for', date);
            });
        });

        // Day cell click handlers (for day selection and creating new appointments)
        container.querySelectorAll('[data-select-day]').forEach(el => {
            el.addEventListener('click', (e) => {
                // Check if click was on an appointment or action button
                if (e.target.closest('.scheduler-appointment') || 
                    e.target.closest('[data-action]') ||
                    e.target.closest('[data-show-more]')) {
                    return; // Let those handlers deal with it
                }
                
                const date = el.dataset.selectDay;
                console.log('Day cell clicked:', date);
                
                // Update selected date
                this.selectedDate = DateTime.fromISO(date, { zone: this.scheduler.options.timezone });
                
                // Update visual selection in calendar grid
                container.querySelectorAll('.scheduler-day-cell').forEach(cell => {
                    cell.classList.remove('ring-2', 'ring-blue-500', 'ring-inset', 'bg-blue-50', 'dark:bg-blue-900/20');
                });
                el.classList.add('ring-2', 'ring-blue-500', 'ring-inset', 'bg-blue-50', 'dark:bg-blue-900/20');
                
                // Update daily appointments section
                this.updateDailySection(container);
                
                // Also support creating new appointment with double-click
                if (e.detail === 2) {
                    this.scheduler.openCreateModal({ date });
                }
            });
        });
    }
    
    /**
     * Update just the daily appointments section without re-rendering entire calendar
     */
    updateDailySection(container) {
        // Update the daily section outside the calendar (if it exists)
        const data = {
            appointments: this.appointments,
            onAppointmentClick: this.scheduler.handleAppointmentClick.bind(this.scheduler)
        };
        this.renderDailySection(data);
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
