/**
 * Custom Scheduler - Day View (Refined)
 * 
 * Renders a detailed single-day view with:
 * - Left panel: Appointment list with provider avatars
 * - Right panel: Mini calendar for date navigation
 * 
 * Matches the unified design system of Month and Week views.
 */

import { DateTime } from 'luxon';
import { getStatusColors, getProviderColor, getProviderInitials, getStatusLabel, isDarkMode } from './appointment-colors.js';
import { getBaseUrl, withBaseUrl } from '../../utils/url-helpers.js';

export class DayView {
    constructor(scheduler) {
        this.scheduler = scheduler;
    }

    render(container, data) {
        const { currentDate, appointments, providers, config, settings } = data;
        
        // Store for use in other methods
        this.settings = settings;
        this.currentDate = currentDate;
        this.appointments = appointments;
        this.providers = providers;
        this.config = config;
        this.data = data;
        
        // Check if this date is blocked
        const blockedPeriods = config?.blockedPeriods || [];
        const isBlocked = this.isDateBlocked(currentDate, blockedPeriods);
        const blockedInfo = isBlocked ? this.getBlockedPeriodInfo(currentDate, blockedPeriods) : null;
        
        // Check if this is a non-working day
        const isNonWorkingDay = settings?.isWorkingDay ? !settings.isWorkingDay(currentDate.weekday % 7) : false;

        // Filter appointments for this day and sort by time
        const dayAppointments = appointments.filter(apt => 
            apt.startDateTime.hasSame(currentDate, 'day')
        ).sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());

        // Render the two-panel layout - Right panel first in DOM for proper layout
        container.innerHTML = `
            <div class="scheduler-day-view bg-white dark:bg-gray-800 rounded-lg">
                <div class="flex flex-col-reverse md:flex-row gap-6 p-6">
                    
                    <!-- Left Panel: Appointments List (appears second in mobile, first in desktop) -->
                    <div class="flex-1 min-w-0 order-2 md:order-1">
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                                ${currentDate.toFormat('EEEE')}'s Appointments
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                ${currentDate.toFormat('MMMM d, yyyy')}
                                ${isBlocked ? ' • <span class="text-red-500">🚫 Blocked</span>' : ''}
                                ${isNonWorkingDay && !isBlocked ? ' • <span class="text-gray-500">Non-working day</span>' : ''}
                            </p>
                        </div>
                        
                        ${isBlocked ? `
                            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                                <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                                    <span class="material-symbols-outlined text-lg">block</span>
                                    <span class="text-sm font-medium">${this.escapeHtml(blockedInfo?.notes || 'This day is blocked for appointments')}</span>
                                </div>
                            </div>
                        ` : ''}
                        
                        <!-- Appointments List with scrollable area -->
                        <div class="space-y-1 max-h-[calc(100vh-300px)] overflow-y-auto" id="day-view-appointments-list">
                            ${dayAppointments.length > 0 ? 
                                dayAppointments.map((apt, idx) => this.renderAppointmentRow(apt, idx === dayAppointments.length - 1)).join('') :
                                this.renderEmptyState(isBlocked, isNonWorkingDay)
                            }
                        </div>
                    </div>
                    
                    <!-- Right Panel: Mini Calendar (appears first in mobile, second in desktop) -->
                    <div class="w-full md:w-80 flex-shrink-0 order-1 md:order-2">
                        <div class="md:sticky md:top-4">
                            ${this.renderMiniCalendar()}
                            
                            <!-- Add Event Button - Links to appointments/create like main New Appointment button -->
                            <a href="${withBaseUrl(`/appointments/create?date=${this.currentDate.toISODate()}`)}"
                               id="day-view-add-event-btn"
                               class="w-full mt-4 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm transition-colors flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-lg">add</span>
                                Add Appointment
                            </a>
                            
                            <!-- Day Summary Card -->
                            ${this.renderDaySummary(dayAppointments)}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Attach event listeners
        this.attachEventListeners(container, data);
        
        // Hide the daily-provider-appointments section in Day View (redundant)
        const dailySection = document.getElementById('daily-provider-appointments');
        if (dailySection) {
            dailySection.style.display = 'none';
        }
    }

    /**
     * Render a single appointment row (matching reference UI)
     */
    renderAppointmentRow(appointment, isLast = false) {
        const provider = this.providers.find(p => p.id === appointment.providerId);
        const darkMode = isDarkMode();
        const statusColors = getStatusColors(appointment.status, darkMode);
        const providerColor = getProviderColor(provider);
        
        const customerName = appointment.name || appointment.customerName || appointment.title || 'Unknown';
        const serviceName = appointment.serviceName || 'Appointment';
        const location = appointment.location || '';
        
        // Format time using localization settings
        const is24Hour = this.settings?.getTimeFormat?.() === '24h';
        const timeFormat = is24Hour ? 'HH:mm' : 'h:mm a';
        const dateTimeDisplay = appointment.startDateTime.toFormat(`MMMM d'${this.getOrdinalSuffix(appointment.startDateTime.day)}', yyyy 'at' ${timeFormat}`);
        
        const providerInitial = getProviderInitials(provider?.name);
        const providerName = provider?.name || 'Unknown Provider';
        
        return `
            <div class="appointment-row group flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-lg transition-colors cursor-pointer"
                 data-appointment-id="${appointment.id}">
                
