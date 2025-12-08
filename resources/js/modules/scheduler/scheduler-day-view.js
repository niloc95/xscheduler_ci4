/**
 * Custom Scheduler - Day View
 * 
 * Renders a single-day calendar with time slots in the same style as Week View.
 * Uses identical appointment card styling, grid alignment, and interactions.
 */

import { DateTime } from 'luxon';
import { getStatusColors, getProviderColor, getStatusLabel, isDarkMode } from './appointment-colors.js';

export class DayView {
    constructor(scheduler) {
        this.scheduler = scheduler;
    }

    render(container, data) {
        const { currentDate, appointments, providers, config, settings } = data;
        
        // Store settings for use in other methods
        this.settings = settings;
        this.currentDate = currentDate;
        this.appointments = appointments;
        this.providers = providers;
        
        // Use slotMinTime and slotMaxTime from calendar config
        const startTime = config?.slotMinTime || '08:00';
        const endTime = config?.slotMaxTime || '17:00';
        
        const businessHours = {
            startTime: startTime,
            endTime: endTime
        };
        
        const timeSlots = this.generateTimeSlots(businessHours);
        
        // Check if this date is blocked
        const blockedPeriods = config?.blockedPeriods || [];
        const isBlocked = this.isDateBlocked(currentDate, blockedPeriods);
        const blockedInfo = isBlocked ? this.getBlockedPeriodInfo(currentDate, blockedPeriods) : null;
        
        // Check if this is a non-working day
        const isNonWorkingDay = settings?.isWorkingDay ? !settings.isWorkingDay(currentDate.weekday % 7) : false;

        // Filter appointments for this day
        const dayAppointments = appointments.filter(apt => 
            apt.startDateTime.hasSame(currentDate, 'day')
        ).sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());

        // Render HTML - matching Week View structure exactly
        container.innerHTML = `
            <div class="scheduler-day-view bg-white dark:bg-gray-800">
                <!-- Calendar Grid - Single Day (matching Week View) -->
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full">
                        <!-- Day Header - matching Week View sticky header -->
                        <div class="grid grid-cols-2 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                            <div class="px-4 py-3 text-center border-r border-gray-200 dark:border-gray-700">
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">Time</span>
                            </div>
                            ${this.renderDayHeader(currentDate, isBlocked, blockedInfo, isNonWorkingDay)}
                        </div>

                        <!-- Time Grid - matching Week View exactly -->
                        <div class="relative">
                            ${timeSlots.map((slot, index) => this.renderTimeSlot(slot, index, dayAppointments, providers, data, isBlocked, isNonWorkingDay)).join('')}
                        </div>
                    </div>
                </div>
                
                ${isBlocked ? `
                    <div class="px-6 py-4 bg-red-50 dark:bg-red-900/20 border-t border-red-200 dark:border-red-800">
                        <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                            <span class="material-symbols-outlined">block</span>
                            <span class="text-sm font-medium">${this.escapeHtml(blockedInfo?.notes || 'This day is blocked')}</span>
                        </div>
                    </div>
                ` : ''}
                
                ${dayAppointments.length === 0 && !isBlocked ? `
                    <div class="px-6 py-8 text-center border-t border-gray-200 dark:border-gray-700">
                        <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Appointments</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            No appointments scheduled for ${currentDate.toFormat('EEEE, MMMM d')}
                        </p>
                    </div>
                ` : ''}
            </div>
        `;

        // Add event listeners
        this.attachEventListeners(container, data);
        
