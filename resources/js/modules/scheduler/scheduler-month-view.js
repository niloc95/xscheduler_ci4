/**
 * Custom Scheduler - Month View
 * 
 * Renders a traditional month calendar grid with appointments.
 * Displays appointments as colored blocks within date cells.
 */

import { DateTime } from 'luxon';
import { getStatusColors, getProviderColor, getProviderDotHtml, isDarkMode } from './appointment-colors.js';
import { logger } from './logger.js';

export class MonthView {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this.appointmentsByDate = {};
        this.selectedDate = null; // Track selected date for daily view
    }
    
    render(container, data) {
        const { currentDate, appointments, providers, config, settings } = data;
        
        logger.debug('🗓️ MonthView.render called');
        logger.debug('   Current date:', currentDate.toISO());
        logger.debug('   Appointments received:', appointments.length);
        logger.debug('   Appointments data:', appointments);
        logger.debug('   Providers:', providers.length);
        
        // Store data for use in other methods
        this.appointments = appointments;
        this.providers = providers;
        this.settings = settings;
        this.blockedPeriods = config?.blockedPeriods || [];
        this.currentDate = currentDate;
        
        // Initialize selected date to today if not set
        if (!this.selectedDate) {
            this.selectedDate = DateTime.now().setZone(this.scheduler.options.timezone);
        }
        
        // Get calendar grid data
        const monthStart = currentDate.startOf('month');
        const monthEnd = currentDate.endOf('month');
        
        // Use first day of week from settings (0 = Sunday, 1 = Monday, etc.)
        const firstDayOfWeek = settings?.getFirstDayOfWeek() || 0;
        
        // Luxon uses Monday as week start by default (ISO 8601)
        // We need to adjust to start from the configured day
        // If firstDayOfWeek is 0 (Sunday), we need to go back 1 more day from Luxon's Monday start
        let gridStart = monthStart.startOf('week'); // This gives us Monday
        if (firstDayOfWeek === 0) {
            // For Sunday start, go back one more day
            gridStart = gridStart.minus({ days: 1 });
        }
        
        let gridEnd = monthEnd.endOf('week'); // This gives us Sunday (end of ISO week)
        if (firstDayOfWeek === 0) {
            // For Sunday start, the week ends on Saturday, so go back one day
            gridEnd = gridEnd.minus({ days: 1 });
        }
        
        // Generate weeks
        const weeks = [];
        let current = gridStart;
        
        while (current <= gridEnd) {
            const week = [];
            for (let i = 0; i < 7; i++) {
                week.push(current);
                current = current.plus({ days: 1 });
            }
            weeks.push(week);
        }

        // Group appointments by date
        this.appointmentsByDate = this.groupAppointmentsByDate(appointments);

        // Render HTML
        // P0-3 FIX: Changed auto-rows-fr to auto-rows-[minmax(100px,auto)] to prevent bottom row clipping
        container.innerHTML = `
            <div class="scheduler-month-view bg-white dark:bg-gray-800">
                <!-- Day Headers -->
                <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
                    ${this.renderDayHeaders(config, settings)}
                </div>

                <!-- Calendar Grid - P0-3 FIX: Use minmax for proper row sizing -->
                <div class="grid grid-cols-7 auto-rows-[minmax(100px,auto)] divide-x divide-y divide-gray-200 dark:divide-gray-700">
                    ${weeks.map(week => week.map(day => 
                        this.renderDayCell(day, monthStart.month, settings)
                    ).join('')).join('')}
                </div>
                
                ${appointments.length === 0 ? `
                <div class="px-6 py-8 text-center border-t border-gray-200 dark:border-gray-700">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Appointments</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Click on any day to create a new appointment
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">
                        💡 Backend API endpoints need to be implemented to load and save appointments
                    </p>
                </div>
                ` : ''}
            </div>
        `;

        // Add event listeners
        this.attachEventListeners(container, data);
        
        // Render daily appointments section for the selected day
        this.renderDailySection(data);
    }

    renderDayHeaders(config, settings) {
        const firstDay = settings?.getFirstDayOfWeek() || config?.firstDayOfWeek || 0;
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        // Rotate array to start with firstDay
        const rotatedDays = [...days.slice(firstDay), ...days.slice(0, firstDay)];
        
        return rotatedDays.map(day => `
            <div class="px-4 py-3 text-center">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">${day}</span>
            </div>
        `).join('');
    }

    renderDayCell(day, currentMonth, settings) {
        const isToday = day.hasSame(DateTime.now(), 'day');
        const isCurrentMonth = day.month === currentMonth;
        const isPast = day < DateTime.now().startOf('day');
        const dayAppointments = this.getAppointmentsForDay(day);
        
        
        const isWorkingDay = settings?.isWorkingDay ? settings.isWorkingDay(day) : true;
        const isBlocked = this.isDateBlocked(day);
        const blockedInfo = isBlocked ? this.getBlockedPeriodInfo(day) : null;
        const isSelected = this.selectedDate && day.hasSame(this.selectedDate, 'day');
        
        const cellClasses = [
            'scheduler-day-cell',
            'min-h-[100px]',
            'p-2',
            'relative',
            // P0-3 FIX: Removed 'overflow-hidden' to prevent content clipping
            'cursor-pointer',
            'hover:bg-gray-50',
            'dark:hover:bg-gray-700/50',
            'transition-colors',
            isToday ? 'today' : '',
            !isCurrentMonth ? 'other-month' : '',
            isPast ? 'past' : '',
            !isWorkingDay ? 'non-working-day' : '',
            isBlocked ? 'bg-red-50 dark:bg-red-900/10' : '',
            isSelected ? 'ring-2 ring-blue-500 ring-inset bg-blue-50 dark:bg-blue-900/20' : ''
        ].filter(Boolean).join(' ');
        
        const html = `
            <div class="${cellClasses}" data-date="${day.toISODate()}" data-click-create="day" data-select-day="${day.toISODate()}">
                <div class="day-number text-sm font-medium mb-1 ${isCurrentMonth ? isBlocked ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-600'}">
                    ${day.day}
                    ${isBlocked ? '<span class="text-xs ml-1">🚫</span>' : ''}
                </div>
                ${isBlocked && blockedInfo ? `
                    <div class="text-[10px] text-red-600 dark:text-red-400 font-medium mb-1 truncate" title="${this.escapeHtml(blockedInfo.notes || 'Blocked')}">
                        ${this.escapeHtml(blockedInfo.notes || 'Blocked')}
                    </div>
                ` : ''}
                <div class="day-appointments space-y-1">
                    ${dayAppointments.slice(0, 3).map(apt => this.renderAppointmentBlock(apt)).join('')}
                    ${dayAppointments.length > 3 ? `<div class="text-xs text-gray-500 dark:text-gray-400 font-medium cursor-pointer hover:text-blue-600" data-show-more="${day.toISODate()}">+${dayAppointments.length - 3} more</div>` : ''}
                </div>
            </div>
        `;
        
        return html;
    }

    renderAppointmentBlock(appointment) {
        try {
            const provider = this.providers.find(p => p.id === appointment.providerId);
            const darkMode = isDarkMode();
            const statusColors = getStatusColors(appointment.status, darkMode);
            const providerColor = getProviderColor(provider);
            
            // Use settings to format time
            const time = this.settings?.formatTime ? this.settings.formatTime(appointment.startDateTime) : appointment.startDateTime.toFormat('h:mm a');
            
            const title = appointment.title || appointment.customerName || 'Appointment';

            const html = `
            <div class="scheduler-appointment text-xs px-2 py-1 rounded cursor-pointer hover:opacity-90 transition-all truncate border-l-4 flex items-center gap-1.5"
                 style="background-color: ${statusColors.bg}; border-left-color: ${statusColors.border}; color: ${statusColors.text};"
                 data-appointment-id="${appointment.id}"
                 title="${title} at ${time} - ${appointment.status}">
                <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${providerColor};" title="${provider?.name || 'Provider'}"></span>
                <span class="font-medium">${time}</span>
                <span class="truncate">${this.escapeHtml(title)}</span>
            </div>
        `;
            
            return html;
        } catch (error) {
            console.error(`Error rendering appointment #${appointment.id}:`, error);
            return `<div class="text-red-500">Error rendering appointment</div>`;
        }
    }
    
    getAppointmentsForDay(day) {
        const dateKey = day.toISODate();
        const appointments = this.appointmentsByDate[dateKey] || [];
        return appointments;
    }

    groupAppointmentsByDate(appointments) {
        const grouped = {};
        
        appointments.forEach(apt => {
            // Check if startDateTime exists
            if (!apt.startDateTime) {
                console.error('Appointment missing startDateTime:', apt);
                return; // Skip this appointment
            }
            
            const dateKey = apt.startDateTime.toISODate();
            if (!grouped[dateKey]) {
                grouped[dateKey] = [];
            }
            grouped[dateKey].push(apt);
        });

        // Sort appointments by start time within each day
        Object.keys(grouped).forEach(dateKey => {
            grouped[dateKey].sort((a, b) => 
                a.startDateTime.toMillis() - b.startDateTime.toMillis()
            );
        });
        
        logger.debug('🗂️ Final grouped appointments:', Object.keys(grouped).map(key => `${key}: ${grouped[key].length} appointments`));
        return grouped;
    }

    /**
     * Render daily appointments section showing provider columns for selected day
     * P0-4 FIX: Now shows ALL appointments with expand functionality instead of truncating
     */
    renderDailyAppointments() {
        // For month view, show all appointments for the entire month grouped by provider
        const monthStart = this.currentDate.startOf('month');
        const monthEnd = this.currentDate.endOf('month');
        
        // Get all appointments for the month
        const monthAppointments = this.appointments.filter(apt => 
            apt.startDateTime >= monthStart && apt.startDateTime <= monthEnd
        );
        
        // Group appointments by provider
        const appointmentsByProvider = {};
        this.providers.forEach(provider => {
            appointmentsByProvider[provider.id] = [];
        });
        
        monthAppointments.forEach(apt => {
            if (appointmentsByProvider[apt.providerId]) {
                appointmentsByProvider[apt.providerId].push(apt);
            }
        });
        
        // Filter to only show providers with appointments
        const activeProviders = this.providers.filter(p => 
            appointmentsByProvider[p.id].length > 0
        );
        
        const timeFormat = this.settings?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        
        // Build the HTML
        let html = `
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Monthly Schedule
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            ${this.currentDate.toFormat('MMMM yyyy')}
                        </p>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        ${monthAppointments.length} ${monthAppointments.length === 1 ? 'appointment' : 'appointments'} this month
                    </span>
                </div>
            </div>
        `;
        
        if (activeProviders.length > 0) {
            html += `
                <!-- Provider Columns -->
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${Math.min(activeProviders.length, 3)} gap-4">
            `;
            
            activeProviders.forEach(provider => {
                const providerAppointments = appointmentsByProvider[provider.id] || [];
                const color = provider.color || '#3B82F6';
                
                // Sort appointments by date
                providerAppointments.sort((a, b) => a.startDateTime.toMillis() - b.startDateTime.toMillis());
                
                // P0-4 FIX: Configuration for initial display and pagination
                const INITIAL_DISPLAY = 10;
                const hasMore = providerAppointments.length > INITIAL_DISPLAY;
                const providerId = provider.id;
                
                html += `
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden" data-provider-card="${providerId}">
                        <!-- Provider Header -->
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                             style="border-left: 4px solid ${color};">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                                     style="background-color: ${color};">
                                    ${provider.name.charAt(0).toUpperCase()}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 dark:text-white truncate">
                                        ${this.escapeHtml(provider.name)}
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        ${providerAppointments.length} ${providerAppointments.length === 1 ? 'appointment' : 'appointments'} this month
                                    </p>
                                </div>
                                ${hasMore ? `
                                <button type="button" 
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium flex items-center gap-1 transition-colors"
                                        data-expand-provider="${providerId}"
                                        title="View all ${providerAppointments.length} appointments">
                                    <span class="material-symbols-outlined text-sm">expand_more</span>
                                    <span>View all</span>
                                </button>
                                ` : ''}
                            </div>
                        </div>
                        
                        <!-- Appointments List - P0-4 FIX: Scrollable container with all appointments -->
                        <div class="divide-y divide-gray-200 dark:divide-gray-700 overflow-y-auto transition-all duration-300"
                             data-provider-appointments="${providerId}"
                             style="max-height: ${hasMore ? '400px' : 'none'};">
                `;
                
                if (providerAppointments.length > 0) {
                    // P0-4 FIX: Render ALL appointments, not just first 10
                    // Initial state shows first 10, expand shows all in scrollable container
                    providerAppointments.forEach((apt, index) => {
                        const date = apt.startDateTime.toFormat('MMM d');
                        const time = apt.startDateTime.toFormat(timeFormat);
                        const customerName = apt.name || apt.customerName || apt.title || 'Unknown';
                        const serviceName = apt.serviceName || 'Appointment';
                        
                        const darkMode = isDarkMode();
                        const statusColors = getStatusColors(apt.status, darkMode);
                        const providerColor = getProviderColor(provider);
                        
                        // P0-4 FIX: Initially hide appointments beyond INITIAL_DISPLAY, show on expand
                        const isHidden = hasMore && index >= INITIAL_DISPLAY;
                        
                        html += `
                            <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer border-l-4 ${isHidden ? 'hidden' : ''}"
                                 style="border-left-color: ${statusColors.border}; background-color: ${statusColors.bg}; color: ${statusColors.text};"
                                 data-appointment-id="${apt.id}"
                                 data-provider-apt="${providerId}"
                                 data-apt-index="${index}">
                                <div class="flex items-start justify-between gap-2 mb-1">
                                    <div class="flex-1 min-w-0 flex items-center gap-2">
                                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${providerColor};"></span>
                                        <div class="text-xs font-medium">
                                            ${date} • ${time}
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full flex-shrink-0"
                                          style="background-color: ${statusColors.dot}; color: white;">
                                        ${apt.status}
                                    </span>
                                </div>
                                <h5 class="font-semibold text-sm mb-1 truncate">
                                    ${this.escapeHtml(customerName)}
                                </h5>
                                <p class="text-xs opacity-80 truncate">
                                    ${this.escapeHtml(serviceName)}
                                </p>
                            </div>
                        `;
                    });
                    
                    // P0-4 FIX: Add expand/collapse button at bottom if there are more appointments
                    if (hasMore) {
                        const hiddenCount = providerAppointments.length - INITIAL_DISPLAY;
                        html += `
                            <div class="p-3 text-center border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 sticky bottom-0"
                                 data-expand-footer="${providerId}">
                                <button type="button"
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-all"
                                        data-expand-toggle="${providerId}"
                                        data-expanded="false">
                                    <span class="material-symbols-outlined text-lg expand-icon">expand_more</span>
                                    <span class="expand-text">Show ${hiddenCount} more appointments</span>
                                </button>
                            </div>
                        `;
                    }
                } else {
                    html += `
                        <div class="p-8 text-center">
                            <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-4xl mb-2">event_available</span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No appointments</p>
                        </div>
                    `;
                }
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        } else {
            html += `
                <!-- Empty State -->
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                    <p class="text-sm text-gray-600 dark:text-gray-400">No appointments scheduled for ${this.currentDate.toFormat('MMMM yyyy')}</p>
                </div>
            `;
        }
        
        return html;
    }
    
    /**
     * Render daily section to the separate container outside the calendar
     */
    renderDailySection(data) {
        const dailyContainer = document.getElementById('daily-provider-appointments');
        if (!dailyContainer) {
            logger.debug('[MonthView] Daily provider appointments container not found');
            return;
        }
        
        logger.debug('[MonthView] Rendering daily section to separate container');
        dailyContainer.innerHTML = this.renderDailyAppointments();
        
        // Attach event listeners to the daily section
        this.attachDailySectionListeners(dailyContainer, data);
    }
    
    /**
     * Attach event listeners to daily section content
     */
    attachDailySectionListeners(container, data) {
        if (!container) return;
        
        // Use passed data or fallback to instance data
        const appointments = data?.appointments || this.appointments;
        const onAppointmentClick = data?.onAppointmentClick || this.scheduler.handleAppointmentClick.bind(this.scheduler);
        
        // Action buttons (view/edit)
        container.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const action = btn.dataset.action;
                const aptId = parseInt(btn.dataset.appointmentId, 10);
                const appointment = appointments.find(a => a.id === aptId);
                
                if (appointment && (action === 'view' || action === 'edit')) {
                    onAppointmentClick(appointment);
                }
            });
        });
        
        // Appointment cards (entire card clickable)
        container.querySelectorAll('[data-appointment-id]:not([data-action])').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('[data-action]')) return;
                
                const aptId = parseInt(card.dataset.appointmentId, 10);
                const appointment = appointments.find(a => a.id === aptId);
                
                if (appointment) {
                    onAppointmentClick(appointment);
                }
            });
        });
        
        // P0-4 FIX: Expand/collapse toggle handlers for provider appointment lists
        container.querySelectorAll('[data-expand-toggle]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const providerId = btn.dataset.expandToggle;
                const isExpanded = btn.dataset.expanded === 'true';
                const appointmentContainer = container.querySelector(`[data-provider-appointments="${providerId}"]`);
                const icon = btn.querySelector('.expand-icon');
                const text = btn.querySelector('.expand-text');
                
                if (!appointmentContainer) return;
                
                // Get all hidden appointments for this provider
                const hiddenAppointments = appointmentContainer.querySelectorAll(`[data-provider-apt="${providerId}"].hidden`);
                const visibleAppointments = appointmentContainer.querySelectorAll(`[data-provider-apt="${providerId}"]:not(.hidden)`);
                
                if (isExpanded) {
                    // Collapse: hide appointments beyond index 9 (0-indexed, so first 10 visible)
                    appointmentContainer.querySelectorAll(`[data-provider-apt="${providerId}"]`).forEach(apt => {
                        const index = parseInt(apt.dataset.aptIndex, 10);
                        if (index >= 10) {
                            apt.classList.add('hidden');
                        }
                    });
                    
                    // Update button state
                    btn.dataset.expanded = 'false';
                    if (icon) icon.textContent = 'expand_more';
                    
                    // Count hidden appointments
                    const totalApts = appointmentContainer.querySelectorAll(`[data-provider-apt="${providerId}"]`).length;
                    const hiddenCount = totalApts - 10;
                    if (text) text.textContent = `Show ${hiddenCount} more appointments`;
                    
                    // Reset container height
                    appointmentContainer.style.maxHeight = '400px';
                    
                    // Scroll to top of container
                    appointmentContainer.scrollTop = 0;
                } else {
                    // Expand: show all appointments
                    hiddenAppointments.forEach(apt => {
                        apt.classList.remove('hidden');
                    });
                    
                    // Update button state
                    btn.dataset.expanded = 'true';
                    if (icon) icon.textContent = 'expand_less';
                    if (text) text.textContent = 'Show less';
                    
                    // Expand container height to show more
                    appointmentContainer.style.maxHeight = '600px';
                }
                
                logger.debug(`[MonthView] Provider ${providerId} appointments ${isExpanded ? 'collapsed' : 'expanded'}`);
            });
        });
        
        // P0-4 FIX: Header "View all" button handler
        container.querySelectorAll('[data-expand-provider]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const providerId = btn.dataset.expandProvider;
                // Find and click the corresponding expand toggle button
                const toggleBtn = container.querySelector(`[data-expand-toggle="${providerId}"]`);
                if (toggleBtn && toggleBtn.dataset.expanded !== 'true') {
                    toggleBtn.click();
                }
            });
        });
    }

    attachEventListeners(container, data) {
        // Appointment click handlers (in calendar grid)
        container.querySelectorAll('.scheduler-appointment').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                logger.debug('[MonthView] Appointment clicked, prevented default');
                const aptId = parseInt(el.dataset.appointmentId, 10);
                const appointment = data.appointments.find(a => a.id === aptId);
                if (appointment && data.onAppointmentClick) {
                    logger.debug('[MonthView] Calling onAppointmentClick');
                    data.onAppointmentClick(appointment);
                } else {
                    logger.warn('[MonthView] No appointment found or no callback');
                }
            });
        });

        // "Show more" handlers - P0-4 FIX: Show modal with all appointments for the day
        container.querySelectorAll('[data-show-more]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const date = el.dataset.showMore;
                
                // Update selected date
                this.selectedDate = DateTime.fromISO(date, { zone: this.scheduler.options.timezone });
                
                // Get all appointments for this day
                const dayAppointments = this.getAppointmentsForDay(this.selectedDate);
                
                // Show modal with all appointments
                this.showDayAppointmentsModal(this.selectedDate, dayAppointments, data);
                
                logger.debug('Show more appointments for', date, '- Total:', dayAppointments.length);
            });
        });

        // Day cell click handlers (for day selection and creating new appointments)
        container.querySelectorAll('[data-select-day]').forEach(el => {
            el.addEventListener('click', (e) => {
                // Check if click was on an appointment or action button
                if (e.target.closest('.scheduler-appointment') || 
                    e.target.closest('[data-action]') ||
                    e.target.closest('[data-show-more]')) {
                    return; // Let those handlers deal with it
                }
                
                const date = el.dataset.selectDay;
                logger.debug('Day cell clicked:', date);
                
                // Update selected date
                this.selectedDate = DateTime.fromISO(date, { zone: this.scheduler.options.timezone });
                
                // Update visual selection in calendar grid
                container.querySelectorAll('.scheduler-day-cell').forEach(cell => {
                    cell.classList.remove('ring-2', 'ring-blue-500', 'ring-inset', 'bg-blue-50', 'dark:bg-blue-900/20');
                });
                el.classList.add('ring-2', 'ring-blue-500', 'ring-inset', 'bg-blue-50', 'dark:bg-blue-900/20');
                
                // Update daily appointments section
                this.updateDailySection(container);
                
                // Removed: Double-click to create appointment modal
            });
        });
    }
    
    /**
     * Update just the daily appointments section without re-rendering entire calendar
     */
    updateDailySection(container) {
        // Update the daily section outside the calendar (if it exists)
        const data = {
            appointments: this.appointments,
            onAppointmentClick: this.scheduler.handleAppointmentClick.bind(this.scheduler)
        };
        this.renderDailySection(data);
    }

    getContrastColor(hexColor) {
        // Convert hex to RGB
        const hex = hexColor.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        
        // Calculate luminance
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        
        return luminance > 0.5 ? '#000000' : '#FFFFFF';
    }

    /**
     * Check if a date falls within a blocked period
     */
    isDateBlocked(date) {
        if (!this.blockedPeriods || this.blockedPeriods.length === 0) return false;
        
        const checkDate = date.toISODate();
        
        return this.blockedPeriods.some(period => {
            const start = period.start;
            const end = period.end;
            return checkDate >= start && checkDate <= end;
        });
    }

    /**
     * Get blocked period information for a date
     */
    getBlockedPeriodInfo(date) {
        if (!this.blockedPeriods || this.blockedPeriods.length === 0) return null;
        
        const checkDate = date.toISODate();
        
        const period = this.blockedPeriods.find(p => {
            return checkDate >= p.start && checkDate <= p.end;
        });
        
        return period || null;
    }

    /**
     * P0-4 FIX: Show modal with all appointments for a specific day
     * This replaces the "+X more" collapsed indicator with a full view
     */
    showDayAppointmentsModal(date, appointments, data) {
        const timeFormat = this.settings?.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        const dateStr = date.toFormat('EEEE, MMMM d, yyyy');
        const onAppointmentClick = data?.onAppointmentClick || this.scheduler.handleAppointmentClick.bind(this.scheduler);
        
        // Remove existing modal if any
        const existingModal = document.getElementById('day-appointments-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Build modal HTML
        const modalHtml = `
            <div id="day-appointments-modal" class="scheduler-modal" role="dialog" aria-modal="true" aria-labelledby="day-modal-title">
                <div class="scheduler-modal-backdrop" data-close-modal></div>
                <div class="scheduler-modal-dialog">
                    <div class="scheduler-modal-panel">
                        <div class="scheduler-modal-header">
                            <div>
                                <h3 id="day-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                                    ${dateStr}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    ${appointments.length} ${appointments.length === 1 ? 'appointment' : 'appointments'}
                                </p>
                            </div>
                            <button type="button" class="p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition-colors" data-close-modal>
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                        <div class="scheduler-modal-body">
                            ${appointments.length > 0 ? `
                                <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    ${appointments.map(apt => {
                                        const time = apt.startDateTime.toFormat(timeFormat);
                                        const endTime = apt.endDateTime ? apt.endDateTime.toFormat(timeFormat) : '';
                                        const customerName = apt.name || apt.customerName || apt.title || 'Unknown';
                                        const serviceName = apt.serviceName || 'Appointment';
                                        const provider = this.providers.find(p => p.id === apt.providerId);
                                        const providerName = provider?.name || 'Unknown Provider';
                                        const providerColor = provider?.color || '#3B82F6';
                                        
                                        const darkMode = isDarkMode();
                                        const statusColors = getStatusColors(apt.status, darkMode);
                                        
                                        return `
                                            <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
                                                 data-modal-appointment-id="${apt.id}">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex-shrink-0 w-1 h-full rounded-full" style="background-color: ${providerColor};"></div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center justify-between gap-2 mb-2">
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                                                    ${time}${endTime ? ` - ${endTime}` : ''}
                                                                </span>
                                                                <span class="px-2 py-0.5 text-xs font-medium rounded-full"
                                                                      style="background-color: ${statusColors.bg}; color: ${statusColors.text}; border: 1px solid ${statusColors.border};">
                                                                    ${apt.status}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <h4 class="font-medium text-gray-900 dark:text-white mb-1">
                                                            ${this.escapeHtml(customerName)}
                                                        </h4>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                                            ${this.escapeHtml(serviceName)}
                                                        </p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1">
                                                            <span class="inline-block w-2 h-2 rounded-full" style="background-color: ${providerColor};"></span>
                                                            ${this.escapeHtml(providerName)}
                                                        </p>
                                                    </div>
                                                    <button type="button" class="p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="View details">
                                                        <span class="material-symbols-outlined text-lg">chevron_right</span>
                                                    </button>
                                                </div>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            ` : `
                                <div class="p-8 text-center">
                                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                                    <p class="text-gray-500 dark:text-gray-400">No appointments scheduled for this day</p>
                                </div>
                            `}
                        </div>
                        <div class="scheduler-modal-footer">
                            <button type="button" class="btn btn-secondary" data-close-modal>
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Insert modal into DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        const modal = document.getElementById('day-appointments-modal');
        
        // Add open animation class after a tick
        requestAnimationFrame(() => {
            modal.classList.add('scheduler-modal-open');
        });
        
        // Close modal handlers
        modal.querySelectorAll('[data-close-modal]').forEach(el => {
            el.addEventListener('click', () => {
                this.closeDayAppointmentsModal();
            });
        });
        
        // Escape key handler
        const escapeHandler = (e) => {
            if (e.key === 'Escape') {
                this.closeDayAppointmentsModal();
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);
        
        // Appointment click handlers in modal
        modal.querySelectorAll('[data-modal-appointment-id]').forEach(el => {
            el.addEventListener('click', () => {
                const aptId = parseInt(el.dataset.modalAppointmentId, 10);
                const appointment = appointments.find(a => a.id === aptId);
                if (appointment) {
                    this.closeDayAppointmentsModal();
                    onAppointmentClick(appointment);
                }
            });
        });
    }
    
    /**
     * Close the day appointments modal
     */
    closeDayAppointmentsModal() {
        const modal = document.getElementById('day-appointments-modal');
        if (modal) {
            modal.classList.remove('scheduler-modal-open');
            setTimeout(() => {
                modal.remove();
            }, 250);
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
