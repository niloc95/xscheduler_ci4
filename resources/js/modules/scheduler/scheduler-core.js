/**
 * Custom Scheduler - Core Module
 * 
 * Main orchestrator for the custom appointment scheduler.
 * Handles initialization, state management, API communication,
 * and coordination between different view modules.
 */

import { DateTime } from 'luxon';
import { MonthView } from './scheduler-month-view.js';
import { WeekView } from './scheduler-week-view.js';
import { DayView } from './scheduler-day-view.js';
import { DragDropManager } from './scheduler-drag-drop.js';
import { SettingsManager } from './settings-manager.js';
import { AppointmentModal } from './appointment-modal.js';

export class SchedulerCore {
    constructor(containerId, options = {}) {
        this.containerId = containerId; // Store the ID for future reference
        this.container = document.getElementById(containerId);
        if (!this.container) {
            throw new Error(`Container with ID "${containerId}" not found`);
        }
        
        this.currentDate = DateTime.now();
        this.currentView = 'month'; // 'month', 'week', or 'day'
        this.appointments = [];
        this.providers = [];
        this.visibleProviders = new Set();
        
        // Initialize settings manager
        this.settingsManager = new SettingsManager();
        
        // Initialize views
        this.views = {
            month: new MonthView(this),
            week: new WeekView(this),
            day: new DayView(this)
        };
        
        // Initialize drag-drop manager
        this.dragDropManager = new DragDropManager(this);
        
        // Initialize appointment modal
        this.appointmentModal = new AppointmentModal(this, this.settingsManager);
        
        this.options = options;
    }

    async init() {
        try {
            console.log('🚀 Initializing Custom Scheduler...');
            
            // Initialize settings manager first
            console.log('⚙️  Loading settings...');
            await this.settingsManager.init();
            console.log('✅ Settings loaded');
            
            // Update timezone from settings
            this.options.timezone = this.settingsManager.getTimezone();
            this.currentDate = this.currentDate.setZone(this.options.timezone);
            console.log(`🌍 Timezone: ${this.options.timezone}`);
            
            // Load initial data
            console.log('📊 Loading data...');
            await Promise.all([
                this.loadCalendarConfig(),
                this.loadProviders(),
                this.loadAppointments()
            ]);
            console.log('✅ Data loaded');

            // Set all providers visible by default
            this.providers.forEach(p => this.visibleProviders.add(p.id));

            // Render the initial view
            console.log('🎨 Rendering view...');
            this.render();

            console.log('✅ Custom Scheduler initialized successfully');
            console.log('📋 Summary:');
            console.log(`   - Providers: ${this.providers.length}`);
            console.log(`   - Appointments: ${this.appointments.length}`);
            console.log(`   - View: ${this.currentView}`);
            console.log(`   - Timezone: ${this.options.timezone}`);
            
            if (this.appointments.length === 0) {
                console.log('💡 To see appointments, implement these backend endpoints:');
                console.log('   1. GET /api/appointments?start=YYYY-MM-DD&end=YYYY-MM-DD');
                console.log('   2. GET /api/providers?includeColors=true');
                console.log('   3. GET /api/v1/settings/* (optional, has fallbacks)');
            }
        } catch (error) {
            console.error('❌ Failed to initialize scheduler:', error);
            console.error('Error stack:', error.stack);
            this.renderError(`Failed to load scheduler: ${error.message}`);
        }
    }

    async loadCalendarConfig() {
        try {
            const response = await fetch('/api/v1/settings/calendarConfig');
            if (!response.ok) throw new Error('Failed to load calendar config');
            const data = await response.json();
            this.calendarConfig = data.data || data;
            console.log('📅 Calendar config loaded:', this.calendarConfig);
        } catch (error) {
            console.error('Failed to load calendar config:', error);
            // Use defaults if config fails to load
            this.calendarConfig = {
                timeFormat: '12h',
                firstDayOfWeek: 0,
                businessHours: {
                    startTime: '09:00',
                    endTime: '17:00'
                }
            };
        }
    }

    async loadProviders() {
        try {
            const response = await fetch('/api/providers?includeColors=true');
            if (!response.ok) throw new Error('Failed to load providers');
            const data = await response.json();
            this.providers = data.data || data || [];
            console.log('👥 Providers loaded:', this.providers.length);
        } catch (error) {
            console.error('Failed to load providers:', error);
            this.providers = [];
        }
    }

