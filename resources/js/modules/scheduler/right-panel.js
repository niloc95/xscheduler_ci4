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

export class RightPanel {
    constructor(scheduler) {
        this.scheduler = scheduler;
        this._slotCache = new Map(); // Cache: `${providerId}:${serviceId}:${date}` → slots[]
        this._currentView = null;
        this._currentDate = null;
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

        // Update header
        if (header) {
            header.textContent = this._getHeaderTitle(state.currentView);
        }
        if (subtitle) {
            subtitle.textContent = this._getHeaderSubtitle(state.currentView, state.currentDate);
        }

        // Render provider cards
        const providerCardsHtml = this._renderProviderCards(state);
        
        // Render slot panel
        const slotPanelHtml = await this._renderSlotPanel(state);

        container.innerHTML = `
            <div class="space-y-4">
                <!-- Provider Cards Section -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Providers (${state.visibleProviders?.size || 0})
                    </h4>
                    <div class="space-y-2" id="provider-cards-container">
                        ${providerCardsHtml}
                    </div>
                </div>

                <!-- Slot Panel Section -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                        Available Slots
                    </h4>
                    <div id="slot-panel-container">
                        ${slotPanelHtml}
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
                        <div class="provider-avatar" style="background-color: ${providerColor};">
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
        const { currentDate, currentView, visibleProviders } = state;
        
        // Determine target date for slot display
        const targetDate = this._getSlotTargetDate(currentView, currentDate);
        
        // Resolve selected service from active filters first, then UI control fallback.
        const selectedService = this._resolveSelectedServiceId();
        
        if (!selectedService) {
            return `
                <div class="p-4 bg-surface-0 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 text-center">
                    <span class="material-symbols-outlined text-3xl text-gray-400 dark:text-gray-500">filter_list</span>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        Select a service to view available time slots
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Use the service filter above
                    </p>
                </div>
            `;
        }
        
        if (!visibleProviders || visibleProviders.size === 0) {
            return `
                <div class="p-4 bg-surface-0 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 text-center">
                    <span class="material-symbols-outlined text-3xl text-gray-400 dark:text-gray-500">person_off</span>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        No providers selected
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Use filters to select providers
                    </p>
                </div>
            `;
        }
        
        // Fetch slots for all visible providers
        const slotsByProvider = await this._fetchSlotsForProviders(
            Array.from(visibleProviders),
            targetDate,
            selectedService
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
        return this._renderSlotGrid(groupedSlots, slotsByProvider, targetDate);
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
    async _fetchSlotsForProviders(providerIds, date, serviceId) {
        const dateKey = date.toFormat('yyyy-MM-dd');
        const slotsByProvider = {};
        
        const fetchPromises = providerIds.map(async (providerId) => {
            const cacheKey = `${providerId}:${serviceId}:${dateKey}`;
            
            // Check cache first
            if (this._slotCache.has(cacheKey)) {
                slotsByProvider[providerId] = this._slotCache.get(cacheKey);
                return;
            }
            
            // Fetch from API
            try {
                const provider = this.scheduler.providers.find(p => Number(p.id) === Number(providerId));
                const url = withBaseUrl(`/api/availability/slots?provider_id=${providerId}&date=${dateKey}&service_id=${serviceId}`);
                
                const response = await fetch(url);
                if (!response.ok) {
                    console.warn(`Failed to fetch slots for provider ${providerId}:`, response.status);
                    slotsByProvider[providerId] = { provider, slots: [] };
                    return;
                }
                
                const data = await response.json();
                const slots = data.data?.slots || [];
                
                // Store in cache
                this._slotCache.set(cacheKey, { provider, slots });
                slotsByProvider[providerId] = { provider, slots };
                
            } catch (error) {
                console.error(`Error fetching slots for provider ${providerId}:`, error);
                slotsByProvider[providerId] = { provider: null, slots: [] };
            }
        });
        
        await Promise.all(fetchPromises);
        return slotsByProvider;
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
        const grouped = {};
        
        Object.entries(slotsByProvider).forEach(([providerId, { provider, slots }]) => {
            slots.forEach(slot => {
                // Parse start time to get hour
                const startTime = DateTime.fromISO(slot.startTime, { setZone: true }).setZone(this.scheduler.options.timezone);
                const hourKey = startTime.hour; // 0-23
                const hourLabel = startTime.toFormat('h:mm a');
                
                if (!grouped[hourKey]) {
                    grouped[hourKey] = {
                        hour: hourKey,
                        label: hourLabel,
                        slots: []
                    };
                }
                
                grouped[hourKey].slots.push({
                    ...slot,
                    providerId: parseInt(providerId, 10),
                    provider
                });
            });
        });
        
        return grouped;
    }
    
    /**
     * Render slot grid grouped by hour.
     */
    _renderSlotGrid(groupedSlots, slotsByProvider, targetDate) {
        const sortedHours = Object.keys(groupedSlots).sort((a, b) => parseInt(a) - parseInt(b));
        
        const slotsHtml = sortedHours.map(hourKey => {
            const group = groupedSlots[hourKey];
            
            // Get unique hour label (use first slot's formatted time)
            const hourLabel = group.slots.length > 0 
                ? DateTime.fromISO(group.slots[0].startTime, { setZone: true }).setZone(this.scheduler.options.timezone).toFormat('h a')
                : `${hourKey}:00`;
            
            const slotsInHour = group.slots.map(slot => {
                const providerColor = getProviderColor(slot.provider);
                const startTime = DateTime.fromISO(slot.startTime, { setZone: true }).setZone(this.scheduler.options.timezone);
                const timeLabel = startTime.toFormat('h:mm a');
                
                return `
                    <button class="slot-button" 
                            data-provider-id="${slot.providerId}"
                            data-start-time="${slot.startTime}"
                            data-date="${targetDate.toFormat('yyyy-MM-dd')}"
                            style="border-color: ${providerColor};"
                            title="${slot.provider?.name || 'Provider'} - ${timeLabel}">
                        <span class="slot-time">${startTime.toFormat('h:mm')}</span>
                        <span class="slot-provider-dot" style="background-color: ${providerColor};"></span>
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
        
        // Slot button clicks (navigate to booking)
        container.querySelectorAll('.slot-button').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const providerId = parseInt(button.dataset.providerId, 10);
                const startTime = button.dataset.startTime;
                const date = button.dataset.date;
                this._handleSlotClick(providerId, date, startTime);
            });
        });
    }
    
    /**
     * Handle slot button click - navigate to booking form.
     */
    _handleSlotClick(providerId, date, startTime) {
        const serviceId = this.scheduler.selectedServiceId;
        
        if (!serviceId) {
            console.warn('No service selected for booking');
            return;
        }
        
        // Parse ISO time to get time component
        const dateTime = DateTime.fromISO(startTime);
        const timeString = dateTime.toFormat('HH:mm');
        
        // Navigate to appointment creation form with pre-filled params
        const url = withBaseUrl(`/appointments/create?provider=${providerId}&date=${date}&time=${timeString}&service=${serviceId}`);
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
        console.log('Provider toggle clicked:', providerId);
        // Phase 4: Toggle visibility in scheduler.visibleProviders and re-render
        // For now, just log the action
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

}
