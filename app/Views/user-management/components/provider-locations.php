<?php
/**
 * Provider Locations Component
 * 
 * Allows management of provider locations with:
 * - Friendly name (primary identifier)
 * - Physical address
 * - Contact number
 * - Working days assignment
 * 
 * Working hours remain global to the provider across all locations.
 * 
 * Props:
 * - $providerId: Provider user ID
 * - $locations: Array of existing locations (from LocationModel)
 */

$providerId = $providerId ?? $user['id'] ?? null;
$locations = $locations ?? [];

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$dayAbbr = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>

<div id="providerLocationsSection" class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Practice Locations</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Configure locations where this provider works</p>
        </div>
        <button type="button" 
                onclick="LocationManager.addLocation()"
                class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <span class="material-symbols-outlined text-sm mr-1">add</span>
            Add Location
        </button>
    </div>

    <!-- Locations Container -->
    <div id="locationsContainer" class="space-y-4">
        <?php if (empty($locations)): ?>
            <div id="noLocationsMessage" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                <span class="material-symbols-outlined text-4xl text-gray-400 mb-2">location_off</span>
                <p class="text-gray-500 dark:text-gray-400">No locations configured yet.</p>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Add a location to enable multi-location booking.</p>
            </div>
        <?php else: ?>
            <?php foreach ($locations as $index => $location): ?>
                <div class="location-card border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800" 
                     data-location-id="<?= esc($location['id']) ?>">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">location_on</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200 location-name-display">
                                <?= esc($location['name']) ?>
                            </span>
                            <?php if (!empty($location['is_primary'])): ?>
                                <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-xs rounded-full">Primary</span>
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

                    <!-- Location Form Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Location Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="locations[<?= $index ?>][name]" 
                                   value="<?= esc($location['name']) ?>"
                                   required
                                   placeholder="e.g., Melrose Practice"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                                   onchange="LocationManager.updateLocation(<?= esc($location['id']) ?>, 'name', this.value)">
                            <input type="hidden" name="locations[<?= $index ?>][id]" value="<?= esc($location['id']) ?>">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Contact Number <span class="text-red-500">*</span>
                            </label>
                            <input type="tel" 
                                   name="locations[<?= $index ?>][contact_number]" 
                                   value="<?= esc($location['contact_number']) ?>"
                                   required
                                   placeholder="e.g., +27 11 555 1234"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                                   onchange="LocationManager.updateLocation(<?= esc($location['id']) ?>, 'contact_number', this.value)">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Physical Address <span class="text-red-500">*</span>
                        </label>
                        <textarea name="locations[<?= $index ?>][address]" 
                                  required
                                  rows="2"
                                  placeholder="e.g., 21 Delta Road, Eltonhill, Johannesburg, 2196"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                                  onchange="LocationManager.updateLocation(<?= esc($location['id']) ?>, 'address', this.value)"><?= esc($location['address']) ?></textarea>
                    </div>

                    <!-- Working Days -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Working Days at this Location
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <?php 
                            $locationDays = $location['days'] ?? [];
                            foreach ($dayNames as $dayNum => $dayName): 
                                $isChecked = in_array($dayNum, $locationDays);
                            ?>
                                <label class="inline-flex items-center px-3 py-1.5 rounded-lg border cursor-pointer transition-colors
                                    <?= $isChecked ? 'bg-blue-100 dark:bg-blue-900/30 border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300' : 'bg-gray-50 dark:bg-gray-700 border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-650' ?>">
                                    <input type="checkbox" 
                                           name="locations[<?= $index ?>][days][]" 
                                           value="<?= $dayNum ?>"
                                           <?= $isChecked ? 'checked' : '' ?>
                                           class="sr-only"
                                           onchange="LocationManager.toggleDay(<?= esc($location['id']) ?>, <?= $dayNum ?>, this.checked); this.closest('label').classList.toggle('bg-blue-100', this.checked); this.closest('label').classList.toggle('dark:bg-blue-900/30', this.checked); this.closest('label').classList.toggle('border-blue-300', this.checked); this.closest('label').classList.toggle('dark:border-blue-700', this.checked); this.closest('label').classList.toggle('text-blue-700', this.checked); this.closest('label').classList.toggle('dark:text-blue-300', this.checked);">
                                    <span class="text-sm font-medium"><?= $dayAbbr[$dayNum] ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Select the days this provider works at this location. Working hours are set globally above.
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Help text -->
    <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <p class="text-sm text-blue-800 dark:text-blue-200">
            <strong>Tip:</strong> A provider can work at different locations on different days. 
            For example: Mon-Wed at Location A, Thu-Fri at Location B.
            Working hours (set above) apply to all locations.
        </p>
    </div>
</div>

