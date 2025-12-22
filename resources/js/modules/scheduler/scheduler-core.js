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
import { AppointmentDetailsModal } from './appointment-details-modal.js';

function getBaseUrl() {
    return String(window.__BASE_URL__ || '').replace(/\/+$/, '');
}

function withBaseUrl(urlPath) {
    const baseUrl = getBaseUrl();
    if (!baseUrl) return urlPath;
    const normalizedPath = urlPath.startsWith('/') ? urlPath : `/${urlPath}`;
    return `${baseUrl}${normalizedPath}`;
}

export class SchedulerCore {
    constructor(containerId, options = {}) {
        this.containerId = containerId; // Store the ID for future reference
        this.container = document.getElementById(containerId);
        if (!this.container) {
            throw new Error(`Container with ID "${containerId}" not found`);
        }
        
        this.currentDate = DateTime.now();
        this.currentView = 'day'; // 'day', 'week', or 'month' - Day view is default
        this.appointments = [];
        this.providers = [];
        this.visibleProviders = new Set();
        
        // Debouncing for render operations
        this.renderDebounceTimer = null;
        this.renderDebounceDelay = 100; // ms
        
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
        
        // Initialize appointment details modal (for viewing/editing)
        this.appointmentDetailsModal = new AppointmentDetailsModal(this);
        
        this.options = options;
    }

    isDebugEnabled() {
        return !!(this.options?.debug || window.appConfig?.debug);
    }

    debugLog(...args) {
        if (this.isDebugEnabled()) {
            console.log(...args);
        }
    }

    async init() {
        try {
            this.debugLog('🚀 Initializing Custom Scheduler...');
            
            // Initialize settings manager first
            this.debugLog('⚙️  Loading settings...');
            await this.settingsManager.init();
            this.debugLog('✅ Settings loaded');
            
            // Update timezone from settings
            this.options.timezone = this.settingsManager.getTimezone();
            this.currentDate = this.currentDate.setZone(this.options.timezone);
            this.debugLog(`🌍 Timezone: ${this.options.timezone}`);
            
            // Load initial data
            this.debugLog('📊 Loading data...');
            await Promise.all([
                this.loadCalendarConfig(),
                this.loadProviders(),
                this.loadAppointments()
            ]);
            this.debugLog('✅ Data loaded');

            // Set all providers visible by default
            // Convert provider IDs to numbers for consistent type matching
            this.providers.forEach(p => {
                const providerId = typeof p.id === 'string' ? parseInt(p.id, 10) : p.id;
                this.visibleProviders.add(providerId);
            });

            this.debugLog('✅ Visible providers initialized:', Array.from(this.visibleProviders));

            // Set initial visibility of daily appointments section
            this.toggleDailyAppointmentsSection();

            // Render the initial view
            this.debugLog('🎨 Rendering view...');
            this.render();

            this.debugLog('✅ Custom Scheduler initialized successfully');
            this.debugLog('📋 Summary:');
            this.debugLog(`   - Providers: ${this.providers.length}`);
            this.debugLog(`   - Appointments: ${this.appointments.length}`);
            this.debugLog(`   - View: ${this.currentView}`);
            this.debugLog(`   - Timezone: ${this.options.timezone}`);
            
            if (this.appointments.length === 0) {
                this.debugLog('💡 To see appointments, implement these backend endpoints:');
                this.debugLog('   1. GET /api/appointments?start=YYYY-MM-DD&end=YYYY-MM-DD');
                this.debugLog('   2. GET /api/providers?includeColors=true');
                this.debugLog('   3. GET /api/v1/settings/* (optional, has fallbacks)');
            }
        } catch (error) {
            console.error('❌ Failed to initialize scheduler:', error);
            console.error('Error stack:', error.stack);
            this.renderError(`Failed to load scheduler: ${error.message}`);
        }
    }

