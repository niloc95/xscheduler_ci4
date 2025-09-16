<?= $this->extend('components/layout') ?><?= $this->extend('components/layout') ?>



<?= $this->section('sidebar') ?><?= $this->section('sidebar') ?>

    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>	<?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>

<?= $this->endSection() ?><?= $this->endSection() ?>



<?= $this->section('header_title') ?>Edit User<?= $this->endSection() ?><?= $this->section('header_title') ?>Edit User<?= $this->endSection() ?>



<?= $this->section('content') ?><?= $this->section('content') ?>

<div class="main-content" data-page-title="Edit User" data-page-subtitle="Update user account details"><?php /** @var array $user */ ?>

<div class="main-content" data-page-title="Edit User" data-page-subtitle="Update user account details">

    <!-- Flash Messages -->

    <?php if (session()->getFlashdata('error')): ?>	<?php if (session()->getFlashdata('error')): ?>

        <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">		<div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">

            <?= esc(session()->getFlashdata('error')) ?>			<?= esc(session()->getFlashdata('error')) ?>

        </div>		</div>

    <?php endif; ?>	<?php endif; ?>

    <?php if (session()->getFlashdata('success')): ?>

        <div class="mb-4 p-3 rounded-lg border border-green-300/60 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200">	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <?= esc(session()->getFlashdata('success')) ?>		<!-- Edit Form -->

        </div>		<div class="lg:col-span-2">

    <?php endif; ?>			<div class="p-4 md:p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand material-shadow">

				<div class="mb-6">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">					<h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">User Information</h2>

        <!-- Edit Form -->					<p class="text-gray-600 dark:text-gray-400 text-sm">Modify the details for this account</p>

        <div class="lg:col-span-2">				</div>

            <div class="p-4 md:p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand material-shadow">

                <div class="mb-6">				<form method="post" action="<?= base_url('user-management/update/' . $user['id']) ?>" class="user-form space-y-6">

                    <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200 transition-colors duration-300">User Information</h2>				<?= csrf_field() ?>

                    <p class="text-gray-600 dark:text-gray-400 text-sm transition-colors duration-300">Modify the details for this user account</p>

                </div>				<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

					<div class="form-group">

                <form method="post" action="<?= base_url('user-management/update/' . ($user['id'] ?? 0)) ?>" class="user-form space-y-6">						<label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full Name <span class="text-red-500">*</span></label>

                <?= csrf_field() ?>						<input type="text" id="name" name="name" value="<?= old('name', $user['name'] ?? '') ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('name') ? 'border-red-500 dark:border-red-400' : '' ?>" placeholder="Enter full name">

                <input type="hidden" name="_method" value="POST">						<?php if (isset($validation) && $validation->hasError('name')): ?><p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('name') ?></p><?php endif; ?>

					</div>

                <!-- Basic Information -->					<div class="form-group">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">						<label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address <span class="text-red-500">*</span></label>

                    <div class="form-group">						<input type="email" id="email" name="email" value="<?= old('email', $user['email'] ?? '') ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('email') ? 'border-red-500 dark:border-red-400' : '' ?>" placeholder="Enter email address">

                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">						<?php if (isset($validation) && $validation->hasError('email')): ?><p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('email') ?></p><?php endif; ?>

                            Full Name <span class="text-red-500">*</span>					</div>

                        </label>				</div>

                        <input type="text" 
<?php /** @var array $user */ ?>
<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Edit User<?= $this->endSection() ?>

<?php
    // Determine if current auth user (through controller-provided data) can change the target user's role.
    // Assumption: controller passes $availableRoles as an array of roles the current user may assign.
    $availableRoles = $availableRoles ?? [];
    $currentRole = $user['role'] ?? 'customer';
    $canChangeRole = !empty($availableRoles) && count($availableRoles) > 1; // more than current role available
    // Alternate rule: require that at least one role differs from current.
    $canChangeRole = $canChangeRole && (count(array_diff($availableRoles, [$currentRole])) > 0);
