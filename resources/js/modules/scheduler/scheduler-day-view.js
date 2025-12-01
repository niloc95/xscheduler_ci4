/**
 * Custom Scheduler - Day View
 * 
 * Renders a single-day calendar with detailed time slots and appointment information.
 * Shows customer details, service information, and quick action buttons.
 */

import { DateTime } from 'luxon';
import { getStatusColors, getProviderColor, getStatusLabel, isDarkMode, formatDuration } from './appointment-colors.js';
import { generateTimeSlots } from './time-slots.js';
import { escapeHtml as escapeHtmlUtil, isDateBlocked as isDateBlockedUtil } from './utils.js';

export class DayView {
    constructor(scheduler) {
        this.scheduler = scheduler;
    }

    render(container, data) {
        const { currentDate, appointments, providers, config } = data;
        
        console.log('[DayView] render() called with:', {
            currentDate: currentDate?.toISO?.() || currentDate,
            appointmentsCount: appointments?.length || 0,
            providersCount: providers?.length || 0,
            configKeys: config ? Object.keys(config) : []
        });
        
        // Debug: Log all appointments to see what we're working with
        if (appointments && appointments.length > 0) {
            console.log('[DayView] All appointments:', appointments.map(apt => ({
                id: apt.id,
                start: apt.startDateTime?.toISO?.() || apt.start,
                provider: apt.providerId,
                name: apt.name || apt.title
            })));
        }
        
        // Use slotMinTime and slotMaxTime from calendar config (reads from business.work_start/work_end settings)
        const startTime = config?.slotMinTime || '08:00';
        const endTime = config?.slotMaxTime || '17:00';
        
        const businessHours = {
            startTime: startTime,
            endTime: endTime,
            isWorkingDay: true
        };
        
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() || '12h';
        // Show 30-minute granularity in day view
        const timeSlots = generateTimeSlots(businessHours, timeFormat, 30);
        
        // Check if this date is blocked
        const isBlocked = isDateBlockedUtil(currentDate, config?.blockedPeriods);
        const blockedNotice = isBlocked ? config.blockedPeriods.find(period => {
            const checkDate = currentDate.toISODate();
            return checkDate >= period.start && checkDate <= period.end;
        }) : null;

        // P0-5 FIX: Filter appointments for this day with improved date comparison
        const currentDateStr = currentDate.toISODate();
        const dayAppointments = appointments.filter(apt => {
            if (!apt.startDateTime) {
                console.warn('[DayView] Appointment missing startDateTime:', apt);
                return false;
            }
            const aptDateStr = apt.startDateTime.toISODate();
            return aptDateStr === currentDateStr;
        }).sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());
        
        console.log(`[DayView] Rendering ${currentDateStr}: ${dayAppointments.length} appointments found (from ${appointments.length} total)`);

        // Render HTML
        container.innerHTML = `
            <div class="scheduler-day-view bg-white dark:bg-gray-800">
                <!-- Calendar Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 p-6">
                    <!-- Time Slots Column (2/3 width) -->
                    <div class="lg:col-span-2 space-y-1">
                        ${timeSlots.map(slot => this.renderTimeSlot(slot, dayAppointments, providers, data)).join('')}
                    </div>

                    <!-- Appointment List Sidebar (1/3 width) -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Today's Schedule
                        </h3>
                        ${dayAppointments.length > 0 ? 
                            dayAppointments.map(apt => this.renderAppointmentCard(apt, providers, data)).join('') :
                            this.renderEmptyState()
                        }
                    </div>
                </div>
            </div>
        `;

