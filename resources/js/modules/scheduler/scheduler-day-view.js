/**
 * Custom Scheduler - Day View
 * 
 * Renders a single-day calendar with detailed time slots and appointment information.
 * Shows customer details, service information, and quick action buttons.
 */

import { DateTime } from 'luxon';

export class DayView {
    constructor(scheduler) {
        this.scheduler = scheduler;
    }

    render(container, data) {
        const { currentDate, appointments, providers, config } = data;
        
        // Use slotMinTime and slotMaxTime from calendar config (reads from business.work_start/work_end settings)
        const startTime = config?.slotMinTime || '08:00';
        const endTime = config?.slotMaxTime || '17:00';
        
        const businessHours = {
            startTime: startTime,
            endTime: endTime,
            isWorkingDay: true
        };
        
        const timeSlots = this.generateTimeSlots(businessHours);
        
        // Check if this date is blocked
        const isBlocked = this.isDateBlocked(currentDate, config?.blockedPeriods);
        const blockedNotice = isBlocked ? config.blockedPeriods.find(period => {
            const checkDate = currentDate.toISODate();
            return checkDate >= period.start && checkDate <= period.end;
        }) : null;

        // Filter appointments for this day
        const dayAppointments = appointments.filter(apt => 
            apt.startDateTime.hasSame(currentDate, 'day')
        ).sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());

        // Render HTML
        container.innerHTML = `
            <div class="scheduler-day-view bg-white dark:bg-gray-800">
                <!-- Day Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        ${currentDate.toFormat('EEEE, MMMM d, yyyy')}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        ${dayAppointments.length} ${dayAppointments.length === 1 ? 'appointment' : 'appointments'} scheduled
                    </p>
                    ${isBlocked ? `
                        <div class="mt-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl">block</span>
                                <div>
                                    <p class="text-sm font-medium text-red-800 dark:text-red-300">Blocked Period</p>
                                    <p class="text-xs text-red-600 dark:text-red-400">${this.escapeHtml(blockedNotice?.notes || 'No appointments allowed')}</p>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>

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
        // Show appointments that start within this hour (regardless of minute)
        const slotAppointments = appointments.filter(apt => {
            const aptHour = apt.startDateTime.hour;
            return aptHour === slot.hour; // Match any appointment starting in this hour
        });

        return `
            <div class="time-slot flex items-start gap-4 p-3 rounded-lg border border-gray-200 dark:border-gray-700 
                        hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer min-h-[80px]"
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
                        <div class="font-semibold truncate">${this.escapeHtml(customerName)}</div>
                        <div class="text-sm opacity-90 truncate">${this.escapeHtml(serviceName)}</div>
                        ${provider ? `<div class="text-xs opacity-75 mt-1">with ${this.escapeHtml(provider.name)}</div>` : ''}
                    </div>
                    <span class="material-symbols-outlined text-lg flex-shrink-0">arrow_forward</span>
                </div>
            </div>
        `;
    }

    renderAppointmentCard(appointment, providers, data) {
        const provider = providers.find(p => p.id === appointment.providerId);
        const color = provider?.color || '#3B82F6';
        
        // Format time based on settings
        const timeFormat = this.scheduler?.settingsManager?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        const time = `${appointment.startDateTime.toFormat(timeFormat)} - ${appointment.endDateTime.toFormat(timeFormat)}`;
        const customerName = appointment.name || appointment.title || 'Unknown';
        const serviceName = appointment.serviceName || 'Appointment';
        const statusColors = {
            confirmed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
            completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
        };
        const statusClass = statusColors[appointment.status] || statusColors.pending;

        return `
            <div class="appointment-card p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow cursor-pointer"
                 style="border-left: 4px solid ${color};"
                 data-appointment-id="${appointment.id}">
                <div class="flex items-start justify-between mb-2">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">${time}</div>
                    <span class="px-2 py-1 text-xs font-medium rounded-full ${statusClass}">
                        ${appointment.status}
                    </span>
                </div>
                
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                    ${this.escapeHtml(customerName)}
                </h4>
                
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    ${this.escapeHtml(serviceName)}
                </p>
                
                ${provider ? `
                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <span class="material-symbols-outlined text-sm">person</span>
                        ${this.escapeHtml(provider.name)}
                    </div>
                ` : ''}
            </div>
        `;
    }

    renderEmptyState() {
        return `
            <div class="text-center py-8">
                <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                <p class="text-sm text-gray-600 dark:text-gray-400">No appointments scheduled</p>
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

        // Time slot click handlers (for creating new appointments)
        container.querySelectorAll('.time-slot').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target === el || e.target.closest('.flex-1:not(.inline-appointment)')) {
                    const time = el.dataset.time;
                    const date = data.currentDate.toISODate();
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

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