    async loadCalendarConfig() {
        try {
            const response = await fetch(withBaseUrl('/api/v1/settings/calendarConfig'));
            if (!response.ok) throw new Error('Failed to load calendar config');
            const data = await response.json();
            this.calendarConfig = data.data || data;
            this.debugLog('📅 Calendar config loaded:', this.calendarConfig);
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
            const response = await fetch(withBaseUrl('/api/providers?includeColors=true'));
            if (!response.ok) throw new Error('Failed to load providers');
            const data = await response.json();
            this.providers = data.data || data || [];
            this.debugLog('👥 Providers loaded:', this.providers.length);
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

            // Request a large batch of appointments for calendar views (up to 1000)
            const url = `${this.options.apiBaseUrl}?start=${start}&end=${end}&length=1000`;
            this.debugLog('🔄 Loading appointments from:', url);
            const response = await fetch(url);
            if (!response.ok) throw new Error('Failed to load appointments');
            const data = await response.json();
            this.debugLog('📥 Raw API response:', data);
            this.appointments = data.data || data || [];
            this.debugLog('📦 Extracted appointments array:', this.appointments);
            
            // Parse dates with timezone awareness and ensure IDs are numbers
            this.appointments = this.appointments.map(apt => ({
                ...apt,
                id: parseInt(apt.id, 10), // Ensure ID is a number
                providerId: parseInt(apt.providerId, 10), // Ensure provider ID is a number
                serviceId: parseInt(apt.serviceId, 10), // Ensure service ID is a number
                customerId: parseInt(apt.customerId, 10), // Ensure customer ID is a number
                startDateTime: DateTime.fromISO(apt.start, { zone: this.options.timezone }),
                endDateTime: DateTime.fromISO(apt.end, { zone: this.options.timezone })
            }));

            this.debugLog('📅 Appointments loaded:', this.appointments.length);
            this.debugLog('📋 Appointment details:', this.appointments);
            return this.appointments;
        } catch (error) {
            console.error('❌ Failed to load appointments:', error);
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
        this.debugLog('📊 getFilteredAppointments called with activeFilters:', this.activeFilters);
        this.debugLog('📊 visibleProviders:', Array.from(this.visibleProviders));
        
        // Convert appointment providerId to number for comparison
        const filtered = this.appointments.filter(apt => {
            const providerId = typeof apt.providerId === 'string' ? parseInt(apt.providerId, 10) : apt.providerId;
            const serviceId = typeof apt.serviceId === 'string' ? parseInt(apt.serviceId, 10) : apt.serviceId;
            
            // Provider filter (via visibleProviders set)
            if (!this.visibleProviders.has(providerId)) {
                this.debugLog(`   Apt ${apt.id}: FILTERED OUT by provider (apt.providerId=${providerId}, not in visibleProviders)`);
                return false;
            }
            
            // Status filter
            if (this.activeFilters?.status && apt.status !== this.activeFilters.status) {
                this.debugLog(`   Apt ${apt.id}: FILTERED OUT by status (apt.status=${apt.status}, filter=${this.activeFilters.status})`);
                return false;
            }
            
            // Service filter
            if (this.activeFilters?.serviceId) {
                if (serviceId !== this.activeFilters.serviceId) {
                    this.debugLog(`   Apt ${apt.id}: FILTERED OUT by service (apt.serviceId=${serviceId}, filter=${this.activeFilters.serviceId})`);
                    return false;
                }
            }
            
            this.debugLog(`   Apt ${apt.id}: INCLUDED (provider=${providerId}, status=${apt.status}, service=${serviceId})`);
            return true;
        });
        
        this.debugLog(`📊 Filter result: ${filtered.length} of ${this.appointments.length} appointments visible`);
        return filtered;
    }

    toggleProvider(providerId) {
        if (this.visibleProviders.has(providerId)) {
            this.visibleProviders.delete(providerId);
        } else {
            this.visibleProviders.add(providerId);
        }
        this.render();
    }
    
    /**
     * Set filters for status, provider, and service
     * This is the unified filter mechanism used by the Advanced Filter Panel
     */
    async setFilters({ status = '', providerId = '', serviceId = '' }) {
        this.debugLog('📊 setFilters called with:', { status, providerId, serviceId });
        
        // Store filter values
        this.activeFilters = {
            status: status || null,
            providerId: providerId ? parseInt(providerId, 10) : null,
            serviceId: serviceId ? parseInt(serviceId, 10) : null
        };
        
        this.debugLog('📊 activeFilters set to:', this.activeFilters);
        
        // Update visible providers based on filter
        if (providerId) {
            // If a specific provider is selected, only show that provider
            this.visibleProviders.clear();
            this.visibleProviders.add(parseInt(providerId, 10));
            this.debugLog('📊 visibleProviders set to single provider:', parseInt(providerId, 10));
        } else {
            // If no provider filter, show all providers
            this.visibleProviders.clear();
            this.providers.forEach(p => {
                const id = typeof p.id === 'string' ? parseInt(p.id, 10) : p.id;
                this.visibleProviders.add(id);
            });
            this.debugLog('📊 visibleProviders set to all providers:', Array.from(this.visibleProviders));
        }
        
        // Re-render with new filters
        this.render();
        
        return true;
    }
    
    /**
     * Get filtered appointments based on all active filters (status, provider, service)
     */

    async changeView(viewName) {
        if (!['day', 'week', 'month'].includes(viewName)) {
            console.error('Invalid view:', viewName);
            return;
        }

        this.currentView = viewName;
        
        // Toggle visibility of daily provider appointments section based on view
        this.toggleDailyAppointmentsSection();
        
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
        // Clear any pending render
        if (this.renderDebounceTimer) {
            clearTimeout(this.renderDebounceTimer);
        }
        
        // Debounce render to avoid duplicate calls
        this.renderDebounceTimer = setTimeout(() => {
            this._performRender();
        }, this.renderDebounceDelay);
    }
    
    _performRender() {
        // Re-find container if it's been lost (e.g., due to DOM manipulation)
        if (!this.container || !document.body.contains(this.container)) {
            this.container = document.getElementById(this.containerId);
            if (!this.container) {
                console.error(`Container #${this.containerId} not found in DOM`);
                return;
            }
        }

        const filteredAppointments = this.getFilteredAppointments();
        this.debugLog('🎨 Rendering view:', this.currentView);
        this.debugLog('🔍 Filtered appointments for display:', filteredAppointments.length);
        this.debugLog('👥 Visible providers:', Array.from(this.visibleProviders));
        this.debugLog('📋 All appointments:', this.appointments.length);

        // Update date display in toolbar
        this.updateDateDisplay();

        const view = this.views[this.currentView];
        if (view && typeof view.render === 'function') {
            view.render(this.container, {
                currentDate: this.currentDate,
                appointments: filteredAppointments,
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
        this.debugLog('[SchedulerCore] handleAppointmentClick called with:', appointment);
        this.debugLog('[SchedulerCore] appointmentDetailsModal exists:', !!this.appointmentDetailsModal);
        
        if (this.options.onAppointmentClick) {
            this.debugLog('[SchedulerCore] Using custom onAppointmentClick');
            this.options.onAppointmentClick(appointment);
        } else {
            this.debugLog('[SchedulerCore] Opening modal with appointmentDetailsModal.open()');
            // Open appointment details modal
            this.appointmentDetailsModal.open(appointment);
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
        // Clear any pending renders
        if (this.renderDebounceTimer) {
            clearTimeout(this.renderDebounceTimer);
            this.renderDebounceTimer = null;
        }
        
        // Cleanup references
        this.container = null;
        this.appointments = [];
        this.providers = [];
        this.visibleProviders.clear();
    }
    
    /**
     * Toggle visibility of daily provider appointments section
     * Hide when in day view (redundant), show for month/week views
     */
    toggleDailyAppointmentsSection() {
        const dailySection = document.getElementById('daily-provider-appointments');
        if (!dailySection) return;
        
        if (this.currentView === 'day') {
            // Hide daily section when in day view (it's redundant)
            dailySection.style.display = 'none';
        } else {
            // Show daily section for month/week views
            dailySection.style.display = 'block';
        }
    }
    
    /**
     * Update the date display in the toolbar based on current view
     */
    updateDateDisplay() {
        const displayElement = document.getElementById('scheduler-date-display');
        if (!displayElement) return;
        
        let displayText = '';
        
        switch (this.currentView) {
            case 'day':
                // Single day: "Monday, November 3, 2025"
                displayText = this.currentDate.toFormat('EEEE, MMMM d, yyyy');
                break;
                
            case 'week':
                // Week range: "Nov 3 - Nov 9, 2025"
                const weekStart = this.currentDate.startOf('week');
                const weekEnd = weekStart.plus({ days: 6 });
                
                if (weekStart.month === weekEnd.month) {
                    // Same month: "Nov 3 - 9, 2025"
                    displayText = `${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('d, yyyy')}`;
                } else if (weekStart.year === weekEnd.year) {
                    // Different months, same year: "Oct 30 - Nov 5, 2025"
                    displayText = `${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('MMM d, yyyy')}`;
                } else {
                    // Different years: "Dec 30, 2024 - Jan 5, 2025"
                    displayText = `${weekStart.toFormat('MMM d, yyyy')} - ${weekEnd.toFormat('MMM d, yyyy')}`;
                }
                break;
                
            case 'month':
            default:
                // Month view: "November 2025"
                displayText = this.currentDate.toFormat('MMMM yyyy');
                break;
        }
        
        displayElement.textContent = displayText;
    }
    
}
