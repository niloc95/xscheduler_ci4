<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>User Management<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="User Management" data-page-subtitle="Manage system users and their permissions">

    <!-- Flash Messages -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="mb-4 p-3 rounded-lg border border-green-300/60 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200">
            <?= esc(session()->getFlashdata('success')) ?>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Users Card -->
        <div class="p-6 text-white transition-colors duration-300 rounded-lg shadow-brand material-shadow" style="background-color: var(--md-sys-color-primary);">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="opacity-80 text-sm">Total Users</p>
                    <p class="text-3xl font-bold"><?= number_format($stats['total'] ?? 0) ?></p>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-white">group</span>
                </div>
            </div>
            <div class="flex items-center text-sm">
                <span class="opacity-80">All system users</span>
            </div>
        </div>

        <!-- Administrators Card -->
        <?php if (($stats['admins'] ?? 0) > 0): ?>
        <div class="p-6 text-white transition-colors duration-300 rounded-lg shadow-brand material-shadow" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="opacity-80 text-sm">Administrators</p>
                    <p class="text-3xl font-bold"><?= number_format($stats['admins'] ?? 0) ?></p>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-white">admin_panel_settings</span>
                </div>
            </div>
            <div class="flex items-center text-sm">
                <span class="opacity-80">Full system access</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Service Providers Card -->
        <?php if (($stats['providers'] ?? 0) > 0): ?>
        <div class="p-6 text-white transition-colors duration-300 rounded-lg shadow-brand material-shadow" style="background-color: var(--md-sys-color-secondary); color: var(--md-sys-color-on-surface);">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="opacity-80 text-sm">Service Providers</p>
                    <p class="text-3xl font-bold"><?= number_format($stats['providers'] ?? 0) ?></p>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-white">storefront</span>
                </div>
            </div>
            <div class="flex items-center text-sm">
                <span class="opacity-80">Business owners</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Staff Members Card -->
        <?php if (($stats['staff'] ?? 0) > 0): ?>
        <div class="p-6 text-white transition-colors duration-300 rounded-lg shadow-brand material-shadow" style="background-color: var(--md-sys-color-tertiary);">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="opacity-80 text-sm">Staff Members</p>
                    <p class="text-3xl font-bold"><?= number_format($stats['staff'] ?? 0) ?></p>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-white">groups</span>
                </div>
            </div>
            <div class="flex items-center text-sm">
                <span class="opacity-80">Team members</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Users Table -->
    <div class="p-4 md:p-6 mb-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand material-shadow">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
            <div>
                <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">System Users</h2>
                <p class="text-gray-600 dark:text-gray-400 text-sm transition-colors duration-300">Manage user accounts and permissions</p>
            </div>
            <?php if ($canCreateAdmin || $canCreateProvider || $canCreateStaff): ?>
            <a href="<?= base_url('user-management/create') ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-colors duration-200 w-full sm:w-auto justify-center">
                <span class="material-symbols-outlined">person_add</span>
                <span>Add New User</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400 transition-colors duration-300">
                <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase border-b border-gray-200 dark:border-gray-600 transition-colors duration-300">
                    <tr>
                        <th class="px-6 py-4 font-semibold">User</th>
                        <th class="px-6 py-4 font-semibold">Role</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold">Provider</th>
                        <th class="px-6 py-4 font-semibold">Created</th>
                        <th class="px-6 py-4 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-300">
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100 transition-colors duration-300">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-medium"><?= esc($user['name']) ?></div>
                                    <div class="text-gray-500 dark:text-gray-400 text-sm"><?= esc($user['email']) ?></div>
                                    <?php if ($user['phone']): ?>
                                        <div class="text-gray-400 dark:text-gray-500 text-xs"><?= esc($user['phone']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php
                            $badgeColors = [
                                'admin' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                'provider' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                'staff' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                'customer' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                            ];
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeColors[$user['role']] ?? $badgeColors['customer'] ?>">
                                <?= get_role_display_name($user['role']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= ($user['is_active'] ?? true) ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' ?>">
                                <?= ($user['is_active'] ?? true) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                            <?php if ($user['provider_id'] ?? false): ?>
                                <?php
                                $provider = array_filter($users, fn($u) => $u['id'] == $user['provider_id']);
                                $provider = reset($provider);
                                ?>
                                <?= $provider ? esc($provider['name']) : 'Unknown' ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-2">
                                <a href="<?= base_url('user-management/edit/' . $user['id']) ?>" class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">
                                    <span class="material-symbols-outlined">edit</span>
                                </a>
                                <?php if ($user['id'] !== session()->get('user_id')): ?>
                                    <?php if ($user['is_active'] ?? true): ?>
                                        <a href="<?= base_url('user-management/deactivate/' . $user['id']) ?>" 
                                           onclick="return confirm('Are you sure you want to deactivate this user?')"
                                           class="p-1 text-gray-600 dark:text-gray-400 hover:text-yellow-600 dark:hover:text-yellow-400 transition-colors duration-200">
                                            <span class="material-symbols-outlined">block</span>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= base_url('user-management/activate/' . $user['id']) ?>" 
                                           class="p-1 text-gray-600 dark:text-gray-400 hover:text-green-600 dark:hover:text-green-400 transition-colors duration-200">
                                            <span class="material-symbols-outlined">check_circle</span>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card Layout -->
        <div class="md:hidden space-y-4">
            <?php foreach ($users as $user): ?>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 transition-colors duration-300 shadow-brand">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100 transition-colors duration-300"><?= esc($user['name']) ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 transition-colors duration-300"><?= esc($user['email']) ?></p>
                        </div>
                    </div>
                    <div class="flex flex-col space-y-1 items-end">
                        <?php
                        $badgeColors = [
                            'admin' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                            'provider' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                            'staff' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            'customer' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                        ];
                        ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeColors[$user['role']] ?? $badgeColors['customer'] ?>">
                            <?= get_role_display_name($user['role']) ?>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= ($user['is_active'] ?? true) ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' ?>">
                            <?= ($user['is_active'] ?? true) ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 mb-3">
                    <span>Created: <?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                    <?php if ($user['provider_id'] ?? false): ?>
                        <?php
                        $provider = array_filter($users, fn($u) => $u['id'] == $user['provider_id']);
                        $provider = reset($provider);
                        ?>
                        <span>Provider: <?= $provider ? esc($provider['name']) : 'Unknown' ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex justify-end space-x-2">
                    <a href="<?= base_url('user-management/edit/' . $user['id']) ?>" class="p-2 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                        <span class="material-symbols-outlined">edit</span>
                    </a>
                    <?php if ($user['id'] !== session()->get('user_id')): ?>
                        <?php if ($user['is_active'] ?? true): ?>
                            <a href="<?= base_url('user-management/deactivate/' . $user['id']) ?>" 
                               onclick="return confirm('Are you sure you want to deactivate this user?')"
                               class="p-2 text-gray-600 dark:text-gray-400 hover:text-yellow-600 dark:hover:text-yellow-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                                <span class="material-symbols-outlined">block</span>
                            </a>
                        <?php else: ?>
                            <a href="<?= base_url('user-management/activate/' . $user['id']) ?>" 
                               class="p-2 text-gray-600 dark:text-gray-400 hover:text-green-600 dark:hover:text-green-400 transition-colors duration-200 bg-white dark:bg-gray-800 rounded-lg">
                                <span class="material-symbols-outlined">check_circle</span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 14px;
    font-weight: 600;
}

.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
</style>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        "responsive": true,
        "pageLength": 25,
        "order": [[5, "desc"]], // Order by created date
        "language": {
            "search": "Search users:",
            "lengthMenu": "Show _MENU_ users per page",
            "info": "Showing _START_ to _END_ of _TOTAL_ users",
            "infoEmpty": "No users found",
            "emptyTable": "No users available"
        }
    });
});
</script>
<?= $this->endSection() ?>
