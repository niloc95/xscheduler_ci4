/**
 * Right Panel Module — Provider Cards + Slot Grid
 * 
 * Fixed right panel (38% width) showing:
 * - Provider load cards with availability stats
 * - Available time slots grid (grouped by hour)
 * 
 * Integrated with SchedulerCore state; updates on view/date changes.
 */

import { DateTime } from 'luxon';
import { getProviderColor, getProviderInitials } from './appointment-colors.js';
import { withBaseUrl } from '../../utils/url-helpers.js';
import { escapeHtml } from '../../utils/html.js';
import { fetchSlotsForDate, groupSlotsByHour } from './availability-slots.js';

export class RightPanel {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this._slotCache = new Map(); // Cache: `${providerId}:${serviceId}:${date}:${locationId}` → slots[]
        this._providerServiceCache = new Map();
        this._providerLocationCache = new Map();
        this._currentView = null;
        this._currentDate = null;
        this._lastRenderState = null;

        // Independent panel controls state.
        this._panelDateIso = null;
        this._panelServiceId = null;
        this._panelProviderId = null;
        this._panelLocationId = null;

        this._providerServices = [];
        this._providerLocations = [];
    }

    _formatCurrency(amount) {
        if (this.scheduler?.settingsManager && typeof this.scheduler.settingsManager.formatCurrency === 'function') {
            return this.scheduler.settingsManager.formatCurrency(amount);
        }

        if (window.currencyFormatter && typeof window.currencyFormatter.format === 'function') {
            return window.currencyFormatter.format(amount);
        }

        const numericAmount = parseFloat(amount) || 0;
        return `R${numericAmount.toFixed(2)}`;
    }

    /**
     * Render the right panel based on current scheduler state.
     * 
     * @param {Object} state - { currentView, currentDate, appointments, providers, visibleProviders }
     */
    async render(state) {
        const container = document.getElementById('rp-body');
        const header = document.getElementById('rp-title');
        const subtitle = document.getElementById('rp-subtitle');

        if (!container) {
            console.warn('Right panel container #rp-body not found');
            return;
        }

        this._currentView = state.currentView;
        this._currentDate = state.currentDate;
        this._lastRenderState = state;
        await this._syncPanelState(state);

        // Update header
        if (header) {
            header.textContent = this._getHeaderTitle(state.currentView);
        }
        if (subtitle) {
            subtitle.textContent = this._getHeaderSubtitle(state.currentView, state.currentDate);
        }

        // Render provider cards
        const providerCardsHtml = this._renderProviderCards(state);
        const showAvailabilitySections = state.currentView !== 'week';

        // Render panel controls
        const controlsHtml = this._renderPanelControls(state);

        // Render slot panel
        const slotPanelHtml = await this._renderSlotPanel(state);

        container.innerHTML = `
            <div class="space-y-4">
                ${showAvailabilitySections ? `
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                            Availability Controls
                        </h4>
                        <div id="rp-controls-container">
                            ${controlsHtml}
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                            Available Slots
                        </h4>
                        <div id="slot-panel-container">
                            ${slotPanelHtml}
                        </div>
                    </div>
                ` : ''}

                <!-- Provider Cards Section -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Providers (${state.visibleProviders?.size || 0})
                    </h4>
                    <div class="space-y-2" id="provider-cards-container">
                        ${providerCardsHtml}
                    </div>
                </div>
            </div>
        `;

        // Attach event listeners
        this._attachListeners(container, state);
    }

    /**
     * Generate header title based on view.
     */
    _getHeaderTitle(view) {
        const titles = {
            today: 'Today\'s Overview',
            day: 'Day Overview',
            week: 'Week Overview',
            month: 'Month Overview',
        };
        return titles[view] || 'Providers';
    }

    /**
     * Generate header subtitle based on view and date.
     */
    _getHeaderSubtitle(view, date) {
        if (!date) return 'Select a provider to view availability';
        
        switch (view) {
            case 'today':
                return date.toFormat('EEEE, MMMM d, yyyy');
            case 'day':
                return date.toFormat('EEEE, MMMM d, yyyy');
            case 'week':
                return `Week of ${date.toFormat('MMM d, yyyy')}`;
            case 'month':
                return date.toFormat('MMMM yyyy');
            default:
                return 'Select a provider to view availability';
        }
    }

    /**
     * Render provider cards with load bars and stats.
     * Shows appointment count and free slot estimate.
     */
    _renderProviderCards(state) {
        const { providers, appointments, visibleProviders, currentDate, currentView } = state;

        if (!providers || providers.length === 0) {
            return '<p class="text-sm text-gray-500 dark:text-gray-400">No providers available.</p>';
        }

        // Filter to visible providers only
        const visibleProvidersList = providers.filter(p => 
            visibleProviders && visibleProviders.has(p.id)
        );

        if (visibleProvidersList.length === 0) {
            return '<p class="text-sm text-gray-500 dark:text-gray-400">No providers selected. Use filters to select providers.</p>';
        }

        return visibleProvidersList.map(provider => {
            const providerColor = getProviderColor(provider);
            const providerInitials = getProviderInitials(provider?.name || provider?.username);

            // Calculate provider load (appointments count for date range)
            const dateRange = this._getDateRangeForView(currentView, currentDate);
            const providerAppointments = appointments.filter(apt => 
                apt.providerId === provider.id &&
                apt.startDateTime >= dateRange.start &&
                apt.startDateTime <= dateRange.end
            );

            const appointmentCount = providerAppointments.length;
            
            // Simplified load calculation (Phase 4 will use actual slot counts)
            // Assume 8 working hours * 2 slots per hour = 16 possible slots per day
            const slotsPerDay = 16;
            const daysInView = this._getDaysInView(currentView);
            const totalSlots = slotsPerDay * daysInView;
            const loadPercentage = Math.min(100, (appointmentCount / totalSlots) * 100);

            // Color-code load bar
            let loadBarClass = 'provider-load-bar--low';
            if (loadPercentage >= 75) loadBarClass = 'provider-load-bar--high';
            else if (loadPercentage >= 50) loadBarClass = 'provider-load-bar--medium';

            return `
                <div class="provider-card" data-provider-id="${provider.id}">
                    <div class="provider-card-header">
                        <div class="provider-avatar" data-bg-color="${escapeHtml(providerColor)}">
                            ${providerInitials}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                ${escapeHtml(provider.name || provider.username)}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                ${appointmentCount} appt${appointmentCount !== 1 ? 's' : ''} • ${Math.round(loadPercentage)}% load
                            </div>
                        </div>
                    </div>
                    <div class="provider-load-bar-container">
                        <div class="provider-load-bar ${loadBarClass}" style="width: ${loadPercentage}%;"></div>
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Render slot panel with available time slots.
     * Shows slots for the current date (or first day of current view).
     */
    async _renderSlotPanel(state) {
        const { visibleProviders } = state;
        const timezone = this.scheduler?.options?.timezone || 'UTC';
        const targetDate = DateTime.fromISO(String(this._panelDateIso), { zone: timezone }).startOf('day');
        const selectedService = this._panelServiceId || this._resolveSelectedServiceId();
        const selectedProvider = this._panelProviderId || null;
        const selectedLocation = this._panelLocationId || null;

        if (!selectedService) {
            return `
                <div class="p-4 bg-surface-0 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 text-center">
                    <span class="material-symbols-outlined text-3xl text-gray-400 dark:text-gray-500">filter_list</span>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        Select a service to view available time slots
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Use the panel service control
                    </p>
                </div>
            `;
        }

        const providerIds = selectedProvider
            ? [selectedProvider]
            : Array.from(visibleProviders || []);

        if (!providerIds.length) {
            return `
                <div class="p-4 bg-surface-0 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 text-center">
                    <span class="material-symbols-outlined text-3xl text-gray-400 dark:text-gray-500">person_off</span>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        No providers selected
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Select a provider in the panel or use global filters
                    </p>
                </div>
            `;
        }

        // Fetch slots for all visible providers
        const slotsByProvider = await this._fetchSlotsForProviders(
            providerIds,
            targetDate,
            selectedService,
            selectedLocation
        );

        // Merge and group slots by hour
        const groupedSlots = this._groupSlotsByHour(slotsByProvider);

        if (Object.keys(groupedSlots).length === 0) {
            return `
                <div class="p-4 bg-surface-0 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 text-center">
                    <span class="material-symbols-outlined text-3xl text-gray-400 dark:text-gray-500">event_busy</span>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        No available slots for ${targetDate.toFormat('MMM d, yyyy')}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Try another date or provider
                    </p>
                </div>
            `;
        }

        // Render slot grid grouped by hour
        return this._renderSlotGrid(groupedSlots, targetDate, {
            serviceId: selectedService,
            locationId: selectedLocation,
        });
    }
    
    /**
     * Determine target date for slot display based on view.
     */
    _getSlotTargetDate(view, currentDate) {
        switch (view) {
            case 'today':
                return DateTime.now().setZone(this.scheduler.options.timezone).startOf('day');
            case 'day':
                return currentDate.startOf('day');
            case 'week':
                // Show slots for today if in current week, else first day of week
                const now = DateTime.now().setZone(this.scheduler.options.timezone);
                const weekStart = currentDate.startOf('week');
                const weekEnd = currentDate.endOf('week');
                if (now >= weekStart && now <= weekEnd) {
                    return now.startOf('day');
                }
                return weekStart;
            case 'month':
                // Show slots for today if in current month, else first day of month
                const today = DateTime.now().setZone(this.scheduler.options.timezone);
                if (currentDate.hasSame(today, 'month')) {
                    return today.startOf('day');
                }
                return currentDate.startOf('month');
            default:
                return currentDate.startOf('day');
        }
    }
    
    /**
     * Fetch available slots for multiple providers (with caching).
     */
    async _fetchSlotsForProviders(providerIds, date, serviceId, locationId = null) {
        const dateKey = date.toFormat('yyyy-MM-dd');
        const slots = await fetchSlotsForDate({
            providerIds,
            dateIso: dateKey,
            serviceId,
            locationId,
        });

        return slots.map(({ providerId, slot }) => ({
            providerId,
            provider: this.scheduler.providers.find((p) => Number(p.id) === Number(providerId)) || null,
            slot,
        }));
    }

    /**
     * Resolve selected service ID from scheduler filters with a DOM fallback.
     * This keeps the panel data-driven while tolerating legacy filter wiring.
     */
    _resolveSelectedServiceId() {
        const fromState = Number(this.scheduler?.selectedServiceId || 0);
        if (fromState > 0) {
            return fromState;
        }

        const serviceSelect = document.getElementById('service-filter') || document.querySelector('[name="service_id"]');
        const fromDom = Number(serviceSelect?.value || 0);
        return fromDom > 0 ? fromDom : null;
    }
    
    /**
     * Group slots by hour for display.
     */
    _groupSlotsByHour(slotsByProvider) {
        return groupSlotsByHour(slotsByProvider, this.scheduler.options.timezone);
    }
    
    /**
     * Render slot grid grouped by hour.
     */
    _renderSlotGrid(groupedSlots, targetDate, context = {}) {
        const sortedHours = Object.keys(groupedSlots).sort((a, b) => parseInt(a) - parseInt(b));

        const slotsHtml = sortedHours.map(hourKey => {
            const group = groupedSlots[hourKey];

            // Get unique hour label (use first slot's formatted time)
            const hourLabel = group.slots.length > 0
                ? DateTime.fromISO(group.slots[0].start, { setZone: true }).setZone(this.scheduler.options.timezone).toFormat('h a')
                : `${hourKey}:00`;

            const slotsInHour = group.slots.map(slot => {
                const providerColor = getProviderColor(slot.provider);
                const startTime = DateTime.fromISO(slot.start, { setZone: true }).setZone(this.scheduler.options.timezone);
                const timeLabel = startTime.toFormat('h:mm a');

                return `
                    <button class="slot-button"
                            data-provider-id="${slot.providerId}"
                            data-service-id="${context.serviceId || ''}"
                            data-location-id="${context.locationId || ''}"
                            data-start-time="${slot.start}"
                            data-date="${targetDate.toFormat('yyyy-MM-dd')}"
                            data-border-color="${escapeHtml(providerColor)}"
                            title="${slot.provider?.name || 'Provider'} - ${timeLabel}">
                        <span class="slot-time">${startTime.toFormat('h:mm')}</span>
                        <span class="slot-provider-dot" data-bg-color="${escapeHtml(providerColor)}"></span>
                    </button>
                `;
            }).join('');

            return `
                <div class="slot-hour-group">
                    <div class="slot-hour-label">${hourLabel}</div>
                    <div class="slot-hour-slots">
                        ${slotsInHour}
                    </div>
                </div>
            `;
        }).join('');
        
        return `
            <div class="slot-grid">
                <div class="slot-grid-header">
                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300">
                        ${targetDate.toFormat('EEE, MMM d')}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        ${Object.values(groupedSlots).reduce((sum, g) => sum + g.slots.length, 0)} slots
                    </span>
                </div>
                <div class="slot-grid-body">
                    ${slotsHtml}
                </div>
            </div>
        `;
    }

    _renderPanelControls(state) {
        const visibleProviders = this._getVisibleProviders(state);
        const serviceOptions = this._getPanelServiceOptions();
        const hasProvider = Boolean(this._panelProviderId);

        const providerOptions = visibleProviders.map(provider => {
            const selected = Number(this._panelProviderId || 0) === Number(provider.id) ? 'selected' : '';
            const label = escapeHtml(provider.name || provider.username || `Provider ${provider.id}`);
            return `<option value="${provider.id}" ${selected}>${label}</option>`;
        }).join('');

        const serviceOptionsHtml = serviceOptions.map(service => {
            const selected = Number(this._panelServiceId || 0) === Number(service.id) ? 'selected' : '';
            const label = escapeHtml(service.label || service.name || `Service ${service.id}`);
            return `<option value="${service.id}" ${selected}>${label}</option>`;
        }).join('');

        const locationOptionsHtml = this._providerLocations.map(location => {
            const selected = Number(this._panelLocationId || 0) === Number(location.id) ? 'selected' : '';
            const label = escapeHtml(location.name || `Location ${location.id}`);
            return `<option value="${location.id}" ${selected}>${label}</option>`;
        }).join('');

        return `
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 space-y-3">
                <div class="grid grid-cols-1 gap-3">
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">
                        Date
                        <input
                            type="date"
                            id="rp-date-input"
                            class="mt-1 w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 px-2 py-1.5"
                            value="${escapeHtml(this._panelDateIso || '')}"
                        />
                    </label>

                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">
                        Provider
                        <select id="rp-provider-select" class="mt-1 w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 px-2 py-1.5">
                            <option value="">All visible providers</option>
                            ${providerOptions}
                        </select>
                    </label>

                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">
                        Service
                        <select id="rp-service-select" class="mt-1 w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 px-2 py-1.5">
                            <option value="">Select a service</option>
                            ${serviceOptionsHtml}
                        </select>
                    </label>

                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">
                        Location
                        <select id="rp-location-select" class="mt-1 w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 px-2 py-1.5" ${hasProvider ? '' : 'disabled'}>
                            <option value="">${hasProvider ? 'Any location' : 'Select a provider first'}</option>
                            ${locationOptionsHtml}
                        </select>
                    </label>
                </div>
            </div>
        `;
    }

    /**
     * Attach event listeners to provider cards and slot buttons.
     */
    _attachListeners(container, state) {
        // Provider card clicks (toggle visibility)
        container.querySelectorAll('.provider-card').forEach(card => {
            card.addEventListener('click', () => {
                const providerId = parseInt(card.dataset.providerId, 10);
                this._handleProviderToggle(providerId, state);
            });
        });

        this._attachSlotListeners(container);

        this._attachControlListeners(container, state);
    }

    _attachControlListeners(container, state) {
        if (!container) {
            return;
        }

        const dateInput = container.querySelector('#rp-date-input');
        const providerSelect = container.querySelector('#rp-provider-select');
        const serviceSelect = container.querySelector('#rp-service-select');
        const locationSelect = container.querySelector('#rp-location-select');

        if (dateInput) {
            dateInput.addEventListener('change', async () => {
                this._panelDateIso = dateInput.value || this._panelDateIso;
                await this._refreshSlotsOnly(state);
            });
        }

        if (serviceSelect) {
            serviceSelect.addEventListener('change', async () => {
                this._panelServiceId = this._parseOptionalInt(serviceSelect.value);
                await this._refreshSlotsOnly(state);
            });
        }

        if (locationSelect) {
            locationSelect.addEventListener('change', async () => {
                this._panelLocationId = this._parseOptionalInt(locationSelect.value);
                await this._refreshSlotsOnly(state);
            });
        }

        if (providerSelect) {
            providerSelect.addEventListener('change', async () => {
                this._panelProviderId = this._parseOptionalInt(providerSelect.value);
                this._panelLocationId = null;
                this._providerLocations = [];

                if (this._panelProviderId) {
                    await this._loadProviderLookups(this._panelProviderId);
                } else {
                    this._providerServices = this._getGlobalServiceOptions();
                }

                // Provider changes can impact service/location option sets.
                await this._refreshControlsAndSlots(state);
            });
        }
    }

    _attachSlotListeners(container) {
        container.querySelectorAll('.slot-button').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const providerId = parseInt(button.dataset.providerId, 10);
                const startTime = button.dataset.startTime;
                const date = button.dataset.date;
                const serviceId = this._parseOptionalInt(button.dataset.serviceId) || this._panelServiceId || this._resolveSelectedServiceId();
                const locationId = this._parseOptionalInt(button.dataset.locationId) || this._panelLocationId;
                this._handleSlotClick(providerId, date, startTime, serviceId, locationId);
            });
        });
    }

    async _refreshSlotsOnly(state) {
        const slotContainer = document.getElementById('slot-panel-container');
        if (!slotContainer) {
            return;
        }

        slotContainer.innerHTML = `
            <div class="p-4 bg-surface-0 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 text-center">
                <div class="loading-spinner mx-auto mb-2"></div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Refreshing slots...</p>
            </div>
        `;

        const html = await this._renderSlotPanel(state);
        slotContainer.innerHTML = html;
        this._attachSlotListeners(document.getElementById('rp-body') || slotContainer);
    }

    async refreshSlotsFromExternal(serviceId = null) {
        this._panelServiceId = this._parseOptionalInt(serviceId);
        if (!this._lastRenderState) {
            return;
        }
        await this._refreshSlotsOnly(this._lastRenderState);
    }

    async _refreshControlsAndSlots(state) {
        const controlsContainer = document.getElementById('rp-controls-container');
        if (controlsContainer) {
            controlsContainer.innerHTML = this._renderPanelControls(state);
        }

        const body = document.getElementById('rp-body');
        this._attachControlListeners(body, state);

        await this._refreshSlotsOnly(state);
    }
    
    /**
     * Handle slot button click - navigate to booking form.
     */
    _handleSlotClick(providerId, date, startTime, serviceId, locationId = null) {
        if (!serviceId) {
            console.warn('No service selected for booking');
            return;
        }

        // Parse ISO time to get time component
        const dateTime = DateTime.fromISO(startTime);
        const timeString = dateTime.toFormat('HH:mm');

        const params = new URLSearchParams({
            provider_id: String(providerId),
            service_id: String(serviceId),
            date,
            time: timeString,
        });

        if (locationId) {
            params.set('location_id', String(locationId));
        }

        // Navigate to appointment creation form with pre-filled params
        const url = withBaseUrl(`/appointments/create?${params.toString()}`);
        window.location.href = url;
    }
    
    /**
     * Invalidate slot cache (call after appointment mutations).
     * @param {string|null} cacheKey - Specific key to invalidate, or null to clear all
     */
    invalidateCache(cacheKey = null) {
        if (cacheKey) {
            this._slotCache.delete(cacheKey);
            return;
        }
        this._slotCache.clear();
    }

    /**
     * Handle provider visibility toggle (Phase 4 will rerender both panes).
     */
    _handleProviderToggle(providerId, state) {
        if (typeof this.scheduler?.toggleProvider === 'function') {
            this.scheduler.toggleProvider(providerId);
        }
    }

    /**
     * Calculate date range for current view.
     */
    _getDateRangeForView(view, date) {
        switch (view) {
            case 'today':
            case 'day':
                return {
                    start: date.startOf('day'),
                    end: date.endOf('day'),
                };
            case 'week':
                return {
                    start: date.startOf('week'),
                    end: date.endOf('week'),
                };
            case 'month':
                return {
                    start: date.startOf('month'),
                    end: date.endOf('month'),
                };
            default:
                return {
                    start: date.startOf('day'),
                    end: date.endOf('day'),
                };
        }
    }

    /**
     * Get number of days in current view (for load calculation).
     */
    _getDaysInView(view) {
        switch (view) {
            case 'today':
            case 'day':
                return 1;
            case 'week':
                return 7;
            case 'month':
                return 30; // Approximate
            default:
                return 1;
        }
    }

    async _syncPanelState(state) {
        const fallbackDate = this._getSlotTargetDate(state.currentView, state.currentDate)
            .setZone(this.scheduler?.options?.timezone || 'UTC')
            .toISODate();

        if (!this._panelDateIso) {
            this._panelDateIso = fallbackDate;
        }

        const active = this.scheduler?.activeFilters || {};
        if (!this._panelProviderId && active.providerId) {
            this._panelProviderId = this._parseOptionalInt(active.providerId);
        }

        if (!this._panelServiceId && active.serviceId) {
            this._panelServiceId = this._parseOptionalInt(active.serviceId);
        }

        if (!this._panelLocationId && active.locationId) {
            this._panelLocationId = this._parseOptionalInt(active.locationId);
        }

        if (this._panelProviderId) {
            await this._loadProviderLookups(this._panelProviderId);
        } else {
            this._providerServices = this._getGlobalServiceOptions();
            this._providerLocations = [];
            this._panelLocationId = null;
        }
    }

    _getVisibleProviders(state) {
        const visible = state?.visibleProviders || new Set();
        return (state?.providers || []).filter(provider => visible.has(Number(provider.id)));
    }

    _getGlobalServiceOptions() {
        const serviceSelect = document.getElementById('service-filter') || document.querySelector('[name="service_id"]');
        if (!serviceSelect) {
            return [];
        }

        return Array.from(serviceSelect.options || [])
            .map(option => {
                const id = this._parseOptionalInt(option.value);
                if (!id) {
                    return null;
                }
                return {
                    id,
                    label: option.textContent?.trim() || `Service ${id}`,
                };
            })
            .filter(Boolean);
    }

    _getPanelServiceOptions() {
        if (this._panelProviderId) {
            return this._providerServices;
        }
        return this._providerServices?.length ? this._providerServices : this._getGlobalServiceOptions();
    }

    async _loadProviderLookups(providerId) {
        const id = this._parseOptionalInt(providerId);
        if (!id) {
            this._providerServices = this._getGlobalServiceOptions();
            this._providerLocations = [];
            return;
        }

        const [services, locations] = await Promise.all([
            this._fetchProviderServices(id),
            this._fetchProviderLocations(id),
        ]);

        this._providerServices = services;
        this._providerLocations = locations;

        const serviceStillValid = this._providerServices.some(s => Number(s.id) === Number(this._panelServiceId));
        if (!serviceStillValid) {
            this._panelServiceId = null;
        }

        const locationStillValid = this._providerLocations.some(l => Number(l.id) === Number(this._panelLocationId));
        if (!locationStillValid) {
            this._panelLocationId = null;
        }
    }

    async _fetchProviderServices(providerId) {
        if (this._providerServiceCache.has(providerId)) {
            return this._providerServiceCache.get(providerId);
        }

        try {
            const response = await fetch(withBaseUrl(`/api/v1/providers/${providerId}/services`));
            if (!response.ok) {
                return [];
            }

            const data = await response.json();
            const services = (data?.data || []).map(service => ({
                id: Number(service.id),
                name: service.name,
                label: service.price != null
                    ? `${service.name} - ${this._formatCurrency(service.price)}`
                    : service.name,
            }));

            this._providerServiceCache.set(providerId, services);
            return services;
        } catch (error) {
            console.error('[right-panel] Failed to load provider services:', error);
            return [];
        }
    }

    async _fetchProviderLocations(providerId) {
        if (this._providerLocationCache.has(providerId)) {
            return this._providerLocationCache.get(providerId);
        }

        try {
            const response = await fetch(withBaseUrl(`/api/locations?provider_id=${providerId}&include_days=1`));
            if (!response.ok) {
                return [];
            }

            const data = await response.json();
            const locations = (data?.data || []).map(location => ({
                id: Number(location.id),
                name: location.name,
                address: location.address || '',
            }));

            this._providerLocationCache.set(providerId, locations);
            return locations;
        } catch (error) {
            console.error('[right-panel] Failed to load provider locations:', error);
            return [];
        }
    }

    _parseOptionalInt(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
    }

}
