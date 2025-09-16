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

                               id="name" 				<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                               name="name" 					<div class="form-group">

                               value="<?= old('name', $user['name'] ?? '') ?>"						<label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone Number</label>

                               required						<input type="tel" id="phone" name="phone" value="<?= old('phone', $user['phone'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('phone') ? 'border-red-500 dark:border-red-400' : '' ?>" placeholder="Enter phone number">

                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('name') ? 'border-red-500 dark:border-red-400' : '' ?>"						<?php if (isset($validation) && $validation->hasError('phone')): ?><p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('phone') ?></p><?php endif; ?>

                               placeholder="Enter full name">					</div>

                        <?php if ($validation && $validation->hasError('name')): ?>					<?php $canChangeRole = isset($availableRoles) && (in_array('admin', $availableRoles) || in_array('provider', $availableRoles) || in_array('staff', $availableRoles) || in_array('customer', $availableRoles)); ?>

                            <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('name') ?></p>					<div class="form-group">

                        <?php endif; ?>						<label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">User Role <?= $canChangeRole ? '<span class="text-red-500">*</span>' : '' ?></label>

                    </div>						<select id="role" name="role" <?= $canChangeRole ? '' : 'disabled' ?> class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('role') ? 'border-red-500 dark:border-red-400' : '' ?>">

							<?php if (!empty($availableRoles)): foreach ($availableRoles as $roleOption): ?>

                    <div class="form-group">								<option value="<?= $roleOption ?>" <?= old('role', $user['role']) === $roleOption ? 'selected' : '' ?>>

                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">									<?= ucfirst($roleOption) ?>

                            Email Address <span class="text-red-500">*</span>								</option>

                        </label>							<?php endforeach; else: ?>

                        <input type="email" 								<option selected><?= ucfirst($user['role']) ?></option>

                               id="email" 							<?php endif; ?>

                               name="email" 						</select>

                               value="<?= old('email', $user['email'] ?? '') ?>"						<?php if (isset($validation) && $validation->hasError('role')): ?><p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('role') ?></p><?php endif; ?>

                               required						<?php if (!$canChangeRole): ?><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Role changes not permitted for your account.</p><?php endif; ?>

                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('email') ? 'border-red-500 dark:border-red-400' : '' ?>"					</div>

                               placeholder="Enter email address">				</div>

                        <?php if ($validation && $validation->hasError('email')): ?>

                            <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('email') ?></p>				<!-- Provider Selection (for Staff) -->

                        <?php endif; ?>				<div id="provider-selection" class="form-group" style="display: none;">

                    </div>					<label for="provider_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Service Provider <span class="text-red-500">*</span></label>

                </div>					<select id="provider_id" name="provider_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('provider_id') ? 'border-red-500 dark:border-red-400' : '' ?>">

						<option value="">Select Provider</option>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">						<?php if (!empty($providers)): foreach ($providers as $provider): ?>

                    <div class="form-group">							<option value="<?= $provider['id'] ?>" <?= (string)old('provider_id', $user['provider_id'] ?? '') === (string)$provider['id'] ? 'selected' : '' ?>>

                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">								<?= esc($provider['name']) ?> (<?= ucfirst($provider['role']) ?>)

                            Phone Number							</option>

                        </label>						<?php endforeach; endif; ?>

                        <input type="tel" 					</select>

                               id="phone" 					<?php if (isset($validation) && $validation->hasError('provider_id')): ?><p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('provider_id') ?></p><?php endif; ?>

                               name="phone" 					<p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Staff members must be assigned to a service provider</p>

                               value="<?= old('phone', $user['phone'] ?? '') ?>"				</div>

                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('phone') ? 'border-red-500 dark:border-red-400' : '' ?>"

                               placeholder="Enter phone number">				<!-- Password Section -->

                        <?php if ($validation && $validation->hasError('phone')): ?>				<div class="border-t border-gray-200 dark:border-gray-700 pt-6">

                            <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('phone') ?></p>					<h3 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4">Password (Optional)</h3>

                        <?php endif; ?>					<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    </div>						<div class="form-group">

							<label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label>

                    <div class="form-group">							<div class="relative">

                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">								<input type="password" id="password" name="password" minlength="8" class="w-full px-3 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('password') ? 'border-red-500 dark:border-red-400' : '' ?>" placeholder="Leave blank to keep current">

                            User Role <?= $this->userModel->canChangeUserRole ?? '' ?>								<button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 z-10"><span class="material-symbols-outlined" id="password-icon">visibility</span></button>

                        </label>							</div>

                        <?php $canChangeRole = in_array('admin', $availableRoles) || in_array('provider', $availableRoles) || in_array('staff', $availableRoles) || in_array('customer', $availableRoles); ?>							<?php if (isset($validation) && $validation->hasError('password')): ?><p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password') ?></p><?php else: ?><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Minimum 8 characters if changing</p><?php endif; ?>

                        <select id="role" 						</div>

                                name="role" 						<div class="form-group">

                                <?= $canChangeRole ? '' : 'disabled' ?>							<label for="password_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm Password</label>

                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('role') ? 'border-red-500 dark:border-red-400' : '' ?>">							<div class="relative">

                            <?php foreach ($availableRoles as $roleOption): ?>								<input type="password" id="password_confirm" name="password_confirm" minlength="8" class="w-full px-3 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?= isset($validation) && $validation->hasError('password_confirm') ? 'border-red-500 dark:border-red-400' : '' ?>" placeholder="Repeat new password">

                                <option value="<?= $roleOption ?>" <?= old('role', $user['role'] ?? '') === $roleOption ? 'selected' : '' ?>>								<button type="button" onclick="togglePassword('password_confirm')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 z-10"><span class="material-symbols-outlined" id="password_confirm-icon">visibility</span></button>

                                    <?= ucfirst($roleOption) ?>							</div>

                                </option>							<?php if (isset($validation) && $validation->hasError('password_confirm')): ?><p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('password_confirm') ?></p><?php endif; ?>

                            <?php endforeach; ?>						</div>

                        </select>					</div>

                        <?php if ($validation && $validation->hasError('role')): ?>				</div>

                            <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('role') ?></p>

                        <?php endif; ?>				<!-- Actions -->

                        <?php if (!$canChangeRole): ?>				<div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">You don't have permission to change this role.</p>					<a href="<?= base_url('user-management') ?>" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 text-sm font-medium transition-colors">

                        <?php endif; ?>						<span class="material-symbols-outlined mr-2">close</span>

                    </div>						Cancel

                </div>					</a>

					<button type="submit" class="inline-flex items-center justify-center px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">

                <!-- Provider Selection (for Staff) -->						<span class="material-symbols-outlined mr-2">save</span>

                <div id="provider-selection" class="form-group" style="display: none;">						Update User

                    <label for="provider_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300">					</button>

                        Service Provider <span class="text-red-500">*</span>				</div>

                    </label>

                    <select id="provider_id" 				</form>

                            name="provider_id"			</div>

                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('provider_id') ? 'border-red-500 dark:border-red-400' : '' ?>">		</div>

                        <option value="">Select Provider</option>

                        <?php foreach ($providers as $provider): ?>		<!-- Side Panel -->

                            <option value="<?= $provider['id'] ?>" <?= old('provider_id', $user['provider_id'] ?? '') == $provider['id'] ? 'selected' : '' ?>>		<div class="lg:col-span-1 space-y-6">

                                <?= esc($provider['name']) ?> (<?= ucfirst($provider['role']) ?>)			<div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-brand material-shadow">

                            </option>				<h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Account Overview</h3>

                        <?php endforeach; ?>				<ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2">

                    </select>					<li><span class="font-medium text-gray-700 dark:text-gray-300">Current Role:</span> <?= ucfirst(esc($user['role'])) ?></li>

                    <?php if ($validation && $validation->hasError('provider_id')): ?>					<li><span class="font-medium text-gray-700 dark:text-gray-300">Created:</span> <?= esc(date('M j, Y', strtotime($user['created_at'] ?? 'now'))) ?></li>

                        <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $validation->getError('provider_id') ?></p>					<li><span class="font-medium text-gray-700 dark:text-gray-300">Status:</span> <?= !empty($user['is_active']) ? 'Active' : 'Inactive' ?></li>

                    <?php endif; ?>				</ul>

                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Staff members must be assigned to a service provider</p>				<?php if (!empty($user['provider_id'])): ?>

                </div>				<div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-xs text-blue-700 dark:text-blue-300">

					Assigned to Provider ID #<?= esc($user['provider_id']) ?>

                <!-- Role Description Placeholder -->				</div>

                <div id="role-description" class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg" style="display: none;">				<?php endif; ?>

                    <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">Role Permissions</h4>			</div>

                    <div id="role-permissions" class="text-sm text-blue-700 dark:text-blue-300"></div>			<?php if (!empty($providers) && ($user['role'] === 'staff')): ?>

                </div>			<div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-brand material-shadow">

				<h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Provider Assignment</h3>

                <!-- Password Section (optional) -->				<p class="text-sm text-gray-600 dark:text-gray-400">Change the provider this staff member is assigned to using the field in the form.</p>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">			</div>

                    <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200 mb-4 transition-colors duration-300">Password (Leave blank to keep current)</h3>			<?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">		</div>

                        <div class="form-group">	</div>

                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 transition-colors duration-300"></div>

                                New Password

                            </label><script>

                            <div class="relative">function togglePassword(fieldId){const f=document.getElementById(fieldId);const i=document.getElementById(fieldId+'-icon');if(!f||!i)return;if(f.type==='password'){f.type='text';i.textContent='visibility_off';}else{f.type='password';i.textContent='visibility';}}

                                <input type="password" function handleRoleChange(){const role=document.getElementById('role')?.value;const providerSel=document.getElementById('provider-selection');if(!providerSel)return;if(role==='staff'){providerSel.style.display='block';document.getElementById('provider_id').required=true;}else{providerSel.style.display='none';document.getElementById('provider_id').required=false;}}

                                       id="password" document.addEventListener('DOMContentLoaded',()=>{handleRoleChange();document.getElementById('role')?.addEventListener('change',handleRoleChange);});

                                       name="password" document.addEventListener('spa:navigated',()=>{if(document.querySelector('[data-page-title="Edit User"]')){handleRoleChange();}});

                                       minlength="8"</script>

                                       class="w-full px-3 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('password') ? 'border-red-500 dark:border-red-400' : '' ?>"<?= $this->endSection() ?>

                                       placeholder="Enter new password (optional)">
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
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Leave blank to keep the current password</p>
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
                                       class="w-full px-3 py-2 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-300 <?= $validation && $validation->hasError('password_confirm') ? 'border-red-500 dark:border-red-400' : '' ?>"
                                       placeholder="Confirm new password">
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
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Only required if you enter a new password</p>
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
                        Update User
                    </button>
                </div>

                </form>
            </div>
        </div>

        <!-- Side Panel -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Current Role Summary -->
            <div class="p-4 md:p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand material-shadow">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 transition-colors duration-300">Current User Role</h3>
                <?php $role = $user['role'] ?? 'staff'; ?>
                <?php $roleBadges = [
                    'admin' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                    'provider' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                    'staff' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                    'customer' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                ]; ?>
                <div class="flex items-center justify-between mb-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $roleBadges[$role] ?? $roleBadges['customer'] ?>">
                        <?= ucfirst($role) ?>
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">User ID: <?= esc($user['id'] ?? '—') ?></span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    <?php
                        $roleDescriptions = [
                            'admin' => 'Full system access including managing all users and settings.',
                            'provider' => 'Can manage services, staff, and bookings for their business.',
                            'staff' => 'Can manage bookings and schedules for assigned provider.',
                            'customer' => 'Can make and manage their own bookings.'
                        ];
                        echo $roleDescriptions[$role] ?? 'Standard user account.';
                    ?>
                </p>
                <div class="text-xs text-gray-500 dark:text-gray-500 space-y-1">
                    <div><span class="font-medium text-gray-700 dark:text-gray-300">Created:</span> <?= !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : '—' ?></div>
                    <div><span class="font-medium text-gray-700 dark:text-gray-300">Last Updated:</span> <?= !empty($user['updated_at']) ? date('M j, Y', strtotime($user['updated_at'])) : '—' ?></div>
                    <div><span class="font-medium text-gray-700 dark:text-gray-300">Status:</span> <span class="<?= ($user['is_active'] ?? true) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' ?>"><?= ($user['is_active'] ?? true) ? 'Active' : 'Inactive' ?></span></div>
                </div>
            </div>

            <!-- Helpful Tips -->
            <div class="p-4 md:p-6 bg-white dark:bg-gray-800 transition-colors duration-300 rounded-lg shadow-brand material-shadow">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3 transition-colors duration-300">Tips</h3>
                <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <li>Leave password fields blank to keep the current password.</li>
                    <li>Only change role if necessary and authorized.</li>
                    <li>Assign a provider if setting role to Staff.</li>
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
    const providerSelect=document.getElementById('provider_id');
    const providerSection=document.getElementById('provider-selection');
    function updateProviderVisibility(){
        const role=roleSelect ? roleSelect.value : '';
        if(role==='staff'){ providerSection.style.display='block'; }
        else { providerSection.style.display='none'; }
    }
    if(roleSelect){
        roleSelect.addEventListener('change',updateProviderVisibility);
        updateProviderVisibility();
    }
})();
</script>
<?= $this->endSection() ?>
