/**
 * Custom Scheduler - Day View (Per-Provider Columns)
 *
 * Shows detailed single-day view with provider columns:
 * - One vertical column per visible provider (side by side)
 * - Expanded 100px/hour height (vs 60px in week view) to use available space
 * - Dynamic appointment cards with adaptive content by rendered pixel height
 * - Overlapping appointments within column split horizontally
 * - Now-line spans all provider columns
 * - Provider headers show avatar + name + count
 *
 * Uses time-grid-utils.js for positioning helpers.
 * Overlap layout metadata (_widthPct, _leftPct) is resolved server-side.
 */

import { DateTime } from 'luxon';
import { getProviderColor, getProviderInitials } from './appointment-colors.js';
import { getBusinessHours } from './time-grid-utils.js';
import { escapeHtml } from '../../utils/html.js';
import {
    DAY_VIEW_HOUR_HEIGHT_PX,
    DAY_VIEW_MIN_HEIGHT_PX,
    DAY_VIEW_SLOT_MARGIN_PX,
} from './scheduler-day-view-config.js';

// Adaptive content tiers — px thresholds for how much info to show in appointment cards
const TIER_TIME_ONLY = 35;
const TIER_NAME = 65;
const TIER_SERVICE = 100;
const TIER_STATUS = 150;

// Status colors as Tailwind class sets — day-view uses class-based styling (not hex)
const STATUS_META = {
    confirmed:   { label: 'Confirmed',  bg: 'bg-emerald-100 dark:bg-emerald-900/40', text: 'text-emerald-700 dark:text-emerald-300', dot: 'bg-emerald-500' },
    pending:     { label: 'Pending',    bg: 'bg-amber-100 dark:bg-amber-900/40',   text: 'text-amber-700 dark:text-amber-300',   dot: 'bg-amber-500' },
    cancelled:   { label: 'Cancelled',  bg: 'bg-red-100 dark:bg-red-900/40',       text: 'text-red-700 dark:text-red-300',       dot: 'bg-red-500' },
    completed:   { label: 'Completed',  bg: 'bg-blue-100 dark:bg-blue-900/40',     text: 'text-blue-700 dark:text-blue-300',     dot: 'bg-blue-500' },
    'no-show':   { label: 'No Show',   bg: 'bg-gray-100 dark:bg-gray-700/60',     text: 'text-gray-600 dark:text-gray-300',     dot: 'bg-gray-400' },
    rescheduled: { label: 'Rescheduled', bg: 'bg-purple-100 dark:bg-purple-900/40', text: 'text-purple-700 dark:text-purple-300', dot: 'bg-purple-500' },
};
const statusMeta = (status) => STATUS_META[status] ?? STATUS_META.pending;

export class DayView {
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

        this.debugLog('DayView.render called (per-provider columns)');
        this.debugLog('Appointments received:', appointments.length);

        this.appointments = appointments;
        this.providers = providers;
        this.settings = settings;
        this.config = config;
        this.currentDate = currentDate;
        this.container = container;

        const businessHours = this._resolveBusinessHours(config, calendarModel);
        this.businessHours = businessHours;

        const visibleProviders = providers.filter((p) => this.scheduler.visibleProviders.has(p.id));
        this.visibleProviders = visibleProviders;

        if (visibleProviders.length === 0) {
            container.innerHTML = this._renderEmptyState('No providers selected', 'person_off');
            return;
        }

        const appointmentsByProvider = this._buildAppointmentsByProvider(visibleProviders, data);

        const today = DateTime.now().setZone(this.scheduler.options.timezone).startOf('day');
        const isToday = currentDate.hasSame(today, 'day');

        container.innerHTML = `
            <div class="day-view-grid p-4">
                ${this._renderHeader(currentDate, isToday)}
                ${this._renderProviderHeaders(visibleProviders, appointmentsByProvider)}
                ${this._renderTimeline(visibleProviders, appointmentsByProvider, data, isToday)}
            </div>
        `;

        this._attachListeners(container, data);

