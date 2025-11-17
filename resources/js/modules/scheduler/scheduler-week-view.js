/**
 * Custom Scheduler - Week View
 * 
 * Renders a weekly calendar with time slots (hourly grid).
 * Displays appointments as positioned blocks in time slots with drag-and-drop support.
 */

import { DateTime } from 'luxon';
import { getStatusColors, getProviderColor, isDarkMode } from './appointment-colors.js';
import { generateTimeSlots } from './time-slots.js';
import { escapeHtml, isDateBlocked as isDateBlockedUtil, getBlockedPeriodInfo as getBlockedInfo } from './utils.js';

export class WeekView {
    constructor(scheduler) {
        this.scheduler = scheduler;
    }

    render(container, data) {
        const { currentDate, appointments, providers, config } = data;
        
        const weekStart = currentDate.startOf('week');
        const weekEnd = weekStart.plus({ days: 6 });
        
        // Generate array of 7 days for the week
        const days = [];
        for (let i = 0; i < 7; i++) {
            days.push(weekStart.plus({ days: i }));
        }
        
        // Get blocked periods from config
        const blockedPeriods = config?.blockedPeriods || [];
        
        // Use slotMinTime and slotMaxTime from calendar config (reads from business.work_start/work_end settings)
        const startTime = config?.slotMinTime || '08:00';
        const endTime = config?.slotMaxTime || '17:00';
        
        const businessHours = {
            startTime: startTime,
            endTime: endTime
        };
        
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() || '12h';
        const timeSlots = generateTimeSlots(businessHours, timeFormat, 30);

        // Group appointments by day
        const appointmentsByDay = this.groupAppointmentsByDay(appointments, weekStart);

        // Render HTML
        container.innerHTML = `
            <div class="scheduler-week-view bg-white dark:bg-gray-800">
                <!-- Calendar Grid -->
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full">
                        <!-- Day Headers -->
                        <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                            <div class="px-4 py-3 text-center border-r border-gray-200 dark:border-gray-700">
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">Time</span>
                            </div>
                            ${days.map(day => this.renderDayHeader(day, blockedPeriods)).join('')}
                        </div>

                        <!-- Time Grid -->
                        <div class="relative">
                            ${timeSlots.map((slot, index) => this.renderTimeSlot(slot, index, days, appointmentsByDay, providers, data, blockedPeriods)).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add event listeners
        this.attachEventListeners(container, data);
        
        // Render daily appointments section for the week
        this.renderWeeklyAppointmentsSection(days, appointments, providers, data);
    }

    renderDayHeader(day, blockedPeriods) {
        const isToday = day.hasSame(DateTime.now(), 'day');
        const isBlocked = isDateBlockedUtil(day, blockedPeriods);
        const blockedInfo = isBlocked ? getBlockedInfo(day, blockedPeriods) : null;
        
        return `
            <div class="px-4 py-3 text-center border-r border-gray-200 dark:border-gray-700 last:border-r-0 ${isBlocked ? 'bg-red-50 dark:bg-red-900/10' : ''}">
                <div class="${isToday ? 'text-blue-600 dark:text-blue-400 font-bold' : isBlocked ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300'}">
                    <div class="text-xs font-medium">${day.toFormat('ccc')}</div>
                    <div class="text-lg ${isToday ? 'flex items-center justify-center w-8 h-8 mx-auto mt-1 rounded-full bg-blue-600 text-white' : 'mt-1'}">
                        ${day.day}
                    </div>
                    ${isBlocked ? `<div class="text-[10px] text-red-600 dark:text-red-400 mt-1 font-medium">🚫 Blocked</div>` : ''}
                </div>
            </div>
        `;
    }

    renderTimeSlot(slot, index, days, appointmentsByDay, providers, data, blockedPeriods) {
        return `
              <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700 last:border-b-0 min-h-[56px]"
                 data-time-slot="${slot.time}">
                <!-- Time Label -->
                <div class="px-4 py-2 text-right border-r border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                    ${slot.display}
                </div>
                
                <!-- Day Columns -->
                ${days.map(day => {
                    const dateKey = day.toISODate();
                    const slotAppointments = this.getAppointmentsForSlot(appointmentsByDay[dateKey] || [], slot);
                    const isBlocked = isDateBlockedUtil(day, blockedPeriods);
                    
                    return `
                        <div class="relative px-2 py-1 border-r border-gray-200 dark:border-gray-700 last:border-r-0 ${isBlocked ? 'bg-red-50 dark:bg-red-900/10 opacity-50' : 'hover:bg-gray-50 dark:hover:bg-gray-700'} transition-colors"
                             data-date="${dateKey}"
                             data-time="${slot.time}">
                            ${slotAppointments.map(apt => this.renderAppointmentBlock(apt, providers, slot)).join('')}
                        </div>
                    `;
                }).join('')}
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

        return `
            <div class="appointment-block absolute inset-x-2 p-2 rounded shadow-sm cursor-pointer hover:shadow-md transition-all text-xs z-10 border-l-4"
                 style="background-color: ${statusColors.bg}; border-left-color: ${statusColors.border}; color: ${statusColors.text};"
                 data-appointment-id="${appointment.id}"
                 title="${customerName} - ${serviceName} at ${time} - ${appointment.status}">
                <div class="flex items-center gap-1.5 mb-1">
                    <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${providerColor};" title="${provider?.name || 'Provider'}"></span>
                    <div class="font-semibold truncate">${time}</div>
                </div>
                <div class="truncate">${escapeHtml(customerName)}</div>
                <div class="text-xs opacity-80 truncate">${escapeHtml(serviceName)}</div>
            </div>
        `;
    }

