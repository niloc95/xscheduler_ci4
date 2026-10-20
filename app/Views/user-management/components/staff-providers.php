<?php
/**
 * Staff Provider Assignment Panel
 * 
 * Displays providers assigned to a staff member.
 * Allows admins to add/remove provider assignments.
 */

use CodeIgniter\I18n\Time;

$assignedProviders = $assignedProviders ?? [];
$availableProviders = $availableProviders ?? [];
$assignedIds = array_map(static fn($row) => $row['id'] ?? null, $assignedProviders);
$staffId = isset($staffId) && is_numeric($staffId) ? (int) $staffId : 0;
$missingStaffId = $staffId <= 0;

$csrfName = csrf_token();
$csrfValue = csrf_hash();
$listUrl = base_url('staff-providers/staff/' . (int) $staffId);
$assignUrl = base_url('staff-providers/assign');
$removeUrl = base_url('staff-providers/remove');
?>

<?php if ($missingStaffId): ?>
    <div class="mb-5 rounded-lg border border-blue-300 bg-blue-50 dark:border-blue-600 dark:bg-blue-900/30 p-4 text-sm text-blue-800 dark:text-blue-100">
        <div class="font-medium mb-1">Provider assignments will be available after saving</div>
        <p>Save this staff member first, then you can assign them to providers.</p>
    </div>
<?php else: ?>
<div class="border-t border-gray-200 dark:border-gray-700 pt-6" data-staff-providers-manager data-staff-id="<?= esc($staffId) ?>" data-assign-url="<?= esc($assignUrl) ?>" data-remove-url="<?= esc($removeUrl) ?>" data-list-url="<?= esc($listUrl) ?>" data-csrf-name="<?= esc($csrfName) ?>" data-csrf-value="<?= esc($csrfValue) ?>">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Assigned Providers</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Providers this staff member can assist</p>
        </div>
    </div>

    <?php if ($canManageAssignments ?? false): ?>
        <div class="mb-5 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="flex flex-col md:flex-row md:items-end md:space-x-4 space-y-3 md:space-y-0">
                <input type="hidden" id="staffId" value="<?= (int) $staffId ?>">
                <input type="hidden" data-csrf name="<?= esc($csrfName) ?>" value="<?= esc($csrfValue) ?>">
                <div class="flex-1">
                    <label for="staff-provider-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Provider</label>
                    <select id="staff-provider-select" data-provider-select class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Choose a provider</option>
                        <?php foreach ($availableProviders as $provider): ?>
                            <?php $isAssigned = in_array($provider['id'], $assignedIds, true); ?>
                            <option value="<?= (int) $provider['id'] ?>" <?= $isAssigned ? 'disabled' : '' ?>>
                                <?= esc($provider['name'] ?? 'Unnamed') ?><?= $isAssigned ? ' (already assigned)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="button" id="staff-provider-assign-btn" data-assign-provider class="btn btn-primary inline-flex items-center px-4 py-2" aria-disabled="true" disabled>
                        <span class="material-symbols-outlined mr-2">person_add</span>
                        Assign Provider
                    </button>
                </div>
            </div>
        </div>
    <?php elseif (empty($assignedProviders)): ?>
        <div class="mb-5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
            <p class="text-sm text-blue-800 dark:text-blue-100">No providers assigned yet. An administrator can add providers from this page.</p>
        </div>
    <?php endif; ?>

    <div data-assigned-wrapper class="space-y-3">
        <?php if (!empty($assignedProviders)): ?>
            <?php foreach ($assignedProviders as $provider): ?>
                <div class="flex items-start justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800" data-provider-row data-provider-id="<?= (int) ($provider['id'] ?? 0) ?>">
                    <div>
                        <div class="flex items-center space-x-3">
                            <?php $providerAvatar = avatar_data($provider, 'P'); ?>
                            <?php if (!empty($providerAvatar['image_url'])): ?>
                                <img src="<?= esc($providerAvatar['image_url']) ?>"
                                     alt="<?= esc($providerAvatar['name']) ?>"
                                     class="w-10 h-10 rounded-full object-cover flex-shrink-0" />
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white font-semibold flex-shrink-0">
                                <?= esc($providerAvatar['initials']) ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100 flex items-center space-x-2">
                                    <span><?= esc($provider['name'] ?? 'Unknown Provider') ?></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-blue-100 dark:bg-blue-700 text-blue-700 dark:text-blue-200">
                                        Provider
                                    </span>
                                </div>
                                <?php if (!empty($provider['email'])): ?>
                                    <div class="text-sm text-gray-500 dark:text-gray-400"><?= esc($provider['email']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($provider['assigned_at'])): ?>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">Assigned <?= esc(Time::parse($provider['assigned_at'])->humanize()) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($canManageAssignments ?? false): ?>
                        <button type="button" class="btn btn-secondary inline-flex items-center px-3 py-2" data-remove-btn data-provider-id="<?= (int) ($provider['id'] ?? 0) ?>">
                            <span class="material-symbols-outlined mr-1 text-base">person_off</span>
                            Remove
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div data-empty-state class="p-4 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg text-center text-gray-500 dark:text-gray-400">
                No providers assigned to this staff member yet.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; // End of !$missingStaffId check ?>
