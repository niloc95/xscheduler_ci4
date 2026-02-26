/**
 * Shared Slot Engine — Available Slots UI for all scheduler views
 * 
 * Extracts the time-slot availability rendering from WeekView into a
 * reusable module consumed by Day, Week, and Month views.
 * 
 * Exports:
 *   - generateSlots(opts)        → slot array with provider availability
 *   - renderSlotPanel(opts)      → full HTML panel (header, pills, slots, legend)
 *   - renderSlotList(opts)       → just the slot items HTML
 *   - renderProviderFilterPills  → provider filter pill row
 *   - renderSlotLegend()         → compact status legend
 *   - computeDayAvailability()   → lightweight per-day summary for month grid
 *   - escapeHtml(text)           → XSS-safe text helper
 */

import { DateTime } from 'luxon';
import { getProviderColor, getProviderInitials } from './appointment-colors.js';
import { generateSlotsWithAvailability } from '../../utils/scheduling-utils.js';
import { withBaseUrl } from '../../utils/url-helpers.js';

// ─── Helpers ──────────────────────────────────────────────────────────

/**
 * Escape HTML to prevent XSS in dynamic content
 */
export function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Extract a short display name: strip titles, show surname
 */
function shortName(provider) {
    const initials = getProviderInitials(provider?.name);
    return (provider?.name || '')
        .replace(/^(dr|mr|mrs|ms|prof|rev)\.?\s*/i, '')
        .replace(/[.,]\s*(md|phd|dds|do|rn|np|pa|dvm|jr|sr|ii|iii|iv)$/i, '')
        .trim()
        .split(/\s+/)
        .pop() || initials;
}

// ─── Slot Generation ──────────────────────────────────────────────────

/**
 * Generate availability slots for a date.
 * Wraps scheduling-utils' generateSlotsWithAvailability with sensible
 * defaults and the blocked-period / weekend guard used by every view.
 *
 * @deprecated Phase 6 — Client-side slot generation is superseded by the
 *   server-side CalendarController endpoints (/api/calendar/day|week|month).
 *   When SchedulerCore runs in mode='server', this function is never called —
 *   the view receives pre-computed slots from calendarModel.days[].dayGrid.slots.
 *   This function is retained for backward-compat with mode='client' and will
 *   be removed in Phase 6 once all views consume the server-provided model.
 *
 * @param {Object} opts
 * @param {DateTime}      opts.date
 * @param {Object}        opts.businessHours  { startTime, endTime }
 * @param {number}        opts.slotDuration   minutes (default 30)
 * @param {Array}         opts.appointments
 * @param {Array}         opts.providers      visible providers to check
 * @param {Array}         opts.blockedPeriods
 * @param {Object|null}   opts.settings       settings manager (getProviderSchedule, etc.)
 * @param {boolean}       opts.filterPast     filter slots before now on today (default true)
 * @returns {Array}       slot objects with availableProviders / bookedProviders
 */
export function generateSlots(opts) {
    const {
        date,
        businessHours = { startTime: '08:00', endTime: '17:00' },
        slotDuration = 30,
        appointments = [],
        providers = [],
        blockedPeriods = [],
        settings = null,
        filterPast = true,
    } = opts;

    // Blocked day → no slots
    if (isDateBlocked(date, blockedPeriods)) return [];

    // Weekend → no slots (Luxon: 6=Sat, 7=Sun)
    if (date.weekday === 6 || date.weekday === 7) return [];

    if (!providers || providers.length === 0) return [];

    // Build provider schedule map
    const providerSchedules = new Map();
    if (settings?.getProviderSchedule) {
        providers.forEach(p => {
            const schedule = settings.getProviderSchedule(p.id);
            if (schedule) providerSchedules.set(String(p.id), schedule);
        });
    }

    const now = DateTime.now();
    const minDateTime = (filterPast && date.hasSame(now, 'day')) ? now.startOf('minute') : null;

    return generateSlotsWithAvailability({
        date,
        businessHours,
        slotDuration,
        appointments,
        providers,
        providerSchedules,
        minDateTime,
        filterEmptyProviders: true,
    });
}

// ─── Lightweight Day Availability (for Month grid indicators) ─────────

/**
 * Compute a lightweight availability summary for a day without
 * building full slot HTML — used for Month grid indicator bars.
 *
 * @returns {{ hasAppointments:boolean, hasOpenSlots:boolean, isFullyBooked:boolean, appointmentCount:number }}
 */
