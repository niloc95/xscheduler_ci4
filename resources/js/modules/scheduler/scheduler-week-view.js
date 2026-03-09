/**
 * Custom Scheduler - Week View (7-Column Time Grid)
 * 
 * Modern week calendar with 7-day time grid:
 * - Monday-first 7 columns (one per day)
 * - 60px row height per hour (--hour-height CSS var)
 * - Appointments absolutely positioned by time + duration
 * - Overlapping appointments split horizontally
 * - Now-line spans today's column
 * - Today column has distinct background
 * 
 * Uses time-grid-utils.js for overlap resolution and positioning.
 */

import { DateTime } from 'luxon';
import { getProviderColor } from './appointment-colors.js';
import { topPx, heightPx, weekStart, getBusinessHours } from './time-grid-utils.js';
import { escapeHtml } from '../../utils/html.js';

export class WeekView {
    constructor(scheduler) {
        this.scheduler = scheduler;
    }

    debugLog(...args) {
        if (this.scheduler?.debugLog) {
            this.scheduler.debugLog(...args);
        } else if (typeof window !== 'undefined' && window.appConfig?.debug) {
            console.log(...args);
        }
    }

    render(container, data) {
        const { currentDate, appointments, providers, config, settings, calendarModel } = data;
        
        this.debugLog('📅 WeekView.render called (7-column time grid)');
        this.debugLog('   Appointments received:', appointments.length);
        
        // Store for internal use
        this.appointments = appointments;
        this.providers = providers;
        this.settings = settings;
        this.config = config;
        this.currentDate = currentDate;
        this.container = container;

        // Prefer server-side business hours when available.
        const businessHours = this._resolveBusinessHours(config, calendarModel);
        this.businessHours = businessHours;

        // Calculate week days (prefer model dates to keep UI data-driven)
        const days = this._resolveWeekDays(currentDate, settings, config, calendarModel);
        const weekStartDate = days[0];
        this.days = days;
        this.weekStart = weekStartDate;
        this.weekEnd = weekStartDate.plus({ days: 6 }).endOf('day');

        const today = DateTime.now().setZone(this.scheduler.options.timezone).startOf('day');

        const appointmentsByDay = this._buildAppointmentsByDay(days, data);

        // Render view
        container.innerHTML = `
            <div class="week-view-grid p-4">
                ${this._renderHeader(days, today, appointmentsByDay)}
                ${this._renderTimeline(days, today, appointmentsByDay)}
            </div>
        `;

        // Attach event listeners
        this._attachListeners(container, data);
        
        // Start now-line updater
        this._startNowLineUpdater();
    }

    /**
     * Render week header with day names and dates.
     */
    _renderHeader(days, today, appointmentsByDay) {
        const dayHeaders = days.map(day => {
            const isToday = day.hasSame(today, 'day');
            const dateKey = day.toISODate();
            const apptCount = appointmentsByDay[dateKey]?.length || 0;
            
            return `
                <div class="text-center ${isToday ? 'text-primary-600 dark:text-primary-400 font-semibold' : 'text-gray-700 dark:text-gray-300'}">
                    <div class="text-xs uppercase tracking-wide mb-1">${day.toFormat('EEE')}</div>
                    <button type="button" 
                            class="week-day-number inline-flex items-center justify-center w-8 h-8 rounded-full transition-colors ${
                                isToday 
                                    ? 'bg-primary-600 text-white font-bold' 
                                    : 'hover:bg-gray-100 dark:hover:bg-gray-700'
                            }"
                            data-date="${dateKey}"
                            title="View ${day.toFormat('MMMM d')}">
                        ${day.day}
                    </button>
                    ${apptCount > 0 ? `<div class="text-xs text-gray-500 dark:text-gray-400 mt-1">${apptCount} appt${apptCount !== 1 ? 's' : ''}</div>` : ''}
                </div>
            `;
        }).join('');

        return `
            <div class="grid grid-cols-8 gap-2 mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide flex items-end pb-2">
                    Time
                </div>
                ${dayHeaders}
            </div>
        `;
    }