        // Add event listeners
        this.attachEventListeners(container, data);
    }

    renderTimeSlot(slot, appointments, providers, data) {
        // P0-5 FIX: Show appointments that start in this time slot's range (e.g., 09:00-09:29 for 09:00 slot)
        const slotHour = parseInt(slot.time.split(':')[0], 10);
        const slotMinute = parseInt(slot.time.split(':')[1], 10);
        
        const slotAppointments = appointments.filter(apt => {
            if (!apt.startDateTime) return false;
            const aptHour = apt.startDateTime.hour;
            const aptMinute = apt.startDateTime.minute;
            
            // Check if appointment falls within this 30-minute slot
            // Slot 09:00 covers 09:00-09:29, Slot 09:30 covers 09:30-09:59
            if (aptHour === slotHour) {
                // Same hour - check if minute falls in range
                if (slotMinute === 0) {
                    return aptMinute >= 0 && aptMinute < 30;
                } else {
                    return aptMinute >= 30 && aptMinute < 60;
                }
            }
            return false;
        });

        return `
            <div class="time-slot flex items-start gap-4 p-2 rounded-lg border border-gray-200 dark:border-gray-700 
                        hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer min-h-[56px]"
                 data-time="${slot.time}"
                 data-hour="${slot.hour}">
                <!-- Time Label -->
                <div class="flex-shrink-0 w-20 text-right">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">${slot.display}</span>
                </div>

                <!-- Appointments Container -->
                <div class="flex-1 space-y-2">
                    ${slotAppointments.length > 0 ? 
                        slotAppointments.map(apt => this.renderInlineAppointment(apt, providers)).join('') :
                        `<div class="text-sm text-gray-400 dark:text-gray-500 italic">Available</div>`
                    }
                </div>
            </div>
        `;
    }

    renderInlineAppointment(appointment, providers) {
        const provider = providers.find(p => p.id === appointment.providerId);
        const darkMode = isDarkMode();
        const statusColors = getStatusColors(appointment.status, darkMode);
        const providerColor = getProviderColor(provider);
        
        // Format time based on settings
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mma';
        const startTime = appointment.startDateTime.toFormat(timeFormat).toLowerCase();
        const endTime = appointment.endDateTime.toFormat(timeFormat).toLowerCase();
        const time = `${startTime}`;
        
        // Calculate duration for badge
        const duration = appointment.endDateTime && appointment.startDateTime 
            ? Math.round((appointment.endDateTime.toMillis() - appointment.startDateTime.toMillis()) / 60000)
            : null;
        const durationLabel = formatDuration(duration);
        
        const customerName = appointment.name || appointment.title || 'Unknown';
        const serviceName = appointment.serviceName || 'Appointment';
        const providerName = provider?.name || 'Provider';

        // Phase 1 Prototype Styling: Rounded-3xl cards like prototype day-view.html
        const ariaLabel = `Appointment: ${customerName} for ${serviceName} at ${time} with ${providerName}`;

        return `
            <article class="inline-appointment rounded-3xl cursor-pointer transition-all 
                          border p-4 shadow-sm hover:shadow-md active:scale-[0.98]"
                 style="background-color: ${statusColors.chipBg}; 
                        border-color: ${statusColors.chipBorder}; 
                        color: ${statusColors.text};"
                 data-appointment-id="${appointment.id}"
                 role="button"
                 tabindex="0"
                 aria-label="${ariaLabel}">
                <!-- Header: Time + Duration badge -->
                <div class="flex items-center justify-between text-[11px] font-semibold mb-1">
                    <span>${startTime}</span>
                    ${durationLabel ? `
                        <span class="rounded-full px-2 py-0.5 text-[10px]" 
                              style="background-color: ${statusColors.badgeBg};">
                            ${durationLabel}
                        </span>
                    ` : ''}
                </div>
                <!-- Service name -->
                <div class="text-sm font-semibold">${escapeHtmlUtil(serviceName)}</div>
                <!-- Description/notes if available -->
                ${appointment.notes ? `<div class="text-xs opacity-70 mt-0.5">${escapeHtmlUtil(appointment.notes.substring(0, 50))}${appointment.notes.length > 50 ? '...' : ''}</div>` : ''}
                <!-- Customer + Provider tags -->
                <div class="flex items-center gap-2 text-[11px] mt-2 flex-wrap">
                    <span class="rounded-full px-2 py-0.5" style="background-color: ${statusColors.badgeBg};">
                        ${escapeHtmlUtil(customerName)}
                    </span>
                    <span class="flex items-center gap-1 rounded-full px-2 py-0.5" 
                          style="background-color: rgba(${this.hexToRgb(providerColor)}, 0.1); color: ${statusColors.text};">
                        <span class="inline-flex h-2 w-2 rounded-full" style="background-color: ${providerColor};"></span>
                        ${escapeHtmlUtil(providerName)}
                    </span>
                </div>
            </article>
        `;
    }

    renderAppointmentCard(appointment, providers, data) {
        const provider = providers.find(p => p.id === appointment.providerId);
        const darkMode = isDarkMode();
        const statusColors = getStatusColors(appointment.status, darkMode);
        const providerColor = getProviderColor(provider);
        
        // Format time based on settings
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mma';
        const startTime = appointment.startDateTime.toFormat(timeFormat).toLowerCase();
        const endTime = appointment.endDateTime.toFormat(timeFormat).toLowerCase();
        
        // Calculate duration for badge
        const duration = appointment.endDateTime && appointment.startDateTime 
            ? Math.round((appointment.endDateTime.toMillis() - appointment.startDateTime.toMillis()) / 60000)
            : null;
        const durationLabel = formatDuration(duration);
        
        const customerName = appointment.name || appointment.title || 'Unknown';
        const serviceName = appointment.serviceName || 'Appointment';
        const statusLabel = getStatusLabel(appointment.status);
        const providerName = provider?.name || 'Provider';
        
        // P1-2: Added ARIA labels for accessibility
        const ariaLabel = `Appointment: ${customerName} for ${serviceName} at ${startTime} with ${providerName}. Status: ${statusLabel}`;

        // Phase 1 Prototype Styling: Large rounded-3xl cards for sidebar
        return `
            <article class="appointment-card rounded-3xl border transition-all cursor-pointer 
                          hover:shadow-lg active:scale-[0.98] mb-4 overflow-hidden"
                 style="background-color: ${statusColors.chipBg}; 
                        border-color: ${statusColors.chipBorder}; 
                        color: ${statusColors.text};"
                 data-appointment-id="${appointment.id}"
                 role="button"
                 tabindex="0"
                 aria-label="${ariaLabel}">
                <!-- Card content -->
                <div class="p-4">
                    <!-- Header: Time + Duration + Status -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-3 w-3 rounded-full flex-shrink-0" 
                                  style="background-color: ${providerColor};" 
                                  aria-hidden="true"></span>
                            <span class="text-sm font-semibold">${startTime} - ${endTime}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            ${durationLabel ? `
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" 
                                      style="background-color: ${statusColors.badgeBg};">
                                    ${durationLabel}
                                </span>
                            ` : ''}
                            <span class="px-2 py-1 text-[10px] font-semibold rounded-full"
                                  style="background-color: ${statusColors.dot}; color: white;">
                                ${statusLabel}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Customer name -->
                    <h4 class="text-lg font-semibold mb-1">
                        ${escapeHtmlUtil(customerName)}
                    </h4>
                    
                    <!-- Service name -->
                    <p class="text-sm mb-3 opacity-80">
                        ${escapeHtmlUtil(serviceName)}
                    </p>
                    
                    <!-- Provider info -->
                    <div class="flex items-center gap-2 text-xs">
                        <span class="flex items-center gap-1.5 rounded-full px-2.5 py-1" 
                              style="background-color: rgba(${this.hexToRgb(providerColor)}, 0.15);">
                            <span class="material-symbols-outlined text-sm" aria-hidden="true">person</span>
                            ${escapeHtmlUtil(providerName)}
                        </span>
                    </div>
                </div>
            </article>
        `;
    }

    renderEmptyState() {
        return `
            <div class="text-center py-8">
                <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">No appointments scheduled for this day</p>
                <p class="text-xs text-gray-500 dark:text-gray-500">Use the navigation arrows to view other dates</p>
            </div>
        `;
    }

    // generateTimeSlots and formatTime moved to shared util (time-slots.js)

    attachEventListeners(container, data) {
        // Appointment card/block click and keyboard handlers
        container.querySelectorAll('[data-appointment-id]').forEach(el => {
            // Click handler
            el.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this._handleAppointmentSelect(el, data);
            });
            
            // P1-2: Keyboard handler for accessibility (Enter and Space)
            el.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    this._handleAppointmentSelect(el, data);
                }
            });
        });

        // Removed: Click-to-create modal functionality
        // Time slots no longer open creation modal
    }
    
    _handleAppointmentSelect(el, data) {
        const aptId = parseInt(el.dataset.appointmentId, 10);
        const appointment = data.appointments.find(a => a.id === aptId);
        if (appointment && data.onAppointmentClick) {
            data.onAppointmentClick(appointment);
        }
    }

    getContrastColor(hexColor) {
        const hex = hexColor.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.5 ? '#000000' : '#FFFFFF';
    }

    /**
     * Convert hex color to RGB string for rgba() usage
     * @param {string} hexColor - Hex color (e.g., '#3B82F6')
     * @returns {string} RGB values (e.g., '59, 130, 246')
     */
    hexToRgb(hexColor) {
        const hex = hexColor.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        return `${r}, ${g}, ${b}`;
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

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    // escapeHtml and isDateBlocked moved to shared utils
}
