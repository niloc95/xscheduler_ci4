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
import { getBaseUrl, withBaseUrl } from '../../utils/url-helpers.js';

// Stats System - following Car Analogy architecture
import { getStatsForView } from './stats/stats-engine.js';
import { STATUS_DEFINITIONS, getStatusDef } from './stats/stats-definitions.js';
import { getViewTitle } from './stats/stats-view-configs.js';

export class SchedulerCore {
    constructor(containerId, options = {}) {
        this.containerId = containerId; // Store the ID for future reference
        this.container = document.getElementById(containerId);
        if (!this.container) {
            throw new Error(`Container with ID "${containerId}" not found`);
        }
        
        this.currentDate = DateTime.now();
        this.currentView = 'week'; // 'day', 'week', or 'month' - Week view is default
        this.appointments = [];
        this.providers = [];
        this.visibleProviders = new Set();
        
        // Debouncing for render operations
        this.renderDebounceTimer = null;
        this.renderDebounceDelay = 100; // ms

        this.lastDateChange = { view: null, date: null };
        
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
        
        // Stats bar container reference (initialized after DOM ready)
        this.statsBarContainer = null;
        
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

            if (this.options?.initialView && ['day', 'week', 'month'].includes(this.options.initialView)) {
                this.currentView = this.options.initialView;
            }

            if (this.options?.initialDate) {
                const initialDate = DateTime.fromISO(String(this.options.initialDate), { zone: this.options.timezone });
                if (initialDate.isValid) {
                    this.currentDate = initialDate.setZone(this.options.timezone);
                }
            }
            
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
            
            // Initialize stats bar if container exists
            this.initStatsBar();

            // Render the initial view
            this.debugLog('🎨 Rendering view...');
            this.render();

            // Check for appointment in URL (query param or hash)
            this.checkUrlForAppointment();

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
            const response = await fetch(withBaseUrl('/api/v1/settings/calendar-config'));
            if (!response.ok) throw new Error('Failed to load calendar config');
            const data = await response.json();
            this.calendarConfig = data.data || data;
            this.debugLog('📅 Calendar config loaded:', this.calendarConfig);
        } catch (error) {
            console.error('Failed to load calendar config:', error);
            // Use defaults if config fails to load
            this.calendarConfig = {
                timeFormat: '24h',
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
            
            // Location filter
            if (this.activeFilters?.locationId) {
                const locationId = apt.locationId ? (typeof apt.locationId === 'string' ? parseInt(apt.locationId, 10) : apt.locationId) : null;
                if (locationId !== this.activeFilters.locationId) {
                    this.debugLog(`   Apt ${apt.id}: FILTERED OUT by location (apt.locationId=${locationId}, filter=${this.activeFilters.locationId})`);
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
    async setFilters({ status = '', providerId = '', serviceId = '', locationId = '' }) {
        this.debugLog('📊 setFilters called with:', { status, providerId, serviceId, locationId });
        
        // Store filter values
        this.activeFilters = {
            status: status || null,
            providerId: providerId ? parseInt(providerId, 10) : null,
            serviceId: serviceId ? parseInt(serviceId, 10) : null,
            locationId: locationId ? parseInt(locationId, 10) : null
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
        await this.navigateToDate(DateTime.now().setZone(this.options.timezone));
    }

    async navigateToDate(targetDate) {
        const normalized = DateTime.isDateTime(targetDate)
            ? targetDate
            : DateTime.fromISO(String(targetDate), { zone: this.options.timezone });

        this.currentDate = normalized.setZone(this.options.timezone);
        await this.loadAppointments();
        this.render();
    }

    async navigateNext() {
        let nextDate = this.currentDate;
        switch (this.currentView) {
            case 'day':
                nextDate = this.currentDate.plus({ days: 1 });
                break;
            case 'week':
                nextDate = this.currentDate.plus({ weeks: 1 });
                break;
            case 'month':
                nextDate = this.currentDate.plus({ months: 1 });
                break;
        }
        await this.navigateToDate(nextDate);
    }

    async navigatePrev() {
        let prevDate = this.currentDate;
        switch (this.currentView) {
            case 'day':
                prevDate = this.currentDate.minus({ days: 1 });
                break;
            case 'week':
                prevDate = this.currentDate.minus({ weeks: 1 });
                break;
            case 'month':
                prevDate = this.currentDate.minus({ months: 1 });
                break;
        }
        await this.navigateToDate(prevDate);
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
            
            // Update stats bar with current data (uses Stats Engine)
            this.updateStatsBar();

            // Emit date-change for external listeners (status filters, summaries)
            this.emitDateChange();
            
            // Dispatch view change event for external components
            window.dispatchEvent(new CustomEvent('scheduler:view-rendered', {
                detail: {
                    view: this.currentView,
                    date: this.currentDate.toISODate(),
                    appointmentCount: filteredAppointments.length
                }
            }));
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

    emitDateChange(force = false) {
        const date = this.currentDate?.toISODate ? this.currentDate.toISODate() : null;
        const view = this.currentView;
        if (!date) return;

        if (!force && this.lastDateChange.view === view && this.lastDateChange.date === date) {
            return;
        }

        this.lastDateChange = { view, date };
        window.dispatchEvent(new CustomEvent('scheduler:date-change', {
            detail: { view, date }
        }));
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
     * Format date range string based on current view
     * Shared by updateDateDisplay() and updateStatsBar()
     * @returns {string}
     */
    getDateRangeText() {
        switch (this.currentView) {
            case 'day':
                return this.currentDate.toFormat('EEEE, MMMM d, yyyy');
            case 'week': {
                const weekStart = this.currentDate.startOf('week');
                const weekEnd = weekStart.plus({ days: 6 });
                if (weekStart.month === weekEnd.month) {
                    return `${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('d, yyyy')}`;
                } else if (weekStart.year === weekEnd.year) {
                    return `${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('MMM d, yyyy')}`;
                }
                return `${weekStart.toFormat('MMM d, yyyy')} - ${weekEnd.toFormat('MMM d, yyyy')}`;
            }
            case 'month':
            default:
                return this.currentDate.toFormat('MMMM yyyy');
        }
    }

    /**
     * Update the date display in the toolbar based on current view
     */
    updateDateDisplay() {
        const displayElement = document.getElementById('scheduler-date-display');
        if (!displayElement) return;
        displayElement.textContent = this.getDateRangeText();
    }
    
    /**
     * Initialize the Stats Bar component
     * Uses simple inline counting (same approach as Day Summary which works 100%)
     */
    initStatsBar() {
        this.statsBarContainer = document.getElementById('scheduler-stats-bar');
        if (!this.statsBarContainer) {
            this.debugLog('📊 Stats bar container not found, skipping stats bar init');
            return;
        }
        
        // Listen for filter clicks from stats bar pills
        this.statsBarContainer.addEventListener('click', (e) => {
            const pill = e.target.closest('[data-filter-status]');
            if (pill) {
                const status = pill.dataset.filterStatus;
                this.debugLog('📊 Stats bar filter clicked:', status);
                
                // Toggle filter - if already active, clear it
                const currentStatus = this.activeFilters?.status;
                this.setStatusFilter(status === currentStatus ? null : status);
            }
        });
        
        this.debugLog('📊 Stats bar initialized');
    }
    
    /**
     * Update stats bar with current data
     * Uses Stats Engine for consistent calculation (Car Analogy: Engine powers all views)
     */
    updateStatsBar() {
        if (!this.statsBarContainer) return;
        
        // Use Stats Engine for calculation (single source of truth)
        const stats = getStatsForView(this.appointments, this.currentView, this.currentDate);
        const activeFilter = this.activeFilters?.status || null;
        
        // Get view-appropriate title from config
        const title = getViewTitle(this.currentView, this.currentDate);
        
        // Get date range display (shared with updateDateDisplay)
        const dateRange = this.getDateRangeText();
        
        // Render the stats bar using definitions from Stats Definitions
        const statusPills = Object.keys(STATUS_DEFINITIONS).map(statusKey => {
            const def = getStatusDef(statusKey);
            const count = stats.byStatus[statusKey] || 0;
            return this.renderStatusPill(statusKey, def.label, count, activeFilter, def);
        }).join('');
        
        // Compact inline rendering — pills only (no card wrapper)
        this.statsBarContainer.innerHTML = `
            ${statusPills}
            ${activeFilter ? `
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-xs text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-500">
                    Showing: <span class="font-medium capitalize">${activeFilter.replace('-', ' ')}</span>
                    <button type="button" data-filter-status="${activeFilter}" class="ml-0.5 font-bold hover:text-blue-900 dark:hover:text-blue-100" title="Clear filter">&times;</button>
                </span>
            ` : ''}
        `;
        
        this.debugLog('📊 Stats bar updated:', stats.byStatus);
    }
    
    /**
     * Render a status pill for the stats bar
     * Uses STATUS_DEFINITIONS for consistent colors (Car Analogy: Sensors)
     * 
     * @param {string} status - Status key
     * @param {string} label - Display label
     * @param {number} count - Count to display
     * @param {string|null} activeFilter - Currently active filter
     * @param {Object} def - Status definition from STATUS_DEFINITIONS
     */
    renderStatusPill(status, label, count, activeFilter, def) {
        const isActive = activeFilter === status;
        const ringClass = isActive ? 'ring-2 ring-blue-500' : '';
        
        // Use colors from status definition
        const colors = def.colors;
        const bgClass = `${colors.light.bg} ${colors.dark.bg}`;
        const borderClass = `${colors.light.border} ${colors.dark.border}`;
        const textClass = `${colors.light.text} ${colors.dark.text}`;
        const dotClass = `${colors.light.dot} ${colors.dark.dot}`;
        
        return `
            <button type="button" 
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border cursor-pointer transition-all
                           ${bgClass} ${borderClass} ${textClass}
                           hover:ring-2 hover:ring-offset-1 ${ringClass}"
                    data-filter-status="${status}"
                    title="Filter by ${label}">
                <span class="w-1.5 h-1.5 rounded-full ${dotClass}"></span>
                <span class="text-xs font-medium">${label}</span>
                <span class="text-xs font-bold">${count}</span>
            </button>
        `;
    }
    
    /**
     * Set status filter and re-render
     * @param {string|null} status - Status to filter by, or null to clear
     */
    setStatusFilter(status) {
        this.debugLog('📊 setStatusFilter called:', status);
        
        // Update active filters
        this.activeFilters = this.activeFilters || {};
        this.activeFilters.status = status || null;
        
        // Store on window for external access (status-filters.js compatibility)
        this.statusFilter = status || null;
        
        // Re-render with new filter
        this.render();
        
        // Dispatch event for other components
        window.dispatchEvent(new CustomEvent('scheduler:status-filter-changed', {
            detail: { status: status || null }
        }));
    }
    
    /**
     * Check URL for appointment ID (query param or hash) and open modal if found
     * Supports formats: ?open={id}, ?open={hash}, #appointment-{id}, #appointment-{hash}
     */
    checkUrlForAppointment() {
        // First check query parameter
        const urlParams = new URLSearchParams(window.location.search);
        const openParam = urlParams.get('open');
        
        if (openParam) {
            this.debugLog('🔗 Found "open" parameter:', openParam);
            this.openAppointmentById(openParam, true);
            return;
        }
        
        // Fall back to hash check
        const hash = window.location.hash;
        this.debugLog('🔍 Checking URL hash:', hash);
        
        if (!hash || !hash.startsWith('#appointment-')) {
            this.debugLog('⏭️  No appointment in URL');
            return;
        }

        // Extract appointment identifier from hash
        const appointmentIdentifier = hash.substring('#appointment-'.length);
        this.debugLog('🔗 Found appointment in hash:', appointmentIdentifier);
        this.openAppointmentById(appointmentIdentifier, false);
    }
    
    /**
     * Open an appointment by ID or hash
     * @param {string} identifier - Appointment ID or hash
     * @param {boolean} clearQueryParam - Whether to clear query param from URL
     */
    openAppointmentById(identifier, clearQueryParam = false) {
        this.debugLog('🔍 Looking for appointment:', identifier);
        this.debugLog('📋 Available appointments:', this.appointments.length);

        // Try to find the appointment by ID or hash
        const appointment = this.appointments.find(apt => {
            const matchById = apt.id && apt.id.toString() === identifier;
            const matchByHash = apt.hash && apt.hash === identifier;
            if (matchById || matchByHash) {
                this.debugLog('✅ Found matching appointment:', apt);
            }
            return matchById || matchByHash;
        });

        if (appointment) {
            this.debugLog('✅ Opening appointment from URL:', appointment);
            
            if (!this.appointmentDetailsModal) {
                console.error('❌ Appointment details modal not initialized!');
                return;
            }
            
            // Delay to ensure modal is fully initialized
            setTimeout(() => {
                try {
                    this.appointmentDetailsModal.open(appointment);
                    
                    // Clean up URL
                    if (clearQueryParam) {
                        // Remove 'open' query parameter
                        const url = new URL(window.location);
                        url.searchParams.delete('open');
                        window.history.replaceState(null, null, url.pathname + (url.search || ''));
                    } else {
                        // Clear hash
                        window.history.replaceState(null, null, window.location.pathname + window.location.search);
                    }
                    
                    this.debugLog('✅ Modal opened and URL cleaned');
                } catch (error) {
                    console.error('❌ Error opening modal:', error);
                }
            }, 300);
        } else {
            console.warn('⚠️  Appointment not found for identifier:', identifier);
            this.debugLog('Available IDs:', this.appointments.map(a => a.id));
            this.debugLog('Available hashes:', this.appointments.map(a => a.hash));
        }
    }
}
