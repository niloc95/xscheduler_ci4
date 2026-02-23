<?php
/**
 * Provider Locations Component
 *
 * Manages provider practice locations via the REST API (/api/locations).
 * All CRUD operations are performed with fetch() — no form submission required.
 *
 * Concepts:
 * - Each provider can have multiple locations.
 * - Each location has a name, address, and contact number.
 * - Day assignments are managed in the Schedule section (not here).
 * - One location can be marked as primary.
 *
 * Props (passed by parent view):
 * @var int|null $providerId  Provider user ID (null on create-user page)
 * @var array    $locations   Pre-loaded rows from LocationModel::getProviderLocationsWithDays()
 */

$providerId   = $providerId ?? $user['id'] ?? null;
$locations    = $providerLocations ?? $locations ?? [];
$maxLocations = \App\Models\LocationModel::MAX_LOCATIONS_PER_PROVIDER;
$overLimit    = count($locations) > $maxLocations;
?>

<div id="providerLocationsSection" class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6"
     data-max-locations="<?= $maxLocations ?>">

    <?php if ($overLimit): ?>
    <!-- Over-limit warning -->
    <div class="mb-4 p-3 rounded-lg border border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-900/20">
        <p class="text-sm text-amber-800 dark:text-amber-200">
            <strong>Notice:</strong> This provider has <?= count($locations) ?> locations but the maximum is <?= $maxLocations ?>.
            Please delete <?= count($locations) - $maxLocations ?> location(s) to comply with the limit.
        </p>
    </div>
    <?php endif; ?>

    <!-- Section header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Practice Locations</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Configure up to <?= $maxLocations ?> locations where this provider works</p>
        </div>
        <button type="button"
                id="addLocationBtn"
                <?= count($locations) >= $maxLocations ? 'disabled' : '' ?>
                class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed <?= count($locations) >= $maxLocations ? 'hidden' : '' ?>">
            <span class="material-symbols-outlined text-sm mr-1">add</span>
            Add Location
        </button>
    </div>

    <!-- Locations list -->
    <div id="locationsContainer" class="space-y-4">

        <?php if (empty($locations)): ?>
            <div id="noLocationsMessage" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <span class="material-symbols-outlined text-4xl text-gray-400 block mb-2">location_off</span>
                <p class="text-gray-500 dark:text-gray-400">No locations configured yet.</p>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Add a location to enable multi-location booking.</p>
            </div>

        <?php else: ?>
            <?php foreach ($locations as $location): ?>
                <div class="location-card border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800"
                     data-location-id="<?= esc($location['id']) ?>">

                    <!-- Card header -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">location_on</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200 location-name-display">
                                <?= esc($location['name']) ?>
                            </span>
                            <?php if (!empty($location['is_primary'])): ?>
                                <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-xs rounded-full">
                                    Primary
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if (empty($location['is_primary'])): ?>
                                <button type="button"
                                        onclick="LocationManager.setPrimary(<?= esc($location['id']) ?>)"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                        title="Set as primary location">
                                    Set Primary
                                </button>
                            <?php endif; ?>
                            <button type="button"
                                    onclick="LocationManager.deleteLocation(<?= esc($location['id']) ?>)"
                                    class="text-gray-400 hover:text-red-600 transition-colors"
                                    title="Remove location">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                        </div>
                    </div>

                    <!-- Fields (API-driven; all saves via updateLocation on change) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">
                                Location Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   value="<?= esc($location['name']) ?>"
                                   required
                                   placeholder="e.g., Melrose Practice"
                                   class="form-input"
                                   onchange="LocationManager.updateLocation(<?= esc($location['id']) ?>, 'name', this.value)">
                        </div>

                        <div>
                            <label class="form-label">Contact Number</label>
                            <input type="tel"
                                   value="<?= esc($location['contact_number']) ?>"
                                   placeholder="e.g., +27 11 555 1234"
                                   class="form-input"
                                   onchange="LocationManager.updateLocation(<?= esc($location['id']) ?>, 'contact_number', this.value)">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="form-label">Physical Address</label>
                        <textarea rows="2"
                                  placeholder="e.g., 21 Delta Road, Eltonhill, Johannesburg, 2196"
                                  class="form-input"
                                  onchange="LocationManager.updateLocation(<?= esc($location['id']) ?>, 'address', this.value)"><?= esc($location['address']) ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- /locationsContainer -->

    <!-- Tip -->
    <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <p class="text-sm text-blue-800 dark:text-blue-200">
            <strong>Tip:</strong> Location details are saved automatically when you edit a field.
            Assign locations to specific days in the <strong>Schedule</strong> section below.
        </p>
    </div>