    // generateTimeSlots and formatTime moved to shared util (time-slots.js)

    groupAppointmentsByDay(appointments, weekStart) {
        const grouped = {};
        
        // Initialize all days
        for (let i = 0; i < 7; i++) {
            const dateKey = weekStart.plus({ days: i }).toISODate();
            grouped[dateKey] = [];
        }
        
        // Group appointments
        appointments.forEach(apt => {
            const dateKey = apt.startDateTime.toISODate();
            if (grouped[dateKey]) {
                grouped[dateKey].push(apt);
            }
        });

        return grouped;
    }

    getAppointmentsForSlot(dayAppointments, slot) {
        return dayAppointments.filter(apt => apt.startDateTime.toFormat('HH:mm') === slot.time);
    }

    attachEventListeners(container, data) {
        // Appointment click handlers
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
    // isDateBlocked, getBlockedPeriodInfo, escapeHtml moved to shared utils
    
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
                                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                                    <!-- Provider Header -->
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
                                                                
                                                                const statusColors = {
                                                                    confirmed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                                    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                                                    completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                                    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                                    'no-show': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                                                };
                                                                const statusClass = statusColors[apt.status] || statusColors.pending;
                                                                
                                                                return `
                                                                    <div class="text-xs p-2 rounded border border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 cursor-pointer transition-colors"
                                                                         data-appointment-id="${apt.id}">
                                                                        <div class="font-medium text-gray-900 dark:text-white truncate">${time}</div>
                                                                        <div class="text-gray-600 dark:text-gray-300 truncate">${this.escapeHtml(customerName)}</div>
                                                                        <span class="inline-block mt-1 px-1.5 py-0.5 text-[10px] font-medium rounded ${statusClass}">
                                                                            ${apt.status}
                                                                        </span>
                                                                    </div>
                                                                `;
                                                            }).join('') 
                                                            : `<div class="text-xs text-gray-400 dark:text-gray-500 italic">No appointments</div>`
                                                        }
                                                        ${dayAppointments.length > 3 ? `
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium text-center pt-1">
                                                                +${dayAppointments.length - 3} more
                                                            </div>
                                                        ` : ''}
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
