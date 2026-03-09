/**
 * Custom Scheduler - Day View (Per-Provider Columns)
 * 
 * Shows detailed single-day view with provider columns:
 * - One vertical column per visible provider (side by side)
 * - Same 60px hour height as week view
 * - Appointments absolutely positioned within provider columns
 * - Overlapping appointments within column split horizontally
 * - Now-line spans all provider columns
 * - Provider headers show avatar + name + count
 * 
 * Uses time-grid-utils.js for overlap resolution and positioning.
 */

import { DateTime } from 'luxon';
import { getProviderColor, getProviderInitials } from './appointment-colors.js';
import { topPx, heightPx, getBusinessHours } from './time-grid-utils.js';
import { generateTimeSlots, calculateRangeHeight } from './utils/timeRangeGenerator.js';
import { escapeHtml } from '../../utils/html.js';

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
        
        this.debugLog('📅 DayView.render called (per-provider columns)');
        this.debugLog('   Appointments received:', appointments.length);
        
        // Store for internal use
        this.appointments = appointments;
        this.providers = providers;
        this.settings = settings;
        this.config = config;
        this.currentDate = currentDate;
        this.container = container;

        // Prefer server-side business hours when available (data-driven model)
        const businessHours = this._resolveBusinessHours(config, calendarModel);
        this.businessHours = businessHours;

        // Get visible providers
        const visibleProviders = providers.filter(p => 
            this.scheduler.visibleProviders.has(p.id)
        );
        this.visibleProviders = visibleProviders;

        if (visibleProviders.length === 0) {
            container.innerHTML = this._renderEmptyState('No providers selected', 'person_off');
            return;
        }

        const appointmentsByProvider = this._buildAppointmentsByProvider(visibleProviders, data);

        const today = DateTime.now().setZone(this.scheduler.options.timezone).startOf('day');
        const isToday = currentDate.hasSame(today, 'day');

        // Render view
        container.innerHTML = `
            <div class="day-view-grid p-4">
                ${this._renderHeader(currentDate, isToday)}
                ${this._renderProviderHeaders(visibleProviders, appointmentsByProvider)}
                ${this._renderTimeline(visibleProviders, appointmentsByProvider, data, isToday)}
            </div>
        `;

        // Attach event listeners
        this._attachListeners(container, data);
        
        // Start now-line updater if today
        if (isToday) {
            this._startNowLineUpdater();
        }
    }

    /**
     * Render day header.
     */
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

    /**
     * Render provider column headers.
     */
    _renderProviderHeaders(visibleProviders, appointmentsByProvider) {
        const providerHeadersHtml = visibleProviders.map(provider => {
            const providerColor = getProviderColor(provider);
            const initials = getProviderInitials(provider.name || provider.username);
            const apptCount = appointmentsByProvider[provider.id]?.length || 0;
            
            return `
                <div class="text-center">
                    <div class="flex flex-col items-center gap-2">
                        <div class="provider-avatar w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold"
                             style="background-color: ${providerColor};">
                            ${initials}
                        </div>
                        <div class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-full px-1">
                            ${escapeHtml(provider.name || provider.username)}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            ${apptCount} appt${apptCount !== 1 ? 's' : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        const gridCols = visibleProviders.length + 1; // +1 for time column

        return `
            <div class="grid gap-2 mb-4" style="grid-template-columns: 60px repeat(${visibleProviders.length}, 1fr);">
                <div></div>
                ${providerHeadersHtml}
            </div>
        `;
    }

    /**
     * Render time grid with provider columns.
     * Uses unified time slot generation for perfect alignment.
     */
    _renderTimeline(visibleProviders, appointmentsByProvider, data, isToday) {
        // Determine the overall time range (union of all provider working hours)
        const timeRange = this._calculateTimelineRange(visibleProviders, data.calendarModel);
        
        // Generate time slots using unified generator (15-minute intervals for hour marks)
        const timeSlots = generateTimeSlots({
            startTime: timeRange.startTime,
            endTime: timeRange.endTime,
            interval: 60, // Generate hourly marks
            includeEnd: false
        });

        const totalHeight = calculateRangeHeight(timeRange.startTime, timeRange.endTime);

        // Render time labels
        const timeLabels = timeSlots.map(slot => {
            const top = topPx(
                DateTime.fromFormat(slot.time, 'HH:mm', { zone: this.scheduler.options.timezone }),
                parseInt(timeRange.startTime.split(':')[0], 10)
            );
            return `
                <div class="timeline-time-label" style="top:${top}px;">
                    ${this._formatHour(slot.hour)}
                </div>
            `;
        }).join('');

        // Render provider columns
        const providerColumnsHtml = visibleProviders.map(provider => {
            const providerAppts = appointmentsByProvider[provider.id] || [];
            
            // Get provider-specific working hours or fallback
            const providerRange = this._getProviderTimeRange(provider.id, data.calendarModel, timeRange);
            const providerHeight = calculateRangeHeight(providerRange.startTime, providerRange.endTime);
            
            // Render hour lines for this provider
            const hourLines = timeSlots.map(slot => {
                const top = topPx(
                    DateTime.fromFormat(slot.time, 'HH:mm', { zone: this.scheduler.options.timezone }),
                    parseInt(timeRange.startTime.split(':')[0], 10)
                );
                return `<div class="timeline-hour-line" style="top:${top}px;"></div>`;
            }).join('');

            const appointmentsHtml = providerAppts.map(apt => this._renderAppointmentBlock(apt, provider, timeRange)).join('');

            return `
                <div class="timeline-column ${this.currentDate.hasSame(DateTime.now().setZone(this.scheduler.options.timezone), 'day') ? 'time-grid-col--today' : ''}" 
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
            <div class="timeline-shell" style="grid-template-columns: 60px repeat(${visibleProviders.length}, 1fr);">
                <div class="timeline-time-column" style="height:${totalHeight}px;">
                    ${timeLabels}
                </div>
                ${providerColumnsHtml}
                ${isToday ? this._renderNowLine(timeRange) : ''}
            </div>
        `;
    }

    /**
     * Render a single appointment block.
     */
    _renderAppointmentBlock(appointment, provider, timeRange) {
        const startHour = timeRange ? parseInt(timeRange.startTime.split(':')[0], 10) : this.businessHours.startHour;
        const providerColor = getProviderColor(provider);

        // Prefer server-side precomputed layout metadata when available.
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
        const endTime = appointment.endDateTime?.toFormat(timeFormat);

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
                    ${startTime}${endTime ? ` - ${endTime}` : ''}
                </div>
                <div class="text-xs font-semibold truncate text-gray-900 dark:text-white">
                    ${escapeHtml(customerName)}
                </div>
                ${serviceName ? `<div class="text-xs truncate text-gray-600 dark:text-gray-400">${escapeHtml(serviceName)}</div>` : ''}
            </div>
        `;
    }

    /**
     * Render now-line spanning all provider columns.
     */
    _renderNowLine(timeRange) {
        const now = DateTime.now().setZone(this.scheduler.options.timezone);
        const startHour = timeRange ? parseInt(timeRange.startTime.split(':')[0], 10) : this.businessHours.startHour;
        const top = topPx(now, startHour);
        
        return `
            <div class="now-line absolute pointer-events-none" 
                 style="
                     top: ${top}px;
                     left: 60px;
                     right: 0;
                 "
                 id="day-now-line">
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

    _buildAppointmentsByProvider(visibleProviders, data) {
        const fromModel = this._extractFromDayModel(data.calendarModel, visibleProviders);
        if (fromModel && this._hasAnyAppointments(fromModel)) {
            return fromModel;
        }

        // Backend-provided appointments already include layout metadata from EventLayoutService.
        // No client-side overlap resolution needed.
        const dayAppointments = data.appointments.filter(apt => apt.startDateTime.hasSame(data.currentDate, 'day'));
        const appointmentsByProvider = {};
        visibleProviders.forEach(provider => {
            const providerAppts = dayAppointments.filter(apt => Number(apt.providerId) === Number(provider.id));
            appointmentsByProvider[provider.id] = providerAppts;
        });
        return appointmentsByProvider;
    }

    _hasAnyAppointments(grouped = {}) {
        return Object.values(grouped).some(list => Array.isArray(list) && list.length > 0);
    }

    _extractFromDayModel(calendarModel, visibleProviders) {
        if (!calendarModel?.providerColumns || !Array.isArray(calendarModel.providerColumns)) {
            return null;
        }

        const mapped = {};
        const visibleSet = new Set(visibleProviders.map(p => Number(p.id)));

        calendarModel.providerColumns.forEach(column => {
            const pid = Number(column?.provider?.id || 0);
            if (!visibleSet.has(pid)) {
                return;
            }

            const slotAppts = [];
            (column?.grid?.slots || []).forEach(slot => {
                (slot.appointments || []).forEach(appt => {
                    slotAppts.push(this._normalizeModelAppointment(appt));
                });
            });

            mapped[pid] = slotAppts;
        });

        visibleProviders.forEach(provider => {
            if (!mapped[provider.id]) {
                mapped[provider.id] = [];
            }
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
     * Render empty state.
     */
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
            const nowLine = document.getElementById('day-now-line');
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

    /**
     * Calculate the overall timeline range (union of all provider working hours).
     * If providers have different hours, use the earliest start and latest end.
     * 
     * @param {Array} visibleProviders - Array of provider objects
     * @param {Object} calendarModel - Server-side calendar model
     * @returns {Object} { startTime: 'HH:MM', endTime: 'HH:MM', source: 'provider'|'business' }
     */
    _calculateTimelineRange(visibleProviders, calendarModel) {
        // Default to business hours
        const fallback = {
            startTime: this.businessHours.startTime || '08:00',
            endTime: this.businessHours.endTime || '17:00',
            source: 'business'
        };

        // If no calendar model or provider columns, use business hours
        if (!calendarModel?.providerColumns) {
            return fallback;
        }

        // Extract working hours from all visible providers
        const providerRanges = [];
        
        for (const provider of visibleProviders) {
            const column = calendarModel.providerColumns.find(c => c.provider.id === provider.id);
            if (column?.workingHours?.isActive) {
                providerRanges.push({
                    startTime: column.workingHours.startTime,
                    endTime: column.workingHours.endTime
                });
            }
        }

        // If no active provider schedules found, use business hours
        if (providerRanges.length === 0) {
            return fallback;
        }

        // Find the earliest start and latest end across all providers
        const earliestStart = providerRanges.reduce((min, range) => 
            range.startTime < min ? range.startTime : min,
            providerRanges[0].startTime
        );

        const latestEnd = providerRanges.reduce((max, range) => 
            range.endTime > max ? range.endTime : max,
            providerRanges[0].endTime
        );

        return {
            startTime: earliestStart,
            endTime: latestEnd,
            source: 'provider'
        };
    }

    /**
     * Get time range for a specific provider.
     * Returns provider working hours if available, otherwise returns the overall range.
     * 
     * @param {number} providerId - Provider ID
     * @param {Object} calendarModel - Server-side calendar model
     * @param {Object} overallRange - Overall timeline range
     * @returns {Object} { startTime: 'HH:MM', endTime: 'HH:MM', isActive: boolean }
     */
    _getProviderTimeRange(providerId, calendarModel, overallRange) {
        // Find provider column in calendar model
        const column = calendarModel?.providerColumns?.find(c => c.provider.id === providerId);
        
        if (column?.workingHours) {
            return {
                startTime: column.workingHours.startTime,
                endTime: column.workingHours.endTime,
                isActive: column.workingHours.isActive,
                source: column.workingHours.source
            };
        }

        // Fallback to overall range
        return {
            ...overallRange,
            isActive: true
        };
    }

    /**
     * Render non-working day overlay for providers who are off on this day.
     */
    _renderNonWorkingDayOverlay() {
        return `
            <div class="absolute inset-0 bg-gray-200/50 dark:bg-gray-800/50 flex items-center justify-center pointer-events-none">
                <div class="text-center px-4">
                    <span class="material-symbols-outlined text-4xl text-gray-400 dark:text-gray-500 mb-2 block">event_busy</span>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Not Working</p>
                </div>
            </div>
        `;
    }
}