                <!-- Provider Avatar -->
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-semibold text-lg shadow-sm"
                         style="background-color: ${providerColor};"
                         title="${this.escapeHtml(providerName)}">
                        ${providerInitial}
                    </div>
                </div>
                
                <!-- Appointment Details -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                            ${this.escapeHtml(customerName)}
                        </h4>
                        <span class="px-2 py-0.5 text-[10px] font-medium rounded-full flex-shrink-0"
                              style="background-color: ${statusColors.bg}; color: ${statusColors.text}; border: 1px solid ${statusColors.border};">
                            ${getStatusLabel(appointment.status)}
                        </span>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                        <!-- Date/Time -->
                        <div class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base">calendar_today</span>
                            <span>${dateTimeDisplay}</span>
                        </div>
                        
                        ${location ? `
                            <!-- Location -->
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base">location_on</span>
                                <span class="truncate max-w-[150px]">${this.escapeHtml(location)}</span>
                            </div>
                        ` : `
                            <!-- Service -->
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base">spa</span>
                                <span class="truncate max-w-[150px]">${this.escapeHtml(serviceName)}</span>
                            </div>
                        `}
                    </div>
                </div>
                
                <!-- Actions Menu -->
                <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button type="button" 
                            class="appointment-menu-btn p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                            data-appointment-id="${appointment.id}"
                            title="More options">
                        <span class="material-symbols-outlined text-gray-500 dark:text-gray-400">more_horiz</span>
                    </button>
                </div>
            </div>
            
