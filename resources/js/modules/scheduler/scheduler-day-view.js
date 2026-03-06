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
import { getStatusColors, getProviderColor, getProviderInitials, isDarkMode } from './appointment-colors.js';
import { withBaseUrl } from '../../utils/url-helpers.js';
import { buildAvailabilityContext, isAvailabilityDebugMode, renderAvailabilityDebugPayload, renderAvailabilitySlotList } from './availability-panel-shared.js';
import { buildAppointmentCountsByDate, buildMonthGridDays, getRotatedWeekdayInitials } from './calendar-grid-shared.js';
import { renderAvailabilityBlock, renderAppointmentCard, renderProviderPanel, renderSchedulerHeader, renderTimeColumn } from './day-view-components.js';

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
        this.container = container;
        
        // Business hours & slot duration from config
        this.businessHours = {
            startTime: config?.slotMinTime || '08:00',
            endTime: config?.slotMaxTime || '17:00',
        };
        let slotDur = config?.slotDuration || 30;
        if (typeof slotDur === 'string' && slotDur.includes(':')) {
            const parts = slotDur.split(':');
            slotDur = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
        }
        this.slotDuration = parseInt(slotDur, 10) || 30;
        this.blockedPeriods = config?.blockedPeriods || [];
        
        // Check if this date is blocked
        const blockedPeriods = this.blockedPeriods;
        const isBlocked = this.isDateBlocked(currentDate, blockedPeriods);
        const blockedInfo = isBlocked ? this.getBlockedPeriodInfo(currentDate, blockedPeriods) : null;
        
        // Check if this is a non-working day
        const isNonWorkingDay = settings?.isWorkingDay ? !settings.isWorkingDay(currentDate.weekday % 7) : false;

        // Filter appointments for this day and sort by time
        const dayAppointments = appointments.filter(apt => 
            apt.startDateTime.hasSame(currentDate, 'day')
        ).sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());

        const schedulerHeader = renderSchedulerHeader({ currentDate, isBlocked, isNonWorkingDay });
        const timeColumn = renderTimeColumn({
            startTime: this.businessHours.startTime,
            endTime: this.businessHours.endTime,
            slotDuration: this.slotDuration,
        });
        const availabilityBlock = renderAvailabilityBlock({ panelHtml: this._renderDaySlotPanel() });
        const providerPanel = renderProviderPanel({
            miniCalendarHtml: this.renderMiniCalendar(),
            addAppointmentUrl: withBaseUrl(`/appointments/create?date=${this.currentDate.toISODate()}`),
            daySummaryHtml: this.renderDaySummary(dayAppointments),
            timeColumnHtml: timeColumn,
            availabilityBlockHtml: availabilityBlock,
        });

        // Render the two-panel layout - Right panel first in DOM for proper layout
        container.innerHTML = `
            <div class="scheduler-day-view bg-surface-0 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex flex-col-reverse md:flex-row gap-4 p-4">
                    
                    <!-- Left Panel: Appointments List (appears second in mobile, first in desktop) -->
                    <div class="flex-1 min-w-0 order-2 md:order-1">
                        ${schedulerHeader}
                        
                        ${isBlocked ? `
                            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                                <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                                    <span class="material-symbols-outlined text-lg">block</span>
                                    <span class="text-sm font-medium">${escapeHtml(blockedInfo?.notes || 'This day is blocked for appointments')}</span>
                                </div>
                            </div>
                        ` : ''}
                        
                        <!-- Appointments List with scrollable area -->
                        <div class="space-y-1 max-h-[calc(100vh-300px)] overflow-y-auto bg-surface-1 dark:bg-gray-700/40 rounded-xl p-2" id="day-view-appointments-list">
                            ${dayAppointments.length > 0 ? 
                                dayAppointments.map((apt, idx) => this.renderAppointmentRow(apt, idx === dayAppointments.length - 1)).join('') :
                                this.renderEmptyState(isBlocked, isNonWorkingDay)
                            }
                        </div>
                    </div>
                    
                    <!-- Right Panel: Provider/Availability -->
                    ${providerPanel}
                </div>
            </div>
        `;

        // Attach event listeners
        this.attachEventListeners(container, data);
        this._refreshDaySlotPanel();
        
        // Hide the daily-provider-appointments section in Day View (redundant)
        const dailySection = document.getElementById('daily-provider-appointments');
        if (dailySection) {
            dailySection.classList.add('hidden');
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
        
        const serviceName = appointment.serviceName || 'Appointment';
        const location = appointment.locationName || '';
        
        // Format time using localization settings
        const is24Hour = this.settings?.getTimeFormat?.() === '24h';
        const timeFormat = is24Hour ? 'HH:mm' : 'h:mm a';
        const dateTimeDisplay = appointment.startDateTime.toFormat(`MMMM d'${this.getOrdinalSuffix(appointment.startDateTime.day)}', yyyy 'at' ${timeFormat}`);
        
        const providerInitial = getProviderInitials(provider?.name);
        const providerName = provider?.name || 'Unknown Provider';

        return renderAppointmentCard({
            appointment,
            providerColor,
            statusColors,
            dateTimeDisplay,
            serviceName,
            location,
            providerInitial,
            providerName,
            isLast,
        });
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
        
        // Get first day of week setting
        const firstDayOfWeek = this.settings?.getFirstDayOfWeek?.() || 0;

        const days = buildMonthGridDays(currentMonth, firstDayOfWeek);
        const rotatedDays = getRotatedWeekdayInitials(firstDayOfWeek);
        const appointmentCounts = buildAppointmentCountsByDate(this.appointments);
        
        return `
            <div class="bg-surface-1 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
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
            <div class="mt-4 bg-surface-1 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
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
                                        <span class="w-2 h-2 rounded-full" data-bg-color="${colors.dot}"></span>
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
                                            <span class="w-2 h-2 rounded-full" data-bg-color="${color}"></span>
                                            <span class="text-gray-600 dark:text-gray-400 truncate max-w-[120px]">${escapeHtml(name)}</span>
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
        
        // Attach slot panel listeners
        this._attachSlotPanelListeners();
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

    // ── Available Slots helpers ───────────────────────────────────────

    /**
     * Render the full Available Slots panel for the current day
     */
    _renderDaySlotPanel() {
        return `<div class="slot-panel" id="day-slot-panel-inner">
            <div class="slot-panel__header">
                <h3 class="slot-panel__title">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">event_available</span>
                    Available Slots
                </h3>
                <div class="slot-panel__provider-count" id="day-slot-provider-count"></div>
            </div>
            <div class="slot-panel__slots">
                <div class="slot-panel__slots-header">
                    <span class="slot-panel__slots-label">Time Slots</span>
                    <span class="slot-panel__slot-count" id="day-slot-count"></span>
                </div>
                <div class="slot-panel__slot-list" id="day-slot-list">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Loading availability...</div>
                </div>
            </div>
        </div>`;
    }

    /**
     * Re-render slot panel (after provider filter toggle)
     */
    _updateSlotPanel() {
        const panelEl = this.container?.querySelector('#day-slot-panel');
        if (!panelEl) return;
        panelEl.innerHTML = this._renderDaySlotPanel();
        this._refreshDaySlotPanel();
    }

    /**
     * Attach event listeners to the slot panel inside the Day view.
     */
    _attachSlotPanelListeners() {
        // no-op: slot panel is API-driven
    }

    _getAvailabilityContext() {
        const visibleProviderIds = this.scheduler?.visibleProviders
            ? Array.from(this.scheduler.visibleProviders)
            : [];

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

    async _refreshDaySlotPanel() {
        const listEl = this.container?.querySelector('#day-slot-list');
        const countEl = this.container?.querySelector('#day-slot-count');
        const providerCountEl = this.container?.querySelector('#day-slot-provider-count');
        if (!listEl) return;

        const context = this._getAvailabilityContext();
        if (!context.ready) {
            listEl.innerHTML = `<div class="text-sm text-gray-500 dark:text-gray-400">${context.message}</div>${this._renderDebugPayload(null)}`;
            if (countEl) countEl.textContent = '';
            if (providerCountEl) providerCountEl.textContent = '';
            return;
        }

        if (providerCountEl) {
            providerCountEl.innerHTML = `<span class="material-symbols-outlined text-sm">group</span><span>1 provider</span>`;
        }

        const date = this.currentDate?.toISODate?.();
        if (!date) {
            listEl.innerHTML = `<div class="text-sm text-gray-500 dark:text-gray-400">Select a date to see availability.</div>${this._renderDebugPayload({
                providerId: context.providerId,
                serviceId: context.serviceId,
                locationId: context.locationId ?? null,
                date: null,
                timezone: 'UTC',
            })}`;
            if (countEl) countEl.textContent = '';
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
                listEl.innerHTML = `<div class="text-sm text-red-500">${data?.error?.message || 'Failed to load slots'}</div>${this._renderDebugPayload({
                    providerId: context.providerId,
                    serviceId: context.serviceId,
                    locationId: context.locationId ?? null,
                    date,
                    timezone: 'UTC',
                })}`;
                if (countEl) countEl.textContent = '';
                return;
            }

            const slots = data?.data?.slots || [];
            if (countEl) countEl.textContent = `${slots.length} slot${slots.length !== 1 ? 's' : ''}`;

            listEl.innerHTML = `${this._renderSlotListFromApi(slots, date, context.providerId, context.serviceId)}${this._renderDebugPayload({
                providerId: context.providerId,
                serviceId: context.serviceId,
                locationId: data?.data?.location_id ?? context.locationId ?? null,
                date,
                timezone: data?.data?.timezone ?? 'UTC',
            })}`;
        } catch (error) {
            console.error('Failed to load availability slots:', error);
            listEl.innerHTML = `<div class="text-sm text-red-500">Failed to load slots</div>${this._renderDebugPayload({
                providerId: context.providerId,
                serviceId: context.serviceId,
                locationId: context.locationId ?? null,
                date,
                timezone: 'UTC',
            })}`;
            if (countEl) countEl.textContent = '';
        }
    }

    _renderSlotListFromApi(slots, date, providerId, serviceId) {
        return renderAvailabilitySlotList(slots, date, providerId, serviceId);
    }

    isDateBlocked(date, periods = []) {
        if (!Array.isArray(periods) || periods.length === 0) return false;
        const target = date.toISODate ? date.toISODate() : String(date);
        return periods.some(period => {
            if (!period?.start || !period?.end) return false;
            return target >= period.start && target <= period.end;
        });
    }

    getBlockedPeriodInfo(date, periods = []) {
        if (!Array.isArray(periods) || periods.length === 0) return null;
        const target = date.toISODate ? date.toISODate() : String(date);
        return periods.find(period => period?.start && period?.end && target >= period.start && target <= period.end) || null;
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
