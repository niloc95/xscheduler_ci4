<?php
/**
 * User Management - Create User View
 *
 * Form for creating new system users (Admin, Provider, Staff, Customer roles).
 * Handles role assignment, password setup, and provider association for staff.
 * 
 * Access: Admin role only
 * Related: index.php (list users), edit.php (update users)
 */
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Create User<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Flash Messages -->
<?= $this->include('components/ui/flash-messages') ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- User Form -->
        <div class="lg:col-span-2">
            <div class="card card-spacious">
                <div class="card-header flex-col items-start gap-2">
                    <h2 class="card-title text-xl">User Information</h2>
                    <p class="card-subtitle">Enter the details for the new user account</p>
                </div>

                <form method="post" action="<?= base_url('user-management/store') ?>" class="user-form" id="createUserForm">
                <?= csrf_field() ?>

                <div class="card-body space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="<?= old('name') ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('name') ? 'border-red-500 dark:border-red-400' : '' ?>"
                               placeholder="Enter full name">
                        <?php if ($validation && $validation->hasError('name')): ?>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('name') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?= old('email') ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('email') ? 'border-red-500 dark:border-red-400' : '' ?>"
                               placeholder="Enter email address">
                        <?php if ($validation && $validation->hasError('email')): ?>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('email') ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                            Phone Number
                        </label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               value="<?= old('phone') ?>"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('phone') ? 'border-red-500 dark:border-red-400' : '' ?>"
                               placeholder="Enter phone number">
                        <?php if ($validation && $validation->hasError('phone')): ?>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('phone') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                            User Role <span class="text-red-500">*</span>
                        </label>
                        <select id="role" 
                                name="role" 
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('role') ? 'border-red-500 dark:border-red-400' : '' ?>">
                            <option value="">Select a role</option>
                            <?php foreach ($availableRoles as $roleOption): ?>
                                <option value="<?= esc($roleOption) ?>" <?= old('role') === $roleOption ? 'selected' : '' ?>>
                                    <?= ucfirst($roleOption) ?>
                                    <?php if ($roleOption === 'admin'): ?>
                                        - Full System Access
                                    <?php elseif ($roleOption === 'provider'): ?>
                                        - Can Manage Services & Staff
                                    <?php elseif ($roleOption === 'staff'): ?>
                                        - Limited Calendar Access
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($validation && $validation->hasError('role')): ?>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('role') ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Provider Color Picker (Optional) -->
                    <div class="form-group provider-color-field hidden">
                        <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                            Calendar Color <span class="text-gray-500 text-xs">(Optional)</span>
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="color" 
                                   id="color" 
                                   name="color" 
                                   value="<?= old('color', '#3B82F6') ?>"
                                   class="h-10 w-20 rounded cursor-pointer border border-gray-300 dark:border-gray-600 transition-colors duration-300"
                                   title="Choose provider color for calendar display">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                Leave default or choose a custom color. A unique color will be auto-assigned if not specified.
                            </span>
                        </div>
                    </div>
                </div>

                <div id="providerScheduleSection" class="<?= old('role') === 'provider' ? '' : 'hidden' ?>">
                    <?= $this->include('user-management/components/provider-schedule') ?>
                </div>

                <div id="providerAssignmentsSection" class="<?= old('role') === 'provider' ? '' : 'hidden' ?>">
                        <?php if (old('role') === 'provider'): ?>
                            <div class="rounded-lg border border-yellow-300 bg-yellow-50 dark:border-yellow-600 dark:bg-yellow-900/30 p-4 text-sm text-yellow-800 dark:text-yellow-100">
                                <div class="font-medium mb-1">Staff assignments locked</div>
                                <p>Save this provider first, then return to assign staff members and manage access.</p>
                            </div>
                        <?php endif; ?>
                </div>

                <div id="staffAssignmentsSection" class="<?= old('role') === 'staff' ? '' : 'hidden' ?>">
                    <?= $this->include('user-management/components/staff-providers', [
                        'assignedProviders' => $assignedProviders ?? [],
                        'availableProviders' => $providers ?? [],
                        'canManageAssignments' => ($currentUser['role'] ?? '') === 'admin',
                        'staffId' => null,
                    ]) ?>
                </div>

                <!-- Role Description -->
                <div id="role-description" class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg hidden">
                    <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">Role Permissions</h4>
                    <div id="role-permissions" class="text-sm text-blue-700 dark:text-blue-300"></div>
                </div>

                <!-- Password Section -->
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4 transition-colors duration-300">Password Setup</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       required
                                       minlength="8"
                                       class="w-full px-3 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('password') ? 'border-red-500 dark:border-red-400' : '' ?>"
                                       placeholder="Enter password">
                                <button type="button" 
                                        onclick="togglePassword('password')"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 z-10">
                                    <span class="material-symbols-outlined" id="password-icon">visibility</span>
                                </button>
                            </div>
                            <?php if ($validation && $validation->hasError('password')): ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password') ?></p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Minimum 8 characters required</p>
                            <?php else: ?>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Minimum 8 characters required</p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="password_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                                Confirm Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="password_confirm" 
                                       name="password_confirm" 
                                       required
                                       minlength="8"
                                       class="w-full px-3 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('password_confirm') ? 'border-red-500 dark:border-red-400' : '' ?>"
                                       placeholder="Confirm password">
                                <button type="button" 
                                        onclick="togglePassword('password_confirm')"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 z-10">
                                    <span class="material-symbols-outlined" id="password_confirm-icon">visibility</span>
                                </button>
                            </div>
                            <?php if ($validation && $validation->hasError('password_confirm')): ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password_confirm') ?></p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Must match the password above</p>
                            <?php else: ?>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Must match the password above</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                </div>

                <div class="card-footer flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <a href="<?= base_url('user-management') ?>" 
                       class="btn btn-secondary">
                        <span class="material-symbols-outlined mr-2">close</span>
                        Cancel
                    </a>
                    <button type="submit" 
                            class="btn btn-primary">
                        <span class="material-symbols-outlined mr-2">save</span>
                        Create User
                    </button>
                </div>

                </form>
            </div>
        </div>

        <!-- Help Panel -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Role Guide -->
            <div class="card card-spacious">
                <div class="card-header">
                    <h3 class="card-title">User Roles Guide</h3>
                </div>
                
                <div class="card-body space-y-4">
                    <?php foreach ($availableRoles as $roleOption): ?>
                        <?php
                        $roleColors = [
                            'admin' => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200',
                            'provider' => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200',
                            'staff' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200'
                        ];
                        $roleDescriptions = [
                            'admin' => 'Full system access, can manage all users, settings, and data. Use with caution.',
                            'provider' => 'Business owner who can manage their own services, staff, and customer bookings.',
                            'staff' => 'Employee who can manage schedules and bookings for their assigned provider.'
                        ];
                        ?>
                        <div class="p-3 border rounded-lg <?= $roleColors[$roleOption] ?? 'bg-gray-50 dark:bg-gray-900/20 border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200' ?>">
                            <div class="flex items-center mb-2">
                                <div class="w-4 h-4 <?= $roleOption === 'admin' ? 'bg-red-500' : ($roleOption === 'provider' ? 'bg-blue-500' : ($roleOption === 'staff' ? 'bg-green-500' : 'bg-gray-500')) ?> rounded mr-2"></div>
                                <h4 class="font-medium"><?= ucfirst($roleOption) ?></h4>
                            </div>
                            <p class="text-sm">
                                <?= $roleDescriptions[$roleOption] ?? 'Standard user with basic access.' ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                    <h4 class="font-medium text-gray-800 dark:text-gray-200 mb-2">Password Requirements</h4>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <li>• Minimum 8 characters</li>
                        <li>• Mix of letters and numbers recommended</li>
                        <li>• User will be prompted to change on first login</li>
                    </ul>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card card-spacious">
                <div class="card-header">
                    <h3 class="card-title">Current System Stats</h3>
                </div>
                
                <div class="card-body space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Total Users</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200"><?= number_format($stats['total'] ?? 0) ?></span>
                    </div>
                    <?php if (($stats['admins'] ?? 0) > 0): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Administrators</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200"><?= number_format($stats['admins']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (($stats['providers'] ?? 0) > 0): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Service Providers</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200"><?= number_format($stats['providers']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (($stats['staff'] ?? 0) > 0): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Staff Members</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200"><?= number_format($stats['staff']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (($stats['customers'] ?? 0) > 0): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Customers</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200"><?= number_format($stats['customers']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const roleSelect = document.getElementById('role');
const roleDescription = document.getElementById('role-description');
const rolePermissions = document.getElementById('role-permissions');
const providerScheduleSection = document.getElementById('providerScheduleSection');
const providerAssignmentsSection = document.getElementById('providerAssignmentsSection');
const staffAssignmentsSection = document.getElementById('staffAssignmentsSection');

function toggleRoleDetails() {
    if (!roleSelect) return;
    const role = roleSelect.value;

    if (role) {
        const descriptions = {
            'admin': 'Full system access including settings, user management, and all features.',
            'provider': 'Can manage own calendar, create staff, manage services and categories.',
            'staff': 'Limited to managing own calendar and assigned appointments. Provider assignments managed after creation.'
        };

        if (rolePermissions) {
            rolePermissions.innerHTML = descriptions[role] || '';
        }
        if (roleDescription) {
            roleDescription.classList.remove('hidden');
        }
    } else if (roleDescription) {
        roleDescription.classList.add('hidden');
    }

    if (providerScheduleSection) {
        providerScheduleSection.classList.toggle('hidden', role !== 'provider');
    }

    if (providerAssignmentsSection) {
        providerAssignmentsSection.classList.toggle('hidden', role !== 'provider');
    }

    if (staffAssignmentsSection) {
        const isStaff = role === 'staff';
        staffAssignmentsSection.classList.toggle('hidden', !isStaff);
    }

    // Toggle provider color field
    const colorFields = document.querySelectorAll('.provider-color-field');
    colorFields.forEach(field => {
        field.classList.toggle('hidden', role !== 'provider');
    });
}

if (roleSelect) {
    roleSelect.addEventListener('change', toggleRoleDetails);
}

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        field.type = 'password';
        icon.textContent = 'visibility';
    }
}

// Initialize form immediately (SPA-compatible — DOMContentLoaded won't re-fire)
if (roleSelect) {
    toggleRoleDetails();
}
</script>
<?= $this->endSection() ?>