    /**
     * Render time grid with appointments.
     */
    _renderTimeline(days, today, appointmentsByDay) {
        const { startHour, endHour } = this.businessHours;
        const totalHeight = Math.max(1, (endHour - startHour) * 60);
        const hours = [];
        for (let h = startHour; h < endHour; h++) {
            hours.push(h);
        }

        const timeLabels = hours.map((hour, idx) => {
            const top = idx * 60;
            return `
                <div class="timeline-time-label" style="top:${top}px;">
                    ${this._formatHour(hour)}
                </div>
            `;
        }).join('');

        const dayColumnsHtml = days.map(day => {
            const isToday = day.hasSame(today, 'day');
            const dateKey = day.toISODate();
            const dayAppts = appointmentsByDay[dateKey] || [];

            const hourLines = hours.map((hour, idx) => {
                const top = idx * 60;
                return `<div class="timeline-hour-line" style="top:${top}px;"></div>`;
            }).join('');

            const appointmentsHtml = dayAppts.map(apt => this._renderAppointmentBlock(apt)).join('');

            return `
                <div class="timeline-column ${isToday ? 'time-grid-col--today' : ''}" 
                     data-date="${dateKey}"
                     style="height:${totalHeight}px;">
                    ${hourLines}
                    ${appointmentsHtml}
                </div>
            `;
        }).join('');

        return `
            <div class="timeline-shell" style="grid-template-columns: 60px repeat(7, 1fr);">
                <div class="timeline-time-column" style="height:${totalHeight}px;">
                    ${timeLabels}
                </div>
                ${dayColumnsHtml}
                ${this._renderNowLine(days, today)}
            </div>
        `;
    }

    /**
     * Render a single appointment block.
     */
    _renderAppointmentBlock(appointment) {
        const { startHour } = this.businessHours;
        const provider = this.providers.find(p => Number(p.id) === Number(appointment.providerId));
        const providerColor = getProviderColor(provider);

        const top = Number.isFinite(appointment._topPx) ? appointment._topPx : topPx(appointment.startDateTime, startHour);
        const durationMinutes = appointment.endDateTime.diff(appointment.startDateTime, 'minutes').minutes;
        const height = Number.isFinite(appointment._heightPx) ? appointment._heightPx : heightPx(durationMinutes);
        const colWidth = Number.isFinite(appointment._widthPct) ? appointment._widthPct : (appointment._colWidth || 100);
        const colLeft = Number.isFinite(appointment._leftPct) ? appointment._leftPct : (appointment._colLeft || 0);
        const zIndex = Number.isFinite(appointment._colIndex) ? appointment._colIndex + 1 : ((appointment._col || 0) + 1);
        
        const customerName = appointment.customerName || appointment.title || 'Appointment';
        const serviceName = appointment.serviceName || '';
        
        const timeFormat = this.settings?.getTimeFormat?.() === '24h' ? 'HH:mm' : 'h:mm a';
        const startTime = appointment.startDateTime.toFormat(timeFormat);

        return `
            <div class="appointment-block absolute inset-x-0 px-1"
                 style="
                     top: ${top}px;
                     height: ${height}px;
                     left: ${colLeft}%;
                     width: ${colWidth}%;
                     background-color: ${providerColor}20;
                     border-left-color: ${providerColor};
                     z-index: ${zIndex};
                 "
                 data-appointment-id="${appointment.id}"
                 title="${customerName} - ${serviceName}">
                <div class="text-xs font-medium truncate" style="color: ${providerColor};">
                    ${startTime}
                </div>
                <div class="text-xs font-semibold truncate text-gray-900 dark:text-white">
                    ${escapeHtml(customerName)}
                </div>
                ${serviceName ? `<div class="text-xs truncate text-gray-600 dark:text-gray-400">${escapeHtml(serviceName)}</div>` : ''}
            </div>
        `;
    }

    /**
     * Render now-line (current time indicator).
     */
    _renderNowLine(days, today) {
        const now = DateTime.now().setZone(this.scheduler.options.timezone);
        const todayColumn = days.findIndex(day => day.hasSame(today, 'day'));
        
        if (todayColumn === -1) return ''; // Not in current week
        
        const { startHour } = this.businessHours;
        const top = topPx(now, startHour);
        
        // Position relative to the grid (accounting for time column)
        const gridColumnStart = todayColumn + 2; // +1 for 1-based, +1 for time column
        
        return `
            <div class="now-line absolute pointer-events-none" 
                 style="
                     top: ${top}px;
                     left: calc((100% / 8) * ${gridColumnStart - 1});
                     width: calc(100% / 8);
                 "
                 id="week-now-line">
            </div>
        `;
    }

    _resolveBusinessHours(config, calendarModel) {
        const fromModel = calendarModel?.businessHours;
        if (fromModel?.startTime && fromModel?.endTime) {
            return getBusinessHours({ businessHours: fromModel });
        }
        return getBusinessHours(config);
    }