</div><!-- /providerLocationsSection -->

<script>
/**
 * LocationManager — provider locations CRUD via /api/locations.
 *
 * Assigned to window (not const) so that:
 *  1. Inline onclick handlers can always resolve the name from any scope context.
 *  2. SPA re-navigation safely reassigns without SyntaxError
 *     (a top-level `const` throws "Identifier already declared" on re-execution).
 */
window.LocationManager = {
    providerId: <?= json_encode($providerId) ?>,

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /** POST a new location then inject its card into the DOM (no reload). */
    async addLocation() {
        if (!this.providerId) {
            alert('Please save this user first before adding locations.');
            return;
        }

        // Enforce max locations limit
        const maxLocations = parseInt(document.getElementById('providerLocationsSection')?.dataset.maxLocations || '2', 10);
        const currentCount = document.querySelectorAll('#locationsContainer [data-location-id]').length;
        if (currentCount >= maxLocations) {
            alert(`Maximum of ${maxLocations} locations per provider allowed.`);
            return;
        }

        try {
            const response = await fetch('<?= base_url('api/locations') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    provider_id: this.providerId,
                    name: 'New Location',
                    address: '',
                    contact_number: '',
                }),
            });

            const result = await response.json();

            if (result.status === 'ok') {
                // Remove the "no locations" empty-state if present
                const emptyMsg = document.getElementById('noLocationsMessage');
                if (emptyMsg) emptyMsg.remove();

                // Build and insert the new card
                const container = document.getElementById('locationsContainer');
                container.insertAdjacentHTML('beforeend', this._buildCardHTML(result.data));

                // Scroll to, highlight, and focus the new card
                const newCard = container.querySelector(`[data-location-id="${result.data.id}"]`);
                if (newCard) {
                    newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const nameInput = newCard.querySelector('input[type="text"]');
                    if (nameInput) {
                        setTimeout(() => { nameInput.focus(); nameInput.select(); }, 400);
                    }
                    // Fade highlight ring away after 3 seconds
                    setTimeout(() => newCard.classList.remove('ring-2', 'ring-blue-400'), 3000);
                }

                this._enforceMaxLimit();

                // Sync tick boxes in Schedule day-blocks
                this._syncScheduleTickBoxes('add', result.data);
            } else {
                alert('Failed to add location: ' + (result.message || 'Unknown error'));
            }
        } catch (err) {
            console.error('LocationManager.addLocation:', err);
            alert('Failed to add location. Please try again.');
        }
    },

    /** PATCH a single field on an existing location. */
    async updateLocation(locationId, field, value) {
        try {
            const response = await fetch(`<?= base_url('api/locations') ?>/${locationId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ [field]: value }),
            });

            const result = await response.json();

            if (result.status !== 'ok') {
                console.error('LocationManager.updateLocation:', result.message);
                return;
            }

            // Keep card header display name in sync
            if (field === 'name') {
                const card = document.querySelector(`[data-location-id="${locationId}"]`);
                if (card) {
                    card.querySelector('.location-name-display').textContent = value;
                }
                // Update schedule tick-box labels too
                document.querySelectorAll(`.schedule-location-checkboxes [data-location-id="${locationId}"]`).forEach(cb => {
                    const span = cb.parentElement?.querySelector('span');
                    if (span) span.textContent = value;
                });
            }
            this._flashSaved(locationId);
        } catch (err) {
            console.error('LocationManager.updateLocation:', err);
            this._flashError(locationId);
        }
    },

    /** Promote a location to primary for this provider. */
    async setPrimary(locationId) {
        try {
            const response = await fetch(`<?= base_url('api/locations') ?>/${locationId}/set-primary`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const result = await response.json();

            if (result.status === 'ok') {
                this._refresh();
            } else {
                alert('Failed to set primary: ' + (result.message || 'Unknown error'));
            }
        } catch (err) {
            console.error('LocationManager.setPrimary:', err);
            alert('Failed to set primary. Please try again.');
        }
    },

    /** Soft-delete a location (sets is_active = 0) and remove its card from the DOM. */
    async deleteLocation(locationId) {
        if (!confirm('Are you sure you want to remove this location?')) return;

        try {
            const response = await fetch(`<?= base_url('api/locations') ?>/${locationId}`, {
                method: 'DELETE',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            const result = await response.json();

            if (result.status === 'ok') {
                const card = document.querySelector(`[data-location-id="${locationId}"]`);
                if (card) card.remove();

                // Remove tick boxes from Schedule day-blocks
                this._syncScheduleTickBoxes('remove', { id: locationId });

                // Show empty state when the last card is removed
                const container = document.getElementById('locationsContainer');
                if (container && !container.querySelector('.location-card')) {
                    container.innerHTML = this._emptyStateHTML();
                }

                this._enforceMaxLimit();
            } else {
                alert('Failed to delete location: ' + (result.message || 'Unknown error'));
            }
        } catch (err) {
            console.error('LocationManager.deleteLocation:', err);
            alert('Failed to delete location. Please try again.');
        }
    },

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Reload the current page to re-render the locations list.
     * Used only by setPrimary() which swaps badges across multiple cards.
     */
    _refresh() {
        location.reload();
    },

    /**
     * Build full card HTML for a newly created location (client-side).
     * Mirrors the PHP template above so the user sees editable fields immediately.
     * @param {Object} loc  – location object returned by the API
     */
    _buildCardHTML(loc) {
        return `
        <div class="location-card border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800 ring-2 ring-blue-400 transition-all"
             data-location-id="${loc.id}">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">location_on</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200 location-name-display">${this._esc(loc.name)}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="LocationManager.setPrimary(${loc.id})"
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                            title="Set as primary location">Set Primary</button>
                    <button type="button" onclick="LocationManager.deleteLocation(${loc.id})"
                            class="text-gray-400 hover:text-red-600 transition-colors"
                            title="Remove location">
                        <span class="material-symbols-outlined text-lg">delete</span>
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Location Name <span class="text-red-500">*</span></label>
                    <input type="text" value="${this._esc(loc.name)}" required
                           placeholder="e.g., Melrose Practice" class="form-input"
                           onchange="LocationManager.updateLocation(${loc.id}, 'name', this.value)">
                </div>
                <div>
                    <label class="form-label">Contact Number</label>
                    <input type="tel" value="${this._esc(loc.contact_number || '')}"
                           placeholder="e.g., +27 11 555 1234" class="form-input"
                           onchange="LocationManager.updateLocation(${loc.id}, 'contact_number', this.value)">
                </div>
            </div>
            <div class="mt-4">
                <label class="form-label">Physical Address</label>
                <textarea rows="2" placeholder="e.g., 21 Delta Road, Eltonhill, Johannesburg, 2196"
                          class="form-input"
                          onchange="LocationManager.updateLocation(${loc.id}, 'address', this.value)">${this._esc(loc.address || '')}</textarea>
            </div>
        </div>`;
    },

    /** HTML-escape a string for safe insertion into templates. */
    _esc(str) {
        const el = document.createElement('span');
        el.textContent = String(str ?? '');
        return el.innerHTML;
    },

    /** Show a brief "Saved" badge on a location card. */
    _flashSaved(locationId) {
        this._flashBadge(locationId, 'Saved', 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300');
    },

    /** Show a brief "Error" badge on a location card. */
    _flashError(locationId) {
        this._flashBadge(locationId, 'Error — try again', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300');
    },

    /** Generic flash badge helper. */
    _flashBadge(locationId, text, cls) {
        const card = document.querySelector(`[data-location-id="${locationId}"]`);
        if (!card) return;
        // Remove any existing badge
        card.querySelector('.loc-flash-badge')?.remove();
        const badge = document.createElement('span');
        badge.className = `loc-flash-badge ml-2 px-2 py-0.5 text-xs rounded-full font-medium transition-opacity ${cls}`;
        badge.textContent = text;
        const header = card.querySelector('.location-name-display');
        if (header) header.after(badge);
        setTimeout(() => { badge.style.opacity = '0'; setTimeout(() => badge.remove(), 300); }, 2000);
    },

    /** Returns the HTML string for the "no locations" empty state. */
    _emptyStateHTML() {
        return `
            <div id="noLocationsMessage" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <span class="material-symbols-outlined text-4xl text-gray-400 block mb-2">location_off</span>
                <p class="text-gray-500 dark:text-gray-400">No locations configured yet.</p>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Add a location to enable multi-location booking.</p>
            </div>`;
    },

    /**
     * Dynamically add or remove location tick-box checkboxes in every
     * Schedule day-block so they stay in sync with LocationManager changes
     * without requiring "Update User" / page reload.
     *
     * @param {'add'|'remove'} action
     * @param {Object}         loc  – { id, name, ... }
     */
    _syncScheduleTickBoxes(action, loc) {
        const dayRows = document.querySelectorAll('[data-schedule-day]');
        if (!dayRows.length) return;

        dayRows.forEach(row => {
            const dayKey   = row.dataset.scheduleDay;               // e.g. "monday"
            const isActive = row.querySelector('.js-day-active')?.checked ?? false;

            if (action === 'add') {
                // Find or create the container
                let container = row.querySelector('.schedule-location-checkboxes');
                if (!container) {
                    container = document.createElement('div');
                    container.className = 'mt-3 flex flex-wrap items-center gap-4 schedule-location-checkboxes'
                        + (isActive ? '' : ' opacity-40 pointer-events-none');
                    // Add the "Locations:" label
                    const lbl = document.createElement('span');
                    lbl.className = 'text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide';
                    lbl.textContent = 'Locations:';
                    container.appendChild(lbl);
                    // Insert before the time grid
                    const timeGrid = row.querySelector('.schedule-time-grid');
                    if (timeGrid) {
                        row.insertBefore(container, timeGrid);
                    } else {
                        row.appendChild(container);
                    }
                }

                // Don't duplicate if already present
                if (container.querySelector(`[data-location-id="${loc.id}"]`)) return;

                const label = document.createElement('label');
                label.className = 'inline-flex items-center gap-1.5 cursor-pointer text-sm';
                label.innerHTML = `
                    <input type="checkbox"
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 js-location-day"
                           name="schedule[${dayKey}][locations][]"
                           value="${loc.id}"
                           data-location-id="${loc.id}"
                           data-day="${dayKey}"
                           ${isActive ? '' : 'disabled'}>
                    <span class="text-gray-700 dark:text-gray-300">${this._esc(loc.name || 'Location ' + loc.id)}</span>`;
                container.appendChild(label);

            } else if (action === 'remove') {
                const container = row.querySelector('.schedule-location-checkboxes');
                if (!container) return;

                // Remove checkbox label for this location
                const cb = container.querySelector(`[data-location-id="${loc.id}"]`);
                if (cb) {
                    const lbl = cb.closest('label');
                    if (lbl) lbl.remove(); else cb.remove();
                }

                // If no checkboxes remain, remove the entire container
                if (!container.querySelector('.js-location-day')) {
                    container.remove();
                }
            }
        });
    },

    /** Show / hide the Add Location button based on current card count vs max. */
    _enforceMaxLimit() {
        const btn = document.getElementById('addLocationBtn');
        if (!btn) return;
        const maxLocations = parseInt(document.getElementById('providerLocationsSection')?.dataset.maxLocations || '2', 10);
        const currentCount = document.querySelectorAll('#locationsContainer [data-location-id]').length;
        if (currentCount >= maxLocations) {
            btn.disabled = true;
            btn.classList.add('hidden');
        } else {
            btn.disabled = false;
            btn.classList.remove('hidden');
        }
    },
};

// Wire the Add Location button with addEventListener (avoids inline onclick on the button element).
// The dataset guard prevents double-binding if this script re-executes on SPA navigation.
(function () {
    const btn = document.getElementById('addLocationBtn');
    if (btn && !btn.dataset.locBound) {
        btn.addEventListener('click', () => window.LocationManager.addLocation());
        btn.dataset.locBound = 'true';
    }
}());
</script>
