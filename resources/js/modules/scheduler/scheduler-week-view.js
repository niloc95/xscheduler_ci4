/**
 * Custom Scheduler - Week View (50/50 Split Layout)
 * 
 * Modern split-view layout:
 * - Left panel (50%): Mini calendar + Appointment summary list
 * - Right panel (50%): Time Slot Availability Engine
 * 
 * No traditional calendar grid - replaced with availability-focused design.
 */

import { DateTime } from 'luxon';
import { getStatusColors, getProviderColor, getProviderInitials, getStatusLabel, isDarkMode } from './appointment-colors.js';
import { generateSlotsWithAvailability, findOverlappingAppointments, getProviderAvailabilityForSlot } from '../../utils/scheduling-utils.js';
import { getBaseUrl, withBaseUrl } from '../../utils/url-helpers.js';

export class WeekView {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.selectedDate = null; // Currently selected date for slot view
        this.slotPickerDisplayMonth = null; // Tracks which month the dropdown calendar shows
        this._scheduleLoadInFlight = new Set();
        this._scheduleLoadFailed = new Set();
        this._scheduleLoadAttempts = new Map();
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
        
        // Store for use in other methods
        this.settings = settings;
        this.currentDate = currentDate;
        this.appointments = appointments;
        this.providers = providers;
        this.config = config;
        this.data = data;
        this.container = container;
        
        // Initialize selected date to current date
        this.selectedDate = this.selectedDate || currentDate;
        
        // Get first day of week from settings (0=Sunday, 1=Monday, etc.)
        const firstDayOfWeek = settings?.getFirstDayOfWeek?.() ?? config?.firstDayOfWeek ?? 0;
        
        // Calculate week start based on firstDayOfWeek setting
        const currentWeekday = currentDate.weekday;
        const luxonFirstDay = firstDayOfWeek === 0 ? 7 : firstDayOfWeek;
        
        let daysToSubtract = currentWeekday - luxonFirstDay;
        if (daysToSubtract < 0) daysToSubtract += 7;
        
        const weekStart = currentDate.minus({ days: daysToSubtract }).startOf('day');
        const weekEnd = weekStart.plus({ days: 6 });
        
        // Store week boundaries
        this.weekStart = weekStart;
        this.weekEnd = weekEnd;
        
        // Generate array of 7 days for the week
        const days = [];
        for (let i = 0; i < 7; i++) {
            days.push(weekStart.plus({ days: i }));
        }
        this.days = days;
        
        // Get blocked periods from config
        this.blockedPeriods = config?.blockedPeriods || [];
        
        // Business hours from settings
        this.businessHours = {
            startTime: config?.slotMinTime || '08:00',
            endTime: config?.slotMaxTime || '17:00'
        };
        
        // Slot duration in minutes (default 30)
        // Config may pass "00:30:00" format or just 30
        let slotDur = config?.slotDuration || 30;
        if (typeof slotDur === 'string' && slotDur.includes(':')) {
            // Parse "00:30:00" or "00:30" format to minutes
            const parts = slotDur.split(':');
            slotDur = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
        }
        this.slotDuration = parseInt(slotDur, 10) || 30;
        
        // Get visible providers
        this.visibleProviders = providers.filter(p => 
            this.scheduler.visibleProviders.has(p.id) || this.scheduler.visibleProviders.has(parseInt(p.id, 10))
        );
        
        // Filter week appointments
        const weekAppointments = appointments.filter(apt => {
            return apt.startDateTime >= weekStart && apt.startDateTime <= weekEnd.endOf('day');
        });
        this.weekAppointments = weekAppointments;