?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Edit User" data-page-subtitle="Update user account details">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Edit Form Column -->
        <div class="lg:col-span-2">
            <?php if (session()->getFlashdata('error')): ?>
                <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
                    <?= esc(session()->getFlashdata('error')) ?>
                </div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('success')): ?>
                <div class="mb-4 p-3 rounded-lg border border-green-300/60 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200">
                    <?= esc(session()->getFlashdata('success')) ?>
                </div>
            <?php endif; ?>

            <div class="p-4 md:p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand material-shadow">
                <div class="mb-6">
                    <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">User Information</h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">Modify the details for this account</p>
                </div>

                <form method="post" action="<?= base_url('user-management/update/' . ($user['id'] ?? 0)) ?>" class="user-form space-y-8">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="POST">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Name -->
                        <div class="form-group md:col-span-1">
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" id="name" name="name" value="<?= old('name', $user['name'] ?? '') ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('name') ? 'border-red-500 dark:border-red-400' : '' ?>"
                                   placeholder="Enter full name">
                            <?php if (isset($validation) && $validation->hasError('name')): ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('name') ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Email -->
                        <div class="form-group md:col-span-1">
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address <span class="text-red-500">*</span></label>
                            <input type="email" id="email" name="email" value="<?= old('email', $user['email'] ?? '') ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('email') ? 'border-red-500 dark:border-red-400' : '' ?>"
                                   placeholder="Enter email address">
                            <?php if (isset($validation) && $validation->hasError('email')): ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('email') ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Phone -->
                        <div class="form-group md:col-span-1">
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?= old('phone', $user['phone'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('phone') ? 'border-red-500 dark:border-red-400' : '' ?>"
                                   placeholder="Enter phone number">
                            <?php if (isset($validation) && $validation->hasError('phone')): ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('phone') ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Role -->
                        <div class="form-group md:col-span-1">
                            <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">User Role <?= $canChangeRole ? '<span class="text-red-500">*</span>' : '' ?></label>
                            <select id="role" name="role" <?= $canChangeRole ? '' : 'disabled' ?>
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('role') ? 'border-red-500 dark:border-red-400' : '' ?>">
                                <?php if (!empty($availableRoles)): ?>
                                    <?php foreach ($availableRoles as $roleOption): ?>
                                        <option value="<?= esc($roleOption) ?>" <?= old('role', $currentRole) === $roleOption ? 'selected' : '' ?>>
                                            <?= ucfirst($roleOption) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option selected><?= ucfirst($currentRole) ?></option>
                                <?php endif; ?>
                            </select>
                            <?php if (isset($validation) && $validation->hasError('role')): ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('role') ?></p>
                            <?php endif; ?>
                            <?php if (!$canChangeRole): ?>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">You don't have permission to change this role.</p>
                                <input type="hidden" name="role" value="<?= esc($currentRole) ?>">
                            <?php endif; ?>
                        </div>

                        <!-- Provider (conditional) -->
                        <div id="provider-selection" class="form-group md:col-span-1" style="display:none;">
                            <label for="provider_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Service Provider <span class="text-red-500">*</span></label>
                            <select id="provider_id" name="provider_id"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('provider_id') ? 'border-red-500 dark:border-red-400' : '' ?>">
                                <option value="">Select Provider</option>
                                <?php if (!empty($providers)): ?>
                                    <?php foreach ($providers as $provider): ?>
                                        <option value="<?= esc($provider['id']) ?>" <?= (string)old('provider_id', $user['provider_id'] ?? '') === (string)$provider['id'] ? 'selected' : '' ?>>
                                            <?= esc($provider['name']) ?> (<?= ucfirst($provider['role']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php if (isset($validation) && $validation->hasError('provider_id')): ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('provider_id') ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Required when role is Staff.</p>
                        </div>
                    </div>

                    <!-- Password Section -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Password (Optional)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label>
                                <div class="relative">
                                    <input type="password" id="password" name="password" minlength="8"
                                           class="w-full px-3 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('password') ? 'border-red-500 dark:border-red-400' : '' ?>"
                                           placeholder="Leave blank to keep current">
                                    <button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 z-10"><span class="material-symbols-outlined" id="password-icon">visibility</span></button>
                                </div>
                                <?php if (isset($validation) && $validation->hasError('password')): ?>
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password') ?></p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Minimum 8 characters required</p>
                                <?php else: ?>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Leave blank to keep current password</p>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="password_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm New Password</label>
                                <div class="relative">
                                    <input type="password" id="password_confirm" name="password_confirm" minlength="8"
                                           class="w-full px-3 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('password_confirm') ? 'border-red-500 dark:border-red-400' : '' ?>"
                                           placeholder="Confirm new password">
                                    <button type="button" onclick="togglePassword('password_confirm')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 z-10"><span class="material-symbols-outlined" id="password_confirm-icon">visibility</span></button>
                                </div>
                                <?php if (isset($validation) && $validation->hasError('password_confirm')): ?>
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password_confirm') ?></p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Must match the password above</p>
                                <?php else: ?>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Only required if setting a new password</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <a href="<?= base_url('user-management') ?>" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm font-medium transition-colors">
                            <span class="material-symbols-outlined mr-2">close</span>
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center justify-center px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">
                            <span class="material-symbols-outlined mr-2">save</span>
                            Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Side Column -->
        <div class="lg:col-span-1 space-y-6">
            <div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-brand material-shadow">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Account Overview</h3>
                <?php $role = $currentRole; ?>
                <?php $roleBadges = [
                    'admin' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                    'provider' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                    'staff' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                    'customer' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                ]; ?>
                <div class="flex items-center justify-between mb-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $roleBadges[$role] ?? $roleBadges['customer'] ?>"><?= ucfirst($role) ?></span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">User ID: <?= esc($user['id'] ?? '—') ?></span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    <?php $roleDescriptions = [
                        'admin' => 'Full system access including managing all users and settings.',
                        'provider' => 'Manage services, staff, and bookings for their business.',
                        'staff' => 'Manage bookings and schedules for assigned provider.',
                        'customer' => 'Make and manage own bookings.'
                    ]; echo $roleDescriptions[$role] ?? 'Standard user account.'; ?>
                </p>
                <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                    <div><span class="font-medium text-gray-700 dark:text-gray-300">Created:</span> <?= !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : '—' ?></div>
                    <div><span class="font-medium text-gray-700 dark:text-gray-300">Updated:</span> <?= !empty($user['updated_at']) ? date('M j, Y', strtotime($user['updated_at'])) : '—' ?></div>
                    <div><span class="font-medium text-gray-700 dark:text-gray-300">Status:</span> <span class="<?= ($user['is_active'] ?? true) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' ?>"><?= ($user['is_active'] ?? true) ? 'Active' : 'Inactive' ?></span></div>
                    <?php if (!empty($user['provider_id'])): ?>
                        <div><span class="font-medium text-gray-700 dark:text-gray-300">Provider ID:</span> <?= esc($user['provider_id']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-brand material-shadow">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">Tips</h3>
                <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <li>Leave password fields blank to keep the current password.</li>
                    <li>Only change role if necessary and authorized.</li>
                    <li>Assign a provider when setting role to Staff.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(id){
    const input=document.getElementById(id);
    const icon=document.getElementById(id+'-icon');
    if(!input||!icon) return;
    if(input.type==='password'){ input.type='text'; icon.textContent='visibility_off'; }
    else { input.type='password'; icon.textContent='visibility'; }
}

(function(){
    const roleSelect=document.getElementById('role');
    const providerSection=document.getElementById('provider-selection');
    const providerField=document.getElementById('provider_id');
    function updateProviderVisibility(){
        if(!roleSelect||!providerSection) return;
        const role=roleSelect.value;
        if(role==='staff'){
            providerSection.style.display='block';
            if(providerField) providerField.required=true;
        } else {
            providerSection.style.display='none';
            if(providerField) providerField.required=false;
        }
    }
    if(roleSelect){
        roleSelect.addEventListener('change', updateProviderVisibility);
        updateProviderVisibility();
    }
})();
</script>
<?= $this->endSection() ?>
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password_confirm') ?></p>
