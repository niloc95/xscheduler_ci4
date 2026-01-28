<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'profile']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Profile<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $profileErrors = $profile_errors ?? [];
    $passwordErrors = $password_errors ?? [];
    $tabState = $active_tab ?? 'profile';
    $profileFormDefaults = array_merge([
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
    ], $profileForm ?? []);
    $alertSuccess = $flashSuccess ?? null;
    $alertError = $flashError ?? null;
    $profileImageUrl = $profileImageUrl ?? null;
?>
<!-- Page Header -->
<div class="mb-6"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Overview -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <!-- Profile Picture -->
                <div class="text-center mb-6">
                    <div class="relative inline-block">
                        <?php if ($profileImageUrl): ?>
                            <img src="<?= esc($profileImageUrl) ?>"
                                 alt="Profile photo"
                                 class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">
                        <?php else: ?>
                            <div class="w-24 h-24 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                                <?= strtoupper(substr($user['name'], 0, 2)) ?>
                            </div>
                        <?php endif; ?>
                        <button type="button"
                                class="absolute bottom-0 right-0 bg-blue-600 hover:bg-blue-700 text-white p-2 rounded-full shadow-lg transition-colors duration-200"
                                onclick="document.getElementById('profile-picture-upload').click()">
                            <span class="material-symbols-rounded text-base align-middle">photo_camera</span>
                        </button>
                        <form id="profile-picture-form" action="<?= base_url('/profile/upload-picture') ?>" method="post" enctype="multipart/form-data" class="hidden">
                            <?= csrf_field() ?>
                            <input type="file" id="profile-picture-upload" name="profile_picture" accept="image/*" onchange="this.form.submit()">
                        </form>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mt-4"><?= esc($user['name']) ?></h2>
                    <p class="text-gray-600 dark:text-gray-400 capitalize"><?= esc($user_role) ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-500"><?= esc($user['email']) ?></p>
                </div>

                <!-- Quick Actions -->
                <div class="space-y-3">
            <button type="button"
                data-profile-tab-trigger="profile"
                class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                        <span class="material-symbols-rounded mr-2 text-base align-middle">edit</span>
                        Edit Profile
                    </button>
                    <button type="button"
                            data-profile-tab-trigger="password"
                            class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 font-medium rounded-lg transition-colors duration-200">
                        <span class="material-symbols-rounded mr-2 text-base align-middle">lock_reset</span>
                        Change Password
                    </button>
                </div>
            </div>

            <!-- Account Info -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mt-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Account Information</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Member Since</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white"><?= $profile_stats['member_since'] ?></p>
                    </div>
                    <?php if (isset($user['phone']) && !empty($user['phone'])): ?>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Phone</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white"><?= esc($user['phone']) ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Account Status</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            Active
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700" data-profile-tabs data-active-tab="<?= esc($tabState) ?>">
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <?php
                        $isProfileActive = $tabState === 'profile';
                        $isPasswordActive = $tabState === 'password';
                        $profileTabClasses = 'flex-1 px-4 py-3 text-sm font-medium transition-colors duration-200 border-b-2 ' . (
                            $isProfileActive
                                ? 'border-blue-600 text-blue-600 bg-blue-50 dark:bg-blue-900/20'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-blue-600'
                        );
                        $passwordTabClasses = 'flex-1 px-4 py-3 text-sm font-medium transition-colors duration-200 border-b-2 ' . (
                            $isPasswordActive
                                ? 'border-blue-600 text-blue-600 bg-blue-50 dark:bg-blue-900/20'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-blue-600'
                        );
                    ?>
                    <button type="button"
                            data-profile-tab-button="profile"
                            class="<?= esc($profileTabClasses) ?>">
                        Profile Information
                    </button>
                    <button type="button"
                            data-profile-tab-button="password"
                            class="<?= esc($passwordTabClasses) ?>">
                        Change Password
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <?php if ($alertSuccess): ?>
                        <?= ui_alert(esc($alertSuccess), 'success'); ?>
                    <?php endif; ?>

                    <?php if ($alertError && empty($profileErrors['general']) && empty($passwordErrors)): ?>
                        <?= ui_alert(esc($alertError), 'error'); ?>
                    <?php endif; ?>

                    <?php if (!empty($profileErrors['general']) && $tabState === 'profile'): ?>
                        <?= ui_alert(esc($profileErrors['general']), 'error'); ?>
                    <?php endif; ?>

                    <form id="profileForm" method="post" action="<?= base_url('/profile/update-profile') ?>" class="space-y-6 <?= $tabState === 'profile' ? '' : 'hidden' ?>">
                        <?= csrf_field() ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php
                                $firstNameError = isset($profileErrors['first_name']);
                                $firstNameClasses = 'w-full px-3 py-2 rounded-lg focus:ring-2 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-200 ';
                                $firstNameClasses .= $firstNameError
                                    ? 'border border-red-500 dark:border-red-400 focus:ring-red-500'
                                    : 'border border-gray-300 dark:border-gray-600 focus:ring-blue-500';
                            ?>
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       id="first_name"
                                       name="first_name"
                                       value="<?= esc(old('first_name', $profileFormDefaults['first_name'])) ?>"
                                       class="<?= esc($firstNameClasses) ?>"
                                       required>
                                <?php if ($firstNameError): ?>
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($profileErrors['first_name']) ?></p>
                                <?php endif; ?>
                            </div>

                            <?php
                                $lastNameError = isset($profileErrors['last_name']);
                                $lastNameClasses = 'w-full px-3 py-2 rounded-lg focus:ring-2 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-200 ';
                                $lastNameClasses .= $lastNameError
                                    ? 'border border-red-500 dark:border-red-400 focus:ring-red-500'
                                    : 'border border-gray-300 dark:border-gray-600 focus:ring-blue-500';
                            ?>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Last Name
                                </label>
                                <input type="text"
                                       id="last_name"
                                       name="last_name"
                                       value="<?= esc(old('last_name', $profileFormDefaults['last_name'])) ?>"
                                       class="<?= esc($lastNameClasses) ?>">
                                <?php if ($lastNameError): ?>
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($profileErrors['last_name']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php
                            $emailError = isset($profileErrors['email']);
                            $emailClasses = 'w-full px-3 py-2 rounded-lg focus:ring-2 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-200 ';
                            $emailClasses .= $emailError
                                ? 'border border-red-500 dark:border-red-400 focus:ring-red-500'
                                : 'border border-gray-300 dark:border-gray-600 focus:ring-blue-500';
                        ?>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="<?= esc(old('email', $profileFormDefaults['email'])) ?>"
                                   class="<?= esc($emailClasses) ?>"
                                   required>
                            <?php if ($emailError): ?>
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($profileErrors['email']) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php
                            $phoneError = isset($profileErrors['phone']);
                            $phoneClasses = 'w-full px-3 py-2 rounded-lg focus:ring-2 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-200 ';
                            $phoneClasses .= $phoneError
                                ? 'border border-red-500 dark:border-red-400 focus:ring-red-500'
                                : 'border border-gray-300 dark:border-gray-600 focus:ring-blue-500';
                        ?>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Phone Number
                            </label>
                            <input type="tel"
                                   id="phone"
                                   name="phone"
                                   value="<?= esc(old('phone', $profileFormDefaults['phone'])) ?>"
                                   class="<?= esc($phoneClasses) ?>"
                                   placeholder="Optional">
                            <?php if ($phoneError): ?>
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($profileErrors['phone']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                                <span class="material-symbols-rounded mr-2 text-base align-middle">save</span>
                                Save Changes
                            </button>
                        </div>
                    </form>

                    <?php if (!empty($passwordErrors['general']) && $tabState === 'password'): ?>
                        <?= ui_alert(esc($passwordErrors['general']), 'error'); ?>
                    <?php endif; ?>

                    <form id="passwordForm" method="post" action="<?= base_url('/profile/change-password') ?>" class="space-y-6 <?= $tabState === 'password' ? '' : 'hidden' ?>">
                        <?= csrf_field() ?>
                        <?php
                            $currentPasswordError = isset($passwordErrors['current_password']);
                            $currentPasswordClasses = 'w-full px-3 py-2 rounded-lg focus:ring-2 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-200 ';
                            $currentPasswordClasses .= $currentPasswordError
                                ? 'border border-red-500 dark:border-red-400 focus:ring-red-500'
                                : 'border border-gray-300 dark:border-gray-600 focus:ring-blue-500';
                        ?>
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Current Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password"
                                   id="current_password"
                                   name="current_password"
                                   class="<?= esc($currentPasswordClasses) ?>"
                                   required>
                            <?php if ($currentPasswordError): ?>
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($passwordErrors['current_password']) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php
                            $newPasswordError = isset($passwordErrors['new_password']);
                            $newPasswordClasses = 'w-full px-3 py-2 rounded-lg focus:ring-2 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-200 ';
                            $newPasswordClasses .= $newPasswordError
                                ? 'border border-red-500 dark:border-red-400 focus:ring-red-500'
                                : 'border border-gray-300 dark:border-gray-600 focus:ring-blue-500';
                        ?>
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                New Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password"
                                   id="new_password"
                                   name="new_password"
                                   class="<?= esc($newPasswordClasses) ?>"
                                   required>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Minimum 8 characters</p>
                            <?php if ($newPasswordError): ?>
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($passwordErrors['new_password']) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php
                            $confirmPasswordError = isset($passwordErrors['confirm_password']);
                            $confirmPasswordClasses = 'w-full px-3 py-2 rounded-lg focus:ring-2 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-colors duration-200 ';
                            $confirmPasswordClasses .= $confirmPasswordError
                                ? 'border border-red-500 dark:border-red-400 focus:ring-red-500'
                                : 'border border-gray-300 dark:border-gray-600 focus:ring-blue-500';
                        ?>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Confirm New Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password"
                                   id="confirm_password"
                                   name="confirm_password"
                                   class="<?= esc($confirmPasswordClasses) ?>"
                                   required>
                            <?php if ($confirmPasswordError): ?>
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($passwordErrors['confirm_password']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                                <span class="material-symbols-rounded mr-2 text-base align-middle">lock_reset</span>
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($user_role === 'customer'): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                    <span class="material-symbols-rounded text-blue-600 dark:text-blue-400 text-2xl">calendar_month</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Appointments</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $profile_stats['total_appointments'] ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                                    <span class="material-symbols-rounded text-green-600 dark:text-green-400 text-2xl">attach_money</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Spent</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">$<?= number_format($profile_stats['total_spent'], 2) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                                    <span class="material-symbols-rounded text-purple-600 dark:text-purple-400 text-2xl">workspace_premium</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Loyalty Points</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $profile_stats['loyalty_points'] ?></p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                                    <span class="material-symbols-rounded text-green-600 dark:text-green-400 text-2xl">attach_money</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Revenue</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">$<?= number_format($profile_stats['total_revenue'], 2) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900 rounded-lg flex items-center justify-center">
                                    <span class="material-symbols-rounded text-amber-600 dark:text-amber-400 text-2xl">star</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Average Rating</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $profile_stats['average_rating'] ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                    <span class="material-symbols-rounded text-blue-600 dark:text-blue-400 text-2xl">group</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    <?= $user_role === 'admin' ? 'Total Users' : 'Clients Served' ?>
                                </p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <?= $user_role === 'admin' ? $profile_stats['total_users'] : $profile_stats['clients_served'] ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Activity</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                        <?php if ($activity['icon'] === 'calendar-check'): ?>
                                            <span class="material-symbols-rounded text-blue-600 dark:text-blue-400 text-base">event_available</span>
                                        <?php elseif ($activity['icon'] === 'user'): ?>
                                            <span class="material-symbols-rounded text-blue-600 dark:text-blue-400 text-base">person</span>
                                        <?php elseif ($activity['icon'] === 'calendar-plus'): ?>
                                            <span class="material-symbols-rounded text-blue-600 dark:text-blue-400 text-base">event</span>
                                        <?php else: ?>
                                            <span class="material-symbols-rounded text-blue-600 dark:text-blue-400 text-base">star</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-white"><?= esc($activity['description']) ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= $activity['time'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Settings Quick Links -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Account Settings</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <a href="<?= base_url('/profile/privacy') ?>" 
                           class="flex items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200">
                            <span class="material-symbols-rounded text-gray-600 dark:text-gray-400 mr-3 text-2xl">verified_user</span>
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">Privacy Settings</h4>

                            <script>
                            document.addEventListener('DOMContentLoaded', () => {
                                const tabContainer = document.querySelector('[data-profile-tabs]');
                                if (!tabContainer) {
                                    return;
                                }

                                const forms = {
                                    profile: document.getElementById('profileForm'),
                                    password: document.getElementById('passwordForm')
                                };

                                const buttons = tabContainer.querySelectorAll('[data-profile-tab-button]');

                                function activateTab(target) {
                                    const isProfile = target === 'profile';
                                    if (forms.profile) {
                                        forms.profile.classList.toggle('hidden', !isProfile);
                                    }
                                    if (forms.password) {
                                        forms.password.classList.toggle('hidden', isProfile);
                                    }

                                    buttons.forEach((btn) => {
                                        const btnTarget = btn.getAttribute('data-profile-tab-button');
                                        const activeClasses = 'border-blue-600 text-blue-600 bg-blue-50 dark:bg-blue-900/20';
                                        const inactiveClasses = 'border-transparent text-gray-500 dark:text-gray-400 hover:text-blue-600';
                                        if (btnTarget === target) {
                                            btn.classList.add(...activeClasses.split(' '));
                                            btn.classList.remove(...inactiveClasses.split(' '));
                                        } else {
                                            btn.classList.remove(...activeClasses.split(' '));
                                            btn.classList.add(...inactiveClasses.split(' '));
                                        }
                                    });
                                }

                                buttons.forEach((btn) => {
                                    btn.addEventListener('click', () => {
                                        const target = btn.getAttribute('data-profile-tab-button');
                                        activateTab(target);
                                    });
                                });

                                document.querySelectorAll('[data-profile-tab-trigger]').forEach((trigger) => {
                                    trigger.addEventListener('click', () => {
                                        const target = trigger.getAttribute('data-profile-tab-trigger');
                                        activateTab(target);
                                    });
                                });
                            });
                            </script>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Control your privacy preferences</p>
                            </div>
                        </a>

                        <a href="<?= base_url('/profile/account') ?>" 
                           class="flex items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200">
                            <span class="material-symbols-rounded text-gray-600 dark:text-gray-400 mr-3 text-2xl">manage_accounts</span>
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">Account Settings</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Security and preferences</p>
                            </div>
                        </a>
                    </div>
                </div>
        </div>
    </div>
</div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const container = document.querySelector('[data-profile-tabs]');
                        if (!container) {
                            return;
                        }

                        const forms = {
                            profile: container.querySelector('#profileForm'),
                            password: container.querySelector('#passwordForm'),
                        };

                        const tabButtons = container.querySelectorAll('[data-profile-tab-button]');
                        const externalTriggers = document.querySelectorAll('[data-profile-tab-trigger]');

                        const setActiveClasses = function (target) {
                            tabButtons.forEach(function (button) {
                                const isActive = button.dataset.profileTabButton === target;
                                button.classList.toggle('border-blue-600', isActive);
                                button.classList.toggle('text-blue-600', isActive);
                                button.classList.toggle('bg-blue-50', isActive);
                                button.classList.toggle('dark:bg-blue-900/20', isActive);
                                button.classList.toggle('border-transparent', !isActive);
                                button.classList.toggle('text-gray-500', !isActive);
                                button.classList.toggle('dark:text-gray-400', !isActive);
                            });
                        };

                        const showForm = function (target) {
                            Object.keys(forms).forEach(function (key) {
                                if (forms[key]) {
                                    forms[key].classList.toggle('hidden', key !== target);
                                }
                            });
                        };

                        const activateTab = function (target) {
                            if (!forms[target]) {
                                return;
                            }
                            container.dataset.activeTab = target;
                            showForm(target);
                            setActiveClasses(target);
                        };

                        tabButtons.forEach(function (button) {
                            button.addEventListener('click', function () {
                                activateTab(button.dataset.profileTabButton);
                            });
                        });

                        externalTriggers.forEach(function (trigger) {
                            trigger.addEventListener('click', function () {
                                activateTab(trigger.dataset.profileTabTrigger);
                                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            });
                        });

                        const initialTab = container.dataset.activeTab || 'profile';
                        activateTab(initialTab);
                    });
                    </script>
<?= $this->endSection() ?>
