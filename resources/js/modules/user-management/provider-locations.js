/**
 * Provider locations CRUD (user-management/components/provider-locations).
 *
 * Talks to /api/locations and keeps the Schedule section's per-day location
 * tick-boxes in sync without a page reload. Server context comes from
 * #providerLocationsSection:
 *   data-provider-id     – provider being edited (empty until the user is saved)
 *   data-max-locations   – per-provider location cap
 */

import { escapeHtml } from '../../utils/html.js';
import { getBaseUrl } from '../../utils/url-helpers.js';

const toast = (opts) => window.XSNotify?.toast?.(opts);

function locationsUrl(suffix = '') {
    return `${getBaseUrl()}/api/locations${suffix}`;
}

function isSuccessfulResponse(response, result) {
    return Boolean(response?.ok) && !result?.error;
}

function getErrorMessage(result) {
    return result?.error?.message || result?.meta?.message || 'Unknown error';
}

function getMaxLocations() {
    return parseInt(document.getElementById('providerLocationsSection')?.dataset.maxLocations || '2', 10);
}

function getLocationCount() {
    return document.querySelectorAll('#locationsContainer [data-location-id]').length;
}

/**
 * Build full card HTML for a newly created location (client-side).
 * Mirrors the PHP template so the user sees editable fields immediately.
 */
function buildCardHTML(loc) {
    return `
        <div class="location-card border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800 ring-2 ring-blue-400 transition-all"
             data-location-id="${escapeHtml(loc.id)}">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">location_on</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200 location-name-display">${escapeHtml(loc.name)}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" data-action="set-primary-location"
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                            title="Set as primary location">Set Primary</button>
                    <button type="button" data-action="delete-location"
                            class="text-gray-400 hover:text-red-600 transition-colors"
                            title="Remove location">
                        <span class="material-symbols-outlined text-lg">delete</span>
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Location Name <span class="text-red-500">*</span></label>
                    <input type="text" value="${escapeHtml(loc.name)}"
                           placeholder="e.g., Melrose Practice" class="form-input"
                           data-field="name">
                </div>
                <div>
                    <label class="form-label">Contact Number</label>
                    <input type="tel" value="${escapeHtml(loc.contact_number || '')}"
                           placeholder="e.g., +27 11 555 1234" class="form-input"
                           data-field="contact_number">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="form-label">City</label>
                    <input type="text" value="${escapeHtml(loc.city || '')}"
                           placeholder="e.g., Johannesburg" class="form-input"
                           data-field="city">
                </div>
                <div>
                    <label class="form-label">Area / Suburb</label>
                    <input type="text" value="${escapeHtml(loc.area || '')}"
                           placeholder="e.g., Rosebank" class="form-input"
                           data-field="area">
                </div>
            </div>
            <div class="mt-4">
                <label class="form-label">Physical Address</label>
                <textarea rows="2" placeholder="e.g., 21 Delta Road, Eltonhill, Johannesburg, 2196"
                          class="form-input"
                          data-field="address">${escapeHtml(loc.address || '')}</textarea>
            </div>
        </div>`;
}

function emptyStateHTML() {
    return `
            <div id="noLocationsMessage" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <span class="material-symbols-outlined text-4xl text-gray-400 block mb-2">location_off</span>
                <p class="text-gray-500 dark:text-gray-400">No locations configured yet.</p>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Add a location to enable multi-location booking.</p>
            </div>`;
}

function flashBadge(locationId, text, cls) {
    const card = document.querySelector(`[data-location-id="${locationId}"]`);
    if (!card) return;
    card.querySelector('.loc-flash-badge')?.remove();
    const badge = document.createElement('span');
    badge.className = `loc-flash-badge ml-2 px-2 py-0.5 text-xs rounded-full font-medium transition-opacity ${cls}`;
    badge.textContent = text;
    card.querySelector('.location-name-display')?.after(badge);
    setTimeout(() => {
        badge.classList.add('opacity-0');
        setTimeout(() => badge.remove(), 300);
    }, 2000);
}