        // Render the 50/50 split layout
        container.innerHTML = `
            <div class="scheduler-week-view bg-white dark:bg-gray-800 rounded-lg">
                <!-- 50/50 Split Layout: responsive grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
                    
                    <!-- LEFT PANEL: Mini Calendar + Appointment Summary -->
                    <div class="week-left-panel border-r-0 md:border-r border-gray-200 dark:border-gray-700 p-4 md:p-6 order-1">
                        
                        <!-- Week Header -->
                        <div class="mb-4">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                                Week of ${weekStart.toFormat('MMMM d')}
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                ${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('MMM d, yyyy')}
                            </p>
                        </div>
                        
                        <!-- Mini Calendar -->
                        ${this.renderMiniCalendar()}
                        
                        <!-- Appointment Summary List -->
                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    Appointments for ${this.selectedDate.toFormat('EEE, MMM d')}
                                </h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400" id="appointment-count">
                                    ${this.getAppointmentsForDate(this.selectedDate).length} appointments
                                </span>
                            </div>
                            <div id="appointment-summary-list" class="space-y-2 max-h-[300px] overflow-y-auto">
                                ${this.renderAppointmentSummaryList(this.selectedDate)}
                            </div>
                        </div>
                        
                        <!-- Add Appointment Button -->
                        <a href="${withBaseUrl(`/appointments/create?date=${this.selectedDate.toISODate()}`)}"
                           id="week-view-add-btn"
                           class="w-full mt-4 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm transition-colors flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg">add</span>
                            Add Appointment
                        </a>
                    </div>
                    
                    <!-- RIGHT PANEL: Time Slot Availability Engine -->
                    <div class="week-right-panel p-4 md:p-6 order-2 bg-gray-50 dark:bg-gray-800/50">
                        
                        <!-- Slot Engine Header with Date Picker -->
                        <div class="mb-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                        <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">event_available</span>
                                        Available Slots
                                    </h3>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full" id="provider-count-badge">
                                    <span class="material-symbols-outlined text-sm">group</span>
                                    <span id="provider-count-label">${this.visibleProviders.length} provider${this.visibleProviders.length !== 1 ? 's' : ''}</span>
                                </div>
                            </div>
                            
                            <!-- Date Picker Row -->
                            <div class="mt-3 flex items-center gap-2">
                                <button type="button" 
                                        id="prev-slot-date"
                                        class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                                        title="Previous day">
                                    <span class="material-symbols-outlined text-gray-600 dark:text-gray-300">chevron_left</span>
                                </button>
                                
                                <div class="relative flex-1">
                                    <button type="button" 
                                            id="slot-date-picker-toggle"
                                            class="w-full flex items-center justify-between gap-2 px-4 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-sm hover:border-blue-400 dark:hover:border-blue-500 transition-all group">
                                        <div class="flex items-center gap-2">
                                            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-lg">calendar_today</span>
                                            <div class="text-left">
                                                <div class="text-sm font-semibold text-gray-900 dark:text-white" id="slot-engine-weekday">
                                                    ${this.selectedDate.toFormat('EEEE')}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400" id="slot-engine-date">
                                                    ${this.selectedDate.toFormat('MMMM d, yyyy')}
                                                </div>
                                            </div>
                                        </div>
                                        <span class="material-symbols-outlined text-gray-400 group-hover:text-blue-500 transition-colors" id="date-picker-chevron">expand_more</span>
                                    </button>
                                    
                                    <!-- Mini Date Picker Dropdown -->
                                    <div id="slot-date-picker-dropdown" 
                                         class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-xl z-50 hidden">
                                        <div class="p-3" id="slot-mini-calendar-container">
                                            ${this.renderSlotDatePicker()}
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="button" 
                                        id="next-slot-date"
                                        class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                                        title="Next day">
                                    <span class="material-symbols-outlined text-gray-600 dark:text-gray-300">chevron_right</span>
                                </button>
                                
                                <button type="button" 
                                        id="today-slot-date"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors"
                                        title="Go to today">
                                    Today
                                </button>
                            </div>
                        </div>
                        
                        <!-- Provider Filter Pills -->
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Filter by Provider</span>
                            </div>
                            <div class="flex flex-wrap gap-2" id="provider-filter-pills">
                                ${this.renderProviderFilterPills()}
                            </div>
                        </div>
                        
                        <!-- Time Slot List -->
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Time Slots</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500" id="slot-count-label"></span>
                            </div>
                            <div id="time-slot-engine" class="space-y-2 max-h-[calc(100vh-420px)] overflow-y-auto pr-1 custom-scrollbar">
                                ${this.renderTimeSlotEngine(this.selectedDate)}
                            </div>
                        </div>
                        
                        <!-- Legend - Compact -->
                        <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                                <div class="flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-sm bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-600"></span>
                                    <span>Open</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-sm bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-400 dark:border-yellow-600"></span>
                                    <span>Partial</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-sm bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-600"></span>
                                    <span>Full</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-sm bg-gray-200 dark:bg-gray-600 border border-gray-400 dark:border-gray-500"></span>
                                    <span>Blocked</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Attach event listeners
        this.attachEventListeners(container, data);
        
        // Render the weekly schedule section below
        this.renderWeeklyAppointmentsSection(days, appointments, providers, data);
    }
    
    /**
     * Render mini calendar with week highlight
     */
    renderMiniCalendar() {
        const displayMonth = this.currentDate.startOf('month');
        const firstDayOfWeek = this.settings?.getFirstDayOfWeek?.() || 0;
        
        // Calculate grid start
        const luxonFirstDay = firstDayOfWeek === 0 ? 7 : firstDayOfWeek;
        const monthStartWeekday = displayMonth.weekday;
        let daysBack = monthStartWeekday - luxonFirstDay;
        if (daysBack < 0) daysBack += 7;
        const gridStart = displayMonth.minus({ days: daysBack });
        
        // Generate 6 weeks of days
        const calDays = [];
        let current = gridStart;
        for (let i = 0; i < 42; i++) {
            calDays.push(current);
            current = current.plus({ days: 1 });
        }
        
        // Day headers
        const dayNames = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        const rotatedDays = [...dayNames.slice(firstDayOfWeek), ...dayNames.slice(0, firstDayOfWeek)];
        
        // Get appointments count per day
        const appointmentCounts = {};
        this.appointments.forEach(apt => {
            const dateKey = apt.startDateTime.toISODate();
            appointmentCounts[dateKey] = (appointmentCounts[dateKey] || 0) + 1;
        });
        
        return `
            <div class="bg-gray-100 dark:bg-gray-700/50 rounded-xl p-4">
                <!-- Month Navigation -->
                <div class="flex items-center justify-between mb-3">
                    <button type="button" 
                            class="mini-cal-nav p-1 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                            data-direction="prev">
                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-300 text-sm">chevron_left</span>
                    </button>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        ${this.currentDate.toFormat('MMMM yyyy')}
                    </span>
                    <button type="button"
                            class="mini-cal-nav p-1 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                            data-direction="next">
                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-300 text-sm">chevron_right</span>
                    </button>
                </div>
                
                <!-- Day Headers -->
                <div class="grid grid-cols-7 mb-1">
                    ${rotatedDays.map(day => `
                        <div class="text-center text-[10px] font-medium text-gray-500 dark:text-gray-400 py-1">
                            ${day}
                        </div>
                    `).join('')}
                </div>
                