    async loadAppointments(start = null, end = null) {
        try {
            // Calculate date range based on current view if not provided
            if (!start || !end) {
                const range = this.getDateRangeForView();
                start = range.start;
                end = range.end;
            }

            const url = `${this.options.apiBaseUrl}?start=${start}&end=${end}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error('Failed to load appointments');
            const data = await response.json();
            this.appointments = data.data || data || [];
            
            // Parse dates with timezone awareness
            this.appointments = this.appointments.map(apt => ({
                ...apt,
                startDateTime: DateTime.fromISO(apt.start, { zone: this.options.timezone }),
                endDateTime: DateTime.fromISO(apt.end, { zone: this.options.timezone })
            }));

            console.log('📅 Appointments loaded:', this.appointments.length);
            return this.appointments;
        } catch (error) {
            console.error('Failed to load appointments:', error);
            this.appointments = [];
            return [];
        }
    }

    getDateRangeForView() {
        let start, end;

        switch (this.currentView) {
            case 'day':
                start = this.currentDate.startOf('day');
                end = this.currentDate.endOf('day');
                break;
            case 'week':
                start = this.currentDate.startOf('week');
                end = this.currentDate.endOf('week');
                break;
            case 'month':
            default:
                // Get full weeks covering the month
                const monthStart = this.currentDate.startOf('month');
                const monthEnd = this.currentDate.endOf('month');
                start = monthStart.startOf('week');
                end = monthEnd.endOf('week');
                break;
        }

        return {
            start: start.toISODate(),
            end: end.toISODate()
        };
    }

    getFilteredAppointments() {
        return this.appointments.filter(apt => 
            this.visibleProviders.has(apt.providerId)
        );
    }

    toggleProvider(providerId) {
        if (this.visibleProviders.has(providerId)) {
            this.visibleProviders.delete(providerId);
        } else {
            this.visibleProviders.add(providerId);
        }
        this.render();
    }

    async changeView(viewName) {
        if (!['day', 'week', 'month'].includes(viewName)) {
            console.error('Invalid view:', viewName);
            return;
        }

        this.currentView = viewName;
        await this.loadAppointments();
        this.render();
    }

    async navigateToToday() {
        this.currentDate = DateTime.now().setZone(this.options.timezone);
        await this.loadAppointments();
        this.render();
    }

    async navigateNext() {
        switch (this.currentView) {
            case 'day':
                this.currentDate = this.currentDate.plus({ days: 1 });
                break;
            case 'week':
                this.currentDate = this.currentDate.plus({ weeks: 1 });
                break;
            case 'month':
                this.currentDate = this.currentDate.plus({ months: 1 });
                break;
        }
        await this.loadAppointments();
        this.render();
    }

    async navigatePrev() {
        switch (this.currentView) {
            case 'day':
                this.currentDate = this.currentDate.minus({ days: 1 });
                break;
            case 'week':
                this.currentDate = this.currentDate.minus({ weeks: 1 });
                break;
            case 'month':
                this.currentDate = this.currentDate.minus({ months: 1 });
                break;
        }
        await this.loadAppointments();
        this.render();
    }

    render() {
        // Re-find container if it's been lost (e.g., due to DOM manipulation)
        if (!this.container || !document.body.contains(this.container)) {
            this.container = document.getElementById(this.containerId);
            if (!this.container) {
                console.error(`Container #${this.containerId} not found in DOM`);
                return;
            }
        }

        const view = this.views[this.currentView];
        if (view && typeof view.render === 'function') {
            view.render(this.container, {
                currentDate: this.currentDate,
                appointments: this.getFilteredAppointments(),
                providers: this.providers,
                config: this.calendarConfig,
                settings: this.settingsManager, // Pass settings manager
                onAppointmentClick: this.handleAppointmentClick.bind(this)
            });

            // Enable drag-and-drop after rendering (if dragDropManager is available)
            if (this.dragDropManager) {
                this.dragDropManager.enableDragDrop(this.container);
            }
        } else {
            console.error(`View not implemented: ${this.currentView}`);
            this.container.innerHTML = `
                <div class="flex items-center justify-center p-12">
                    <div class="text-center">
                        <span class="material-symbols-outlined text-gray-400 text-6xl mb-4">construction</span>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                            ${this.currentView.charAt(0).toUpperCase() + this.currentView.slice(1)} View Coming Soon
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            This view is currently under development.
                        </p>
                    </div>
                </div>
            `;
        }
    }

    handleAppointmentClick(appointment) {
        if (this.options.onAppointmentClick) {
            this.options.onAppointmentClick(appointment);
        } else {
            console.log('Appointment clicked:', appointment);
        }
    }

    renderError(message) {
        // Re-find container if it's been lost
        if (!this.container || !document.body.contains(this.container)) {
            this.container = document.getElementById(this.containerId);
        }
        if (!this.container) return;
        
        this.container.innerHTML = `
            <div class="flex items-center justify-center p-12">
                <div class="text-center">
                    <span class="material-symbols-outlined text-red-500 text-6xl mb-4">error</span>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Error</h3>
                    <p class="text-gray-600 dark:text-gray-400">${message}</p>
                </div>
            </div>
        `;
    }

    destroy() {
        // Cleanup if needed
        this.container = null;
        this.appointments = [];
        this.providers = [];
    }
    
    /**
     * Open appointment creation modal
     * @param {Object} options - { date, time, providerId }
     */
    openCreateModal(options = {}) {
        this.appointmentModal.open(options);
    }
}
