/**
 * Custom Scheduler - Week View
 * 
 * Renders a weekly calendar with time slots (hourly grid).
 * Displays appointments as positioned blocks in time slots with drag-and-drop support.
 */

import { DateTime } from 'luxon';

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
        
        const timeSlots = this.generateTimeSlots(businessHours);

        // Group appointments by day
        const appointmentsByDay = this.groupAppointmentsByDay(appointments, weekStart);

        // Render HTML
        container.innerHTML = `
            <div class="scheduler-week-view bg-white dark:bg-gray-800">
                <!-- Week Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        ${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('MMM d, yyyy')}
                    </h2>
                </div>

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
    }

    renderDayHeader(day, blockedPeriods) {
        const isToday = day.hasSame(DateTime.now(), 'day');
        const isBlocked = this.isDateBlocked(day, blockedPeriods);
        const blockedInfo = isBlocked ? this.getBlockedPeriodInfo(day, blockedPeriods) : null;
        
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
            <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700 last:border-b-0 min-h-[60px]"
                 data-time-slot="${slot.time}">
                <!-- Time Label -->
                <div class="px-4 py-2 text-right border-r border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                    ${slot.display}
                </div>
                
                <!-- Day Columns -->
                ${days.map(day => {
                    const dateKey = day.toISODate();
                    const slotAppointments = this.getAppointmentsForSlot(appointmentsByDay[dateKey] || [], slot);
                    const isBlocked = this.isDateBlocked(day, blockedPeriods);
                    
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
        const color = provider?.color || '#3B82F6';
        const textColor = this.getContrastColor(color);
        
        const customerName = appointment.name || appointment.title || 'Unknown';
        const serviceName = appointment.serviceName || 'Appointment';
        
        // Format time based on settings
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        const time = appointment.startDateTime.toFormat(timeFormat);

        return `
            <div class="appointment-block absolute inset-x-2 p-2 rounded shadow-sm cursor-pointer hover:shadow-md transition-shadow text-xs z-10"
                 style="background-color: ${color}; color: ${textColor};"
                 data-appointment-id="${appointment.id}"
                 title="${customerName} - ${serviceName} at ${time}">
                <div class="font-semibold truncate">${time}</div>
                <div class="truncate">${this.escapeHtml(customerName)}</div>
                <div class="text-xs opacity-90 truncate">${this.escapeHtml(serviceName)}</div>
            </div>
        `;
    }

    generateTimeSlots(businessHours) {
        const slots = [];
        
        // Handle both 'HH:MM:SS' and 'HH:MM' formats
        const parseTime = (timeStr) => {
            if (!timeStr) return 9; // Default to 9 AM
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
        // Get time format from settings (default to 12h if not available)
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() || '12h';
        
        if (timeFormat === '24h') {
            // 24-hour format: 09:00, 13:00, etc.
            return `${hour.toString().padStart(2, '0')}:00`;
        } else {
            // 12-hour format with AM/PM
            if (hour === 0) return '12:00 AM';
            if (hour === 12) return '12:00 PM';
            if (hour < 12) return `${hour}:00 AM`;
            return `${hour - 12}:00 PM`;
        }
    }

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
        return dayAppointments.filter(apt => {
            const aptHour = apt.startDateTime.hour;
            return aptHour === slot.hour;
        });
    }

    attachEventListeners(container, data) {
        // Appointment click handlers
        container.querySelectorAll('.appointment-block').forEach(el => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const aptId = parseInt(el.dataset.appointmentId, 10);
                const appointment = data.appointments.find(a => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    data.onAppointmentClick(appointment);
                }
            });
        });

        // Time slot click handlers (for creating new appointments)
        container.querySelectorAll('[data-date][data-time]').forEach(el => {
            if (!el.closest('[data-time-slot]')) return; // Only day cells, not time labels
            
            el.addEventListener('click', (e) => {
                if (e.target === el && !e.target.closest('.appointment-block')) {
                    const date = el.dataset.date;
                    const time = el.dataset.time;
                    console.log('Time slot clicked:', date, time);
                    this.scheduler.openCreateModal({ date, time });
                }
            });
        });
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

    /**
     * Get blocked period information for a date
     */
    getBlockedPeriodInfo(date, blockedPeriods) {
        if (!blockedPeriods || blockedPeriods.length === 0) return null;
        
        const checkDate = date.toISODate();
        
        const period = blockedPeriods.find(p => {
            return checkDate >= p.start && checkDate <= p.end;
        });
        
        return period || null;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
