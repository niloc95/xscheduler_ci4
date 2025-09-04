<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'profile']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="My Profile" data-page-subtitle="Manage your account information and preferences">
    <!-- Page Header -->
    <div class="mb-6"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Overview -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <!-- Profile Picture -->
                <div class="text-center mb-6">
                    <div class="relative inline-block">
                        <div class="w-24 h-24 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                            <?= strtoupper(substr($user['name'], 0, 2)) ?>
                        </div>
                        <button class="absolute bottom-0 right-0 bg-blue-600 hover:bg-blue-700 text-white p-2 rounded-full shadow-lg transition-colors duration-200"
                                onclick="document.getElementById('profile-picture-upload').click()">
                            <span class="material-symbols-rounded text-base align-middle">photo_camera</span>
                        </button>
                        <form id="profile-picture-form" action="<?= base_url('/profile/upload-picture') ?>" method="post" enctype="multipart/form-data" style="display: none;">
                            <input type="file" id="profile-picture-upload" name="profile_picture" accept="image/*" onchange="this.form.submit()">
                        </form>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mt-4"><?= esc($user['name']) ?></h2>
                    <p class="text-gray-600 dark:text-gray-400 capitalize"><?= esc($user_role) ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-500"><?= esc($user['email']) ?></p>
                </div>

                <!-- Quick Actions -->
                <div class="space-y-3">
                    <a href="<?= base_url('/profile/edit') ?>" 
                       class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                        <span class="material-symbols-rounded mr-2 text-base align-middle">edit</span>
                        Edit Profile
                    </a>
                    <a href="<?= base_url('/profile/password') ?>" 
                       class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 font-medium rounded-lg transition-colors duration-200">
                        <span class="material-symbols-rounded mr-2 text-base align-middle">lock_reset</span>
                        Change Password
                    </a>
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
<?= $this->endSection() ?>