export function computeDayAvailability(opts) {
    const {
        date,
        businessHours = { startTime: '08:00', endTime: '17:00' },
        slotDuration = 30,
        appointments = [],
        providers = [],
        blockedPeriods = [],
        settings = null,
    } = opts;

    const dayAppts = appointments.filter(a => a.startDateTime.hasSame(date, 'day'));
    const hasAppointments = dayAppts.length > 0;

    // Non-working day or blocked → no slots
    if (isDateBlocked(date, blockedPeriods) || date.weekday === 6 || date.weekday === 7) {
        return { hasAppointments, hasOpenSlots: false, isFullyBooked: false, appointmentCount: dayAppts.length };
    }

    if (!providers || providers.length === 0) {
        return { hasAppointments, hasOpenSlots: false, isFullyBooked: false, appointmentCount: dayAppts.length };
    }

    // Generate slots (no past-time filter — we want daylight overview)
    const slots = generateSlots({
        date, businessHours, slotDuration, appointments, providers, blockedPeriods, settings,
        filterPast: false,
    });

    const hasOpenSlots = slots.some(s => s.availableProviders.length > 0);
    const isFullyBooked = slots.length > 0 && slots.every(s => s.availableProviders.length === 0);

    return { hasAppointments, hasOpenSlots, isFullyBooked, appointmentCount: dayAppts.length };
}

// ─── Rendering Helpers ────────────────────────────────────────────────

/**
 * Render an available (clickable) provider chip
 */
function renderAvailableChip(provider, date, slotTime, formattedTime) {
    const color = getProviderColor(provider);
    const name = shortName(provider);
    const bookUrl = withBaseUrl(
        `/appointments/create?date=${date.toISODate()}&time=${slotTime}&provider_id=${provider.id}`
    );
    return `<a href="${bookUrl}"
       class="slot-chip slot-chip--available"
       data-provider-color="${color}"
       title="Book with ${escapeHtml(provider.name)} at ${formattedTime}"
       data-provider-id="${provider.id}"
       data-slot-time="${slotTime}"
       data-slot-date="${date.toISODate()}">
        <span class="slot-chip__dot" data-bg-color="${color}"></span>
        ${escapeHtml(name)}
    </a>`;
}

/**
 * Render a booked (non-interactive) provider chip
 */
function renderBookedChip(provider) {
    const name = shortName(provider);
    return `<span class="slot-chip slot-chip--booked"
       title="${escapeHtml(provider.name)} — Booked">
        <span class="slot-chip__dot"></span>
        ${escapeHtml(name)}
    </span>`;
}

/**
 * Render availability badge (icon + text)
 */
function renderAvailabilityBadge(availableCount, totalProviders, isBlocked, isFullyBooked, isPartiallyBooked) {
    let statusClass, statusIcon, statusText;

    if (isBlocked) {
        statusClass = 'slot-badge--blocked';
        statusIcon = 'block';
        statusText = 'Blocked';
    } else if (isFullyBooked) {
        statusClass = 'slot-badge--booked';
        statusIcon = 'event_busy';
        statusText = 'Fully booked';
    } else if (isPartiallyBooked) {
        statusClass = 'slot-badge--partial';
        statusIcon = 'schedule';
        statusText = `${availableCount} of ${totalProviders} available`;
    } else {
        statusClass = 'slot-badge--available';
        statusIcon = 'check_circle';
        statusText = `${availableCount} provider${availableCount !== 1 ? 's' : ''} available`;
    }

    return `<div class="slot-badge ${statusClass}">
        <span class="material-symbols-outlined text-sm">${statusIcon}</span>
        <span class="text-xs font-medium">${statusText}</span>
    </div>`;
}

// ─── Full Panel Rendering ─────────────────────────────────────────────

/**
 * Render the complete slot list (just the time-slot items)
 *
 * @param {Object} opts
 * @param {DateTime}  opts.date
 * @param {Array}     opts.slots          pre-generated slot array
 * @param {Array}     opts.providers      visible providers
 * @param {string}    opts.timeFormat     Luxon format string (e.g. 'h:mm a')
 * @returns {string}  HTML
 */
