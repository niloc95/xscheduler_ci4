<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'profile']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Profile<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $profileErrors = $profile_errors ?? [];
    $passwordErrors = $password_errors ?? [];
    $profileFormDefaults = array_merge([
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
    ], $profileForm ?? []);
    $alertSuccess = $flashSuccess ?? null;
    $alertError = $flashError ?? null;
    $profileImageUrl = $profileImageUrl ?? null;
    $profileInitials = $profileInitials ?? 'U';
    $accountSummary = $account_summary ?? [];
    $summaryCards = $summary_cards ?? [];
    $recentActivity = $recent_activity ?? [];
    $notificationsEnabled = !empty($user['notify_on_appointments']);
    $showNotifications = !empty($showNotificationsTab);
    $availableTabs = ['profile', 'password'];

    if ($showNotifications) {
        $availableTabs[] = 'notifications';
    }

    $tabState = in_array(($active_tab ?? 'profile'), $availableTabs, true) ? $active_tab : 'profile';

    $toneClasses = [
        'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    ];

    $inputClasses = static function (bool $hasError): string {
        return 'w-full rounded-lg border px-3 py-2 text-gray-900 transition-colors duration-200 focus:border-transparent focus:ring-2 dark:bg-gray-700 dark:text-gray-100 ' . (
            $hasError
                ? 'border-red-500 focus:ring-red-500 dark:border-red-400'
                : 'border-gray-300 focus:ring-blue-500 dark:border-gray-600'
        );
    };

    $tabButtonClasses = static function (bool $isActive): string {
        return 'inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-medium transition-colors duration-200 ' . (
            $isActive
                ? 'bg-blue-600 text-white shadow-sm'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
        );
    };
?>

<div class="space-y-6" data-profile-page data-default-tab="<?= esc($tabState) ?>">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
        <aside class="space-y-6">
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="h-24 bg-gradient-to-r from-sky-500 via-cyan-500 to-blue-600"></div>
                <div class="px-6 pb-6">
                    <div class="-mt-12 flex items-end justify-between gap-4">
                        <div class="relative">
                            <?php if ($profileImageUrl): ?>
                                <img src="<?= esc($profileImageUrl) ?>"
                                     alt="Profile photo"
                                     class="h-24 w-24 rounded-2xl border-4 border-white object-cover shadow-lg dark:border-gray-800">
                            <?php else: ?>
                                <div class="flex h-24 w-24 items-center justify-center rounded-2xl border-4 border-white bg-slate-900 text-2xl font-semibold text-white shadow-lg dark:border-gray-800">
                                    <?= esc($profileInitials) ?>
                                </div>
                            <?php endif; ?>

                            <button type="button"
                                    class="absolute -bottom-2 -right-2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-md transition-colors duration-200 hover:bg-blue-700"
                                    data-profile-image-trigger>
                                <span class="material-symbols-rounded text-base">photo_camera</span>
                            </button>
                        </div>

                        <form action="<?= base_url('/profile/upload-picture') ?>"
                              method="post"
                              enctype="multipart/form-data"
                              class="hidden"
                              data-profile-image-form>
                            <?= csrf_field() ?>
                            <input type="file"
                                   id="profile-picture-upload"
                                   name="profile_picture"
                                   accept="image/*"
                                   class="hidden"
                                   data-profile-image-input>
                        </form>
                    </div>

                    <div class="mt-4 space-y-1">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white"><?= esc($user['name']) ?></h2>
                        <p class="text-sm font-medium uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400"><?= esc($user_role) ?></p>
                        <p class="text-sm text-gray-500 dark:text-gray-400"><?= esc($user['email']) ?></p>
                    </div>

                    <div class="mt-6 space-y-3">
                        <button type="button"
                                data-profile-tab-trigger="profile"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition-colors duration-200 hover:bg-blue-700">
                            <span class="material-symbols-rounded text-base">edit</span>
                            Edit profile
                        </button>
                        <button type="button"
                                data-profile-tab-trigger="password"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors duration-200 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            <span class="material-symbols-rounded text-base">lock_reset</span>
                            Change password
                        </button>
                        <?php if ($showNotifications): ?>
                            <button type="button"
                                    data-profile-tab-trigger="notifications"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors duration-200 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                <span class="material-symbols-rounded text-base">notifications</span>
                                Notification settings
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Account summary</h3>
                    <span class="material-symbols-rounded text-gray-400">manage_accounts</span>
                </div>

                <div class="space-y-4">
                    <?php foreach ($accountSummary as $item): ?>
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300">
                                <span class="material-symbols-rounded text-base"><?= esc($item['icon']) ?></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500"><?= esc($item['label']) ?></p>
                                <?php if (!empty($item['badge'])): ?>
                                    <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= esc($item['classes'] ?? '') ?>">
                                        <?= esc($item['value']) ?>
                                    </span>
                                <?php else: ?>
                                    <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white"><?= esc($item['value']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </aside>

        <div class="space-y-6">
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-700">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Profile settings</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">These values are loaded from your live account record and saved directly to your user profile.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button"
                                    data-profile-tab-button="profile"
                                    class="<?= esc($tabButtonClasses($tabState === 'profile')) ?>">
                                Profile
                            </button>
                            <button type="button"
                                    data-profile-tab-button="password"
                                    class="<?= esc($tabButtonClasses($tabState === 'password')) ?>">
                                Password
                            </button>
                            <?php if ($showNotifications): ?>
                                <button type="button"
                                        data-profile-tab-button="notifications"
                                        class="<?= esc($tabButtonClasses($tabState === 'notifications')) ?>">
                                    Notifications
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 p-6">
                    <?php if ($alertSuccess): ?>
                        <?= ui_alert(esc($alertSuccess), 'success'); ?>
                    <?php endif; ?>

                    <?php if ($alertError && empty($profileErrors['general']) && empty($passwordErrors['general'])): ?>
                        <?= ui_alert(esc($alertError), 'error'); ?>
                    <?php endif; ?>

                    <form id="profileForm"
                          method="post"
                          action="<?= base_url('/profile/update-profile') ?>"
                          class="space-y-6 <?= $tabState === 'profile' ? '' : 'hidden' ?>"
                          data-profile-tab-panel="profile">
                        <?= csrf_field() ?>

                        <?php if (!empty($profileErrors['general'])): ?>
                            <?= ui_alert(esc($profileErrors['general']), 'error'); ?>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label for="first_name" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       id="first_name"
                                       name="first_name"
                                       value="<?= esc(old('first_name', $profileFormDefaults['first_name'])) ?>"
                                       class="<?= esc($inputClasses(isset($profileErrors['first_name']))) ?>"
                                       required>
                                <?php if (isset($profileErrors['first_name'])): ?>
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($profileErrors['first_name']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label for="last_name" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                                <input type="text"
                                       id="last_name"
                                       name="last_name"
                                       value="<?= esc(old('last_name', $profileFormDefaults['last_name'])) ?>"
                                       class="<?= esc($inputClasses(isset($profileErrors['last_name']))) ?>">
                                <?php if (isset($profileErrors['last_name'])): ?>
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($profileErrors['last_name']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="<?= esc(old('email', $profileFormDefaults['email'])) ?>"
                                   class="<?= esc($inputClasses(isset($profileErrors['email']))) ?>"
                                   required>
                            <?php if (isset($profileErrors['email'])): ?>
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($profileErrors['email']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="phone" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone Number</label>
                            <input type="tel"
                                   id="phone"
                                   name="phone"
                                   value="<?= esc(old('phone', $profileFormDefaults['phone'])) ?>"
                                   class="<?= esc($inputClasses(isset($profileErrors['phone']))) ?>"
                                   placeholder="Optional">
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">The country selector is applied automatically when available.</p>
                            <?php if (isset($profileErrors['phone'])): ?>
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($profileErrors['phone']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition-colors duration-200 hover:bg-blue-700">
                                <span class="material-symbols-rounded text-base">save</span>
                                Save changes
                            </button>
                        </div>
                    </form>

                    <form id="passwordForm"
                          method="post"
                          action="<?= base_url('/profile/change-password') ?>"
                          class="space-y-6 <?= $tabState === 'password' ? '' : 'hidden' ?>"
                          data-profile-tab-panel="password">
                        <?= csrf_field() ?>

                        <?php if (!empty($passwordErrors['general'])): ?>
                            <?= ui_alert(esc($passwordErrors['general']), 'error'); ?>
                        <?php endif; ?>

                        <div>
                            <label for="current_password" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Current Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password"
                                   id="current_password"
                                   name="current_password"
                                   class="<?= esc($inputClasses(isset($passwordErrors['current_password']))) ?>"
                                   required>
                            <?php if (isset($passwordErrors['current_password'])): ?>
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($passwordErrors['current_password']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="new_password" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                New Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password"
                                   id="new_password"
                                   name="new_password"
                                   class="<?= esc($inputClasses(isset($passwordErrors['new_password']))) ?>"
                                   required>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Minimum 8 characters. Reuse of the current password is blocked.</p>
                            <?php if (isset($passwordErrors['new_password'])): ?>
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($passwordErrors['new_password']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="confirm_password" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Confirm New Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password"
                                   id="confirm_password"
                                   name="confirm_password"
                                   class="<?= esc($inputClasses(isset($passwordErrors['confirm_password']))) ?>"
                                   required>
                            <?php if (isset($passwordErrors['confirm_password'])): ?>
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400"><?= esc($passwordErrors['confirm_password']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition-colors duration-200 hover:bg-blue-700">
                                <span class="material-symbols-rounded text-base">lock_reset</span>
                                Update password
                            </button>
                        </div>
                    </form>

                    <?php if ($showNotifications): ?>
                        <form id="notificationsForm"
                              method="post"
                              action="<?= base_url('/profile/update-notifications') ?>"
                              class="space-y-6 <?= $tabState === 'notifications' ? '' : 'hidden' ?>"
                              data-profile-tab-panel="notifications">
                            <?= csrf_field() ?>

                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">Appointment notifications</h4>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Receive updates when appointments are booked, rescheduled, cancelled, or when reminders are sent.</p>
                                    </div>
                                    <span class="material-symbols-rounded text-gray-400">notifications</span>
                                </div>

                                <label class="mt-5 flex cursor-pointer items-start gap-3">
                                    <input type="hidden" name="notify_on_appointments" value="0">
                                    <input type="checkbox"
                                           id="profile_notify_on_appointments"
                                           name="notify_on_appointments"
                                           value="1"
                                           <?= $notificationsEnabled ? 'checked' : '' ?>
                                           class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900 dark:text-white">Email me about my appointment queue</span>
                                        <span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">This preference is stored on your live user record.</span>
                                    </span>
                                </label>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition-colors duration-200 hover:bg-blue-700">
                                    <span class="material-symbols-rounded text-base">save</span>
                                    Save preferences
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (!empty($summaryCards)): ?>
                <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($summaryCards as $card): ?>
                        <?php $cardToneClasses = $toneClasses[$card['tone'] ?? 'sky'] ?? $toneClasses['sky']; ?>
                        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= esc($card['label']) ?></p>
                                    <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white"><?= number_format((int) ($card['value'] ?? 0)) ?></p>
                                </div>
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl <?= esc($cardToneClasses) ?>">
                                    <span class="material-symbols-rounded text-xl"><?= esc($card['icon']) ?></span>
                                </div>
                            </div>
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400"><?= esc($card['description']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent activity</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This feed comes from the account audit trail, not placeholder content.</p>
                </div>

                <div class="p-6">
                    <?php if (empty($recentActivity)): ?>
                        <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-10 text-center dark:border-gray-600">
                            <span class="material-symbols-rounded text-3xl text-gray-400">history</span>
                            <p class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No account activity has been recorded yet.</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">New sign-ins and profile changes will appear here automatically.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentActivity as $activity): ?>
                                <article class="flex items-start gap-4 rounded-2xl border border-gray-200 p-4 dark:border-gray-700">
                                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                        <span class="material-symbols-rounded text-base"><?= esc($activity['icon']) ?></span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white"><?= esc($activity['title']) ?></h4>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?= esc($activity['description']) ?></p>
                                            </div>
                                            <div class="text-left sm:text-right">
                                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200"><?= esc($activity['time']) ?></p>
                                                <p class="text-xs text-gray-400 dark:text-gray-500"><?= esc($activity['timestamp']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
