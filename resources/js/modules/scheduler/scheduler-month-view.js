/**
 * Custom Scheduler - Month View
 * 
 * Renders a traditional month calendar grid with appointments.
 * Displays appointments as colored blocks within date cells.
 */

import { DateTime } from 'luxon';
import { getStatusColors, getProviderColor, getProviderDotHtml, isDarkMode } from './appointment-colors.js';

export class MonthView {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.appointmentsByDate = {};
        this.selectedDate = null; // Track selected date for daily view
    }

    debugLog(...args) {
        if (this.scheduler?.debugLog) {
            this.scheduler.debugLog(...args);
            return;
        }

        if (typeof window !== 'undefined' && window.appConfig?.debug) {
            console.log(...args);
        }
    }
    
    render(container, data) {
        const { currentDate, appointments, providers, config, settings } = data;
        
        this.debugLog('🗓️ MonthView.render called');
        this.debugLog('   Current date:', currentDate.toISO());
        this.debugLog('   Current date zone:', currentDate.zoneName);
        this.debugLog('   Appointments received:', appointments.length);
        if (appointments.length > 0) {
            this.debugLog('   First appointment:', {
                id: appointments[0].id,
                start: appointments[0].start,
                startDateTime: appointments[0].startDateTime?.toISO?.(),
                startDateTimeZone: appointments[0].startDateTime?.zoneName,
                dateKey: appointments[0].startDateTime?.toISODate?.()
            });
        }
        this.debugLog('   Providers:', providers.length);
        
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
        
        // Use first day of week from settings (0=Sunday, 1=Monday, etc.)
        const firstDayOfWeek = settings?.getFirstDayOfWeek?.() || 0;
        
        // Calculate grid start: find the first day of the week containing month start
        // Luxon weekday: 1=Mon, 7=Sun. We need to convert our firstDayOfWeek (0=Sun) to Luxon format
        const luxonFirstDay = firstDayOfWeek === 0 ? 7 : firstDayOfWeek;
        const monthStartWeekday = monthStart.weekday; // 1-7 (Mon-Sun)
        
        // Calculate days to go back to reach the first day of the week
        let daysBack = monthStartWeekday - luxonFirstDay;
        if (daysBack < 0) daysBack += 7;
        
        const gridStart = monthStart.minus({ days: daysBack });
        
        // Calculate grid end: find the last day of the week containing month end
        const monthEndWeekday = monthEnd.weekday;
        const lastDayOfWeek = luxonFirstDay === 1 ? 7 : luxonFirstDay - 1; // Last day is one before first
        let daysForward = (lastDayOfWeek === 7 ? 7 : lastDayOfWeek) - monthEndWeekday;
        if (daysForward < 0) daysForward += 7;
        if (daysForward === 0 && monthEndWeekday !== (luxonFirstDay === 1 ? 7 : luxonFirstDay - 1)) {
            // We're not on the last day of week, so we need to complete the week
        }
        // Simpler approach: just complete 6 weeks from grid start
        const gridEnd = gridStart.plus({ days: 41 }); // 6 weeks - 1 day
        
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
        this.debugLog('📅 Appointments grouped by date:', this.appointmentsByDate);

        // Render HTML
        container.innerHTML = `
            <div class="scheduler-month-view bg-white dark:bg-gray-800">
                <!-- Day Headers -->
                <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
                    ${this.renderDayHeaders(config, settings)}
                </div>

                <!-- Calendar Grid - using grid-rows-6 for consistent row sizing -->
                <div class="grid grid-cols-7 grid-rows-6 divide-x divide-y divide-gray-200 dark:divide-gray-700 border-b border-gray-200 dark:border-gray-700">
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
            'h-full',  // Ensure cell fills grid row height
            'p-2',
            'relative',
            'overflow-hidden',
            'cursor-pointer',
            'hover:bg-gray-50',
            'dark:hover:bg-gray-700/50',
            'transition-colors',
            'bg-white',
            'dark:bg-gray-800',
            isToday ? 'today' : '',
            !isCurrentMonth ? 'other-month bg-gray-50 dark:bg-gray-800/50' : '',
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
                <div class="day-appointments space-y-0.5">
                    ${dayAppointments.slice(0, 2).map(apt => this.renderAppointmentBlock(apt)).join('')}
                    ${dayAppointments.slice(2).map(apt => this.renderAppointmentBlock(apt, true)).join('')}
                    ${dayAppointments.length > 2 ? `
                        <button type="button" class="expand-day-btn w-full text-xs text-blue-600 dark:text-blue-400 font-medium cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded px-1 py-0.5 flex items-center justify-center gap-1 transition-colors" 
                             data-expand-day="${day.toISODate()}"
                             data-expanded="false"
                             title="Click to view all ${dayAppointments.length} appointments">
                            <svg class="expand-icon w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12" />
                            </svg>
                            <span class="expand-text">+${dayAppointments.length - 2} more</span>
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }

    renderAppointmentBlock(appointment, isHidden = false) {
        const provider = this.providers.find(p => p.id === appointment.providerId);
        const darkMode = isDarkMode();
        const statusColors = getStatusColors(appointment.status, darkMode);
        const providerColor = getProviderColor(provider);
        
        // Use settings to format time - shorter format for month view
        const is24Hour = this.settings?.getTimeFormat?.() === '24h';
        const time = appointment.startDateTime.toFormat(is24Hour ? 'HH:mm' : 'h:mm');
        const ampm = is24Hour ? '' : appointment.startDateTime.toFormat('a').toLowerCase();
        const title = appointment.title || appointment.customerName || 'Appointment';
        const hiddenClass = isHidden ? 'hidden' : '';

        return `
            <div class="scheduler-appointment text-[11px] px-1.5 py-0.5 rounded cursor-pointer hover:shadow-sm transition-all truncate border-l-2 flex items-center gap-1 ${hiddenClass}"
                 style="background-color: ${statusColors.bg}; border-left-color: ${statusColors.border}; color: ${statusColors.text};"
                 data-appointment-id="${appointment.id}"
                 title="${title} at ${time}${ampm} - ${appointment.status}">
                <span class="inline-block w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: ${providerColor};"></span>
                <span class="font-medium">${time}</span>
                <span class="truncate opacity-80">${this.escapeHtml(title)}</span>
            </div>
        `;
    }
    
    getAppointmentsForDay(day) {
        const dateKey = day.toISODate();
        const appointments = this.appointmentsByDate[dateKey] || [];
        if (appointments.length > 0) {
            this.debugLog(`📅 Day ${dateKey}: ${appointments.length} appointments found`);
        }
        return appointments;
    }

    groupAppointmentsByDate(appointments) {
        const grouped = {};
        
        this.debugLog(`📊 Grouping ${appointments.length} appointments by date`);
        
        appointments.forEach((apt, idx) => {
            if (!apt.startDateTime || typeof apt.startDateTime.toISODate !== 'function') {
                console.error(`❌ Appointment ${apt.id} has invalid startDateTime:`, apt.startDateTime);
                return;
            }
            const dateKey = apt.startDateTime.toISODate();
            if (idx < 3) {
                this.debugLog(`   Apt ${apt.id}: startDateTime=${apt.startDateTime.toISO()}, dateKey=${dateKey}`);
            }
            if (!grouped[dateKey]) {
                grouped[dateKey] = [];
            }
            grouped[dateKey].push(apt);
        });
        
        this.debugLog(`📅 Grouped into ${Object.keys(grouped).length} unique dates:`, Object.keys(grouped).slice(0, 10));

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
                        <!-- Provider Header - matching Week View -->
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                             style="border-left: 4px solid ${color};">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold"
                                     style="background-color: ${color};">
                                    ${provider.name.charAt(0).toUpperCase()}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                                        ${this.escapeHtml(provider.name)}
                                    </h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        ${providerAppointments.length} ${providerAppointments.length === 1 ? 'appointment' : 'appointments'} this month
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Appointments List -->
                        <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
                `;
                
                if (providerAppointments.length > 0) {
                    // Show all appointments, but hide those beyond first 10
                    const maxVisible = 10;
                    
                    providerAppointments.forEach((apt, index) => {
                        const date = apt.startDateTime.toFormat('MMM d');
                        const time = apt.startDateTime.toFormat(timeFormat);
                        const customerName = apt.name || apt.customerName || apt.title || 'Unknown';
                        const serviceName = apt.serviceName || 'Appointment';
                        
                        const darkMode = isDarkMode();
                        const statusColors = getStatusColors(apt.status, darkMode);
                        const providerColor = getProviderColor(provider);
                        
                        // Hide appointments beyond maxVisible
                        const hiddenClass = index >= maxVisible ? 'hidden' : '';
                        
                        html += `
                            <div class="appointment-item p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer border-l-4 ${hiddenClass}"
                                 style="border-left-color: ${statusColors.border}; background-color: ${statusColors.bg}; color: ${statusColors.text};"
                                 data-appointment-id="${apt.id}">
                                <div class="flex items-start justify-between gap-2 mb-1">
                                    <div class="flex-1 min-w-0 flex items-center gap-2">
                                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${providerColor};"></span>
                                        <div class="text-xs font-medium">
                                            ${date} • ${time}
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full flex-shrink-0"
                                          style="background-color: ${statusColors.dot}; color: white;">
                                        ${apt.status}
                                    </span>
                                </div>
                                <h5 class="font-semibold text-sm mb-1 truncate">
                                    ${this.escapeHtml(customerName)}
                                </h5>
                                <p class="text-xs opacity-80 truncate">
                                    ${this.escapeHtml(serviceName)}
                                </p>
                            </div>
                        `;
                    });
                    
                    if (providerAppointments.length > maxVisible) {
                        const remaining = providerAppointments.length - maxVisible;
                        html += `
                            <div class="p-3 text-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors border-t border-gray-200 dark:border-gray-700"
                                 data-expand-provider="${provider.id}"
                                 data-expanded="false">
                                <div class="flex items-center justify-center gap-2 text-sm text-blue-600 dark:text-blue-400 font-medium">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12" />
                                    </svg>
                                    <span>+${remaining} more</span>
                                </div>
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
            this.debugLog('[MonthView] Daily provider appointments container not found');
            return;
        }
        
        this.debugLog('[MonthView] Rendering daily section to separate container');
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
                if (e.target.closest('[data-expand-provider]')) return;
                
                const aptId = parseInt(card.dataset.appointmentId, 10);
                const appointment = appointments.find(a => a.id === aptId);
                
                if (appointment) {
                    onAppointmentClick(appointment);
                }
            });
        });
        
        // "Expand provider" handlers for monthly schedule panel
        container.querySelectorAll('[data-expand-provider]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const providerId = parseInt(el.dataset.expandProvider, 10);
                this.toggleProviderExpansion(providerId, el);
            });
        });
    }

    attachEventListeners(container, data) {
        // Appointment click handlers (in calendar grid)
        container.querySelectorAll('.scheduler-appointment').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.debugLog('[MonthView] Appointment clicked, prevented default');
                const aptId = parseInt(el.dataset.appointmentId, 10);
                const appointment = data.appointments.find(a => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    this.debugLog('[MonthView] Calling onAppointmentClick');
                    data.onAppointmentClick(appointment);
                } else {
                    console.warn('[MonthView] No appointment found or no callback');
                }
            });
        });

        // "Expand day" handlers for calendar grid - toggle hidden appointments
        container.querySelectorAll('[data-expand-day]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleDayExpansion(el);
            });
        });

        // "Expand provider" handlers for monthly schedule panel
        container.querySelectorAll('[data-expand-provider]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const providerId = parseInt(el.dataset.expandProvider, 10);
                this.toggleProviderExpansion(providerId, el);
            });
        });

        // Day cell click handlers (for day selection and creating new appointments)
        container.querySelectorAll('[data-select-day]').forEach(el => {
            el.addEventListener('click', (e) => {
                // Check if click was on an appointment or action button
                if (e.target.closest('.scheduler-appointment') || 
                    e.target.closest('[data-action]') ||
                    e.target.closest('[data-expand-day]')) {
                    return; // Let those handlers deal with it
                }
                
                const date = el.dataset.selectDay;
                this.debugLog('Day cell clicked:', date);
                
                // Update selected date
                this.selectedDate = DateTime.fromISO(date, { zone: this.scheduler.options.timezone });
                
                // Update visual selection in calendar grid
                container.querySelectorAll('.scheduler-day-cell').forEach(cell => {
                    cell.classList.remove('ring-2', 'ring-blue-500', 'ring-inset', 'bg-blue-50', 'dark:bg-blue-900/20');
                });
                el.classList.add('ring-2', 'ring-blue-500', 'ring-inset', 'bg-blue-50', 'dark:bg-blue-900/20');
                
                // Update daily appointments section
                this.updateDailySection(container);
                
                // Removed: Double-click to create appointment modal
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

    /**
     * Toggle expansion of provider appointments in monthly schedule
     */
    toggleProviderExpansion(providerId, buttonEl) {
        const card = buttonEl.closest('.bg-white, [class*="dark:bg-gray-800"]');
        if (!card) return;
        
        const appointmentsList = card.querySelector('.divide-y');
        if (!appointmentsList) return;
        
        const maxVisible = 10;
        const isExpanded = buttonEl.dataset.expanded === 'true';
        const allAppointments = appointmentsList.querySelectorAll('.appointment-item');
        
        if (isExpanded) {
            // Collapse - hide items beyond first 10
            allAppointments.forEach((item, index) => {
                if (index >= maxVisible) {
                    item.classList.add('hidden');
                }
            });
            buttonEl.dataset.expanded = 'false';
            const icon = buttonEl.querySelector('svg');
            if (icon) {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12" />';
            }
            const textSpan = buttonEl.querySelector('span');
            if (textSpan) {
                const totalHidden = Math.max(0, allAppointments.length - maxVisible);
                textSpan.textContent = `+${totalHidden} more`;
            }
        } else {
            // Expand - show all items
            allAppointments.forEach(item => {
                item.classList.remove('hidden');
            });
            buttonEl.dataset.expanded = 'true';
            const icon = buttonEl.querySelector('svg');
            if (icon) {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h12" />';
            }
            const textSpan = buttonEl.querySelector('span');
            if (textSpan) {
                textSpan.textContent = 'Show less';
            }
        }
    }

    /**
     * Toggle expansion of appointments in a calendar day cell
     */
    toggleDayExpansion(buttonEl) {
        const dayCell = buttonEl.closest('.scheduler-day-cell');
        if (!dayCell) {
            console.warn('[MonthView] toggleDayExpansion: dayCell not found');
            return;
        }
        
        const appointmentsContainer = dayCell.querySelector('.day-appointments');
        if (!appointmentsContainer) {
            console.warn('[MonthView] toggleDayExpansion: appointmentsContainer not found');
            return;
        }
        
        const maxVisible = 2; // Show only 2 appointments by default
        const isExpanded = buttonEl.dataset.expanded === 'true';
        const allAppointments = appointmentsContainer.querySelectorAll('.scheduler-appointment');
        
        this.debugLog('[MonthView] toggleDayExpansion:', { isExpanded, totalAppointments: allAppointments.length });
        
        if (isExpanded) {
            // Collapse - hide appointments beyond first 2
            allAppointments.forEach((item, index) => {
                if (index >= maxVisible) {
                    item.classList.add('hidden');
                }
            });
            buttonEl.dataset.expanded = 'false';
            const icon = buttonEl.querySelector('.expand-icon');
            if (icon) {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12" />';
            }
            const textSpan = buttonEl.querySelector('.expand-text');
            if (textSpan) {
                const totalHidden = Math.max(0, allAppointments.length - maxVisible);
                textSpan.textContent = `+${totalHidden} more`;
            }
        } else {
            // Expand - show all appointments
            allAppointments.forEach(item => {
                item.classList.remove('hidden');
            });
            buttonEl.dataset.expanded = 'true';
            const icon = buttonEl.querySelector('.expand-icon');
            if (icon) {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />';
            }
            const textSpan = buttonEl.querySelector('.expand-text');
            if (textSpan) {
                textSpan.textContent = 'Show less';
            }
        }
    }
}
