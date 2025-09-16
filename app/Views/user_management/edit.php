<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Edit User<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Edit User" data-page-subtitle="Update user account details">

    <!-- Flash Messages -->
    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- User Form -->
        <div class="lg:col-span-2">
            <div class="p-4 md:p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand material-shadow">
                <div class="mb-6">
                    <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">User Information</h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm transition-colors duration-300">Edit details for this user account</p>
                </div>

                <form method="post" action="<?= base_url('user-management/update/' . ($user['id'] ?? '')) ?>" class="user-form space-y-6">
                <?= csrf_field() ?>

                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="<?= old('name', $user['name'] ?? '') ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= isset($validation) && $validation && $validation->hasError('name') ? 'border-red-500 dark:border-red-400' : '' ?>"
                               placeholder="Enter full name">
                        <?php if (isset($validation) && $validation && $validation->hasError('name')): ?>
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
                               value="<?= old('email', $user['email'] ?? '') ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= isset($validation) && $validation && $validation->hasError('email') ? 'border-red-500 dark:border-red-400' : '' ?>"
                               placeholder="Enter email address">
                        <?php if (isset($validation) && $validation && $validation->hasError('email')): ?>
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
                               value="<?= old('phone', $user['phone'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= isset($validation) && $validation && $validation->hasError('phone') ? 'border-red-500 dark:border-red-400' : '' ?>"
                               placeholder="Enter phone number">
                        <?php if (isset($validation) && $validation && $validation->hasError('phone')): ?>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('phone') ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($canChangeRole)): ?>
                    <div class="form-group">
                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                            User Role <span class="text-red-500">*</span>
                        </label>
                        <select id="role" 
                                name="role" 
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= isset($validation) && $validation && $validation->hasError('role') ? 'border-red-500 dark:border-red-400' : '' ?>">
                            <option value="">Select a role</option>
                            <?php foreach ($availableRoles as $roleOption): ?>
                                <option value="<?= $roleOption ?>" <?= old('role', $user['role'] ?? '') === $roleOption ? 'selected' : '' ?>>
                                    <?= ucfirst($roleOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($validation) && $validation && $validation->hasError('role')): ?>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('role') ?></p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="role" value="<?= esc($user['role'] ?? '') ?>">
                    <?php endif; ?>
                </div>

                <!-- Provider Selection (for Staff) -->
                <div id="provider-selection" class="form-group" style="display: none;">
                    <label for="provider_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                        Service Provider <span class="text-red-500">*</span>
                    </label>
                    <select id="provider_id" 
                            name="provider_id"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= isset($validation) && $validation && $validation->hasError('provider_id') ? 'border-red-500 dark:border-red-400' : '' ?>">
                        <option value="">Select Provider</option>
                        <?php foreach ($providers as $provider): ?>
                            <option value="<?= $provider['id'] ?>" <?= old('provider_id', $user['provider_id'] ?? '') == $provider['id'] ? 'selected' : '' ?>>
                                <?= esc($provider['name']) ?> (<?= ucfirst($provider['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($validation) && $validation && $validation->hasError('provider_id')): ?>
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('provider_id') ?></p>
                    <?php endif; ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Staff members must be assigned to a service provider</p>
                </div>

                <!-- Password Section -->
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4 transition-colors duration-300">Password</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Leave blank to keep the current password.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                                New Password
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="password" 
                                       name="password"
                                       minlength="8"
                                       class="w-full px-3 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= isset($validation) && $validation && $validation->hasError('password') ? 'border-red-500 dark:border-red-400' : '' ?>"
                                       placeholder="Enter new password">
                                <button type="button" 
                                        onclick="togglePassword(this, 'password')"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 z-10"
                                        aria-pressed="false"
                                        aria-label="Toggle password visibility">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                            <?php if (isset($validation) && $validation && $validation->hasError('password')): ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password') ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="password_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
                                Confirm New Password
                            </label>
                            <div class="relative">
                                <input type="password" 
                                       id="password_confirm" 
                                       name="password_confirm"
                                       minlength="8"
                                       class="w-full px-3 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= isset($validation) && $validation && $validation->hasError('password_confirm') ? 'border-red-500 dark:border-red-400' : '' ?>"
                                       placeholder="Confirm new password">
                                <button type="button" 
                                        onclick="togglePassword(this, 'password_confirm')"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 z-10"
                                        aria-pressed="false"
                                        aria-label="Toggle confirm password visibility">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                            </div>
                            <?php if (isset($validation) && $validation && $validation->hasError('password_confirm')): ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password_confirm') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <a href="<?= base_url('user-management') ?>" 
                       class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200 text-sm font-medium">
                        <span class="material-symbols-outlined mr-2">close</span>
                        Cancel
                    </a>
                    <button type="submit" 
                            class="inline-flex items-center justify-center px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200 text-sm font-medium">
                        <span class="material-symbols-outlined mr-2">save</span>
                        Save Changes
                    </button>
                </div>

                </form>
            </div>
        </div>

        <!-- Help Panel -->
        <div class="lg:col-span-1 space-y-6">
            <div class="p-4 md:p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand material-shadow">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 transition-colors duration-300">Tips</h3>
                <ul class="list-disc pl-5 text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <li>Leave password fields empty to keep the current password.</li>
                    <li>Only admins can change user roles.</li>
                    <li>Staff must be assigned to a Service Provider.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide provider selection based on role
function syncProviderVisibility() {
    const role = document.getElementById('role') ? document.getElementById('role').value : '<?= esc($user['role'] ?? '') ?>';
    const providerSelection = document.getElementById('provider-selection');
    if (!providerSelection) return;

    if (role === 'staff') {
        providerSelection.style.display = 'block';
        const pid = document.getElementById('provider_id');
        if (pid) pid.required = true;
    } else {
        providerSelection.style.display = 'none';
        const pid = document.getElementById('provider_id');
        if (pid) { pid.required = false; pid.value = ''; }
    }
}

if (document.getElementById('role')) {
    document.getElementById('role').addEventListener('change', syncProviderVisibility);
}

// Toggle password visibility (same helper as create.php pattern)
function togglePassword(button, fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    const icon = button && button.querySelector('.material-symbols-outlined');
    const isPassword = field.getAttribute('type') === 'password';
    field.setAttribute('type', isPassword ? 'text' : 'password');
    if (icon) icon.textContent = isPassword ? 'visibility_off' : 'visibility';
    if (button) button.setAttribute('aria-pressed', String(isPassword));
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    syncProviderVisibility();
});
</script>
<?= $this->endSection() ?>
