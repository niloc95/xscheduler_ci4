<?php
/**
 * Provider Staff Assignment Panel
 */

use CodeIgniter\I18n\Time;

$assignedStaff = $assignedStaff ?? [];
$availableStaff = $availableStaff ?? [];
$assignedIds = array_map(static fn($row) => $row['id'] ?? null, $assignedStaff);
$providerId = isset($providerId) && is_numeric($providerId) ? (int) $providerId : 0;
$missingProviderId = $providerId <= 0;
$csrfName = csrf_token();
$csrfValue = csrf_hash();
$listUrl = base_url('provider-staff/provider/' . (int) $providerId);
$assignUrl = base_url('provider-staff/assign');
$removeUrl = base_url('provider-staff/remove');
?>

<?php if ($missingProviderId): ?>
    <div class="mb-5 rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
        Staff assignments locked - Save this provider first, then return to assign staff members
    </div>
<?php endif; ?>

<div class="border-t border-gray-200 dark:border-gray-700 pt-6" data-provider-staff-manager data-provider-id="<?= esc($providerId) ?>" data-assign-url="<?= esc($assignUrl) ?>" data-remove-url="<?= esc($removeUrl) ?>" data-list-url="<?= esc($listUrl) ?>" data-csrf-name="<?= esc($csrfName) ?>" data-csrf-value="<?= esc($csrfValue) ?>">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Assigned Staff</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Team members who can access this provider's schedule</p>
        </div>
        <?php if ($canManageAssignments ?? false): ?>
            <button type="button" data-refresh-list class="hidden"></button>
        <?php endif; ?>
    </div>

    <?php if ($canManageAssignments ?? false): ?>
        <div class="mb-5 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="flex flex-col md:flex-row md:items-end md:space-x-4 space-y-3 md:space-y-0">
                <div class="flex-1">
                    <label for="provider-staff-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Staff Member</label>
                    <select id="provider-staff-select" data-staff-select class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Choose staff member</option>
                        <?php foreach ($availableStaff as $staff): ?>
                            <?php $isAssigned = in_array($staff['id'], $assignedIds, true); ?>
                            <option value="<?= (int) $staff['id'] ?>" <?= $isAssigned ? 'disabled' : '' ?>>
                                <?= esc($staff['name'] ?? 'Unnamed') ?><?= $isAssigned ? ' (already assigned)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="button" id="provider-staff-assign-btn" data-assign-btn class="btn btn-primary inline-flex items-center px-4 py-2" disabled aria-disabled="true">
                        <span class="material-symbols-outlined mr-2">person_add</span>
                        Assign Staff
                    </button>
                </div>
            </div>
        </div>
    <?php elseif (empty($assignedStaff)): ?>
        <div class="mb-5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
            <p class="text-sm text-blue-800 dark:text-blue-100">No staff assigned yet. An administrator can add staff from this page.</p>
        </div>
    <?php endif; ?>

    <div data-assigned-wrapper class="space-y-3">
        <?php if (!empty($assignedStaff)): ?>
            <?php foreach ($assignedStaff as $staff): ?>
                <div class="flex items-start justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800" data-staff-row data-staff-id="<?= (int) ($staff['id'] ?? 0) ?>">
                    <div>
                        <div class="flex items-center space-x-3">
                            <?php $staffAvatar = avatar_data($staff, 'S'); ?>
                            <?php if (!empty($staffAvatar['image_url'])): ?>
                                <img src="<?= esc($staffAvatar['image_url']) ?>"
                                     alt="<?= esc($staffAvatar['name']) ?>"
                                     class="w-10 h-10 rounded-full object-cover flex-shrink-0" />
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-500 to-blue-500 flex items-center justify-center text-white font-semibold flex-shrink-0">
                                <?= esc($staffAvatar['initials']) ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100 flex items-center space-x-2">
                                    <span><?= esc($staff['name'] ?? 'Unknown Staff') ?></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                                        <?= esc(ucfirst($staff['role'] ?? 'staff')) ?>
                                    </span>
                                </div>
                                <?php if (!empty($staff['email'])): ?>
                                    <div class="text-sm text-gray-500 dark:text-gray-400"><?= esc($staff['email']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($staff['phone'])): ?>
                                    <div class="text-sm text-gray-400 dark:text-gray-500"><?= esc($staff['phone']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($staff['assigned_at'])): ?>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">Assigned <?= esc(Time::parse($staff['assigned_at'])->humanize()) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($canManageAssignments ?? false): ?>
                        <button type="button" class="btn btn-secondary inline-flex items-center px-3 py-2" data-remove-btn data-staff-id="<?= (int) ($staff['id'] ?? 0) ?>">
                            <span class="material-symbols-outlined mr-1 text-base">person_off</span>
                            Remove
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div data-empty-state class="p-4 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg text-center text-gray-500 dark:text-gray-400">
                No staff assigned to this provider yet.
            </div>
        <?php endif; ?>
    </div>
</div>
