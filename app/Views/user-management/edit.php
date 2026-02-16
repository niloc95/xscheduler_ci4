<?php
/**
 * User Management - Edit User View
 *
 * Form for updating existing system user accounts (Admin, Provider, Staff, Customer).
 * Allows editing of user details, role, status, and password reset.
 * 
 * Access: Admin role
 * Related: index.php (list users), create.php (new users)
 */
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
	<?= $this->include('components/unified-sidebar', ['current_page' => 'user-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Edit User<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Flash Messages -->
<?= $this->include('components/ui/flash-messages') ?>

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
		<!-- User Form -->
		<div class="lg:col-span-2">
			<div class="card card-spacious">
				<div class="card-header flex-col items-start gap-2">
					<h2 class="card-title text-xl">User Information</h2>
					<p class="card-subtitle">Update the details for this user account</p>
				</div>

				<form method="post" action="<?= base_url('user-management/update/' . ($user['id'] ?? 0)) ?>" class="user-form">
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
									   value="<?= esc(old('name', $user['name'] ?? '')) ?>"
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
									   value="<?= esc(old('email', $user['email'] ?? '')) ?>"
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
									   value="<?= esc(old('phone', $user['phone'] ?? '')) ?>"
									   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('phone') ? 'border-red-500 dark:border-red-400' : '' ?>"
									   placeholder="Enter phone number">
								<?php if ($validation && $validation->hasError('phone')): ?>
									<p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('phone') ?></p>
								<?php endif; ?>
							</div>

							<div class="form-group">
								<label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
									Role <span class="text-red-500">*</span>
								</label>
								<select id="role" 
										name="role"
										required
										class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('role') ? 'border-red-500 dark:border-red-400' : '' ?>">
									<option value="">Select Role</option>
									<?php 
									$currentRole = old('role', $user['role'] ?? '');
									$availableRoles = $availableRoles ?? ['admin', 'provider', 'staff', 'customer'];
									foreach ($availableRoles as $roleOption): 
									?>
										<option value="<?= esc($roleOption) ?>" <?= $currentRole === $roleOption ? 'selected' : '' ?>>
											<?= ucfirst($roleOption) ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ($validation && $validation->hasError('role')): ?>
									<p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('role') ?></p>
								<?php endif; ?>
							</div>
						</div>

						<!-- Provider Color Picker (Admin Only) -->
						<?php 
						$currentRole = old('role', $user['role'] ?? '');
						$isProvider = ($currentRole === 'provider');
						$canEditColor = ($currentUser['role'] ?? '') === 'admin';
						?>
						<?php if ($isProvider && $canEditColor): ?>
						<div class="form-group provider-color-field">
							<label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
								Calendar Color
							</label>
							<div class="flex items-center gap-3">
								<input type="color" 
									   id="color" 
									   name="color" 
									   value="<?= esc(old('color', $user['color'] ?? '#3B82F6')) ?>"
									   class="h-10 w-20 rounded cursor-pointer border border-gray-300 dark:border-gray-600 transition-colors duration-300"
									   title="Choose provider color for calendar display">
								<span class="text-sm text-gray-600 dark:text-gray-400">
									This color will be used to display <?= esc($user['first_name'] ?? 'this provider') ?>'s appointments on the calendar.
								</span>
							</div>
						</div>
						<?php elseif ($isProvider): ?>
						<div class="form-group provider-color-field">
							<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
								Calendar Color
							</label>
							<div class="flex items-center gap-3">
							<div class="h-10 w-20 rounded border border-gray-300 dark:border-gray-600 provider-color-preview" 
								 data-color="<?= esc($user['color'] ?? '#3B82F6') ?>"
									 title="Provider calendar color"></div>
								<span class="text-sm text-gray-600 dark:text-gray-400">
									Calendar color (only admins can change this)
								</span>
							</div>
						</div>
						<?php endif; ?>


					<div id="providerScheduleSection" class="<?= old('role', $user['role'] ?? '') === 'provider' ? '' : 'hidden' ?>">
						<?= $this->include('user-management/components/provider-schedule') ?>
					</div>

					<!-- Provider Locations Section -->
					<div id="providerLocationsWrapper" class="<?= old('role', $user['role'] ?? '') === 'provider' ? '' : 'hidden' ?>">
						<?php if (($user['role'] ?? '') === 'provider'): ?>
							<?= $this->include('user-management/components/provider-locations', [
								'providerId' => $user['id'] ?? null,
								'locations' => $providerLocations ?? [],
							]) ?>
						<?php endif; ?>
					</div>

						<?php if (($user['role'] ?? '') === 'provider'): ?>
							<?= $this->include('user-management/components/provider-staff', [
								'assignedStaff' => $assignedStaff ?? [],
								'availableStaff' => $availableStaff ?? [],
								'canManageAssignments' => $canManageAssignments ?? false,
								'providerId' => $user['id'] ?? null,
							]) ?>
						<?php elseif (($user['role'] ?? '') === 'staff'): ?>
							<?= $this->include('user-management/components/staff-providers', [
								'assignedProviders' => $assignedProviders ?? [],
								'availableProviders' => $availableProviders ?? [],
								'canManageAssignments' => $canManageAssignments ?? false,
								'staffId' => $user['id'] ?? $staffId ?? null,
							]) ?>
						<?php endif; ?>

						<!-- Status -->
						<div class="form-group">
							<label class="flex items-center space-x-3 cursor-pointer">
								<input type="checkbox" 
									   name="is_active" 
									   value="1" 
									   <?= old('is_active', $user['is_active'] ?? true) ? 'checked' : '' ?>
									   class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
								<span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active User</span>
							</label>
							<p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Inactive users cannot log in to the system</p>
						</div>

						<!-- Password Reset Section -->
						<div class="border-t border-gray-200 dark:border-gray-700 pt-6">
							<h3 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4 transition-colors duration-300">Password Management</h3>
							
							<div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg mb-4">
								<p class="text-sm text-blue-800 dark:text-blue-200">
									<strong>Note:</strong> Leave password fields empty to keep the current password. Only fill them if you want to change the password.
								</p>
							</div>

							<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
								<div class="form-group">
									<label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
										New Password
									</label>
									<div class="relative">
										<input type="password" 
											   id="password" 
											   name="password" 
											   value="<?= old('password') ?>"
											   class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('password') ? 'border-red-500 dark:border-red-400' : '' ?>"
											   placeholder="Enter new password">
										<button type="button" 
												onclick="togglePassword('password')"
												class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
											<span class="material-symbols-outlined" id="password-icon">visibility</span>
										</button>
									</div>
									<?php if ($validation && $validation->hasError('password')): ?>
										<p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password') ?></p>
									<?php endif; ?>
									<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Minimum 8 characters</p>
								</div>

								<div class="form-group">
									<label for="password_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">
										Confirm New Password
									</label>
									<div class="relative">
										<input type="password" 
											   id="password_confirm" 
											   name="password_confirm" 
											   value="<?= old('password_confirm') ?>"
											   class="w-full px-3 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('password_confirm') ? 'border-red-500 dark:border-red-400' : '' ?>"
											   placeholder="Confirm new password">
										<button type="button" 
												onclick="togglePassword('password_confirm')"
												class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
											<span class="material-symbols-outlined" id="password_confirm-icon">visibility</span>
										</button>
									</div>
									<?php if ($validation && $validation->hasError('password_confirm')): ?>
										<p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password_confirm') ?></p>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<!-- Form Actions -->
					</div>

					<div class="card-footer flex flex-col gap-3 sm:flex-row sm:justify-end">
						<a href="<?= base_url('user-management') ?>" 
						   class="btn btn-secondary">
							<span class="material-symbols-outlined mr-2">cancel</span>
							Cancel
						</a>
						<button type="submit" 
								class="btn btn-primary">
							<span class="material-symbols-outlined mr-2">save</span>
							Update User
						</button>
					</div>

				</form>
			</div>
		</div>

		<!-- Help Panel -->
		<div class="lg:col-span-1 space-y-6">
			<!-- Current User Info -->
			<div class="card card-spacious">
				<div class="card-header">
					<h3 class="card-title">Current User Info</h3>
				</div>
				
				<div class="card-body space-y-3">
					<div class="flex justify-between items-center">
						<span class="text-sm text-gray-600 dark:text-gray-400">User ID</span>
						<span class="font-medium text-gray-800 dark:text-gray-200">#<?= esc($user['id'] ?? 'N/A') ?></span>
					</div>
					<div class="flex justify-between items-center">
						<span class="text-sm text-gray-600 dark:text-gray-400">Created</span>
						<span class="font-medium text-gray-800 dark:text-gray-200">
							<?= !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : 'N/A' ?>
						</span>
					</div>
					<div class="flex justify-between items-center">
						<span class="text-sm text-gray-600 dark:text-gray-400">Last Updated</span>
						<span class="font-medium text-gray-800 dark:text-gray-200">
							<?= !empty($user['updated_at']) ? date('M j, Y', strtotime($user['updated_at'])) : 'Never' ?>
						</span>
					</div>
				</div>
			</div>

			<!-- Password Requirements -->
			<div class="card card-spacious">
				<div class="card-header">
					<h3 class="card-title">Password Requirements</h3>
				</div>
				
				<div class="card-body">
					<ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
						<li>• Minimum 8 characters</li>
						<li>• Mix of letters and numbers recommended</li>
						<li>• Leave blank to keep current password</li>
						<li>• Both fields must match</li>
					</ul>
				</div>
			</div>

			<!-- Role Guide -->
			<div class="card card-spacious">
				<div class="card-header">
					<h3 class="card-title">Role Permissions</h3>
				</div>
				
				<div class="card-body space-y-3">
					<div class="text-sm">
						<strong class="text-red-700 dark:text-red-300">Admin:</strong>
						<p class="text-gray-600 dark:text-gray-400">Full system access</p>
					</div>
					<div class="text-sm">
						<strong class="text-blue-700 dark:text-blue-300">Provider:</strong>
						<p class="text-gray-600 dark:text-gray-400">Manage own services and staff</p>
					</div>
					<div class="text-sm">
						<strong class="text-green-700 dark:text-green-300">Staff:</strong>
						<p class="text-gray-600 dark:text-gray-400">Limited to providers they assist</p>
					</div>
					<div class="text-sm">
						<strong class="text-gray-700 dark:text-gray-300">Customer:</strong>
						<p class="text-gray-600 dark:text-gray-400">Book and manage own appointments</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
// Provider fields visibility — runs immediately (SPA-compatible)
(function() {
	const roleSelect = document.getElementById('role');
	const scheduleWrapper = document.getElementById('providerScheduleSection');
	const locationsWrapper = document.getElementById('providerLocationsWrapper');
	const colorFields = document.querySelectorAll('.provider-color-field');
	if (!roleSelect) return;

	function toggleProviderFields() {
		const isProvider = roleSelect.value === 'provider';
		if (scheduleWrapper) scheduleWrapper.classList.toggle('hidden', !isProvider);
		if (locationsWrapper) locationsWrapper.classList.toggle('hidden', !isProvider);
		colorFields.forEach(field => {
			field.classList.toggle('hidden', !isProvider);
		});
	}

	roleSelect.addEventListener('change', toggleProviderFields);
	toggleProviderFields();
})();

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
</script>
<?= $this->endSection() ?>