        if (isToday) {
            this._startNowLineUpdater();
        }
    }

    _renderHeader(currentDate, isToday) {
        return `
            <div class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold ${isToday ? 'text-primary-600 dark:text-primary-400' : 'text-gray-900 dark:text-white'}">
                    ${currentDate.toFormat('EEEE, MMMM d, yyyy')}
                    ${isToday ? '<span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-2">(Today)</span>' : ''}
                </h2>
            </div>
        `;
    }

    _renderProviderHeaders(visibleProviders, appointmentsByProvider) {
        const providerHeadersHtml = visibleProviders.map((provider) => {
            const providerColor = getProviderColor(provider);
            const initials = getProviderInitials(provider.name || provider.username);
            const apptCount = appointmentsByProvider[provider.id]?.length || 0;

            return `
                <div class="px-1 min-w-0">
                    <div class="flex items-center justify-center gap-2 min-w-0">
                        <div class="provider-avatar w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold shadow-sm flex-shrink-0"
                             style="background-color: ${providerColor};">
                            ${initials}
                        </div>
                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-[140px] text-left">
                            ${escapeHtml(provider.name || provider.username)}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap flex-shrink-0">
                            ${apptCount} appt${apptCount !== 1 ? 's' : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        return `
            <div class="grid gap-2 mb-4" style="grid-template-columns: 64px repeat(${visibleProviders.length}, 1fr);">
                <div></div>
                ${providerHeadersHtml}
            </div>
        `;
    }

    _renderTimeline(visibleProviders, appointmentsByProvider, data, isToday) {
        const timeRange = this._calculateTimelineRange(visibleProviders, data.calendarModel);
        const totalHeight = this._calcTotalHeight(timeRange.startTime, timeRange.endTime);

        const timeSlots = this._generate15MinSlots(timeRange.startTime, timeRange.endTime);

        const startMinutes = this._timeToMinutes(timeRange.startTime);
        const endMinutes = this._timeToMinutes(timeRange.endTime);
        this._activeTimelineStartMinutes = startMinutes;
        this._activeTimelineEndMinutes = endMinutes;

        const timeLabels = timeSlots
            .filter((slot) => slot.minute === 0 || slot.minute === 30)
            .map((slot) => {
            const top = this._topPxDay(
                DateTime.fromObject(
                    { hour: slot.hour, minute: slot.minute },
                    { zone: this.scheduler.options.timezone },
                ),
                startMinutes,
            );
            const isHour = slot.minute === 0;
            return `
                <div class="timeline-time-label absolute right-2 -translate-y-1/2 select-none whitespace-nowrap ${isHour ? 'text-xs font-semibold text-gray-500 dark:text-gray-400' : 'text-[11px] font-medium text-gray-400 dark:text-gray-500'}"
                     style="top:${top}px;">
                    ${isHour ? this._formatHour(slot.hour) : this._formatHalfHour(slot.hour)}
                </div>
            `;
            })
            .join('');

        const providerColumnsHtml = visibleProviders.map((provider) => {
            const providerAppts = appointmentsByProvider[provider.id] || [];
            const providerRange = this._getProviderTimeRange(provider.id, data.calendarModel, timeRange);
            const layoutMap = this._resolveOverlapLayout(providerAppts);

            const hourLines = timeSlots.map((slot) => {
                const top = this._topPxDay(
                    DateTime.fromObject(
                        { hour: slot.hour, minute: slot.minute },
                        { zone: this.scheduler.options.timezone },
                    ),
                    startMinutes,
                );
                if (slot.minute === 0) {
                    return `<div class="timeline-hour-line absolute left-0 right-0 border-t border-gray-100 dark:border-gray-700/60" style="top:${top}px;"></div>`;
                }
                if (slot.minute === 30) {
                    return `<div class="absolute left-0 right-0 border-t border-dashed border-gray-100 dark:border-gray-700/50 pointer-events-none" style="top:${top}px;"></div>`;
                }
                return `<div class="absolute left-0 right-0 border-t border-dotted border-gray-100/80 dark:border-gray-700/35 pointer-events-none" style="top:${top}px;"></div>`;
            }).join('');

            const appointmentsHtml = providerAppts
                .map((apt) => this._renderAppointmentBlock(apt, provider, timeRange, layoutMap.get(Number(apt.id)) || null))
                .join('');

            const isProviderToday = this.currentDate.hasSame(
                DateTime.now().setZone(this.scheduler.options.timezone),
                'day',
            );

            return `
                <div class="timeline-column relative border-l border-gray-100 dark:border-gray-700/50 ${isProviderToday ? 'time-grid-col--today' : ''}"
                     data-provider-id="${provider.id}"
                     data-working-start="${providerRange.startTime}"
                     data-working-end="${providerRange.endTime}"
                     style="height:${totalHeight}px;">
                    ${hourLines}
                    ${appointmentsHtml}
                    ${!providerRange.isActive ? this._renderNonWorkingDayOverlay() : ''}
                </div>
            `;
        }).join('');

        return `
            <div class="timeline-shell relative grid gap-0"
                 style="grid-template-columns: 64px repeat(${visibleProviders.length}, 1fr);">
                <div class="timeline-time-column relative" style="height:${totalHeight}px;">
                    ${timeLabels}
                </div>
                ${providerColumnsHtml}
                ${isToday ? this._renderNowLine(timeRange) : ''}
            </div>
        `;
    }

    _renderAppointmentBlock(appointment, provider, timeRange, layout = null) {
        const startMinutes = timeRange ? this._timeToMinutes(timeRange.startTime) : (this.businessHours.startHour * 60);
        const providerColor = getProviderColor(provider);

        const top = this._topPxDay(appointment.startDateTime, startMinutes);
        const height = this._heightPxDay(
            appointment.endDateTime.diff(appointment.startDateTime, 'minutes').minutes,
        );

        const totalCols = Math.max(1, Number(layout?.totalCols || 1));
        const colIndex = Math.max(0, Number(layout?.column || 0));
        const colWidth = 100 / totalCols;
        const colLeft = colIndex * colWidth;

        const zIndex = colIndex + 1;
        const customerName = appointment.customerName || appointment.title || 'Appointment';
        const serviceName = appointment.serviceName || '';
        const status = appointment.status || 'pending';
        const location = appointment.location || appointment.locationName || '';
        const notes = appointment.notes || '';
        const meta = statusMeta(status);

        const timeFormat = this.settings?.getTimeFormat?.() === '24h' ? 'HH:mm' : 'h:mm a';
        const startTime = appointment.startDateTime.toFormat(timeFormat);
        const endTime = appointment.endDateTime?.toFormat(timeFormat) ?? '';
        const durationMin = appointment.endDateTime.diff(appointment.startDateTime, 'minutes').minutes;
        const compactStatusPill = `
            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold flex-shrink-0 ${meta.bg} ${meta.text}">
                <span class="w-1.5 h-1.5 rounded-full ${meta.dot}"></span>
                ${meta.label}
            </span>
        `;
        const statusDot = `<span class="w-2 h-2 rounded-full flex-shrink-0 ${meta.dot}" title="${meta.label}"></span>`;

        const lightBg = `${providerColor}18`;
        const borderCol = providerColor;

        let innerHtml;

        if (height < TIER_TIME_ONLY) {
            innerHtml = `
                <div class="flex items-center gap-1 h-full overflow-hidden px-1.5">
                    <span class="text-[10px] font-semibold leading-none whitespace-nowrap" style="color:${providerColor};">${startTime}</span>
                    ${statusDot}
                    <span class="text-[10px] font-medium truncate text-gray-700 dark:text-gray-200 leading-none">${escapeHtml(customerName)}</span>
                </div>
            `;
        } else if (height < TIER_NAME) {
            innerHtml = `
                <div class="flex flex-col justify-center h-full overflow-hidden px-2 py-1 gap-0.5">
                    <div class="flex items-center justify-between gap-1">
                        <div class="text-[10px] font-semibold leading-none truncate" style="color:${providerColor};">${startTime}${endTime ? ` - ${endTime}` : ''}</div>
                        ${compactStatusPill.replace('text-[9px]', 'text-[8px]').replace('px-1.5 py-0.5', 'px-1 py-0.5')}
                    </div>
                    <div class="text-xs font-semibold truncate text-gray-900 dark:text-white leading-snug">${escapeHtml(customerName)}</div>
                </div>
            `;
        } else if (height < TIER_SERVICE) {
            innerHtml = `
                <div class="flex flex-col h-full overflow-hidden px-2 py-1.5 gap-1">
                    <div class="flex items-center justify-between gap-1.5">
                        <div class="text-[10px] font-semibold leading-none truncate" style="color:${providerColor};">${startTime}${endTime ? ` - ${endTime}` : ''}</div>
                        ${compactStatusPill}
                    </div>
                    <div class="text-xs font-bold truncate text-gray-900 dark:text-white leading-snug">${escapeHtml(customerName)}</div>
                    ${serviceName ? `<div class="text-[11px] truncate text-gray-500 dark:text-gray-400 leading-none flex items-center gap-1"><span class="material-symbols-outlined text-[11px]">spa</span>${escapeHtml(serviceName)}</div>` : ''}
                </div>
            `;
        } else if (height < TIER_STATUS) {
            innerHtml = `
                <div class="flex flex-col h-full overflow-hidden px-2.5 py-2 gap-1.5">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] font-semibold leading-none" style="color:${providerColor};">${startTime}${endTime ? ` - ${endTime}` : ''}</span>
                        ${compactStatusPill}
                    </div>
                    <div class="text-sm font-bold truncate text-gray-900 dark:text-white leading-snug">${escapeHtml(customerName)}</div>
                    ${serviceName ? `<div class="text-xs truncate text-gray-500 dark:text-gray-400 flex items-center gap-1"><span class="material-symbols-outlined text-xs">spa</span>${escapeHtml(serviceName)}</div>` : ''}
                </div>
            `;
        } else {
            const initials = getProviderInitials(provider.name || provider.username || '?');
            const durationLabel = durationMin >= 60
                ? `${Math.floor(durationMin / 60)}h${durationMin % 60 ? ` ${durationMin % 60}m` : ''}`
                : `${durationMin}m`;

            innerHtml = `
                <div class="flex flex-col h-full overflow-hidden px-0">
                    <div class="flex items-center justify-between px-2.5 py-1.5 flex-shrink-0" style="background-color:${providerColor}28;">
                        <span class="text-xs font-bold leading-none" style="color:${providerColor};">${startTime}${endTime ? ` - ${endTime}` : ''}</span>
                        <div class="flex items-center gap-1.5">
                            ${compactStatusPill.replace('text-[9px]', 'text-[10px]').replace('px-1.5 py-0.5', 'px-2 py-0.5')}
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 font-medium">${durationLabel}</span>
                        </div>
                    </div>

                    <div class="flex flex-col flex-1 overflow-hidden px-2.5 py-2 gap-1.5">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center text-[9px] font-bold text-white shadow-sm" style="background-color:${providerColor};">
                                ${initials}
                            </div>
                            <div class="font-semibold text-sm text-gray-900 dark:text-white truncate leading-snug">${escapeHtml(customerName)}</div>
                        </div>

                        ${serviceName ? `<div class="flex items-start gap-1.5 text-xs text-gray-600 dark:text-gray-300"><span class="material-symbols-outlined text-[13px] flex-shrink-0 mt-px" style="color:${providerColor};">spa</span><span class="truncate">${escapeHtml(serviceName)}</span></div>` : ''}

                        ${location ? `<div class="flex items-start gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="material-symbols-outlined text-[13px] flex-shrink-0 mt-px">location_on</span><span class="truncate">${escapeHtml(location)}</span></div>` : ''}

                        ${notes && height >= 200 ? `<div class="flex items-start gap-1.5 text-xs text-gray-400 dark:text-gray-500 mt-auto pt-1 border-t border-gray-200/60 dark:border-gray-600/40"><span class="material-symbols-outlined text-[13px] flex-shrink-0 mt-px">notes</span><span class="line-clamp-2 leading-relaxed">${escapeHtml(notes)}</span></div>` : ''}
                    </div>
                </div>
            `;
        }

        return `
            <div class="appointment-block absolute group cursor-pointer overflow-hidden rounded-lg border-l-[3px] shadow-sm transition-shadow duration-150 hover:shadow-md hover:ring-1 hover:ring-inset"
                 style="
                     top: ${top}px;
                     height: ${height}px;
                     left: calc(${colLeft}% + 4px);
                     width: calc(${colWidth}% - 8px);
                     background-color: ${lightBg};
                     border-left-color: ${borderCol};
                     --tw-ring-color: ${borderCol}40;
                     z-index: ${zIndex};
                 "
                 data-appointment-id="${appointment.id}"
                 title="${escapeHtml(customerName)}${serviceName ? ' - ' + escapeHtml(serviceName) : ''} - ${startTime}${endTime ? ' - ' + endTime : ''}">
                ${innerHtml}
            </div>
        `;
    }

    _renderNowLine(timeRange) {
        const now = DateTime.now().setZone(this.scheduler.options.timezone);
        const startMinutes = this._timeToMinutes(timeRange.startTime);
        const endMinutes = this._timeToMinutes(timeRange.endTime);
        const nowMinutes   = now.hour * 60 + now.minute;

        // Hide when current time is outside the visible timeline range.
        if (nowMinutes < startMinutes || nowMinutes >= endMinutes) {
            return '';
        }

        const top = this._topPxDay(now, startMinutes);

        return `
            <div class="now-line absolute pointer-events-none flex items-center"
                 style="top:${top}px; left:64px; right:0; z-index:50;"
                 id="day-now-line">
                <div class="w-2.5 h-2.5 rounded-full bg-red-500 -ml-1.5 flex-shrink-0 shadow-sm"></div>
                <div class="flex-1 h-px bg-red-500 opacity-70"></div>
            </div>
        `;
    }

    _renderNonWorkingDayOverlay() {
        return `
            <div class="absolute inset-0 bg-gray-100/60 dark:bg-gray-800/60 flex items-center justify-center pointer-events-none">
                <div class="text-center px-4">
                    <span class="material-symbols-outlined text-3xl text-gray-300 dark:text-gray-600 mb-2 block">event_busy</span>
                    <p class="text-xs font-medium text-gray-400 dark:text-gray-500">Not Working</p>
                </div>
            </div>
        `;
    }

    _renderEmptyState(message, icon) {
        return `
            <div class="text-center py-12 px-4">
                <span class="material-symbols-outlined text-4xl text-gray-400 dark:text-gray-500 mb-3 block">${icon}</span>
                <p class="text-sm text-gray-500 dark:text-gray-400">${message}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                    Use the advanced filters to select providers.
                </p>
            </div>
        `;
    }

    _attachListeners(container, data) {
        container.querySelectorAll('.appointment-block').forEach((block) => {
            block.addEventListener('click', () => {
                const aptId = parseInt(block.dataset.appointmentId, 10);
                const appointment = data.appointments.find((a) => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    data.onAppointmentClick(appointment);
                }
            });
        });
    }

    _startNowLineUpdater() {
        if (this._nowLineTimer) {
            clearInterval(this._nowLineTimer);
        }

        this._nowLineTimer = setInterval(() => {
            const nowLine = document.getElementById('day-now-line');
            if (!nowLine) {
                clearInterval(this._nowLineTimer);
                return;
            }

            const now = DateTime.now().setZone(this.scheduler.options.timezone);
            const startMinutes = this._activeTimelineStartMinutes ?? (this.businessHours.startHour * 60);
            const endMinutes   = this._activeTimelineEndMinutes   ?? (startMinutes + (9 * 60));
            const nowMinutes   = now.hour * 60 + now.minute;

            if (nowMinutes < startMinutes || nowMinutes >= endMinutes) {
                nowLine.style.display = 'none';
                return;
            }

            nowLine.style.display = '';
            nowLine.style.top = `${this._topPxDay(now, startMinutes)}px`;
        }, 60000);
    }

    _generate15MinSlots(startTime, endTime) {
        const zone = this.scheduler.options.timezone;
        const startMins = this._timeToMinutes(startTime);
        const endMins = this._timeToMinutes(endTime);
        let cursor = DateTime.fromObject(
            { hour: Math.floor(startMins / 60), minute: startMins % 60 },
            { zone },
        );
        const end = DateTime.fromObject(
            { hour: Math.floor(endMins / 60), minute: endMins % 60 },
            { zone },
        );
        const slots = [];

        while (cursor < end) {
            slots.push({
                time: cursor.toFormat('HH:mm'),
                hour: cursor.hour,
                minute: cursor.minute,
            });
            cursor = cursor.plus({ minutes: 15 });
        }

        return slots;
    }

    _topPxDay(dateTime, timelineStartMinutes = 8 * 60) {
        const minutesSinceStart = (dateTime.hour * 60 + dateTime.minute) - timelineStartMinutes;
        return Math.max(0, (minutesSinceStart / 60) * DAY_VIEW_HOUR_HEIGHT_PX);
    }

    _heightPxDay(durationMinutes) {
        const calculated = (durationMinutes / 60) * DAY_VIEW_HOUR_HEIGHT_PX - DAY_VIEW_SLOT_MARGIN_PX;
        return Math.max(DAY_VIEW_MIN_HEIGHT_PX, calculated);
    }

    _calcTotalHeight(startTime, endTime) {
        const totalMinutes = Math.max(60, this._timeToMinutes(endTime) - this._timeToMinutes(startTime));
        return (totalMinutes / 60) * DAY_VIEW_HOUR_HEIGHT_PX;
    }

    _timeToMinutes(time) {
        const parts = String(time || '').split(':').map(Number);
        const hour = Number.isFinite(parts[0]) ? parts[0] : 0;
        const minute = Number.isFinite(parts[1]) ? parts[1] : 0;
        return (hour * 60) + minute;
    }

    _resolveBusinessHours(config, calendarModel) {
        const fromModel = calendarModel?.businessHours;
        if (fromModel?.startTime && fromModel?.endTime) {
            return getBusinessHours({ businessHours: fromModel });
        }
        return getBusinessHours(config);
    }

    _calculateTimelineRange(visibleProviders, calendarModel) {
        // The timeline is always anchored to business hours (Settings > Business Hours).
        // Individual provider working hours render as overlays within this fixed range.
        // This ensures all providers share a consistent grid regardless of their schedules
        // and prevents appointments from rendering outside the visible grid.
        const bh = this._resolveBusinessHours(this.config, calendarModel);
        const toMinutes = (time) => {
            const [h = 0, m = 0] = String(time || '').split(':').map(Number);
            return (h * 60) + m;
        };

        let startTime = bh.startTime || '08:00';
        let endTime = bh.endTime || '17:00';

        // Guard against zero/negative ranges that collapse the day grid.
        if (toMinutes(endTime) <= toMinutes(startTime)) {
            startTime = '08:00';
            endTime = '17:00';
        }

        return {
            startTime,
            endTime,
            source:    'business',
        };
    }

    _getProviderTimeRange(providerId, calendarModel, overallRange) {
        const column = calendarModel?.providerColumns?.find((c) => c.provider.id === providerId);
        if (column?.workingHours) {
            return {
                startTime: column.workingHours.startTime,
                endTime: column.workingHours.endTime,
                isActive: column.workingHours.isActive,
                source: column.workingHours.source,
            };
        }

        return { ...overallRange, isActive: true };
    }

    _buildAppointmentsByProvider(visibleProviders, data) {
        // Flat list is the authoritative source — always contains ALL appointments for the
        // day regardless of provider working-hours slot-map coverage gaps.
        const dayAppointments = data.appointments.filter(
            (apt) => apt.startDateTime && apt.startDateTime.hasSame(data.currentDate, 'day'),
        );
        const byProvider = {};
        visibleProviders.forEach((provider) => {
            byProvider[provider.id] = dayAppointments.filter(
                (apt) => Number(apt.providerId) === Number(provider.id),
            );
        });

        // Server-model path: enrich with server-side slot metadata (overlap layout etc.)
        // when available. Uses flat list as the guaranteed-complete base so appointments
        // outside a provider's personal schedule (e.g. 08:00–09:59 for a provider starting
        // at 10:00) are never dropped when the slot map has incomplete coverage.
        const fromModel = this._extractFromDayModel(data.calendarModel, visibleProviders);
        if (fromModel && this._hasAnyAppointments(fromModel)) {
            // data.appointments has already been filtered by the core (status, service, etc.)
            // Cross-reference to ensure the same filters apply to model-sourced appointments
            const allowedIds = new Set(data.appointments.map((a) => Number(a.id)));
            for (const [pid, modelApts] of Object.entries(fromModel)) {
                const modelFiltered = modelApts.filter((apt) => allowedIds.has(Number(apt.id)));
                if (modelFiltered.length === 0) continue;

                // Merge: model-positioned appointments take precedence (they carry server-side
                // overlap layout metadata), then append any flat-list appointments the slot
                // map missed for this provider.
                const modelIds = new Set(modelFiltered.map((a) => Number(a.id)));
                const flatOnly = (byProvider[pid] || []).filter((a) => !modelIds.has(Number(a.id)));
                byProvider[pid] = [...modelFiltered, ...flatOnly];
            }
        }

        return byProvider;
    }

    _resolveOverlapLayout(appointments = []) {
        const sorted = appointments
            .filter((apt) => apt?.id && apt?.startDateTime && apt?.endDateTime)
            .slice()
            .sort((a, b) => {
                const startDiff = a.startDateTime.toMillis() - b.startDateTime.toMillis();
                if (startDiff !== 0) return startDiff;
                return a.endDateTime.toMillis() - b.endDateTime.toMillis();
            });

        const active = [];
        const items = [];
        const membersByCluster = new Map();
        const maxColsByCluster = new Map();
        let nextClusterId = 1;

        const mergeClusters = (targetId, sourceId) => {
            if (targetId === sourceId) {
                return targetId;
            }

            const targetMembers = membersByCluster.get(targetId) || new Set();
            const sourceMembers = membersByCluster.get(sourceId) || new Set();

            sourceMembers.forEach((item) => {
                item.clusterId = targetId;
                targetMembers.add(item);
            });

            membersByCluster.set(targetId, targetMembers);
            membersByCluster.delete(sourceId);

            maxColsByCluster.set(
                targetId,
                Math.max(maxColsByCluster.get(targetId) || 1, maxColsByCluster.get(sourceId) || 1),
            );
            maxColsByCluster.delete(sourceId);

            return targetId;
        };

        sorted.forEach((apt) => {
            for (let i = active.length - 1; i >= 0; i -= 1) {
                if (active[i].endMs <= apt.startDateTime.toMillis()) {
                    active.splice(i, 1);
                }
            }

            const usedCols = new Set(active.map((entry) => entry.column));
            let column = 0;
            while (usedCols.has(column)) {
                column += 1;
            }

            const overlappingClusters = new Set(active.map((entry) => entry.clusterId));
            let clusterId;
            if (!overlappingClusters.size) {
                clusterId = nextClusterId;
                nextClusterId += 1;
                membersByCluster.set(clusterId, new Set());
                maxColsByCluster.set(clusterId, 1);
            } else {
                const [firstCluster] = overlappingClusters;
                clusterId = firstCluster;
                [...overlappingClusters].slice(1).forEach((otherCluster) => {
                    clusterId = mergeClusters(clusterId, otherCluster);
                });
            }

            const item = {
                id: Number(apt.id),
                column,
                clusterId,
                endMs: apt.endDateTime.toMillis(),
            };

            membersByCluster.get(clusterId).add(item);
            maxColsByCluster.set(clusterId, Math.max(maxColsByCluster.get(clusterId) || 1, column + 1));

            items.push(item);
            active.push(item);
        });

        const layoutMap = new Map();
        items.forEach((item) => {
            layoutMap.set(item.id, {
                column: item.column,
                totalCols: maxColsByCluster.get(item.clusterId) || 1,
            });
        });

        return layoutMap;
    }

    _hasAnyAppointments(grouped = {}) {
        return Object.values(grouped).some((list) => Array.isArray(list) && list.length > 0);
    }

    _extractFromDayModel(calendarModel, visibleProviders) {
        if (!calendarModel?.providerColumns || !Array.isArray(calendarModel.providerColumns)) {
            return null;
        }

        const mapped = {};
        const visibleSet = new Set(visibleProviders.map((p) => Number(p.id)));

        calendarModel.providerColumns.forEach((column) => {
            const pid = Number(column?.provider?.id || 0);
            if (!visibleSet.has(pid)) {
                return;
            }

            const slotAppts = [];
            (column?.grid?.slots || []).forEach((slot) => {
                (slot.appointments || []).forEach((appt) => {
                    const normalized = this._normalizeModelAppointment(appt);
                    if (!normalized) {
                        return;
                    }
                    if (!slotAppts.some((item) => item.id === normalized.id)) {
                        slotAppts.push(normalized);
                    }
                });
            });

            mapped[pid] = slotAppts;
        });

        visibleProviders.forEach((provider) => {
            if (!mapped[provider.id]) {
                mapped[provider.id] = [];
            }
        });

        return mapped;
    }

    _normalizeModelAppointment(modelAppointment) {
        const startIso = modelAppointment.start || modelAppointment.start_datetime || modelAppointment.startDateTime;
        const endIso = modelAppointment.end || modelAppointment.end_datetime || modelAppointment.endDateTime;

        const startDateTime = this._parseModelDateTime(startIso);
        const endDateTime = this._parseModelDateTime(endIso);

        if (!startDateTime || !endDateTime) {
            return null;
        }

        return {
            ...modelAppointment,
            id: Number(modelAppointment.id),
            providerId: Number(modelAppointment.provider_id ?? modelAppointment.providerId),
            customerName: modelAppointment.customer_name ?? modelAppointment.customerName,
            serviceName: modelAppointment.service_name ?? modelAppointment.serviceName,
            location: modelAppointment.location ?? modelAppointment.locationName ?? '',
            notes: modelAppointment.notes ?? '',
            status: modelAppointment.status || 'pending',
            startDateTime,
            endDateTime,
        };
    }

    _parseModelDateTime(value) {
        if (!value) {
            return null;
        }

        const timezone = this.scheduler.options.timezone;
        const raw = String(value).trim();

        // Prefer offset/UTC aware parsing when timezone info exists.
        const hasOffset = /([zZ]|[+-]\d{2}:?\d{2})$/.test(raw);
        if (hasOffset) {
            const isoWithZone = DateTime.fromISO(raw, { setZone: true });
            if (isoWithZone.isValid) {
                return isoWithZone.setZone(timezone);
            }
        }

        // Naive ISO/local datetime (e.g. 2026-05-05T10:00:00)
        const isoLocal = DateTime.fromISO(raw, { zone: timezone });
        if (isoLocal.isValid) {
            return isoLocal;
        }

        // SQL UTC/local datetime fallback (e.g. 2026-05-05 10:00:00)
        const sqlUtc = DateTime.fromSQL(raw, { zone: 'utc' });
        if (sqlUtc.isValid) {
            return sqlUtc.setZone(timezone);
        }

        const sqlLocal = DateTime.fromSQL(raw, { zone: timezone });
        if (sqlLocal.isValid) {
            return sqlLocal;
        }

        return null;
    }

    _formatHour(hour) {
        const is24h = this.settings?.getTimeFormat?.() === '24h';
        if (is24h) {
            return `${hour.toString().padStart(2, '0')}:00`;
        }
        const period = hour < 12 ? 'AM' : 'PM';
        const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
        return `${displayHour} ${period}`;
    }

    _formatHalfHour(hour) {
        const is24h = this.settings?.getTimeFormat?.() === '24h';
        if (is24h) {
            return `${hour.toString().padStart(2, '0')}:30`;
        }
        const period = hour < 12 ? 'AM' : 'PM';
        const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
        return `${displayHour}:30 ${period}`;
    }
}
