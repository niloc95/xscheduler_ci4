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

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
		<!-- User Form -->
		<div class="lg:col-span-2">
			<div class="card card-spacious">
				<div class="card-header flex-col items-start gap-2">
					<h2 class="card-title text-xl">User Information</h2>
					<p class="card-subtitle">Update the details for this user account</p>
				</div>

				<form method="post" action="<?= base_url('user-management/update/' . ($user['id'] ?? 0)) ?>" class="user-form" id="editUserForm" data-no-spa="true" enctype="multipart/form-data">
					<?= csrf_field() ?>

					<div class="card-body space-y-6">
						<!-- Basic Information -->
						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div class="form-group">
								<label for="name" class="form-label required">Full Name</label>
								<input type="text" 
									   id="name" 
									   name="name" 
									   value="<?= esc(old('name', $user['name'] ?? '')) ?>"
									   required
									   class="form-input <?= $validation && $validation->hasError('name') ? 'border-red-500 dark:border-red-400' : '' ?>"
									   placeholder="Enter full name">
								<?php if ($validation && $validation->hasError('name')): ?>
									<p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('name') ?></p>
								<?php endif; ?>
							</div>

							<div class="form-group">
								<label for="email" class="form-label required">Email Address</label>
								<input type="email" 
									   id="email" 
									   name="email" 
									   value="<?= esc(old('email', $user['email'] ?? '')) ?>"
									   required
									   class="form-input <?= $validation && $validation->hasError('email') ? 'border-red-500 dark:border-red-400' : '' ?>"
									   placeholder="Enter email address">
								<?php if ($validation && $validation->hasError('email')): ?>
									<p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('email') ?></p>
								<?php endif; ?>
							</div>
						</div>

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div class="form-group">
								<label for="phone" class="form-label">Phone Number</label>
								<input type="tel" 
									   id="phone" 
									   name="phone" 
									   value="<?= esc(old('phone', $user['phone'] ?? '')) ?>"
									   class="form-input <?= $validation && $validation->hasError('phone') ? 'border-red-500 dark:border-red-400' : '' ?>"
									   placeholder="Enter phone number">
								<?php if ($validation && $validation->hasError('phone')): ?>
									<p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('phone') ?></p>
								<?php endif; ?>
							</div>

							<div class="form-group">
								<label class="form-label required">Roles</label>
								<?php 
								$selectedRoles = old('roles') ?? ($user['roles'] ?? []);
								if (is_string($selectedRoles)) {
									$selectedRoles = [$selectedRoles]; // Handle legacy single role
								}
								$selfAdminLocked = ($user['id'] ?? 0) === (session('user_id') ?? 0) && in_array('admin', (array) $selectedRoles, true);
								?>
								<?php if ($selfAdminLocked): ?>
									<input type="hidden" name="roles[]" value="admin">
								<?php endif; ?>
								<div class="space-y-3 p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900/30 <?= $validation && $validation->hasError('roles') ? 'border-red-500 dark:border-red-400' : '' ?>">
									<?php foreach (['admin', 'provider', 'staff'] as $roleOption): ?>
										<div class="flex items-center gap-3">
											<input type="checkbox" 
												   id="role_<?= $roleOption ?>" 
												   name="roles[]" 
												   value="<?= $roleOption ?>"
												   <?= in_array($roleOption, (array) $selectedRoles) ? 'checked' : '' ?>
												   class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 cursor-pointer shrink-0"
												   <?= $roleOption === 'admin' && ($user['id'] ?? 0) === (session('user_id') ?? 0) ? 'disabled title="You cannot remove your own admin role"' : '' ?>>
											<label for="role_<?= $roleOption ?>" class="cursor-pointer flex-1">
												<div class="font-medium text-gray-900 dark:text-gray-100 text-sm"><?= ucfirst($roleOption) ?></div>
												<div class="text-xs text-gray-600 dark:text-gray-400">
													<?php if ($roleOption === 'admin'): ?>
														Full System Access
													<?php elseif ($roleOption === 'provider'): ?>
														Can Manage Services & Staff
													<?php elseif ($roleOption === 'staff'): ?>
														Limited Calendar Access
													<?php endif; ?>
												</div>
											</label>
										</div>
									<?php endforeach; ?>
								</div>
								<?php if ($validation && $validation->hasError('roles')): ?>
									<p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('roles') ?></p>
								<?php endif; ?>
							</div>
						</div>

						<!-- Provider Color Picker (Admin Only) -->
						<?php 
						$selectedRoles = old('roles') ?? ($user['roles'] ?? []);
						if (is_string($selectedRoles)) {
							$selectedRoles = [$selectedRoles];
						}
						$isProvider = in_array('provider', (array) $selectedRoles, true);
						$canEditColor = ($currentUser['role'] ?? '') === 'admin';
						helper('app');
						$currentProfileImage = old('profile_image', $user['profile_image'] ?? null);
						$currentProfileImageUrl = function_exists('provider_image_url')
							? provider_image_url($currentProfileImage)
							: (!empty($currentProfileImage) ? base_url((string) $currentProfileImage) : null);
						?>
						<?php if ($isProvider && $canEditColor): ?>
						<div class="form-group provider-color-field">
							<label for="color" class="form-label">Calendar Color</label>
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
							<label class="form-label">Calendar Color</label>
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

					<!-- Provider Public Profile -->
					<div id="providerPublicProfileSection" class="<?= in_array('provider', (array) $selectedRoles) ? '' : 'hidden' ?>">
						<div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-4">
							<div>
								<h3 class="text-base font-semibold text-slate-800 dark:text-slate-200">Public Provider Profile</h3>
								<p class="text-sm text-slate-500 dark:text-slate-400">These details are exposed on public booking pages when present.</p>
							</div>
							<div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
								<div class="form-group">
									<label class="form-label">Profile Image</label>
									<div class="flex items-start gap-4">
										<div id="providerProfileImagePreviewWrap" class="h-20 w-20 rounded-full overflow-hidden border border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-300 text-xs">
											<?php if (!empty($currentProfileImageUrl)): ?>
												<img id="providerProfileImagePreview" src="<?= esc($currentProfileImageUrl) ?>" alt="Provider profile image" class="h-full w-full object-cover">
											<?php else: ?>
												<span id="providerProfileImageFallback">No image</span>
												<img id="providerProfileImagePreview" src="" alt="Provider profile image" class="hidden h-full w-full object-cover">
											<?php endif; ?>
										</div>
										<div class="flex-1 space-y-2">
											<input type="file"
												   id="profile_picture"
												   name="profile_picture"
												   accept="image/png,image/jpeg,image/webp,image/gif"
												   class="form-input"
												   data-provider-image-input>
											<label class="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300 cursor-pointer">
												<input type="checkbox" name="remove_profile_image" value="1" data-provider-image-remove class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
												Remove current image
											</label>
											<p class="text-xs text-slate-500 dark:text-slate-400">PNG, JPG, WEBP, or GIF up to 2MB. Images are resized for fast booking cards.</p>
										</div>
									</div>
								</div>
							</div>
							<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
								<div class="form-group">
									<label for="title" class="form-label">Professional Title</label>
									<input type="text"
										   id="title"
										   name="title"
										   value="<?= esc(old('title', $user['title'] ?? '')) ?>"
										   class="form-input"
										   placeholder="e.g., Dr, Dentist, GP">
								</div>
								<div class="form-group">
									<label for="slug" class="form-label">Public URL Slug</label>
									<input type="text"
										   id="slug"
										   name="slug"
										   value="<?= esc(old('slug', $user['slug'] ?? '')) ?>"
										   class="form-input"
										   <?= !empty($user['slug']) ? 'readonly' : '' ?>
										   placeholder="Auto-generated from name if blank">
									<?php if (!empty($user['slug'])): ?>
										<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Slug is locked after first save to preserve SEO URLs.</p>
									<?php endif; ?>
								</div>
							</div>
							<div class="form-group">
								<label for="bio" class="form-label">Background / History</label>
								<textarea id="bio" name="bio" rows="3" class="form-input" placeholder="Short background shown to booking customers."><?= esc(old('bio', $user['bio'] ?? '')) ?></textarea>
							</div>
							<div class="form-group">
								<label for="education" class="form-label">Education</label>
								<textarea id="education" name="education" rows="2" class="form-input" placeholder="Degrees, institutions, certifications."><?= esc(old('education', $user['education'] ?? '')) ?></textarea>
							</div>
							<div class="form-group">
								<label for="qualifications" class="form-label">Qualifications</label>
								<textarea id="qualifications" name="qualifications" rows="2" class="form-input" placeholder="Professional registrations and qualifications."><?= esc(old('qualifications', $user['qualifications'] ?? '')) ?></textarea>
							</div>
						</div>
					</div>


					<!-- Provider Locations Section (FIRST — configure locations before schedule) -->
					<?php $selectedRoles = old('roles') ?? ($user['roles'] ?? []); if (is_string($selectedRoles)) { $selectedRoles = [$selectedRoles]; } ?>
					<div id="providerLocationsWrapper" class="<?= in_array('provider', (array) $selectedRoles) ? '' : 'hidden' ?>">
						<?php if (in_array('provider', (array) $selectedRoles)): ?>
						<?= $this->include('user-management/components/provider-locations') ?>
						<?php endif; ?>
					</div>

					<!-- Provider Schedule Section (SECOND — uses locations for day assignment) -->
					<?php $selectedRoles = old('roles') ?? ($user['roles'] ?? []); if (is_string($selectedRoles)) { $selectedRoles = [$selectedRoles]; } ?>
					<div id="providerScheduleSection" class="<?= in_array('provider', (array) $selectedRoles) ? '' : 'hidden' ?>">
						<?= $this->include('user-management/components/provider-schedule') ?>
					</div>

						<?php if (in_array('provider', (array) $selectedRoles)): ?>
							<?= $this->include('user-management/components/provider-staff', [
								'assignedStaff' => $assignedStaff ?? [],
								'availableStaff' => $availableStaff ?? [],
								'canManageAssignments' => $canManageAssignments ?? false,
								'providerId' => $user['id'] ?? null,
							]) ?>
						<?php elseif (in_array('staff', (array) $selectedRoles)): ?>
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
												<!-- Appointment Notifications (provider/staff only) -->
												<?php
												$_editRoles = old('roles') ?? ($user['roles'] ?? []);
												if (is_string($_editRoles)) { $_editRoles = [$_editRoles]; }
												if (array_intersect(['provider', 'staff'], (array) $_editRoles)):
												?>
												<div class="form-group">
													<label class="flex items-center space-x-3 cursor-pointer">
														<input type="hidden" name="notify_on_appointments" value="0">
														<input type="checkbox"
															   id="notify_on_appointments"
															   name="notify_on_appointments"
															   value="1"
															   <?= old('notify_on_appointments', $user['notify_on_appointments'] ?? 1) ? 'checked' : '' ?>
															   class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
														<span class="text-sm font-medium text-gray-700 dark:text-gray-300">Receive appointment notifications</span>
													</label>
													<p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Send this user an email when appointments are booked, rescheduled, or cancelled</p>
												</div>
												<?php endif; ?>

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
									<label for="password" class="form-label">New Password</label>
									<div class="relative">
										<input type="password" 
											   id="password" 
											   name="password" 
											   value="<?= old('password') ?>"
											   class="form-input pr-10 <?= $validation && $validation->hasError('password') ? 'border-red-500 dark:border-red-400' : '' ?>"
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
									<label for="password_confirm" class="form-label">Confirm New Password</label>
									<div class="relative">
										<input type="password" 
											   id="password_confirm" 
											   name="password_confirm" 
											   value="<?= old('password_confirm') ?>"
											   class="form-input pr-10 <?= $validation && $validation->hasError('password_confirm') ? 'border-red-500 dark:border-red-400' : '' ?>"
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
						<?= view('components/button', [
							'tag' => 'a',
							'label' => 'Cancel',
							'href' => base_url('user-management'),
							'variant' => 'outlined',
							'size' => 'md',
							'icon' => 'cancel',
							'attrs' => ['id' => 'cancelUserButton']
						]) ?>
						<?= view('components/button', [
							'tag' => 'button',
							'label' => 'Update User',
							'type' => 'submit',
							'variant' => 'filled',
							'size' => 'md',
							'icon' => 'save',
							'attrs' => ['id' => 'updateUserButton']
						]) ?>
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
						<span class="text-sm text-gray-600 dark:text-gray-400">User Name</span>
						<span class="font-medium text-gray-800 dark:text-gray-200"><?= esc($user['name'] ?? 'N/A') ?></span>
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