    _resolveWeekDays(currentDate, settings, config, calendarModel) {
        if (Array.isArray(calendarModel?.days) && calendarModel.days.length === 7) {
            return calendarModel.days.map(day =>
                DateTime.fromISO(String(day.date), { zone: this.scheduler.options.timezone }).startOf('day')
            );
        }

        const firstDayOfWeek = settings?.getFirstDayOfWeek?.() ?? config?.firstDayOfWeek ?? 1;
        const weekStartDate = weekStart(currentDate, firstDayOfWeek);
        const days = [];
        for (let i = 0; i < 7; i++) {
            days.push(weekStartDate.plus({ days: i }));
        }
        return days;
    }

    _buildAppointmentsByDay(days, data) {
        const fromModel = this._extractFromWeekModel(data.calendarModel);
        if (fromModel && this._hasAnyAppointments(fromModel)) {
            return fromModel;
        }

        // Backend-provided appointments already include layout metadata from EventLayoutService.
        // No client-side overlap resolution needed.
        const weekAppointments = data.appointments.filter(apt => apt.startDateTime >= this.weekStart && apt.startDateTime <= this.weekEnd);
        const appointmentsByDay = {};
        days.forEach(day => {
            const dateKey = day.toISODate();
            const dayAppts = weekAppointments.filter(apt => apt.startDateTime.hasSame(day, 'day'));
            appointmentsByDay[dateKey] = dayAppts;
        });
        return appointmentsByDay;
    }

    _hasAnyAppointments(grouped = {}) {
        return Object.values(grouped).some(list => Array.isArray(list) && list.length > 0);
    }

    _extractFromWeekModel(calendarModel) {
        if (!Array.isArray(calendarModel?.days)) {
            return null;
        }

        const mapped = {};
        calendarModel.days.forEach(day => {
            const dateKey = day.date;
            const slotAppts = [];

            ((day.dayGrid?.slots) || []).forEach(slot => {
                (slot.appointments || []).forEach(appt => {
                    slotAppts.push(this._normalizeModelAppointment(appt));
                });
            });

            mapped[dateKey] = slotAppts;
        });

        return mapped;
    }

    _normalizeModelAppointment(modelAppointment) {
        const startIso = modelAppointment.start || modelAppointment.start_datetime || modelAppointment.startDateTime;
        const endIso = modelAppointment.end || modelAppointment.end_datetime || modelAppointment.endDateTime;
        const startDateTime = DateTime.fromISO(String(startIso), { zone: 'utc', setZone: true }).setZone(this.scheduler.options.timezone);
        const endDateTime = DateTime.fromISO(String(endIso), { zone: 'utc', setZone: true }).setZone(this.scheduler.options.timezone);

        return {
            ...modelAppointment,
            id: Number(modelAppointment.id),
            providerId: Number(modelAppointment.provider_id ?? modelAppointment.providerId),
            customerName: modelAppointment.customer_name ?? modelAppointment.customerName,
            serviceName: modelAppointment.service_name ?? modelAppointment.serviceName,
            startDateTime,
            endDateTime,
        };
    }

    /**
     * Format hour for display.
     */
    _formatHour(hour) {
        const is24h = this.settings?.getTimeFormat?.() === '24h';
        if (is24h) {
            return `${hour.toString().padStart(2, '0')}:00`;
        } else {
            const period = hour < 12 ? 'AM' : 'PM';
            const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
            return `${displayHour} ${period}`;
        }
    }

    /**
     * Attach event listeners.
     */
    _attachListeners(container, data) {
        // Appointment clicks
        container.querySelectorAll('.appointment-block').forEach(block => {
            block.addEventListener('click', () => {
                const aptId = parseInt(block.dataset.appointmentId, 10);
                const appointment = data.appointments.find(a => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    data.onAppointmentClick(appointment);
                }
            });
        });

        // Day number clicks (navigate to day view)
        container.querySelectorAll('.week-day-number').forEach(btn => {
            btn.addEventListener('click', () => {
                const date = btn.dataset.date;
                const targetDate = DateTime.fromISO(date, { zone: this.scheduler.options.timezone });
                this.scheduler.navigateToDate(targetDate);
                this.scheduler.changeView('day');
            });
        });
    }

    /**
     * Start now-line updater (updates every minute).
     */
    _startNowLineUpdater() {
        // Clear existing timer
        if (this._nowLineTimer) {
            clearInterval(this._nowLineTimer);
        }

        // Update now-line position every minute
        this._nowLineTimer = setInterval(() => {
            const nowLine = document.getElementById('week-now-line');
            if (!nowLine) {
                clearInterval(this._nowLineTimer);
                return;
            }

            const now = DateTime.now().setZone(this.scheduler.options.timezone);
            const { startHour } = this.businessHours;
            const top = topPx(now, startHour);
            nowLine.style.top = `${top}px`;
        }, 60000); // Update every minute
    }
}