            ${!isLast ? '<div class="border-b border-gray-200 dark:border-gray-700 mx-4"></div>' : ''}
        `;
    }

    /**
     * Render empty state when no appointments
     */
    renderEmptyState(isBlocked, isNonWorkingDay) {
        let message = 'No appointments scheduled';
        let icon = 'event_available';
        
        if (isBlocked) {
            message = 'This day is blocked';
            icon = 'block';
        } else if (isNonWorkingDay) {
            message = 'Non-working day';
            icon = 'weekend';
        }
        
        return `
            <div class="py-12 text-center">
                <span class="material-symbols-outlined text-gray-300 dark:text-gray-600 text-6xl mb-4">${icon}</span>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">${message}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    ${isBlocked ? 'Appointments cannot be scheduled on this day' : 
                      isNonWorkingDay ? 'This is a scheduled day off' :
                      'Click "Add Appointment" to schedule one'}
                </p>
            </div>
        `;
    }

    /**
     * Render mini calendar for date navigation
     */
    renderMiniCalendar() {
        const currentMonth = this.currentDate.startOf('month');
        const monthEnd = this.currentDate.endOf('month');
        
        // Get first day of week setting
        const firstDayOfWeek = this.settings?.getFirstDayOfWeek?.() || 0;
        
        // Calculate grid start
        const luxonFirstDay = firstDayOfWeek === 0 ? 7 : firstDayOfWeek;
        const monthStartWeekday = currentMonth.weekday;
        let daysBack = monthStartWeekday - luxonFirstDay;
        if (daysBack < 0) daysBack += 7;
        const gridStart = currentMonth.minus({ days: daysBack });
        
        // Generate 6 weeks of days
        const days = [];
        let current = gridStart;
        for (let i = 0; i < 42; i++) {
            days.push(current);
            current = current.plus({ days: 1 });
        }
        
        // Day headers
        const dayNames = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        const rotatedDays = [...dayNames.slice(firstDayOfWeek), ...dayNames.slice(0, firstDayOfWeek)];
        
        // Get appointments count per day for indicators
        const appointmentCounts = {};
        this.appointments.forEach(apt => {
            const dateKey = apt.startDateTime.toISODate();
            appointmentCounts[dateKey] = (appointmentCounts[dateKey] || 0) + 1;
        });
        
        return `
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <!-- Month Navigation -->
                <div class="flex items-center justify-between mb-4">
                    <button type="button" 
                            class="mini-cal-nav p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                            data-direction="prev">
                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-300">chevron_left</span>
                    </button>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        ${this.currentDate.toFormat('MMMM yyyy')}
                    </span>
                    <button type="button"
                            class="mini-cal-nav p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                            data-direction="next">
                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-300">chevron_right</span>
                    </button>
                </div>
                
                <!-- Day Headers -->
                <div class="grid grid-cols-7 mb-2">
                    ${rotatedDays.map(day => `
                        <div class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">
                            ${day}
                        </div>
                    `).join('')}
                </div>
                
                <!-- Days Grid -->
                <div class="grid grid-cols-7 gap-1">
                    ${days.map(day => {
                        const isToday = day.hasSame(DateTime.now(), 'day');
                        const isSelected = day.hasSame(this.currentDate, 'day');
                        const isCurrentMonth = day.month === this.currentDate.month;
                        const dateKey = day.toISODate();
                        const hasAppointments = appointmentCounts[dateKey] > 0;
                        
                        let classes = 'mini-cal-day relative w-8 h-8 flex items-center justify-center text-sm rounded-full transition-colors cursor-pointer ';
                        
                        if (isSelected) {
                            classes += 'bg-blue-600 text-white font-semibold ';
                        } else if (isToday) {
                            classes += 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold ';
                        } else if (isCurrentMonth) {
                            classes += 'text-gray-900 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-600 ';
                        } else {
                            classes += 'text-gray-400 dark:text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-600 ';
                        }
                        
                        return `
                            <button type="button" 
                                    class="${classes}"
                                    data-date="${day.toISODate()}">
                                ${day.day}
                                ${hasAppointments && !isSelected ? `
                                    <span class="absolute bottom-0.5 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-blue-500"></span>
                                ` : ''}
                            </button>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    /**
     * Render day summary card
     */
    renderDaySummary(dayAppointments) {
        // Group by status
        const statusCounts = {
            pending: 0,
            confirmed: 0,
            completed: 0,
            cancelled: 0,
            'no-show': 0
        };
        
        dayAppointments.forEach(apt => {
            if (statusCounts.hasOwnProperty(apt.status)) {
                statusCounts[apt.status]++;
            }
        });
        
        // Group by provider
        const providerCounts = {};
        dayAppointments.forEach(apt => {
            const provider = this.providers.find(p => p.id === apt.providerId);
            const name = provider?.name || 'Unknown';
            providerCounts[name] = (providerCounts[name] || 0) + 1;
        });
        
        const activeStatuses = Object.entries(statusCounts).filter(([_, count]) => count > 0);
        const activeProviders = Object.entries(providerCounts);
        
        if (dayAppointments.length === 0) return '';
        
        return `
            <div class="mt-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Day Summary</h4>
                
                <!-- Total -->
                <div class="flex items-center justify-between mb-3 pb-3 border-b border-gray-200 dark:border-gray-600">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Appointments</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">${dayAppointments.length}</span>
                </div>
                
                ${activeStatuses.length > 0 ? `
                    <!-- By Status -->
                    <div class="space-y-2 mb-3">
                        ${activeStatuses.map(([status, count]) => {
                            const colors = getStatusColors(status, isDarkMode());
                            return `
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full" style="background-color: ${colors.dot};"></span>
                                        <span class="text-gray-600 dark:text-gray-400 capitalize">${status.replace('-', ' ')}</span>
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white">${count}</span>
                                </div>
                            `;
                        }).join('')}
                    </div>
                ` : ''}
                
                ${activeProviders.length > 1 ? `
                    <!-- By Provider -->
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-600">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">By Provider</span>
                        <div class="mt-2 space-y-2">
                            ${activeProviders.map(([name, count]) => {
                                const provider = this.providers.find(p => p.name === name);
                                const color = getProviderColor(provider);
                                return `
                                    <div class="flex items-center justify-between text-sm">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full" style="background-color: ${color};"></span>
                                            <span class="text-gray-600 dark:text-gray-400 truncate max-w-[120px]">${this.escapeHtml(name)}</span>
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white">${count}</span>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    /**
     * Attach all event listeners
     */
    attachEventListeners(container, data) {
        // Appointment row clicks - open details
        container.querySelectorAll('.appointment-row').forEach(el => {
            el.addEventListener('click', (e) => {
                // Don't trigger if clicking the menu button
                if (e.target.closest('.appointment-menu-btn')) return;
                
                const aptId = parseInt(el.dataset.appointmentId, 10);
                const appointment = data.appointments.find(a => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    data.onAppointmentClick(appointment);
                }
            });
        });
        
        // Menu button clicks
        container.querySelectorAll('.appointment-menu-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const aptId = parseInt(btn.dataset.appointmentId, 10);
                const appointment = data.appointments.find(a => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    data.onAppointmentClick(appointment);
                }
            });
        });
        
        // Mini calendar day clicks
        container.querySelectorAll('.mini-cal-day').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const dateStr = btn.dataset.date;
                const newDate = DateTime.fromISO(dateStr, { zone: this.scheduler.options.timezone });
                
                // Update scheduler's current date and re-render
                this.scheduler.currentDate = newDate;
                await this.scheduler.loadAppointments();
                this.scheduler.render();
            });
        });
        
        // Mini calendar navigation
        container.querySelectorAll('.mini-cal-nav').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const direction = btn.dataset.direction;
                if (direction === 'prev') {
                    this.scheduler.currentDate = this.scheduler.currentDate.minus({ months: 1 });
                } else {
                    this.scheduler.currentDate = this.scheduler.currentDate.plus({ months: 1 });
                }
                await this.scheduler.loadAppointments();
                this.scheduler.render();
            });
        });
        
        // Add event button is now an <a> link, no JS handler needed
        // It navigates to /appointments/create?date=YYYY-MM-DD
    }

    /**
     * Get ordinal suffix for day number (1st, 2nd, 3rd, etc.)
     */
    getOrdinalSuffix(day) {
        if (day > 3 && day < 21) return 'th';
        switch (day % 10) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
            default: return 'th';
        }
    }

    isDateBlocked(date, blockedPeriods) {
        if (!blockedPeriods || blockedPeriods.length === 0) return false;
        const checkDate = date.toISODate();
        return blockedPeriods.some(period => {
            return checkDate >= period.start && checkDate <= period.end;
        });
    }
    
    getBlockedPeriodInfo(date, blockedPeriods) {
        if (!blockedPeriods || blockedPeriods.length === 0) return null;
        const checkDate = date.toISODate();
        return blockedPeriods.find(p => checkDate >= p.start && checkDate <= p.end) || null;
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