<script>
// Handle role checkbox changes
document.addEventListener('DOMContentLoaded', function() {
	const roleCheckboxes = document.querySelectorAll('input[name="roles[]"]');
	const imageInput = document.querySelector('[data-provider-image-input]');
	const removeImage = document.querySelector('[data-provider-image-remove]');
	const imagePreview = document.getElementById('providerProfileImagePreview');
	const imageFallback = document.getElementById('providerProfileImageFallback');
	
	function updateSectionVisibility() {
		const isProvider = document.getElementById('role_provider')?.checked || false;
		const isStaff = document.getElementById('role_staff')?.checked || false;
		
		// Show/hide provider sections
		const providerLocations = document.getElementById('providerLocationsWrapper');
		const providerSchedule = document.getElementById('providerScheduleSection');
		const providerPublicProfile = document.getElementById('providerPublicProfileSection');
		const providerStaff = document.querySelector('[data-provider-staff-manager]');
		
		if (providerLocations) {
			providerLocations.classList.toggle('hidden', !isProvider);
		}
		if (providerSchedule) {
			providerSchedule.classList.toggle('hidden', !isProvider);
		}
		if (providerPublicProfile) {
			providerPublicProfile.classList.toggle('hidden', !isProvider);
		}
		if (providerStaff) {
			providerStaff.classList.toggle('hidden', !isProvider);
		}
		
		// Show/hide staff assignment sections
		const staffProviders = document.querySelector('[data-staff-providers-manager]');
		if (staffProviders) {
			staffProviders.classList.toggle('hidden', !isStaff);
		}
	}
	
	// Add event listeners to all role checkboxes
	roleCheckboxes.forEach(checkbox => {
		checkbox.addEventListener('change', updateSectionVisibility);
	});

	if (imageInput && imagePreview) {
		imageInput.addEventListener('change', function () {
			const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
			if (!file) {
				return;
			}

			if (removeImage) {
				removeImage.checked = false;
			}

			const objectUrl = URL.createObjectURL(file);
			imagePreview.src = objectUrl;
			imagePreview.classList.remove('hidden');
			if (imageFallback) {
				imageFallback.classList.add('hidden');
			}
		});
	}

	if (removeImage && imagePreview) {
		removeImage.addEventListener('change', function () {
			if (!removeImage.checked) {
				return;
			}

			if (imageInput) {
				imageInput.value = '';
			}

			imagePreview.src = '';
			imagePreview.classList.add('hidden');
			if (imageFallback) {
				imageFallback.classList.remove('hidden');
			}
		});
	}
	
	// Initialize visibility on page load
	updateSectionVisibility();
});
</script>

<?= $this->endSection() ?>