const flashSaved = (id) => flashBadge(id, 'Saved', 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300');
const flashError = (id) => flashBadge(id, 'Error — try again', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300');

/** Show / hide the Add Location button based on current card count vs max. */
function enforceMaxLimit() {
    const btn = document.getElementById('addLocationBtn');
    if (!btn) return;
    const atLimit = getLocationCount() >= getMaxLocations();
    btn.disabled = atLimit;
    btn.classList.toggle('hidden', atLimit);
}

/**
 * Add or remove location tick-boxes in every Schedule day-block so they stay
 * in sync with location changes without a save/reload.
 *
 * @param {'add'|'remove'} action
 * @param {Object}         loc  – { id, name, ... }
 */
function syncScheduleTickBoxes(action, loc) {
    document.querySelectorAll('[data-schedule-day]').forEach((row) => {
        const dayKey = row.dataset.scheduleDay;
        const isActive = row.querySelector('.js-day-active')?.checked ?? false;

        if (action === 'add') {
            let container = row.querySelector('.schedule-location-checkboxes');
            if (!container) {
                container = document.createElement('div');
                container.className = 'mt-3 flex flex-wrap items-center gap-4 schedule-location-checkboxes'
                    + (isActive ? '' : ' opacity-40 pointer-events-none');
                const lbl = document.createElement('span');
                lbl.className = 'text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide';
                lbl.textContent = 'Locations:';
                container.appendChild(lbl);
                const timeGrid = row.querySelector('.schedule-time-grid');
                if (timeGrid) {
                    row.insertBefore(container, timeGrid);
                } else {
                    row.appendChild(container);
                }
            }

            if (container.querySelector(`[data-location-id="${loc.id}"]`)) return;

            const label = document.createElement('label');
            label.className = 'inline-flex items-center gap-1.5 cursor-pointer text-sm';
            label.innerHTML = `
                    <input type="checkbox"
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 js-location-day"
                           name="schedule[${escapeHtml(dayKey)}][locations][]"
                           value="${escapeHtml(loc.id)}"
                           data-location-id="${escapeHtml(loc.id)}"
                           data-day="${escapeHtml(dayKey)}"
                           ${isActive ? '' : 'disabled'}>
                    <span class="text-gray-700 dark:text-gray-300">${escapeHtml(loc.name || 'Location ' + loc.id)}</span>`;
            container.appendChild(label);
        } else if (action === 'remove') {
            const container = row.querySelector('.schedule-location-checkboxes');
            if (!container) return;

            const cb = container.querySelector(`[data-location-id="${loc.id}"]`);
            if (cb) {
                (cb.closest('label') || cb).remove();
            }

            if (!container.querySelector('.js-location-day')) {
                container.remove();
            }
        }
    });
}

async function sendJson(url, method, body) {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    return { response, result: await response.json() };
}

/** POST a new location then inject its card into the DOM (no reload). */
async function addLocation(providerId) {
    if (!providerId) {
        toast({ type: 'warning', title: 'Save Required', message: 'Please save this user first before adding locations.' });
        return;
    }

    const maxLocations = getMaxLocations();
    if (getLocationCount() >= maxLocations) {
        toast({ type: 'warning', title: 'Limit Reached', message: `Maximum of ${maxLocations} locations per provider allowed.` });
        return;
    }

    try {
        const { response, result } = await sendJson(locationsUrl(), 'POST', {
            provider_id: providerId,
            name: 'New Location',
            address: '',
            city: '',
            area: '',
            contact_number: '',
        });
        const location = result?.data ?? null;

        if (!isSuccessfulResponse(response, result) || !location) {
            toast({ type: 'error', title: 'Location Error', message: 'Failed to add location: ' + getErrorMessage(result) });
            return;
        }

        document.getElementById('noLocationsMessage')?.remove();

        const container = document.getElementById('locationsContainer');
        container.insertAdjacentHTML('beforeend', buildCardHTML(location));

        const newCard = container.querySelector(`[data-location-id="${location.id}"]`);
        if (newCard) {
            newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const nameInput = newCard.querySelector('input[type="text"]');
            if (nameInput) {
                setTimeout(() => { nameInput.focus(); nameInput.select(); }, 400);
            }
            setTimeout(() => newCard.classList.remove('ring-2', 'ring-blue-400'), 3000);
        }

        enforceMaxLimit();
        syncScheduleTickBoxes('add', location);
    } catch (err) {
        console.error('provider-locations addLocation:', err);
        toast({ type: 'error', title: 'Location Error', message: 'Failed to add location. Please try again.' });
    }
}

/** Save a single field on an existing location. */
async function updateLocation(locationId, field, value) {
    try {
        const { response, result } = await sendJson(locationsUrl(`/${locationId}`), 'PUT', { [field]: value });

        if (!isSuccessfulResponse(response, result)) {
            console.error('provider-locations updateLocation:', getErrorMessage(result));
            flashError(locationId);
            return;
        }

        if (field === 'name') {
            const display = document.querySelector(`[data-location-id="${locationId}"] .location-name-display`);
            if (display) display.textContent = value;
            document.querySelectorAll(`.schedule-location-checkboxes [data-location-id="${locationId}"]`).forEach((cb) => {
                const span = cb.parentElement?.querySelector('span');
                if (span) span.textContent = value;
            });
        }
        flashSaved(locationId);
    } catch (err) {
        console.error('provider-locations updateLocation:', err);
        flashError(locationId);
    }
}

/** Promote a location to primary; reloads because badges move across cards. */
async function setPrimary(locationId) {
    try {
        const { response, result } = await sendJson(locationsUrl(`/${locationId}/set-primary`), 'POST');

        if (isSuccessfulResponse(response, result)) {
            window.location.reload();
        } else {
            toast({ type: 'error', title: 'Location Error', message: 'Failed to set primary: ' + getErrorMessage(result) });
        }
    } catch (err) {
        console.error('provider-locations setPrimary:', err);
        toast({ type: 'error', title: 'Location Error', message: 'Failed to set primary. Please try again.' });
    }
}

/** Soft-delete a location (sets is_active = 0) and remove its card. */
async function deleteLocation(locationId) {
    const ok = await window.XSConfirm?.show({ title: 'Remove Location', message: 'Are you sure you want to remove this location?', confirmText: 'Remove', danger: true });
    if (!ok) return;

    try {
        const { response, result } = await sendJson(locationsUrl(`/${locationId}`), 'DELETE');

        if (!isSuccessfulResponse(response, result)) {
            toast({ type: 'error', title: 'Location Error', message: 'Failed to delete location: ' + getErrorMessage(result) });
            return;
        }

        document.querySelector(`[data-location-id="${locationId}"]`)?.remove();
        syncScheduleTickBoxes('remove', { id: locationId });

        const container = document.getElementById('locationsContainer');
        if (container && !container.querySelector('.location-card')) {
            container.innerHTML = emptyStateHTML();
        }

        enforceMaxLimit();
    } catch (err) {
        console.error('provider-locations deleteLocation:', err);
        toast({ type: 'error', title: 'Location Error', message: 'Failed to delete location. Please try again.' });
    }
}

export function initProviderLocations() {
    const section = document.getElementById('providerLocationsSection');
    if (!section || section.dataset.locationsInitialized === 'true') {
        return;
    }
    section.dataset.locationsInitialized = 'true';

    const providerId = Number(section.dataset.providerId || 0) || null;

    document.getElementById('addLocationBtn')?.addEventListener('click', () => addLocation(providerId));

    // Delegated so server-rendered and dynamically added cards both work.
    const container = document.getElementById('locationsContainer');
    if (!container) return;

    container.addEventListener('change', (e) => {
        const field = e.target.dataset.field;
        if (!field) return;
        const card = e.target.closest('[data-location-id]');
        if (!card) return;
        updateLocation(parseInt(card.dataset.locationId, 10), field, e.target.value);
    });

    container.addEventListener('click', (e) => {
        const actionBtn = e.target.closest('[data-action]');
        if (!actionBtn) return;
        const card = actionBtn.closest('[data-location-id]');
        if (!card) return;
        const locationId = parseInt(card.dataset.locationId, 10);
        if (actionBtn.dataset.action === 'delete-location') {
            deleteLocation(locationId);
        } else if (actionBtn.dataset.action === 'set-primary-location') {
            setPrimary(locationId);
        }
    });
}
