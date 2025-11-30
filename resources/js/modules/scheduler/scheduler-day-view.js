/**
 * Custom Scheduler - Day View
 * 
 * Renders a single-day calendar with detailed time slots and appointment information.
 * Shows customer details, service information, and quick action buttons.
 */

import { DateTime } from 'luxon';
import { getStatusColors, getProviderColor, getStatusLabel, isDarkMode } from './appointment-colors.js';
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
        const color = provider?.color || '#3B82F6';
        const textColor = this.getContrastColor(color);
        
        // Format time based on settings
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        const time = `${appointment.startDateTime.toFormat(timeFormat)} - ${appointment.endDateTime.toFormat(timeFormat)}`;
        const customerName = appointment.name || appointment.title || 'Unknown';
        const serviceName = appointment.serviceName || 'Appointment';

        return `
            <div class="inline-appointment p-3 rounded-lg shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                 style="background-color: ${color}; color: ${textColor};"
                 data-appointment-id="${appointment.id}">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-medium opacity-90 mb-1">${time}</div>
                        <div class="font-semibold truncate">${escapeHtmlUtil(customerName)}</div>
                        <div class="text-sm opacity-90 truncate">${escapeHtmlUtil(serviceName)}</div>
                        ${provider ? `<div class="text-xs opacity-75 mt-1">with ${escapeHtmlUtil(provider.name)}</div>` : ''}
                    </div>
                    <span class="material-symbols-outlined text-lg flex-shrink-0">arrow_forward</span>
                </div>
            </div>
        `;
    }

    renderAppointmentCard(appointment, providers, data) {
        const provider = providers.find(p => p.id === appointment.providerId);
        const darkMode = isDarkMode();
        const statusColors = getStatusColors(appointment.status, darkMode);
        const providerColor = getProviderColor(provider);
        
        // Format time based on settings
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        const time = `${appointment.startDateTime.toFormat(timeFormat)} - ${appointment.endDateTime.toFormat(timeFormat)}`;
        const customerName = appointment.name || appointment.title || 'Unknown';
        const serviceName = appointment.serviceName || 'Appointment';
        const statusLabel = getStatusLabel(appointment.status);

        return `
            <div class="appointment-card p-4 rounded-lg border-2 hover:shadow-md transition-all cursor-pointer"
                 style="background-color: ${statusColors.bg}; border-color: ${statusColors.border}; color: ${statusColors.text};"
                 data-appointment-id="${appointment.id}">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded-full flex-shrink-0" style="background-color: ${providerColor};" title="${provider?.name || 'Provider'}"></span>
                        <div class="text-sm font-medium">${time}</div>
                    </div>
                    <span class="px-2 py-1 text-xs font-medium rounded-full border"
                          style="background-color: ${statusColors.dot}; border-color: ${statusColors.border}; color: white;">
                        ${statusLabel}
                    </span>
                </div>
                
                <h4 class="text-lg font-semibold mb-1">
                    ${escapeHtmlUtil(customerName)}
                </h4>
                
                <p class="text-sm mb-2 opacity-90">
                    ${escapeHtmlUtil(serviceName)}
                </p>
                
                ${provider ? `
                    <div class="flex items-center gap-2 text-xs opacity-75">
                        <span class="material-symbols-outlined text-sm">person</span>
                        ${escapeHtmlUtil(provider.name)}
                    </div>
                ` : ''}
            </div>
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
        // Appointment card/block click handlers
        container.querySelectorAll('[data-appointment-id]').forEach(el => {
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

        // Removed: Click-to-create modal functionality
        // Time slots no longer open creation modal
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
