/**
 * Custom Scheduler - Today View
 * 
 * New view showing today's appointments with:
 * - Hero stats (total, upcoming, completed, free slots)
 * - Upcoming appointments list (normal opacity)
 * - Completed appointments list (65% opacity)
 * 
 * Designed for operational daily overview.
 */

import { DateTime } from 'luxon';
import { getStatusColors, getProviderColor, getProviderInitials, isDarkMode } from './appointment-colors.js';
import { withBaseUrl } from '../../utils/url-helpers.js';
import { escapeHtml } from '../../utils/html.js';
import { getBusinessHours } from './time-grid-utils.js';
import { createDebugLogger } from './scheduler-debug.js';

export class TodayView {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.debugLog = createDebugLogger(() => this.scheduler);
    }

    render(container, data) {
        const { currentDate, appointments, providers, config, settings } = data;
        
        this.debugLog('📅 TodayView.render called');
        this.debugLog('   Appointments received:', appointments.length);
        
        // Store for internal use
        this.appointments = appointments;
        this.providers = providers;
        this.settings = settings;
        this.config = config;
        this.currentDate = currentDate;
        this.container = container;

        // Get today's date (normalized to scheduler timezone)
        const today = DateTime.now().setZone(this.scheduler.options.timezone).startOf('day');
        
        // Filter appointments for today only
        const todayAppointments = appointments.filter(apt => {
            return apt.startDateTime && apt.startDateTime.hasSame(today, 'day');
        });

        this.debugLog('   Today appointments:', todayAppointments.length);

        // Calculate hero stats
        const stats = this._calculateStats(todayAppointments, today, config);

        // Separate upcoming vs completed
        const now = DateTime.now().setZone(this.scheduler.options.timezone);
        const upcoming = todayAppointments.filter(apt => 
            apt.startDateTime >= now && !['completed', 'cancelled', 'noshow'].includes(apt.status)
        ).sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());

        const completed = todayAppointments.filter(apt => 
            ['completed', 'cancelled', 'noshow'].includes(apt.status) || apt.endDateTime < now
        ).sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());

        // Render view
        container.innerHTML = `
            <div class="today-view p-6 space-y-6">
                <!-- Hero Stats Section -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    ${this._renderHeroStat('Total', stats.total, 'event', 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400')}
                    ${this._renderHeroStat('Upcoming', stats.upcoming, 'schedule', 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400')}
                    ${this._renderHeroStat('Completed', stats.completed, 'check_circle', 'bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-400')}
                    ${this._renderHeroStat('Free Slots', stats.freeSlots, 'event_available', 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400')}
                </div>

                <!-- Action Bar -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Today's Schedule
                    </h3>
                    <a href="${withBaseUrl(`/appointments/create?date=${today.toISODate()}`)}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-lg">add</span>
                        <span>Book Appointment</span>
                    </a>
                </div>

                <!-- Upcoming Appointments -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-3">
                        Upcoming (${upcoming.length})
                    </h4>
                    <div class="space-y-2">
                        ${upcoming.length > 0 
                            ? upcoming.map(apt => this._renderAppointmentCard(apt, false)).join('') 
                            : this._renderEmptyState('No upcoming appointments', 'event_busy')}
                    </div>
                </div>

                <!-- Completed Appointments -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-3">
                        Completed (${completed.length})
                    </h4>
                    <div class="space-y-2 opacity-65">
                        ${completed.length > 0 
                            ? completed.map(apt => this._renderAppointmentCard(apt, true)).join('') 
                            : this._renderEmptyState('No completed appointments', 'check_circle')}
                    </div>
                </div>
            </div>
        `;

        // Attach event listeners
        this._attachListeners(container, data);
    }

    /**
     * Calculate hero stats for today.
     */
    _calculateStats(todayAppointments, today, config) {
        const now = DateTime.now().setZone(this.scheduler.options.timezone);

        const total = todayAppointments.length;
        
        const upcoming = todayAppointments.filter(apt => 
            apt.startDateTime >= now && !['completed', 'cancelled', 'noshow'].includes(apt.status)
        ).length;

        const completed = todayAppointments.filter(apt => 
            ['completed'].includes(apt.status)
        ).length;

        // Simplified free slots calculation
        // Assume 8 working hours * 2 slots per hour = 16 possible slots
        const businessHoursConfig = config?.businessHours || { startTime: '08:00', endTime: '17:00' };
        const { startHour, endHour } = getBusinessHours({ businessHours: businessHoursConfig });
        const hoursPerDay = endHour - startHour;
        const slotsPerHour = 2; // 30-minute slots
        const providerCount = Math.max(1, this.scheduler?.visibleProviders?.size || this.providers?.length || 1);
        const totalSlots = hoursPerDay * slotsPerHour * providerCount;
        const usedSlots = todayAppointments.filter(apt => !['cancelled', 'noshow'].includes(apt.status)).length;
        const freeSlots = Math.max(0, totalSlots - usedSlots);

        return { total, upcoming, completed, freeSlots };
    }

    /**
     * Render a hero stat card.
     */
    _renderHeroStat(label, value, icon, colorClass) {
        return `
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 ${colorClass}">
                <div class="flex items-center justify-between mb-2">
                    <span class="material-symbols-outlined text-2xl">${icon}</span>
                    <span class="text-3xl font-bold">${value}</span>
                </div>
                <div class="text-sm font-medium opacity-80">${label}</div>
            </div>
        `;
    }

    /**
     * Render an appointment card.
     */
    _renderAppointmentCard(appointment, isCompleted) {
        const provider = this.providers.find(p => p.id === appointment.providerId);
        const providerColor = getProviderColor(provider);
        const providerInitials = getProviderInitials(provider?.name || provider?.username);
        const statusColors = getStatusColors(appointment.status, isDarkMode());
        
        const timeFormat = this.settings?.getTimeFormat?.() === '24h' ? 'HH:mm' : 'h:mm a';
        const startTime = appointment.startDateTime.toFormat(timeFormat);
        const endTime = appointment.endDateTime?.toFormat(timeFormat);
        
        const customerName = appointment.customerName || appointment.title || 'Unknown Customer';
        const serviceName = appointment.serviceName || 'Service';

        return `
            <div class="appointment-card group p-3 bg-surface-0 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-primary-300 dark:hover:border-primary-700 transition-all cursor-pointer"
                 data-appointment-id="${appointment.id}">
                <div class="flex items-start gap-3">
                    <!-- Provider Avatar -->
                    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                        data-bg-color="${providerColor}">
                        ${providerInitials}
                    </div>
                    
                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <div class="font-medium text-gray-900 dark:text-white truncate">
                                ${escapeHtml(customerName)}
                            </div>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full whitespace-nowrap ${statusColors.bgClass} ${statusColors.textClass}">
                                ${escapeHtml(appointment.status)}
                            </span>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                            ${escapeHtml(serviceName)}
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span class="material-symbols-outlined text-sm">schedule</span>
                            <span>${startTime}${endTime ? ` - ${endTime}` : ''}</span>
                            ${provider?.name ? `
                                <span class="mx-1">•</span>
                                <span>${escapeHtml(provider.name)}</span>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Chevron -->
                    <span class="material-symbols-outlined text-gray-400 group-hover:text-primary-600 transition-colors">
                        chevron_right
                    </span>
                </div>
            </div>
        `;
    }

    /**
     * Render empty state.
     */
    _renderEmptyState(message, icon) {
        return `
            <div class="text-center py-8 px-4 bg-surface-1 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <span class="material-symbols-outlined text-4xl text-gray-400 dark:text-gray-500 mb-2 block">${icon}</span>
                <p class="text-sm text-gray-500 dark:text-gray-400">${message}</p>
            </div>
        `;
    }

    /**
     * Attach event listeners.
     */
    _attachListeners(container, data) {
        // Appointment card clicks
        container.querySelectorAll('.appointment-card').forEach(card => {
            card.addEventListener('click', () => {
                const aptId = parseInt(card.dataset.appointmentId, 10);
                const appointment = data.appointments.find(a => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    data.onAppointmentClick(appointment);
                }
            });
        });
    }
}