                <!-- Days Grid -->
                <div class="grid grid-cols-7 gap-0.5">
                    ${calDays.map(day => {
                        const isToday = day.hasSame(DateTime.now(), 'day');
                        const isSelected = day.hasSame(this.selectedDate, 'day');
                        const isInCurrentWeek = day >= this.weekStart && day <= this.weekEnd;
                        const isCurrentMonth = day.month === this.currentDate.month;
                        const dateKey = day.toISODate();
                        const hasAppointments = appointmentCounts[dateKey] > 0;
                        const count = appointmentCounts[dateKey] || 0;
                        
                        let classes = 'mini-cal-day relative w-7 h-7 flex items-center justify-center text-xs rounded transition-colors cursor-pointer ';
                        
                        if (isSelected) {
                            classes += 'bg-blue-600 text-white font-bold ';
                        } else if (isToday) {
                            classes += 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 font-bold ';
                        } else if (isInCurrentWeek) {
                            classes += 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 ';
                        } else if (isCurrentMonth) {
                            classes += 'text-gray-900 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-600 ';
                        } else {
                            classes += 'text-gray-400 dark:text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-600 ';
                        }
                        
                        return `
                            <button type="button" 
                                    class="${classes}"
                                    data-date="${day.toISODate()}"
                                    title="${day.toFormat('MMMM d, yyyy')}${count > 0 ? ` - ${count} appointment${count > 1 ? 's' : ''}` : ''}">
                                ${day.day}
                                ${hasAppointments && !isSelected ? `
                                    <span class="absolute -bottom-0.5 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-blue-500"></span>
                                ` : ''}
                            </button>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }
    
    /**
     * Render mini date picker dropdown for slot panel
     */
    renderSlotDatePicker() {
        // Use dedicated display month so month nav doesn't change selectedDate
        if (!this.slotPickerDisplayMonth) {
            this.slotPickerDisplayMonth = this.selectedDate.startOf('month');
        }
        const displayMonth = this.slotPickerDisplayMonth;
        const firstDayOfWeek = this.settings?.getFirstDayOfWeek?.() || 0;
        
        // Calculate grid start
        const luxonFirstDay = firstDayOfWeek === 0 ? 7 : firstDayOfWeek;
        const monthStartWeekday = displayMonth.weekday;
        let daysBack = monthStartWeekday - luxonFirstDay;
        if (daysBack < 0) daysBack += 7;
        const gridStart = displayMonth.minus({ days: daysBack });
        
        // Generate 6 weeks of days
        const calDays = [];
        let current = gridStart;
        for (let i = 0; i < 42; i++) {
            calDays.push(current);
            current = current.plus({ days: 1 });
        }
        
        // Day headers
        const dayNames = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        const rotatedDays = [...dayNames.slice(firstDayOfWeek), ...dayNames.slice(0, firstDayOfWeek)];
        
        return `
            <!-- Month Navigation -->
            <div class="flex items-center justify-between mb-2">
                <button type="button" 
                        class="slot-picker-nav p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        data-direction="prev">
                    <span class="material-symbols-outlined text-gray-600 dark:text-gray-300 text-sm">chevron_left</span>
                </button>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                    ${displayMonth.toFormat('MMMM yyyy')}
                </span>
                <button type="button"
                        class="slot-picker-nav p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        data-direction="next">
                    <span class="material-symbols-outlined text-gray-600 dark:text-gray-300 text-sm">chevron_right</span>
                </button>
            </div>
            
            <!-- Day Headers -->
            <div class="grid grid-cols-7 mb-1">
                ${rotatedDays.map(day => `
                    <div class="text-center text-[10px] font-medium text-gray-500 dark:text-gray-400 py-1">
                        ${day}
                    </div>
                `).join('')}
            </div>
            
            <!-- Days Grid -->
            <div class="grid grid-cols-7 gap-0.5">
                ${calDays.map(day => {
                    const isToday = day.hasSame(DateTime.now(), 'day');
                    const isSelected = day.hasSame(this.selectedDate, 'day');
                    const isCurrentMonth = day.month === displayMonth.month;
                    const isPast = day < DateTime.now().startOf('day');
                    
                    let classes = 'slot-picker-day w-8 h-8 flex items-center justify-center text-xs rounded-lg transition-colors ';
                    
                    if (isSelected) {
                        classes += 'bg-blue-600 text-white font-bold cursor-pointer ';
                    } else if (isPast) {
                        classes += 'text-gray-300 dark:text-gray-600 cursor-not-allowed ';
                    } else if (isToday) {
                        classes += 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 font-bold cursor-pointer hover:bg-blue-200 dark:hover:bg-blue-900/60 ';
                    } else if (isCurrentMonth) {
                        classes += 'text-gray-900 dark:text-white cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 ';
                    } else {
                        classes += 'text-gray-400 dark:text-gray-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 ';
                    }
                    
                    return `
                        <button type="button" 
                                class="${classes}"
                                data-date="${day.toISODate()}"
                                ${isPast ? 'disabled' : ''}
                                title="${day.toFormat('MMMM d, yyyy')}">
                            ${day.day}
                        </button>
                    `;
                }).join('')}
            </div>
        `;
    }
    
    /**
     * Render appointment summary list for selected date
     */
    renderAppointmentSummaryList(date) {
        const dayAppointments = this.getAppointmentsForDate(date);
        
        if (dayAppointments.length === 0) {
            return `
                <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                    <span class="material-symbols-outlined text-3xl mb-2 block">event_available</span>
                    <p class="text-sm">No appointments scheduled</p>
                </div>
            `;
        }
        
        // Sort by time
        dayAppointments.sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());
        
        const timeFormat = this.settings?.getTimeFormat?.() === '24h' ? 'HH:mm' : 'h:mm a';
        
        return dayAppointments.map(apt => {
            const provider = this.providers.find(p => p.id === apt.providerId);
            const statusColors = getStatusColors(apt.status, isDarkMode());
            const providerColor = getProviderColor(provider);
            const time = apt.startDateTime.toFormat(timeFormat);
            const customerName = apt.name || apt.customerName || apt.title || 'Unknown';
            const serviceName = apt.serviceName || 'Service';
            
            return `
                <div class="appointment-summary-item flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:shadow-md transition-all"
                     data-appointment-id="${apt.id}"
                     style="border-left: 3px solid ${providerColor};">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                             style="background-color: ${providerColor};">
                            ${getProviderInitials(provider?.name)}
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-sm text-gray-900 dark:text-white truncate">${this.escapeHtml(customerName)}</span>
                            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded"
                                  style="background-color: ${statusColors.bg}; color: ${statusColors.text};">
                                ${apt.status}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            <span class="font-medium">${time}</span> • ${this.escapeHtml(serviceName)}
                        </div>
                        ${apt.locationName ? `
                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 flex items-center gap-1 truncate">
                            <span class="material-symbols-outlined text-xs">location_on</span>
                            ${this.escapeHtml(apt.locationName)}
                        </div>` : ''}
                    </div>
                    <span class="material-symbols-outlined text-gray-400 text-lg">chevron_right</span>
                </div>
            `;
        }).join('');
    }
    
    /**
     * Render provider filter pills
     */
    renderProviderFilterPills() {
        // Use all providers if visibleProviders is empty
        const providersToShow = this.visibleProviders.length > 0 ? this.visibleProviders : this.providers;
        
        if (providersToShow.length === 0) {
            return `<span class="text-xs text-gray-500 dark:text-gray-400">No providers available</span>`;
        }
        
        return providersToShow.map(provider => {
            const color = getProviderColor(provider);
            return `
                <button type="button"
                        class="provider-pill flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all border-2"
                        style="background-color: ${color}20; border-color: ${color}; color: ${color};"
                        data-provider-id="${provider.id}"
                        data-active="true">
                    <span class="w-2 h-2 rounded-full" style="background-color: ${color};"></span>
                    ${this.escapeHtml(provider.name)}
                </button>
            `;
        }).join('');
    }
    
    /**
     * Render time slot availability engine
     */
    renderTimeSlotEngine(date) {
        const slots = this.generateAvailabilitySlots(date);
        
        // Use all providers if visibleProviders is empty
        const providersToShow = this.visibleProviders.length > 0 ? this.visibleProviders : this.providers;
        
        if (slots.length === 0) {
            return `
                <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <span class="material-symbols-outlined text-5xl mb-3 block">event_busy</span>
                    <p class="text-sm font-medium">No available time slots</p>
                    <p class="text-xs mt-1">This day may be blocked or outside business hours</p>
                </div>
            `;
        }
        
        const timeFormat = this.settings?.getTimeFormat?.() === '24h' ? 'HH:mm' : 'h:mm a';
        
        return slots.map(slot => {
            const availableCount = slot.availableProviders.length;
            const bookedCount = slot.bookedProviders.length;
            const totalProviders = providersToShow.length;
            const isFullyBooked = availableCount === 0;
            const isPartiallyBooked = bookedCount > 0 && availableCount > 0;
            const isBlocked = slot.isBlocked;
            
            // Determine slot styling
            let slotBgClass = 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900/30';
            let statusText = `${availableCount} provider${availableCount !== 1 ? 's' : ''} available`;
            let statusIcon = 'check_circle';
            let statusColor = 'text-green-600 dark:text-green-400';
            
            if (isBlocked) {
                slotBgClass = 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 cursor-not-allowed opacity-60';
                statusText = 'Blocked';
                statusIcon = 'block';
                statusColor = 'text-gray-500 dark:text-gray-400';
            } else if (isFullyBooked) {
                slotBgClass = 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 cursor-not-allowed';
                statusText = 'Fully booked';
                statusIcon = 'event_busy';
                statusColor = 'text-red-600 dark:text-red-400';
            } else if (isPartiallyBooked) {
                slotBgClass = 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 hover:bg-yellow-100 dark:hover:bg-yellow-900/30';
                statusText = `${availableCount} of ${totalProviders} available`;
                statusIcon = 'schedule';
                statusColor = 'text-yellow-600 dark:text-yellow-400';
            }
            
            const formattedTime = DateTime.fromFormat(slot.time, 'HH:mm').toFormat(timeFormat);
            
            return `
                <div class="time-slot-item flex items-center gap-3 p-3 rounded-lg border ${slotBgClass} transition-all cursor-pointer"
                     data-slot-time="${slot.time}"
                     data-date="${date.toISODate()}"
                     ${isBlocked || isFullyBooked ? 'data-disabled="true"' : ''}>
                    
                    <!-- Time -->
                    <div class="flex-shrink-0 w-16 text-center">
                        <span class="text-base font-bold text-gray-900 dark:text-white">${formattedTime}</span>
                    </div>
                    
                    <!-- Provider Availability — clickable name chips -->
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap gap-1.5 mb-1">
                            ${providersToShow.map(provider => {
                                const isBooked = slot.bookedProviders.some(bp => bp.id === provider.id);
                                const color = getProviderColor(provider);
                                const initials = getProviderInitials(provider.name);
                                // Extract a short display name: strip "Dr./Mr./etc", show surname
                                const shortName = (provider.name || '')
                                    .replace(/^(dr|mr|mrs|ms|prof|rev)\\.?\\s*/i, '')
                                    .replace(/[.,]\\s*(md|phd|dds|do|rn|np|pa|dvm|jr|sr|ii|iii|iv)$/i, '')
                                    .trim()
                                    .split(/\\s+/)
                                    .pop() || initials;
                                
                                if (isBooked) {
                                    return `
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 line-through opacity-60"
                                              title="${this.escapeHtml(provider.name)} — Booked">
                                            <span class="w-2 h-2 rounded-full bg-gray-400 flex-shrink-0"></span>
                                            ${this.escapeHtml(shortName)}
                                        </span>`;
                                }
                                // Available provider: clickable chip → opens booking form pre-populated
                                const bookUrl = withBaseUrl(
                                    `/appointments/create?date=${date.toISODate()}&time=${slot.time}&provider_id=${provider.id}`
                                );
                                return `
                                    <a href="${bookUrl}"
                                       class="provider-book-chip inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium text-gray-800 dark:text-gray-100 cursor-pointer hover:ring-2 hover:ring-offset-1 transition-all"
                                       style="background-color: ${color}20; border: 1px solid ${color}40;"
                                       title="Book with ${this.escapeHtml(provider.name)} at ${formattedTime}"
                                       data-provider-id="${provider.id}"
                                       data-slot-time="${slot.time}"
                                       data-slot-date="${date.toISODate()}">
                                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${color};"></span>
                                        ${this.escapeHtml(shortName)}
                                    </a>`;
                            }).join('')}
                        </div>
                        <div class="flex items-center gap-1 ${statusColor}">
                            <span class="material-symbols-outlined text-sm">${statusIcon}</span>
                            <span class="text-xs font-medium">${statusText}</span>
                        </div>
                    </div>
                    
                    <!-- Book Button (first available provider) -->
                    ${!isBlocked && !isFullyBooked ? `
                        <a href="${withBaseUrl(`/appointments/create?date=${date.toISODate()}&time=${slot.time}&provider_id=${slot.availableProviders[0]?.id || ''}`)}"
                           class="flex-shrink-0 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors whitespace-nowrap"
                           data-slot-time="${slot.time}"
                           data-slot-date="${date.toISODate()}"
                           data-provider-id="${slot.availableProviders[0]?.id || ''}">
                            Book
                        </a>
                    ` : ''}
                </div>
            `;
        }).join('');
    }
    
    /**
     * Generate availability slots for a specific date
     * Uses centralized scheduling utilities for consistent overlap detection
     */
    generateAvailabilitySlots(date) {
        this.debugLog('🔧 generateAvailabilitySlots called for date:', date.toISODate());
        this.debugLog('🔧 Business hours:', this.businessHours);
        this.debugLog('🔧 Slot duration:', this.slotDuration);
        this.debugLog('🔧 Providers available:', this.providers?.length);
        this.debugLog('🔧 Visible providers:', this.visibleProviders?.length);
        
        // Check if date is blocked
        const isDateBlocked = this.isDateBlocked(date, this.blockedPeriods);
        this.debugLog('🔧 Is date blocked:', isDateBlocked);
        
        if (isDateBlocked) {
            return []; // Return empty - date is blocked
        }
        
        // Check if it's a non-working day
        // Luxon weekday: 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun
        // Default: Mon-Fri (1-5) are working days, Sat-Sun (6-7) are not
        const luxonWeekday = date.weekday;
        const isWeekend = luxonWeekday === 6 || luxonWeekday === 7;
        
        this.debugLog('🔧 Day check - luxonWeekday:', luxonWeekday, 'isWeekend:', isWeekend);
        
        if (isWeekend) {
            this.debugLog('🔧 Returning empty - weekend day');
            return []; // Return empty - weekend
        }
        
        // Use all providers if visibleProviders is empty
        const providersToUse = this.visibleProviders.length > 0 ? this.visibleProviders : this.providers;
        this.debugLog('🔧 Providers to use:', providersToUse?.length, providersToUse?.map(p => p.name));
        
        if (!providersToUse || providersToUse.length === 0) {
            console.warn('🔧 No providers to use, returning empty slots');
            return [];
        }

        // Load provider schedules in the background if missing
        this.ensureProviderSchedulesLoaded(providersToUse);

        // Build provider schedule map for working-hours filtering
        const providerSchedules = new Map();
        if (this.settings?.getProviderSchedule) {
            providersToUse.forEach(provider => {
                const schedule = this.settings.getProviderSchedule(provider.id);
                if (schedule) {
                    providerSchedules.set(String(provider.id), schedule);
                }
            });
        }

        const now = DateTime.now();
        const minDateTime = date.hasSame(now, 'day') ? now.startOf('minute') : null;
        
        // Use centralized slot generation with proper overlap detection
        // This correctly handles appointments that SPAN multiple slots
        const slots = generateSlotsWithAvailability({
            date,
            businessHours: this.businessHours,
            slotDuration: this.slotDuration,
            appointments: this.appointments,
            providers: providersToUse,
            providerSchedules,
            minDateTime,
            filterEmptyProviders: true
        });
        
        this.debugLog('🔧 Generated', slots.length, 'slots with proper overlap detection');
        return slots;
    }
    
    /**
     * Get appointments for a specific date
     */
    getAppointmentsForDate(date) {
        return this.appointments.filter(apt => apt.startDateTime.hasSame(date, 'day'));
    }

    /**
     * Check if a date falls within a blocked period
     */
    isDateBlocked(date, blockedPeriods) {
        if (!blockedPeriods || blockedPeriods.length === 0) return false;
        
        const checkDate = date.toISODate();
        
        return blockedPeriods.some(period => {
            const start = period.start;
            const end = period.end;
            return checkDate >= start && checkDate <= end;
        });
    }

    async ensureProviderSchedulesLoaded(providers) {
        if (!this.settings?.loadProviderSchedule || !this.settings?.getProviderSchedule) return;
        if (!providers || providers.length === 0) return;

        const missingProviders = providers.filter(provider => {
            if (this.settings.getProviderSchedule(provider.id)) return false;
            if (this._scheduleLoadFailed.has(provider.id)) return false;
            const attempts = this._scheduleLoadAttempts.get(provider.id) || 0;
            return attempts < 2;
        });

        if (missingProviders.length === 0) return;

        const loadPromises = missingProviders.map(provider => {
            if (this._scheduleLoadInFlight.has(provider.id)) return null;
            this._scheduleLoadInFlight.add(provider.id);
            this._scheduleLoadAttempts.set(
                provider.id,
                (this._scheduleLoadAttempts.get(provider.id) || 0) + 1
            );
            return this.settings.loadProviderSchedule(provider.id)
                .then(schedule => ({ providerId: provider.id, schedule }))
                .catch(() => ({ providerId: provider.id, schedule: null }))
                .finally(() => this._scheduleLoadInFlight.delete(provider.id));
        }).filter(Boolean);

        if (loadPromises.length === 0) return;

        const results = await Promise.all(loadPromises);
        let loadedAny = false;
        results.forEach(result => {
            if (!result) return;
            if (result.schedule) {
                loadedAny = true;
                return;
            }
            this._scheduleLoadFailed.add(result.providerId);
        });

        if (loadedAny) {
            this.updateSlotDateDisplay();
            this.updateTimeSlotEngine();
            this.updateAppointmentSummary();
        }
    }
    
    /**
     * Attach event listeners for the new 50/50 split layout
     */
    attachEventListeners(container, data) {
        // Mini calendar day clicks - update selected date
        container.querySelectorAll('.mini-cal-day').forEach(el => {
            el.addEventListener('click', () => {
                const dateStr = el.dataset.date;
                if (dateStr) {
                    this.selectedDate = DateTime.fromISO(dateStr);
                    this.updateSelectedDate();
                }
            });
        });
        
        // Mini calendar navigation
        container.querySelectorAll('.mini-cal-nav').forEach(el => {
            el.addEventListener('click', () => {
                const direction = el.dataset.direction;
                if (this.scheduler) {
                    if (direction === 'prev') {
                        this.scheduler.navigateToDate(this.currentDate.minus({ weeks: 1 }));
                    } else {
                        this.scheduler.navigateToDate(this.currentDate.plus({ weeks: 1 }));
                    }
                }
            });
        });
        
        // Appointment summary item clicks
        container.querySelectorAll('.appointment-summary-item').forEach(el => {
            el.addEventListener('click', () => {
                const aptId = parseInt(el.dataset.appointmentId, 10);
                const appointment = data.appointments.find(a => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    data.onAppointmentClick(appointment);
                }
            });
        });
        
        // Time slot clicks (for available slots)
        container.querySelectorAll('.time-slot-item:not([data-disabled])').forEach(el => {
            el.addEventListener('click', (e) => {
                // Don't trigger if clicking a link (Book button or provider chip)
                if (e.target.closest('a')) return;
                
                const time = el.dataset.slotTime;
                const date = el.dataset.date;
                this.debugLog('Selected slot:', time, 'on', date);
            });
        });
        
        // Provider filter pills
        container.querySelectorAll('.provider-pill').forEach(el => {
            el.addEventListener('click', () => {
                const providerId = parseInt(el.dataset.providerId, 10);
                const isActive = el.dataset.active === 'true';
                
                // Toggle visibility
                if (isActive) {
                    this.scheduler.visibleProviders.delete(providerId);
                    el.dataset.active = 'false';
                    el.classList.add('opacity-40');
                } else {
                    this.scheduler.visibleProviders.add(providerId);
                    el.dataset.active = 'true';
                    el.classList.remove('opacity-40');
                }
                
                // Update visible providers list
                this.visibleProviders = this.providers.filter(p => 
                    this.scheduler.visibleProviders.has(p.id) || this.scheduler.visibleProviders.has(parseInt(p.id, 10))
                );
                
                // Update both the slot engine AND the appointment summary
                this.updateTimeSlotEngine();
                this.updateAppointmentSummary();
            });
        });
        
        // --- Slot Date Picker Controls (Event Delegation) ---
        // Uses delegation on stable container so innerHTML replacements don't break handlers.
        
        const datePickerToggle = container.querySelector('#slot-date-picker-toggle');
        const datePickerDropdown = container.querySelector('#slot-date-picker-dropdown');
        const datePickerChevron = container.querySelector('#date-picker-chevron');
        
        // Helper: close the dropdown
        const closeDropdown = () => {
            if (datePickerDropdown) {
                datePickerDropdown.classList.add('hidden');
                if (datePickerChevron) datePickerChevron.textContent = 'expand_more';
            }
        };
        
        // Helper: update slot panel after date change
        const onDateChanged = () => {
            this.slotPickerDisplayMonth = this.selectedDate.startOf('month');
            this.updateSlotDateDisplay();
            this.updateTimeSlotEngine();
            this.updateAppointmentSummary();
        };
        
        // Toggle dropdown
        if (datePickerToggle && datePickerDropdown) {
            datePickerToggle.addEventListener('click', () => {
                const isHidden = datePickerDropdown.classList.contains('hidden');
                datePickerDropdown.classList.toggle('hidden');
                if (datePickerChevron) {
                    datePickerChevron.textContent = isHidden ? 'expand_less' : 'expand_more';
                }
            });
            
            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!datePickerToggle.contains(e.target) && !datePickerDropdown.contains(e.target)) {
                    closeDropdown();
                }
            });
        }
        
        // Delegated click handler on the dropdown calendar container
        const calendarContainer = container.querySelector('#slot-mini-calendar-container');
        if (calendarContainer) {
            calendarContainer.addEventListener('click', (e) => {
                // Day click
                const dayBtn = e.target.closest('.slot-picker-day:not([disabled])');
                if (dayBtn) {
                    const dateStr = dayBtn.dataset.date;
                    if (dateStr) {
                        this.selectedDate = DateTime.fromISO(dateStr);
                        closeDropdown();
                        onDateChanged();
                    }
                    return;
                }
                
                // Month nav click
                const navBtn = e.target.closest('.slot-picker-nav');
                if (navBtn) {
                    const direction = navBtn.dataset.direction;
                    if (direction === 'prev') {
                        this.slotPickerDisplayMonth = (this.slotPickerDisplayMonth || this.selectedDate.startOf('month')).minus({ months: 1 });
                    } else {
                        this.slotPickerDisplayMonth = (this.slotPickerDisplayMonth || this.selectedDate.startOf('month')).plus({ months: 1 });
                    }
                    calendarContainer.innerHTML = this.renderSlotDatePicker();
                    return;
                }
            });
        }
        
        // Previous day button
        container.querySelector('#prev-slot-date')?.addEventListener('click', () => {
            this.selectedDate = this.selectedDate.minus({ days: 1 });
            onDateChanged();
        });
        
        // Next day button
        container.querySelector('#next-slot-date')?.addEventListener('click', () => {
            this.selectedDate = this.selectedDate.plus({ days: 1 });
            onDateChanged();
        });
        
        // Today button
        container.querySelector('#today-slot-date')?.addEventListener('click', () => {
            this.selectedDate = DateTime.now().startOf('day');
            onDateChanged();
        });
    }
    
    /**
     * Update the appointment summary list (called when providers are toggled)
     */
    updateAppointmentSummary() {
        const summaryList = this.container.querySelector('#appointment-summary-list');
        if (summaryList) {
            summaryList.innerHTML = this.renderAppointmentSummaryList(this.selectedDate);
            // Re-attach appointment click handlers
            summaryList.querySelectorAll('.appointment-summary-item').forEach(el => {
                el.addEventListener('click', () => {
                    const aptId = parseInt(el.dataset.appointmentId, 10);
                    const appointment = this.data.appointments.find(a => a.id === aptId);
                    if (appointment && this.data.onAppointmentClick) {
                        this.data.onAppointmentClick(appointment);
                    }
                });
            });
        }
        
        // Update appointment count
        const countEl = this.container.querySelector('#appointment-count');
        if (countEl) {
            const count = this.getAppointmentsForDate(this.selectedDate).length;
            countEl.textContent = `${count} appointment${count !== 1 ? 's' : ''}`;
        }
    }
    
    /**
     * Update selected date and refresh UI
     */
    updateSelectedDate() {
        // Update appointment summary list
        this.updateAppointmentSummary();
        
        // Update the header in left panel
        const headerEl = this.container.querySelector('.week-left-panel h3');
        if (headerEl) {
            headerEl.textContent = `Appointments for ${this.selectedDate.toFormat('EEE, MMM d')}`;
        }
        
        // Update time slot engine
        this.updateTimeSlotEngine();
        
        // Update add button link
        const addBtn = this.container.querySelector('#week-view-add-btn');
        if (addBtn) {
            addBtn.href = withBaseUrl(`/appointments/create?date=${this.selectedDate.toISODate()}`);
        }
        
        // Update mini calendar selection
        this.container.querySelectorAll('.mini-cal-day').forEach(el => {
            const elDate = el.dataset.date;
            if (elDate === this.selectedDate.toISODate()) {
                el.classList.add('bg-blue-600', 'text-white', 'font-bold');
                el.classList.remove('bg-blue-50', 'dark:bg-blue-900/20', 'text-blue-700', 'dark:text-blue-300', 'bg-blue-100', 'dark:bg-blue-900/40', 'text-blue-600', 'dark:text-blue-400');
            } else {
                el.classList.remove('bg-blue-600', 'text-white');
            }
        });
    }
    
    /**
     * Update the slot date picker display
     */
    updateSlotDateDisplay() {
        // Sync the display month to the selected date's month
        this.slotPickerDisplayMonth = this.selectedDate.startOf('month');

        // Update the date picker toggle display
        const weekdayEl = this.container.querySelector('#slot-engine-weekday');
        const fullDateEl = this.container.querySelector('#slot-engine-date');
        
        if (weekdayEl) {
            weekdayEl.textContent = this.selectedDate.toFormat('EEEE');
        }
        if (fullDateEl) {
            fullDateEl.textContent = this.selectedDate.toFormat('MMMM d, yyyy');
        }
        
        // Re-render the dropdown calendar (delegation handles listeners)
        const calendarContainer = this.container.querySelector('#slot-mini-calendar-container');
        if (calendarContainer) {
            calendarContainer.innerHTML = this.renderSlotDatePicker();
        }
    }
    
    /**
     * Update time slot engine
     */
    updateTimeSlotEngine() {
        // Update visible providers
        this.visibleProviders = this.providers.filter(p => 
            this.scheduler.visibleProviders.has(p.id) || this.scheduler.visibleProviders.has(parseInt(p.id, 10))
        );
        
        // Update provider count label
        const countLabel = this.container.querySelector('#provider-count-label');
        if (countLabel) {
            countLabel.textContent = `${this.visibleProviders.length} provider${this.visibleProviders.length !== 1 ? 's' : ''}`;
        }
        
        // Re-render time slots
        const slotEngine = this.container.querySelector('#time-slot-engine');
        if (slotEngine) {
            slotEngine.innerHTML = this.renderTimeSlotEngine(this.selectedDate);
            
            // Re-attach slot click handlers
            slotEngine.querySelectorAll('.time-slot-item:not([data-disabled])').forEach(el => {
                el.addEventListener('click', (e) => {
                    if (e.target.closest('a')) return;
                    const time = el.dataset.slotTime;
                    const date = el.dataset.date;
                    this.debugLog('Selected slot:', time, 'on', date);
                });
            });
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Render weekly appointments section showing provider schedules for the week
     */
    renderWeeklyAppointmentsSection(days, appointments, providers, data) {
        const dailySection = document.getElementById('daily-provider-appointments');
        if (!dailySection) return;
        
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        
        // Group appointments by provider and day
        const appointmentsByProvider = {};
        providers.forEach(provider => {
            appointmentsByProvider[provider.id] = {};
            days.forEach(day => {
                appointmentsByProvider[provider.id][day.toISODate()] = [];
            });
        });
        
        appointments.forEach(apt => {
            const dateKey = apt.startDateTime.toISODate();
            if (appointmentsByProvider[apt.providerId] && appointmentsByProvider[apt.providerId][dateKey]) {
                appointmentsByProvider[apt.providerId][dateKey].push(apt);
            }
        });
        
        // Calculate total appointments per provider for the week
        const providerTotals = {};
        providers.forEach(provider => {
            providerTotals[provider.id] = Object.values(appointmentsByProvider[provider.id]).flat().length;
        });
        
        // Filter to only show providers with appointments
        const activeProviders = providers.filter(p => providerTotals[p.id] > 0);
        
        const weekStart = days[0];
        const weekEnd = days[days.length - 1];
        
        dailySection.innerHTML = `
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Weekly Schedule
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            ${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('MMM d, yyyy')}
                        </p>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        ${appointments.length} ${appointments.length === 1 ? 'appointment' : 'appointments'} this week
                    </span>
                </div>
            </div>
            
            ${activeProviders.length > 0 ? `
                <!-- Provider Schedule Grid -->
                <div class="p-6">
                    <div class="space-y-6">
                        ${activeProviders.map(provider => {
                            const color = provider.color || '#3B82F6';
                            const weekAppointments = Object.values(appointmentsByProvider[provider.id]).flat();
                            
                            return `
                                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden transition-all"
                                     data-provider-id="${provider.id}">
                                    <!-- Provider Header -->
                                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                                         style="border-left: 4px solid ${color};">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold"
                                                 style="background-color: ${color};">
                                                ${getProviderInitials(provider.name)}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                                                    ${this.escapeHtml(provider.name)}
                                                </h4>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    ${weekAppointments.length} ${weekAppointments.length === 1 ? 'appointment' : 'appointments'} this week
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Days Grid -->
                                    <div class="grid grid-cols-1 md:grid-cols-7 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-gray-700">
                                        ${days.map(day => {
                                            const dateKey = day.toISODate();
                                            const dayAppointments = appointmentsByProvider[provider.id][dateKey] || [];
                                            const isToday = day.hasSame(DateTime.now(), 'day');
                                            
                                            return `
                                                <div class="p-3 min-h-[120px] ${isToday ? 'bg-blue-50 dark:bg-blue-900/10' : ''}">
                                                    <!-- Day Header -->
                                                    <div class="mb-2">
                                                        <div class="text-xs font-medium ${isToday ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'}">
                                                            ${day.toFormat('ccc')}
                                                        </div>
                                                        <div class="${isToday ? 'inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-sm font-semibold' : 'text-lg font-semibold text-gray-900 dark:text-white'}">
                                                            ${day.day}
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Appointments List -->
                                                    <div class="space-y-1.5">
                                                        ${dayAppointments.length > 0 ? 
                                                            dayAppointments.slice(0, 3).map(apt => {
                                                                const time = apt.startDateTime.toFormat(timeFormat);
                                                                const customerName = apt.name || apt.customerName || apt.title || 'Unknown';
                                                                
                                                                const darkModeCheck = typeof isDarkMode === 'function' ? isDarkMode() : false;
                                                                const aptStatusColors = getStatusColors(apt.status, darkModeCheck);
                                                                
                                                                return '<div class="text-xs p-2 rounded cursor-pointer transition-colors border-l-2" ' +
                                                                    'style="background-color: ' + aptStatusColors.bg + '; border-left-color: ' + aptStatusColors.border + '; color: ' + aptStatusColors.text + ';" ' +
                                                                    'data-appointment-id="' + apt.id + '">' +
                                                                    '<div class="font-medium truncate">' + time + '</div>' +
                                                                    '<div class="truncate opacity-90">' + this.escapeHtml(customerName) + '</div>' +
                                                                    '<span class="inline-block mt-1 px-1.5 py-0.5 text-[10px] font-medium rounded-full" ' +
                                                                    'style="background-color: ' + aptStatusColors.dot + '; color: white;">' + apt.status + '</span>' +
                                                                    '</div>';
                                                            }).join('') 
                                                            : '<div class="text-xs text-gray-400 dark:text-gray-500 italic">No appointments</div>'
                                                        }
                                                        ${dayAppointments.length > 3 ? '<div class="text-xs text-blue-600 dark:text-blue-400 font-medium text-center pt-1 cursor-pointer hover:underline flex items-center justify-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12" /></svg>+' + (dayAppointments.length - 3) + ' more</div>' : ''}
                                                    </div>
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            ` : `
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                    <p class="text-sm text-gray-600 dark:text-gray-400">No appointments scheduled this week</p>
                </div>
            `}
        `;
        
        // Add click handlers for appointments in the section
        this.attachWeeklySectionListeners(dailySection, data);
    }
    
    /**
     * Attach event listeners to appointments in the weekly section
     */
    attachWeeklySectionListeners(section, data) {
        section.querySelectorAll('[data-appointment-id]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const aptId = parseInt(el.dataset.appointmentId, 10);
                const appointment = data.appointments.find(a => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    data.onAppointmentClick(appointment);
                }
            });
        });
    }
}
