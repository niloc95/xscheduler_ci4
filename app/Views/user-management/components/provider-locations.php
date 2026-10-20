<?php
/**
 * Provider Locations Component
 *
 * Manages provider practice locations via the REST API (/api/locations).
 * All CRUD operations are performed with fetch() — no form submission required.
 * Behaviour: resources/js/modules/user-management/provider-locations.js
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
     data-provider-id="<?= esc((string) ($providerId ?? ''), 'attr') ?>"
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
                                        data-action="set-primary-location"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                        title="Set as primary location">
                                    Set Primary
                                </button>
                            <?php endif; ?>
                            <button type="button"
                                    data-action="delete-location"
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
                                   placeholder="e.g., Melrose Practice"
                                   class="form-input"
                                   data-field="name">
                        </div>

                        <div>
                            <label class="form-label">Contact Number</label>
                            <input type="tel"
                                   value="<?= esc($location['contact_number']) ?>"
                                   placeholder="e.g., +27 11 555 1234"
                                   class="form-input"
                                   data-field="contact_number">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="form-label">City</label>
                            <input type="text"
                                   value="<?= esc($location['city'] ?? '') ?>"
                                   placeholder="e.g., Johannesburg"
                                   class="form-input"
                                   data-field="city">
                        </div>
                        <div>
                            <label class="form-label">Area / Suburb</label>
                            <input type="text"
                                   value="<?= esc($location['area'] ?? '') ?>"
                                   placeholder="e.g., Rosebank"
                                   class="form-input"
                                   data-field="area">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="form-label">Physical Address</label>
                        <textarea rows="2"
                                  placeholder="e.g., 21 Delta Road, Eltonhill, Johannesburg, 2196"
                                  class="form-input"
                                  data-field="address"><?= esc($location['address']) ?></textarea>
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