export function renderSlotList({ date, slots, providers, timeFormat = 'h:mm a' }) {
    if (!slots || slots.length === 0) {
        return `<div class="slot-empty-state">
            <span class="material-symbols-outlined text-5xl mb-3 block">event_busy</span>
            <p class="text-sm font-medium">No available time slots</p>
            <p class="text-xs mt-1">This day may be blocked or outside business hours</p>
        </div>`;
    }

    const totalProviders = providers.length;

    return slots.map(slot => {
        const availableCount = slot.availableProviders.length;
        const bookedCount = slot.bookedProviders.length;
        const isFullyBooked = availableCount === 0;
        const isPartiallyBooked = bookedCount > 0 && availableCount > 0;
        const isBlocked = slot.isBlocked;

        // Status class
        let statusClass = 'slot-item--available';
        if (isBlocked)          statusClass = 'slot-item--blocked';
        else if (isFullyBooked) statusClass = 'slot-item--booked';
        else if (isPartiallyBooked) statusClass = 'slot-item--partial';

        const formattedTime = DateTime.fromFormat(slot.time, 'HH:mm').toFormat(timeFormat);

        // Provider chips
        const chips = providers.map(provider => {
            const isBooked = slot.bookedProviders.some(bp => bp.id === provider.id);
            return isBooked
                ? renderBookedChip(provider)
                : renderAvailableChip(provider, date, slot.time, formattedTime);
        }).join('');

        // Badge
        const badge = renderAvailabilityBadge(availableCount, totalProviders, isBlocked, isFullyBooked, isPartiallyBooked);

        // Book button (secondary, de-emphasized)
        const bookBtn = (!isBlocked && !isFullyBooked) ? `
            <a href="${withBaseUrl(`/appointments/create?date=${date.toISODate()}&time=${slot.time}&provider_id=${slot.availableProviders[0]?.id || ''}`)}"
               class="slot-book-btn"
               data-slot-time="${slot.time}"
               data-slot-date="${date.toISODate()}"
               data-provider-id="${slot.availableProviders[0]?.id || ''}">
                Book
            </a>` : '';

        return `<div class="slot-item ${statusClass}"
     data-slot-time="${slot.time}"
     data-date="${date.toISODate()}"
     ${isBlocked || isFullyBooked ? 'data-disabled="true"' : ''}>
    <div class="slot-item__time">
        <span class="text-base font-bold text-gray-900 dark:text-white">${formattedTime}</span>
    </div>
    <div class="slot-item__body">
        <div class="slot-item__chips">${chips}</div>
        ${badge}
    </div>
    ${bookBtn}
</div>`;
    }).join('');
}

/**
 * Render provider filter pills row
 */
export function renderProviderFilterPills(providers) {
    if (!providers || providers.length === 0) {
        return `<span class="text-xs text-gray-500 dark:text-gray-400">No providers available</span>`;
    }

    return providers.map(provider => {
        const color = getProviderColor(provider);
        return `<button type="button"
        class="provider-filter-pill"
        data-pill-color="${color}"
        data-provider-id="${provider.id}"
        data-active="true">
    <span class="provider-filter-pill__dot" data-bg-color="${color}"></span>
    ${escapeHtml(provider.name)}
</button>`;
    }).join('');
}

/**
 * Render compact status legend
 */
export function renderSlotLegend() {
    return `<div class="slot-legend">
    <div class="slot-legend__item">
        <span class="slot-legend__swatch slot-legend__swatch--open"></span><span>Open</span>
    </div>
    <div class="slot-legend__item">
        <span class="slot-legend__swatch slot-legend__swatch--partial"></span><span>Partial</span>
    </div>
    <div class="slot-legend__item">
        <span class="slot-legend__swatch slot-legend__swatch--full"></span><span>Full</span>
    </div>
    <div class="slot-legend__item">
        <span class="slot-legend__swatch slot-legend__swatch--blocked"></span><span>Blocked</span>
    </div>
</div>`;
}

/**
 * Render the full Available Slots panel for any view.
 *
 * @param {Object}   opts
 * @param {DateTime} opts.date            selected date
 * @param {Array}    opts.providers       visible providers
 * @param {Array}    opts.allProviders    all providers (for filter pills)
 * @param {Array}    opts.appointments
 * @param {Object}   opts.businessHours
 * @param {number}   opts.slotDuration
 * @param {Array}    opts.blockedPeriods
 * @param {Object}   opts.settings
 * @param {boolean}  opts.showDatePicker  show date nav (default false; week view handles its own)
 * @param {string}   opts.panelId         DOM id prefix for targeting updates (default 'shared')
 * @returns {string} HTML
 */