<!-- Location Card Template (for JS) -->
<template id="locationCardTemplate">
    <div class="location-card border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800" 
         data-location-id="">
        <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">location_on</span>
                <span class="font-medium text-gray-800 dark:text-gray-200 location-name-display">New Location</span>
                <span class="primary-badge px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-xs rounded-full hidden">Primary</span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="set-primary-btn text-xs text-blue-600 dark:text-blue-400 hover:underline">
                    Set Primary
                </button>
                <button type="button" class="delete-btn text-gray-400 hover:text-red-600 transition-colors">
                    <span class="material-symbols-outlined text-lg">delete</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Location Name <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       name="locations[NEW][name]" 
                       required
                       placeholder="e.g., Melrose Practice"
                       class="location-name w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                <input type="hidden" name="locations[NEW][id]" value="">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Contact Number <span class="text-red-500">*</span>
                </label>
                <input type="tel" 
                       name="locations[NEW][contact_number]" 
                       required
                       placeholder="e.g., +27 11 555 1234"
                       class="location-contact w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
            </div>
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Physical Address <span class="text-red-500">*</span>
            </label>
            <textarea name="locations[NEW][address]" 
                      required
                      rows="2"
                      placeholder="e.g., 21 Delta Road, Eltonhill, Johannesburg, 2196"
                      class="location-address w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"></textarea>
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Working Days at this Location
            </label>
            <div class="flex flex-wrap gap-2 days-container">
                <!-- Days will be populated by JS -->
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                Select the days this provider works at this location. Working hours are set globally above.
            </p>
        </div>
    </div>
</template>

<script>
const LocationManager = {
    providerId: <?= json_encode($providerId) ?>,
    dayNames: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    dayAbbr: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    
    async addLocation() {
        const data = {
            provider_id: this.providerId,
            name: 'New Location',
            address: '',
            contact_number: '',
            days: []
        };
        
        try {
            const response = await fetch('<?= base_url('api/locations') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.status === 'ok') {
                // Reload to show new location
                location.reload();
            } else {
                alert('Failed to add location: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error adding location:', error);
            alert('Failed to add location. Please try again.');
        }
    },
    
    async updateLocation(locationId, field, value) {
        const data = {};
        data[field] = value;
        
        try {
            const response = await fetch(`<?= base_url('api/locations') ?>/${locationId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.status !== 'ok') {
                console.error('Failed to update location:', result.message);
            } else if (field === 'name') {
                // Update display name
                const card = document.querySelector(`[data-location-id="${locationId}"]`);
                if (card) {
                    card.querySelector('.location-name-display').textContent = value;
                }
            }
        } catch (error) {
            console.error('Error updating location:', error);
        }
    },
    
    async toggleDay(locationId, dayNum, isChecked) {
        // Get current days from the form
        const card = document.querySelector(`[data-location-id="${locationId}"]`);
        const checkboxes = card.querySelectorAll('input[type="checkbox"][name*="[days]"]');
        const days = [];
        
        checkboxes.forEach(cb => {
            if (cb.checked) {
                days.push(parseInt(cb.value));
            }
        });
        
        try {
            const response = await fetch(`<?= base_url('api/locations') ?>/${locationId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ days: days })
            });
            
            const result = await response.json();
            
            if (result.status !== 'ok') {
                console.error('Failed to update days:', result.message);
            }
        } catch (error) {
            console.error('Error updating days:', error);
        }
    },
    
    async setPrimary(locationId) {
        try {
            const response = await fetch(`<?= base_url('api/locations') ?>/${locationId}/set-primary`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (result.status === 'ok') {
                location.reload();
            } else {
                alert('Failed to set primary: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error setting primary:', error);
            alert('Failed to set primary. Please try again.');
        }
    },
    
    async deleteLocation(locationId) {
        if (!confirm('Are you sure you want to remove this location?')) {
            return;
        }
        
        try {
            const response = await fetch(`<?= base_url('api/locations') ?>/${locationId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (result.status === 'ok') {
                // Remove card from DOM
                const card = document.querySelector(`[data-location-id="${locationId}"]`);
                if (card) {
                    card.remove();
                }
                
                // Show empty message if no locations left
                const container = document.getElementById('locationsContainer');
                if (container && !container.querySelector('.location-card')) {
                    container.innerHTML = `
                        <div id="noLocationsMessage" class="p-6 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
                            <span class="material-symbols-outlined text-4xl text-gray-400 mb-2">location_off</span>
                            <p class="text-gray-500 dark:text-gray-400">No locations configured yet.</p>
                            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Add a location to enable multi-location booking.</p>
                        </div>
                    `;
                }
            } else {
                alert('Failed to delete location: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error deleting location:', error);
            alert('Failed to delete location. Please try again.');
        }
    }
};
</script>
