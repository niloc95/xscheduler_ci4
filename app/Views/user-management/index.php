<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?>User Management<?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?>Manage system users and their permissions<?= $this->endSection() ?>

<?= $this->section('dashboard_content_top') ?>
    <!-- Flash Messages -->
    <?= $this->include('components/ui/flash-messages') ?>

    <!-- Role Filter Cards (replicated dashboard layout) -->
    <div class="mb-6">
        <div id="role-user-cards" class="grid grid-cols-2 md:grid-cols-5 gap-4" aria-live="polite"
             data-current-user-id="<?= (int) current_user_id() ?>"
             data-is-admin="<?= has_role('admin') ? 'true' : 'false' ?>"
             data-fallback-counts="<?= esc(json_encode([
                 'total'     => (int) ($stats['total'] ?? 0),
                 'admins'    => (int) ($stats['admins'] ?? 0),
                 'providers' => (int) ($stats['providers'] ?? 0),
                 'staff'     => (int) ($stats['staff'] ?? 0),
             ]), 'attr') ?>"></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <!-- Users Table (main container starts here) -->
    <div class="p-4 md:p-6 mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="mb-6">
            <div class="flex flex-col items-end space-y-2 text-right">
                <h2 id="users-dynamic-title" class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">Total Users</h2>
               
                <?php if (($canCreateAdmin ?? false) || ($canCreateProvider ?? false) || ($canCreateStaff ?? false)): ?>
                <?= view('components/button', [
                    'tag' => 'a',
                    'href' => base_url('user-management/create'),
                    'label' => 'Add New User',
                    'icon' => 'person_add',
                    'variant' => 'filled',
                ]) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="block overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400" id="usersTable">
                <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase border-b border-gray-200 dark:border-gray-600">
                    <tr>
                        <th class="px-6 py-4 font-semibold">User</th>
                        <th class="px-6 py-4 font-semibold">Role</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold hidden md:table-cell">Assignments</th>
                        <th class="px-6 py-4 font-semibold hidden md:table-cell">Created</th>
                        <th class="px-6 py-4 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                <?php if (!empty($users)): foreach ($users as $user): ?>
                    <?php $rowAvatar = avatar_data($user, 'U'); ?>
                    <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                            <div class="flex items-center">
                                <?php if (!empty($rowAvatar['image_url'])): ?>
                                    <img src="<?= esc($rowAvatar['image_url']) ?>"
                                         alt="<?= esc($rowAvatar['name'] ?: ($user['name'] ?? 'User')) ?>"
                                         class="w-10 h-10 rounded-full object-cover mr-3" />
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">
                                        <?= esc($rowAvatar['initials']) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="font-medium"><?= esc($user['name'] ?? 'Unknown') ?></div>
                                    <div class="text-gray-500 dark:text-gray-400 text-sm"><?= esc($user['email'] ?? '') ?></div>
                                    <?php if (!empty($user['phone'])): ?>
                                        <div class="text-gray-400 dark:text-gray-500 text-xs"><?= esc($user['phone']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php $displayRoles = get_user_display_roles($user); ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($displayRoles as $displayRole): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= get_role_badge_classes($displayRole) ?>">
                                        <?= esc(get_role_display_name($displayRole)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?= view('components/status_badge', [
                                'status' => ($user['is_active'] ?? true) ? 'active' : 'inactive',
                                'label' => ($user['is_active'] ?? true) ? 'Active' : 'Inactive',
                            ]) ?>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400 hidden md:table-cell">
                            <?php if (!empty($user['assignments'])): ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    $names = explode(', ', $user['assignments']);
                                    foreach ($names as $name): 
                                    ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            <?= esc($name) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-400 dark:text-gray-500 italic text-sm">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400 hidden md:table-cell">
                            <?= !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : '—' ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-2">
                                <a href="<?= base_url('user-management/edit/' . ($user['id'] ?? 0)) ?>" class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" title="Edit User">
                                    <span class="material-symbols-outlined">edit</span>
                                </a>
                                <?php if (($user['id'] ?? null) !== session()->get('user_id')): ?>
                                    <?php if ($user['is_active'] ?? true): ?>
                                        <form method="post" action="<?= base_url('user-management/deactivate/' . ($user['id'] ?? 0)) ?>" data-confirm-message="Deactivate this user?" class="inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="p-1 text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300" title="Deactivate User">
                                                <span class="material-symbols-outlined">person_cancel</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= base_url('user-management/activate/' . ($user['id'] ?? 0)) ?>" data-confirm-message="Activate this user?" class="inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="p-1 text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300" title="Activate User">
                                                <span class="material-symbols-outlined">person_add</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (has_role('admin')): ?>
                                        <button type="button"
                                                class="p-1 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                                title="Delete User"
                                                data-delete-user-id="<?= (int) ($user['id'] ?? 0) ?>"
                                                data-delete-user-name="<?= esc($user['name'] ?? 'Unknown', 'attr') ?>"
                                                data-delete-user-role="<?= esc($user['role'] ?? '', 'attr') ?>">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No users found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="users-error" class="hidden mt-4 text-sm text-red-600 dark:text-red-400"></div>
    </div>

    <div id="delete-user-modal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-black/50" data-delete-modal-close></div>
        <div class="relative mx-auto mt-12 w-full max-w-2xl rounded-lg bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Delete User</h3>
                <button type="button" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" data-delete-modal-close>
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4" id="delete-modal-content"></div>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 px-4 py-2 text-sm border border-outline text-on-surface hover:bg-surface-variant" data-delete-modal-close>
                    <span>Cancel</span>
                </button>
                <button type="button" id="delete-user-confirm-btn" class="inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 px-4 py-2 text-sm bg-error text-on-error hover:bg-error-600 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <span class="material-symbols-outlined text-base">delete</span>
                    <span>Delete User</span>
                </button>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