export function renderSlotPanel(opts) {
    const {
        date,
        providers = [],
        allProviders = null,
        appointments = [],
        businessHours,
        slotDuration,
        blockedPeriods = [],
        settings = null,
        showDatePicker = false,
        panelId = 'shared',
    } = opts;

    const slots = generateSlots({
        date, businessHours, slotDuration, appointments,
        providers, blockedPeriods, settings,
    });

    const timeFormat = settings?.getTimeFormat?.() === '24h' ? 'HH:mm' : 'h:mm a';
    const pillProviders = allProviders || providers;
    const slotListHtml = renderSlotList({ date, slots, providers, timeFormat });

    const datePickerHtml = showDatePicker ? `
        <div class="slot-panel__date-nav">
            <button type="button" class="slot-panel__nav-btn" data-slot-nav="prev" title="Previous day">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <div class="slot-panel__date-display">
                <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-lg">calendar_today</span>
                <div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white" id="${panelId}-slot-weekday">${date.toFormat('EEEE')}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400" id="${panelId}-slot-date">${date.toFormat('MMMM d, yyyy')}</div>
                </div>
            </div>
            <button type="button" class="slot-panel__nav-btn" data-slot-nav="next" title="Next day">
                <span class="material-symbols-outlined">chevron_right</span>
            </button>
            <button type="button" class="slot-panel__today-btn" data-slot-nav="today">Today</button>
        </div>` : '';

    return `<div class="slot-panel" id="${panelId}-slot-panel">
    <!-- Header -->
    <div class="slot-panel__header">
        <h3 class="slot-panel__title">
            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">event_available</span>
            Available Slots
        </h3>
        <div class="slot-panel__provider-count" id="${panelId}-provider-count">
            <span class="material-symbols-outlined text-sm">group</span>
            <span id="${panelId}-provider-count-label">${providers.length} provider${providers.length !== 1 ? 's' : ''}</span>
        </div>
    </div>

    ${datePickerHtml}

    <!-- Provider Filter Pills -->
    <div class="slot-panel__filters">
        <span class="slot-panel__filter-label">Filter by Provider</span>
        <div class="slot-panel__pills" id="${panelId}-provider-pills">
            ${renderProviderFilterPills(pillProviders)}
        </div>
    </div>

    <!-- Slot List -->
    <div class="slot-panel__slots">
        <div class="slot-panel__slots-header">
            <span class="slot-panel__slots-label">Time Slots</span>
            <span class="slot-panel__slot-count" id="${panelId}-slot-count">${slots.length} slot${slots.length !== 1 ? 's' : ''}</span>
        </div>
        <div class="slot-panel__slot-list" id="${panelId}-slot-list">
            ${slotListHtml}
        </div>
    </div>

    <!-- Legend -->
    ${renderSlotLegend()}
</div>`;
}

// ─── Utilities ────────────────────────────────────────────────────────

export function isDateBlocked(date, blockedPeriods) {
    if (!blockedPeriods || blockedPeriods.length === 0) return false;
    const checkDate = date.toISODate();
    return blockedPeriods.some(p => checkDate >= p.start && checkDate <= p.end);
}

/**
 * Attach shared slot-panel event listeners (pills, nav, slot clicks).
 * Call after inserting renderSlotPanel() HTML into the DOM.
 *
 * @param {HTMLElement}  container     parent element containing the panel
 * @param {Object}       callbacks
 * @param {Function}     callbacks.onProviderToggle(providerId, isActive)
 * @param {Function}     callbacks.onDateNav(direction)  'prev'|'next'|'today'
 * @param {Function}     callbacks.onSlotClick(time, date)
 */
export function attachSlotPanelListeners(container, callbacks = {}) {
    if (!container) return;

    // Provider filter pills
    container.querySelectorAll('.provider-filter-pill').forEach(el => {
        el.addEventListener('click', () => {
            const providerId = parseInt(el.dataset.providerId, 10);
            const isActive = el.dataset.active === 'true';
            const newState = !isActive;

            el.dataset.active = String(newState);
            el.classList.toggle('provider-filter-pill--inactive', !newState);

            if (callbacks.onProviderToggle) {
                callbacks.onProviderToggle(providerId, newState);
            }
        });
    });

    // Date nav buttons
    container.querySelectorAll('[data-slot-nav]').forEach(btn => {
        btn.addEventListener('click', () => {
            const direction = btn.dataset.slotNav;
            if (callbacks.onDateNav) callbacks.onDateNav(direction);
        });
    });

    // Slot item clicks (non-link area)
    container.querySelectorAll('.slot-item:not([data-disabled])').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target.closest('a')) return;
            const time = el.dataset.slotTime;
            const date = el.dataset.date;
            if (callbacks.onSlotClick) callbacks.onSlotClick(time, date);
        });
    });
}
