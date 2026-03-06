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
import { getStatusColors, getProviderColor, getProviderInitials, isDarkMode } from './appointment-colors.js';
import { withBaseUrl } from '../../utils/url-helpers.js';
import { buildAvailabilityContext, isAvailabilityDebugMode, renderAvailabilityDebugPayload, renderAvailabilitySlotList } from './availability-panel-shared.js';
import { buildAppointmentCountsByDate, buildMonthGridDays, getRotatedWeekdayInitials } from './calendar-grid-shared.js';
import { renderAppointmentSummaryCard, renderAppointmentSummaryEmptyState, renderWeekHeader, renderWeekLeftPanel, renderWeekRightPanel, renderWeekSlotEngineHeader } from './week-view-components.js';

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

        const weekHeaderHtml = renderWeekHeader({ weekStart, weekEnd });
        const weekLeftPanel = renderWeekLeftPanel({
            weekHeaderHtml,
            miniCalendarHtml: this.renderMiniCalendar(),
            selectedDateLabel: this.selectedDate.toFormat('EEE, MMM d'),
            appointmentCount: this.getAppointmentsForDate(this.selectedDate).length,
            appointmentSummaryHtml: this.renderAppointmentSummaryList(this.selectedDate),
            addAppointmentUrl: withBaseUrl(`/appointments/create?date=${this.selectedDate.toISODate()}`),
        });
        const weekSlotEngineHeader = renderWeekSlotEngineHeader({
            selectedDate: this.selectedDate,
            providerCount: this.visibleProviders.length,
            slotDatePickerHtml: this.renderSlotDatePicker(),
        });
        const weekRightPanel = renderWeekRightPanel({
            slotEngineHeaderHtml: weekSlotEngineHeader,
            filterPillsHtml: this._renderFilterPills(),
            slotsHtml: this._renderSlots(this.selectedDate),
        });

        // Render the 50/50 split layout
        container.innerHTML = `
            <div class="scheduler-week-view bg-surface-0 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <!-- 50/50 Split Layout: responsive grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
                    ${weekLeftPanel}
                    ${weekRightPanel}
                </div>
            </div>
        `;

        // Attach event listeners
        this.attachEventListeners(container, data);
        this._refreshSlotPanel();
        
        // Render the weekly schedule section below
        this.renderWeeklyAppointmentsSection(days, appointments, providers, data);
    }
    
    /**
     * Render mini calendar with week highlight
     */
    renderMiniCalendar() {
        const displayMonth = this.currentDate.startOf('month');
        const firstDayOfWeek = this.settings?.getFirstDayOfWeek?.() || 0;

        const calDays = buildMonthGridDays(displayMonth, firstDayOfWeek);
        const rotatedDays = getRotatedWeekdayInitials(firstDayOfWeek);
        const appointmentCounts = buildAppointmentCountsByDate(this.appointments);
        
        return `
            <div class="bg-surface-1 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
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

        const calDays = buildMonthGridDays(displayMonth, firstDayOfWeek);
        const rotatedDays = getRotatedWeekdayInitials(firstDayOfWeek);
        
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
            return renderAppointmentSummaryEmptyState();
        }
        
        // Sort by time
        dayAppointments.sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());
        
        const timeFormat = this.settings?.getTimeFormat?.() === '24h' ? 'HH:mm' : 'h:mm a';
        
        return dayAppointments.map(apt => {
            const provider = this.providers.find(p => p.id === apt.providerId);
            const statusColors = getStatusColors(apt.status, isDarkMode());
            const providerColor = getProviderColor(provider);
            const time = apt.startDateTime.toFormat(timeFormat);
            const customerName = apt.customerName || apt.title || 'Unknown';
            const serviceName = apt.serviceName || 'Service';
            
            return renderAppointmentSummaryCard({
                appointmentId: apt.id,
                providerColor,
                providerInitials: getProviderInitials(provider?.name),
                customerName,
                status: apt.status,
                statusColors,
                time,
                serviceName,
                locationName: apt.locationName || '',
            });
        }).join('');
    }
    
    /**
     * Render provider filter note for API-driven availability
     */
    _renderFilterPills() {
        const providerCount = this.visibleProviders.length > 0 ? this.visibleProviders.length : this.providers.length;
        return `<span class="text-xs text-gray-500 dark:text-gray-400">Availability requires a provider + service filter (${providerCount} providers visible).</span>`;
    }

    /**
     * Render time slot list placeholder (filled by API)
     */
    _renderSlots() {
        return `<div class="text-sm text-gray-500 dark:text-gray-400">Loading availability...</div>`;
    }
    
    /**
     * Get appointments for a specific date
     */
    getAppointmentsForDate(date) {
        return this.appointments.filter(apt => apt.startDateTime.hasSame(date, 'day'));
    }

    async ensureProviderSchedulesLoaded() {
        // no-op: client-side scheduling removed
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
        
        // Time slot clicks (for available slots) — now uses .slot-item class
        container.querySelectorAll('.slot-item:not([data-disabled])').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target.closest('a')) return;
                const time = el.dataset.slotTime;
                const date = el.dataset.date;
                this.debugLog('Selected slot:', time, 'on', date);
            });
        });
        
        // Provider filter pills — now uses .provider-filter-pill class
        container.querySelectorAll('.provider-filter-pill').forEach(el => {
            el.addEventListener('click', () => {
                const providerId = parseInt(el.dataset.providerId, 10);
                const isActive = el.dataset.active === 'true';
                
                // Toggle visibility
                if (isActive) {
                    this.scheduler.visibleProviders.delete(providerId);
                    el.dataset.active = 'false';
                    el.classList.add('provider-filter-pill--inactive');
                } else {
                    this.scheduler.visibleProviders.add(providerId);
                    el.dataset.active = 'true';
                    el.classList.remove('provider-filter-pill--inactive');
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
            this.updateSelectedDate();
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

        // Update slot date picker display
        this.updateSlotDateDisplay();
        
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
        
        // Refresh slot list via API
        this._refreshSlotPanel();
    }

    _getAvailabilityContext() {
        const visibleProviderIds = (this.visibleProviders || []).map(provider => provider.id);

        return buildAvailabilityContext({
            activeFilters: this.scheduler?.activeFilters,
            visibleProviderIds,
        });
    }

    _isDebugMode() {
        return isAvailabilityDebugMode(this.scheduler);
    }

    _renderDebugPayload(payload = null) {
        return renderAvailabilityDebugPayload(this.scheduler, payload);
    }

    async _refreshSlotPanel() {
        const slotEngine = this.container?.querySelector('#time-slot-engine');
        const countLabel = this.container?.querySelector('#slot-count-label');
        if (!slotEngine) return;

        const context = this._getAvailabilityContext();
        if (!context.ready) {
            slotEngine.innerHTML = `<div class="text-sm text-gray-500 dark:text-gray-400">${context.message}</div>${this._renderDebugPayload(null)}`;
            if (countLabel) countLabel.textContent = '';
            return;
        }

        const date = this.selectedDate?.toISODate?.();
        if (!date) {
            slotEngine.innerHTML = `<div class="text-sm text-gray-500 dark:text-gray-400">Select a date to see availability.</div>${this._renderDebugPayload({
                providerId: context.providerId,
                serviceId: context.serviceId,
                locationId: context.locationId ?? null,
                date: null,
                timezone: 'UTC',
            })}`;
            if (countLabel) countLabel.textContent = '';
            return;
        }

        const params = new URLSearchParams({
            provider_id: String(context.providerId),
            service_id: String(context.serviceId),
            date,
            timezone: 'UTC',
        });
        if (context.locationId) params.append('location_id', String(context.locationId));

        try {
            const response = await fetch(withBaseUrl(`/api/availability/slots?${params.toString()}`), { cache: 'no-store' });
            const data = await response.json();
            if (!response.ok) {
                slotEngine.innerHTML = `<div class="text-sm text-red-500">${data?.error?.message || 'Failed to load slots'}</div>${this._renderDebugPayload({
                    providerId: context.providerId,
                    serviceId: context.serviceId,
                    locationId: context.locationId ?? null,
                    date,
                    timezone: 'UTC',
                })}`;
                if (countLabel) countLabel.textContent = '';
                return;
            }

            const slots = data?.data?.slots || [];
            if (countLabel) countLabel.textContent = `${slots.length} slot${slots.length !== 1 ? 's' : ''}`;
            slotEngine.innerHTML = `${this._renderSlotListFromApi(slots, date, context.providerId, context.serviceId)}${this._renderDebugPayload({
                providerId: context.providerId,
                serviceId: context.serviceId,
                locationId: data?.data?.location_id ?? context.locationId ?? null,
                date,
                timezone: data?.data?.timezone ?? 'UTC',
            })}`;
        } catch (error) {
            console.error('Failed to load availability slots:', error);
            slotEngine.innerHTML = `<div class="text-sm text-red-500">Failed to load slots</div>${this._renderDebugPayload({
                providerId: context.providerId,
                serviceId: context.serviceId,
                locationId: context.locationId ?? null,
                date,
                timezone: 'UTC',
            })}`;
            if (countLabel) countLabel.textContent = '';
        }
    }

    _renderSlotListFromApi(slots, date, providerId, serviceId) {
        return renderAvailabilitySlotList(slots, date, providerId, serviceId);
    }

    /**
     * Render weekly appointments section showing provider schedules for the week
     */
    renderWeeklyAppointmentsSection(days, appointments, providers, data) {
        const dailySection = document.getElementById('daily-provider-appointments');
        if (!dailySection) return;
        const parseHour = (timeValue, fallbackValue) => {
            const parsed = Number.parseInt(String(timeValue || '').split(':')[0], 10);
            return Number.isNaN(parsed) ? fallbackValue : parsed;
        };

        const startHour = Math.max(0, parseHour(this.businessHours?.startTime, 9));
        const endHourCandidate = parseHour(this.businessHours?.endTime, 18);
        const endHour = Math.min(24, endHourCandidate > startHour ? endHourCandidate : startHour + 8);
        const totalHours = endHour - startHour;
        const is24Hour = this.scheduler?.settingsManager?.getTimeFormat() === '24h';

        const timeRows = Array.from({ length: totalHours }, (_, index) => {
            const hour = startHour + index;
            const label = is24Hour
                ? `${String(hour).padStart(2, '0')}:00`
                : DateTime.fromObject({ hour }).toFormat('ha').toLowerCase();
            return { hour, label };
        });

        const dayEntries = days.map(day => {
            const dayAppointments = appointments
                .filter(apt => apt.startDateTime?.hasSame(day, 'day'))
                .sort((left, right) => left.startDateTime.toMillis() - right.startDateTime.toMillis());

            const appointmentsByHour = {};
            dayAppointments.forEach(appointment => {
                const hour = appointment.startDateTime?.hour;
                if (hour === undefined || hour < startHour || hour >= endHour) {
                    return;
                }

                if (!appointmentsByHour[hour]) {
                    appointmentsByHour[hour] = [];
                }

                appointmentsByHour[hour].push(appointment);
            });

            return {
                day,
                appointmentsByHour,
                isToday: day.hasSame(DateTime.now(), 'day')
            };
        });

        const weekStart = days[0];
        const weekEnd = days[days.length - 1];

        const renderAppointmentCard = (appointment) => {
            const startDateTime = appointment.startDateTime;
            const darkModeEnabled = typeof isDarkMode === 'function' ? isDarkMode() : false;
            const statusColors = getStatusColors(appointment.status, darkModeEnabled);
            const provider = providers.find(candidate => candidate.id === appointment.providerId);
            const providerColor = getProviderColor(provider);
            const title = escapeHtml(appointment.customerName || appointment.title || 'Appointment');
            const timeText = startDateTime.toFormat(is24Hour ? 'HH:mm' : 'h:mma').toLowerCase();

            return `
                <button
                    type="button"
                    class="week-timeline-event group w-full flex items-center gap-1.5 rounded px-1.5 py-1 text-left text-[11px] leading-tight border-l-2 truncate cursor-pointer transition-colors hover:bg-gray-100 dark:hover:bg-white/5 text-gray-800 dark:text-gray-200"
                    data-appointment-id="${appointment.id}"
                    data-border-left-color="${providerColor}"
                    title="${title}">
                    <span class="font-semibold flex-shrink-0 tabular-nums opacity-80">${timeText}</span>
                    <span class="font-medium truncate">${title}</span>
                </button>
            `;
        };

        dailySection.innerHTML = `
            <div class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-800 dark:text-slate-200">
                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900/80">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">${weekStart.toFormat('MMMM yyyy')}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('MMM d, yyyy')}</p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-md border border-slate-300 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                        ${appointments.length} ${appointments.length === 1 ? 'event' : 'events'}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <div class="min-w-[980px]">
                        <div class="week-timeline-grid border-l border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950">
                            <div class="grid grid-cols-[56px_repeat(7,minmax(120px,1fr))]">
                                <div class="h-[48px] border-r border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950"></div>
                            ${dayEntries.map(({ day, isToday }) => `
                                    <div class="h-[48px] border-r border-b border-slate-200 dark:border-slate-800 text-center py-2 ${isToday ? 'bg-indigo-50 dark:bg-slate-900' : 'bg-slate-50 dark:bg-slate-950'}">
                                    <div class="text-[10px] font-medium uppercase tracking-wide ${isToday ? 'text-indigo-600 dark:text-indigo-300' : 'text-slate-500 dark:text-slate-400'}">${day.toFormat('ccc')}</div>
                                    <div class="mt-0.5 ${isToday ? 'inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-500 text-white text-xs font-semibold' : 'text-xs font-semibold text-slate-700 dark:text-slate-200'}">${day.day}</div>
                                </div>
                            `).join('')}

                            ${timeRows.map(row => `
                                <div class="min-h-[48px] px-2 pt-1.5 border-b border-r border-slate-200 dark:border-slate-800 text-[11px] font-medium text-slate-500 dark:text-slate-500 bg-slate-50 dark:bg-slate-950">${row.label}</div>
                                ${dayEntries.map(({ appointmentsByHour }) => {
                                    const rowAppointments = appointmentsByHour[row.hour] || [];
                                    const maxVisible = 3;
                                    const visibleAppointments = rowAppointments.slice(0, maxVisible);
                                    const hiddenAppointments = rowAppointments.slice(maxVisible);
                                    const hiddenCount = hiddenAppointments.length;

                                    return `<div class="min-h-[48px] border-b border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-1 py-0.5">
                                        <div class="space-y-0.5">
                                            ${visibleAppointments.map(renderAppointmentCard).join('')}
                                            ${hiddenAppointments.map(apt => renderAppointmentCard(apt).replace('class="week-timeline-event', 'class="week-timeline-event hidden week-timeline-overflow')).join('')}
                                            ${hiddenCount > 0 ? `<button type="button" class="week-expand-hour text-[10px] font-semibold text-indigo-600 dark:text-indigo-300 hover:text-indigo-800 dark:hover:text-indigo-100 cursor-pointer transition-colors w-full text-left px-1" data-expand-count="${hiddenCount}">+${hiddenCount} more</button>` : ''}
                                        </div>
                                    </div>`;
                                }).join('')}
                            `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add click handlers for appointments in the section
        this.attachWeeklySectionListeners(dailySection, data);
    }
    
    /**
     * Attach event listeners to appointments in the weekly section
     */
    attachWeeklySectionListeners(section, data) {
        // Appointment click handlers
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

        // Expand-hour handlers: reveal hidden overflow appointments
        section.querySelectorAll('.week-expand-hour').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const container = btn.parentElement;
                if (!container) return;
                container.querySelectorAll('.week-timeline-overflow.hidden').forEach(item => {
                    item.classList.remove('hidden');
                });
                btn.remove();
            });
        });
    }
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