        // Render daily appointments section (matching Week View pattern)
        this.renderDailyAppointmentsSection(dayAppointments, providers, data);
    }

    renderDayHeader(day, isBlocked, blockedInfo, isNonWorkingDay) {
        const isToday = day.hasSame(DateTime.now(), 'day');
        
        // Determine background class based on status - matching Week View
        let bgClass = '';
        if (isBlocked) {
            bgClass = 'bg-red-100 dark:bg-red-900/20';
        } else if (isNonWorkingDay) {
            bgClass = 'bg-gray-50 dark:bg-gray-800';
        }
        
        return `
            <div class="px-4 py-3 text-center ${bgClass}">
                <div class="${isToday ? 'text-blue-600 dark:text-blue-400 font-bold' : isBlocked ? 'text-red-600 dark:text-red-400' : isNonWorkingDay ? 'text-gray-500 dark:text-gray-400' : 'text-gray-700 dark:text-gray-300'}">
                    <div class="text-xs font-medium">${day.toFormat('cccc')}</div>
                    <div class="text-2xl ${isToday ? 'flex items-center justify-center w-10 h-10 mx-auto mt-1 rounded-full bg-blue-600 text-white' : 'mt-1'}">
                        ${day.day}
                    </div>
                    <div class="text-sm mt-1">${day.toFormat('MMMM yyyy')}</div>
                    ${isBlocked ? `<div class="text-[10px] text-red-600 dark:text-red-400 mt-1 font-medium">🚫 Blocked</div>` : ''}
                </div>
            </div>
        `;
    }

    renderTimeSlot(slot, index, dayAppointments, providers, data, isBlocked, isNonWorkingDay) {
        const slotAppointments = this.getAppointmentsForSlot(dayAppointments, slot);
        
        // Determine cell styling - matching Week View exactly
        let cellClass = 'hover:bg-gray-50 dark:hover:bg-gray-700';
        if (isBlocked) {
            cellClass = 'bg-red-100 dark:bg-red-900/20';
        } else if (isNonWorkingDay) {
            cellClass = 'bg-gray-50 dark:bg-gray-800';
        }
        
        return `
            <div class="grid grid-cols-2 border-b border-gray-200 dark:border-gray-700 last:border-b-0 min-h-[60px]"
                 data-time-slot="${slot.time}">
                <!-- Time Label - matching Week View -->
                <div class="px-4 py-2 text-right border-r border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                    ${slot.display}
                </div>
                
                <!-- Appointments Column - matching Week View -->
                <div class="relative px-2 py-1 ${cellClass} transition-colors"
                     data-date="${this.currentDate.toISODate()}"
                     data-time="${slot.time}">
                    ${slotAppointments.map(apt => this.renderAppointmentBlock(apt, providers, slot)).join('')}
                </div>
            </div>
        `;
    }

    renderAppointmentBlock(appointment, providers, slot) {
        const provider = providers.find(p => p.id === appointment.providerId);
        const darkMode = isDarkMode();
        const statusColors = getStatusColors(appointment.status, darkMode);
        const providerColor = getProviderColor(provider);
        
        const customerName = appointment.name || appointment.title || 'Unknown';
        const serviceName = appointment.serviceName || 'Appointment';
        
        // Format time based on settings
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        const time = appointment.startDateTime.toFormat(timeFormat);

        // Matching Week View appointment block style exactly
        return `
            <div class="appointment-block absolute inset-x-2 p-2 rounded shadow-sm cursor-pointer hover:shadow-md transition-all text-xs z-10 border-l-4"
                 style="background-color: ${statusColors.bg}; border-left-color: ${statusColors.border}; color: ${statusColors.text};"
                 data-appointment-id="${appointment.id}"
                 title="${customerName} - ${serviceName} at ${time} - ${appointment.status}">
                <div class="flex items-center gap-1.5 mb-1">
                    <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${providerColor};" title="${provider?.name || 'Provider'}"></span>
                    <div class="font-semibold truncate">${time}</div>
                </div>
                <div class="truncate">${this.escapeHtml(customerName)}</div>
                <div class="text-xs opacity-80 truncate">${this.escapeHtml(serviceName)}</div>
            </div>
        `;
    }

    /**
     * Render daily appointments section - matching Week View's Weekly Schedule pattern
     */
    renderDailyAppointmentsSection(dayAppointments, providers, data) {
        const dailySection = document.getElementById('daily-provider-appointments');
        if (!dailySection) return;
        
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        
        // Group appointments by provider
        const appointmentsByProvider = {};
        providers.forEach(provider => {
            appointmentsByProvider[provider.id] = [];
        });
        
        dayAppointments.forEach(apt => {
            if (appointmentsByProvider[apt.providerId]) {
                appointmentsByProvider[apt.providerId].push(apt);
            }
        });
        
        // Filter to only show providers with appointments
        const activeProviders = providers.filter(p => 
            appointmentsByProvider[p.id].length > 0
        );
        
        dailySection.innerHTML = `
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Daily Schedule
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            ${this.currentDate.toFormat('EEEE, MMMM d, yyyy')}
                        </p>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        ${dayAppointments.length} ${dayAppointments.length === 1 ? 'appointment' : 'appointments'} today
                    </span>
                </div>
            </div>
            
            ${activeProviders.length > 0 ? `
                <!-- Provider Schedule - matching Week View -->
                <div class="p-6">
                    <div class="space-y-4">
                        ${activeProviders.map(provider => {
                            const providerAppointments = appointmentsByProvider[provider.id] || [];
                            const color = provider.color || '#3B82F6';
                            
                            // Sort by time
                            providerAppointments.sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());
                            
                            return `
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
                                                    ${providerAppointments.length} ${providerAppointments.length === 1 ? 'appointment' : 'appointments'} today
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Appointments List - matching Week View card styling -->
                                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                        ${providerAppointments.map(apt => {
                                            const time = apt.startDateTime.toFormat(timeFormat);
                                            const endTime = apt.endDateTime.toFormat(timeFormat);
                                            const customerName = apt.name || apt.customerName || apt.title || 'Unknown';
                                            const serviceName = apt.serviceName || 'Appointment';
                                            
                                            const darkMode = isDarkMode();
                                            const statusColors = getStatusColors(apt.status, darkMode);
                                            
                                            return `
                                                <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer border-l-4"
                                                     style="border-left-color: ${statusColors.border}; background-color: ${statusColors.bg}; color: ${statusColors.text};"
                                                     data-appointment-id="${apt.id}">
                                                    <div class="flex items-start justify-between gap-2 mb-1">
                                                        <div class="flex-1 min-w-0 flex items-center gap-2">
                                                            <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${color};"></span>
                                                            <div class="text-xs font-medium">${time} - ${endTime}</div>
                                                        </div>
                                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full flex-shrink-0"
                                                              style="background-color: ${statusColors.dot}; color: white;">
                                                            ${apt.status}
                                                        </span>
                                                    </div>
                                                    <h5 class="font-semibold text-sm mb-1 truncate">${this.escapeHtml(customerName)}</h5>
                                                    <p class="text-xs opacity-80 truncate">${this.escapeHtml(serviceName)}</p>
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
                    <p class="text-sm text-gray-600 dark:text-gray-400">No appointments scheduled for ${this.currentDate.toFormat('EEEE, MMMM d')}</p>
                </div>
            `}
        `;
        
        // Add click handlers for appointments in the section
        this.attachDailySectionListeners(dailySection, data);
    }

    getAppointmentsForSlot(dayAppointments, slot) {
        return dayAppointments.filter(apt => {
            const aptHour = apt.startDateTime.hour;
            return aptHour === slot.hour;
        });
    }

    generateTimeSlots(businessHours) {
        const slots = [];
        
        const parseTime = (timeStr) => {
            if (!timeStr) return 9;
            const parts = timeStr.split(':');
            return parseInt(parts[0], 10);
        };
        
        const startHour = parseTime(businessHours.startTime);
        const endHour = parseTime(businessHours.endTime);
        
        for (let hour = startHour; hour <= endHour; hour++) {
            const time = `${hour.toString().padStart(2, '0')}:00`;
            const display = this.formatTime(hour);
            slots.push({ time, display, hour });
        }
        
        return slots;
    }

    formatTime(hour) {
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() || '12h';
        
        if (timeFormat === '24h') {
            return `${hour.toString().padStart(2, '0')}:00`;
        } else {
            if (hour === 0) return '12:00 AM';
            if (hour === 12) return '12:00 PM';
            if (hour < 12) return `${hour}:00 AM`;
            return `${hour - 12}:00 PM`;
        }
    }

    attachEventListeners(container, data) {
        // Appointment click handlers - matching Week View
        container.querySelectorAll('.appointment-block').forEach(el => {
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
    
    attachDailySectionListeners(section, data) {
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
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
