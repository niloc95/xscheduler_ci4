<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'notifications']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Notifications<?= $this->endSection() ?>

<?php
$notifications = $notifications ?? [];
$unread_count = $unread_count ?? 0;
$filter = $filter ?? 'all';
$notificationTab = $notificationTab ?? 'activity';
$notificationIsAdmin = $notificationIsAdmin ?? false;
$notificationCurrentBusinessId = (int) ($notificationCurrentBusinessId ?? 1);
$notificationBusinessContext = is_array($notificationBusinessContext ?? null) ? $notificationBusinessContext : [];
$notificationFeedSummary = $notificationFeedSummary ?? [
    'total' => 0,
    'unread' => 0,
    'read' => 0,
    'today' => 0,
];
$notificationDeliveryLogs = $notificationDeliveryLogs ?? [];
$notificationDeliveryLogFilters = $notificationDeliveryLogFilters ?? [
    'status' => '',
    'channel' => '',
    'event' => '',
];
$notificationDeliveryLogSummary = $notificationDeliveryLogSummary ?? [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
    'cancelled' => 0,
    'skipped' => 0,
    'resendable' => 0,
];
$notificationDeliveryLogEventOptions = $notificationDeliveryLogEventOptions ?? [];
$notificationPageHeading = (string) ($notificationPageHeading ?? 'Activity Feed');
$notificationPageDescription = (string) ($notificationPageDescription ?? '');
$notificationUi = is_array($notificationUi ?? null) ? $notificationUi : [];
$currentPageUrl = (string) ($notificationUi['currentPageUrl'] ?? base_url('notifications'));
$settingsNotificationsUrl = (string) ($notificationUi['settingsNotificationsUrl'] ?? base_url('settings'));
$activityTabUrl = (string) ($notificationUi['activityTabUrl'] ?? base_url('notifications'));
$deliveryLogsTabUrl = (string) ($notificationUi['deliveryLogsTabUrl'] ?? base_url('notifications'));
$deliveryLogClearUrl = (string) ($notificationUi['deliveryLogClearUrl'] ?? base_url('notifications'));
$deliveryLogsFormAction = (string) ($notificationUi['deliveryLogsFormAction'] ?? base_url('notifications'));
$activityFilters = is_array($notificationUi['activityFilters'] ?? null) ? $notificationUi['activityFilters'] : [];

$colorBgMap = [
    'blue' => 'bg-blue-100 dark:bg-blue-900',
    'green' => 'bg-green-100 dark:bg-green-900',
    'amber' => 'bg-amber-100 dark:bg-amber-900',
    'red' => 'bg-red-100 dark:bg-red-900',
    'purple' => 'bg-purple-100 dark:bg-purple-900',
    'pink' => 'bg-pink-100 dark:bg-pink-900',
    'indigo' => 'bg-indigo-100 dark:bg-indigo-900',
];
$colorTextMap = [
    'blue' => 'text-blue-600 dark:text-blue-400',
    'green' => 'text-green-600 dark:text-green-400',
    'amber' => 'text-amber-600 dark:text-amber-400',
    'red' => 'text-red-600 dark:text-red-400',
    'purple' => 'text-purple-600 dark:text-purple-400',
    'pink' => 'text-pink-600 dark:text-pink-400',
    'indigo' => 'text-indigo-600 dark:text-indigo-400',
];
$iconMap = [
    'calendar' => 'calendar_month',
    'bell' => 'notifications',
    'x-circle' => 'cancel',
    'credit-card' => 'credit_card',
    'star' => 'star',
    'users' => 'group',
    'gift' => 'card_giftcard',
    'cog' => 'settings',
];
$channelBadgeMap = [
    'email' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'sms' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'whatsapp' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    'system' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
];
$statusBadgeMap = [
    'success' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    'queued' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
    'skipped' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200',
    'info' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
];
?>

<?= $this->section('content') ?>
<div class="space-y-6" data-notifications-page>
    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="space-y-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        <?= esc($notificationPageHeading) ?>
                    </h2>
                    <?php if ($notificationPageDescription !== ''): ?>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400"><?= esc($notificationPageDescription) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($notificationIsAdmin && !empty($notificationBusinessContext['options'] ?? [])): ?>
                    <?= view('components/notification-business-context', [
                        'businessContext' => $notificationBusinessContext,
                    ]) ?>
                <?php endif; ?>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <?php if ($notificationTab === 'activity' && $unread_count > 0): ?>
                    <form method="POST" action="<?= base_url('notifications/mark-all-read') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="redirect_to" value="<?= esc($currentPageUrl) ?>">
                        <input type="hidden" name="business_id" value="<?= esc((string) $notificationCurrentBusinessId) ?>">
                        <button type="submit" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors duration-200 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            <span class="material-symbols-outlined mr-2 text-base">done_all</span>
                            Mark All Read
                        </button>
                    </form>
                <?php endif; ?>

                <a href="<?= esc($settingsNotificationsUrl) ?>"
                   class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-blue-700">
                    <span class="material-symbols-outlined mr-2 text-base">settings</span>
                    Settings
                </a>
            </div>
        </div>

        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Notification surfaces">
            <a href="<?= esc($activityTabUrl) ?>"
               class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors duration-200 <?= $notificationTab === 'activity' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' ?>">
                Activity Feed
            </a>
            <?php if ($notificationIsAdmin): ?>
                <a href="<?= esc($deliveryLogsTabUrl) ?>"
                   class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors duration-200 <?= $notificationTab === 'delivery-logs' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' ?>">
                    Delivery Logs
                    <span class="ml-2 inline-flex items-center rounded-full bg-white/20 px-2 py-0.5 text-xs font-semibold <?= $notificationTab === 'delivery-logs' ? 'text-white dark:text-gray-900' : 'text-gray-600 dark:text-gray-300' ?>">
                        Admin
                    </span>
                </a>
            <?php endif; ?>
        </nav>
    </section>

    <?php if ($notificationTab === 'activity'): ?>
        <section class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Activity</p>
                <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white"><?= esc((string) ($notificationFeedSummary['total'] ?? 0)) ?></p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Unread</p>
                <p class="mt-3 text-3xl font-semibold text-amber-600 dark:text-amber-400"><?= esc((string) ($notificationFeedSummary['unread'] ?? 0)) ?></p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Read</p>
                <p class="mt-3 text-3xl font-semibold text-green-600 dark:text-green-400"><?= esc((string) ($notificationFeedSummary['read'] ?? 0)) ?></p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today</p>
                <p class="mt-3 text-3xl font-semibold text-indigo-600 dark:text-indigo-400"><?= esc((string) ($notificationFeedSummary['today'] ?? 0)) ?></p>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <nav class="flex flex-wrap gap-2" aria-label="Activity filters">
                <?php foreach ($activityFilters as $option): ?>
                    <a href="<?= esc((string) ($option['url'] ?? '#')) ?>"
                       class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors duration-200 <?= !empty($option['isActive']) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' ?>">
                        <?= esc((string) ($option['label'] ?? 'Filter')) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <?php if (!empty($notifications)): ?>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                            $nColor = $notification['color'] ?? 'gray';
                            $bgClass = $colorBgMap[$nColor] ?? 'bg-gray-100 dark:bg-gray-900';
                            $txtClass = $colorTextMap[$nColor] ?? 'text-gray-600 dark:text-gray-400';
                            $iconName = $iconMap[$notification['icon'] ?? ''] ?? 'settings';
                            $channelClass = $channelBadgeMap[$notification['channel'] ?? 'system'] ?? $channelBadgeMap['system'];
                            $statusClass = $statusBadgeMap[$notification['status'] ?? 'info'] ?? $statusBadgeMap['info'];
                        ?>
                        <div class="p-6 transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700 <?= !($notification['read'] ?? true) ? 'bg-blue-50 dark:bg-blue-900/10' : '' ?>">
                            <div class="flex items-start gap-4">
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full <?= $bgClass ?>">
                                    <span class="material-symbols-outlined text-lg <?= $txtClass ?>"><?= esc($iconName) ?></span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div class="space-y-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white"><?= esc((string) ($notification['title'] ?? 'Notification')) ?></h3>
                                                <?php if (($notification['channel'] ?? 'system') !== 'system'): ?>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?= $channelClass ?>">
                                                        <?= esc(strtoupper((string) ($notification['channel'] ?? ''))) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (($notification['status'] ?? 'info') !== 'info'): ?>
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusClass ?>">
                                                        <?= esc(ucfirst((string) ($notification['status'] ?? ''))) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <?= esc((string) ($notification['message'] ?? '')) ?>
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <?php if (!($notification['read'] ?? true)): ?>
                                                <span class="h-2 w-2 rounded-full bg-blue-600"></span>
                                            <?php endif; ?>
                                            <span><?= esc((string) ($notification['time'] ?? '')) ?></span>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center gap-3">
                                        <?php if (!($notification['read'] ?? true)): ?>
                                            <form method="POST" action="<?= base_url('notifications/mark-read/' . ($notification['id'] ?? '')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="redirect_to" value="<?= esc($currentPageUrl) ?>">
                                                <input type="hidden" name="business_id" value="<?= esc((string) $notificationCurrentBusinessId) ?>">
                                                <button type="submit" class="text-sm font-medium text-blue-600 transition-colors duration-200 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                                                    Mark as read
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($notificationIsAdmin && str_starts_with((string) ($notification['id'] ?? ''), 'queue_')): ?>
                                            <form method="POST" action="<?= base_url('notifications/delete/' . ($notification['id'] ?? '')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="redirect_to" value="<?= esc($currentPageUrl) ?>">
                                                <input type="hidden" name="business_id" value="<?= esc((string) $notificationCurrentBusinessId) ?>">
                                                <button type="submit" class="text-sm font-medium text-red-600 transition-colors duration-200 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                                    Cancel queued send
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined mb-4 block text-5xl text-gray-400 dark:text-gray-500">notifications_off</span>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">No notifications yet</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Notifications will appear here when appointments are confirmed, reminded, cancelled, or rescheduled.</p>
                    <a href="<?= esc($settingsNotificationsUrl) ?>" class="mt-4 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400">
                        <span class="material-symbols-outlined mr-1 text-base">settings</span>
                        Configure notification settings
                    </a>
                </div>
            <?php endif; ?>
        </section>
    <?php elseif ($notificationIsAdmin): ?>
        <section class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Recent Rows</p>
                <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white"><?= esc((string) ($notificationDeliveryLogSummary['total'] ?? 0)) ?></p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Failed</p>
                <p class="mt-3 text-3xl font-semibold text-red-600 dark:text-red-400"><?= esc((string) ($notificationDeliveryLogSummary['failed'] ?? 0)) ?></p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Successful</p>
                <p class="mt-3 text-3xl font-semibold text-green-600 dark:text-green-400"><?= esc((string) ($notificationDeliveryLogSummary['success'] ?? 0)) ?></p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Resendable</p>
                <p class="mt-3 text-3xl font-semibold text-blue-600 dark:text-blue-400"><?= esc((string) ($notificationDeliveryLogSummary['resendable'] ?? 0)) ?></p>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Filter Delivery Logs</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Business switching stays explicit in the URL, and these filters stay scoped to the selected business.</p>
                </div>
                <a href="<?= esc($deliveryLogClearUrl) ?>" class="text-sm font-medium text-gray-600 transition-colors duration-200 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">Clear filters</a>
            </div>

            <form method="GET" action="<?= esc($deliveryLogsFormAction) ?>" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-4">
                <input type="hidden" name="tab" value="delivery-logs">
                <input type="hidden" name="business_id" value="<?= esc((string) $notificationCurrentBusinessId) ?>">

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</span>
                    <select name="log_status" class="form-input">
                        <option value="">All statuses</option>
                        <?php foreach (['success' => 'Success', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'skipped' => 'Skipped'] as $value => $label): ?>
                            <option value="<?= esc($value) ?>" <?= ($notificationDeliveryLogFilters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Channel</span>
                    <select name="log_channel" class="form-input">
                        <option value="">All channels</option>
                        <?php foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $value => $label): ?>
                            <option value="<?= esc($value) ?>" <?= ($notificationDeliveryLogFilters['channel'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Event</span>
                    <select name="log_event" class="form-input">
                        <option value="">All events</option>
                        <?php foreach ($notificationDeliveryLogEventOptions as $value => $label): ?>
                            <option value="<?= esc((string) $value) ?>" <?= ($notificationDeliveryLogFilters['event'] ?? '') === $value ? 'selected' : '' ?>><?= esc((string) $label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="flex items-end">
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-blue-700">
                        Apply filters
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <?php if ($notificationDeliveryLogs === []): ?>
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined mb-4 block text-5xl text-gray-400 dark:text-gray-500">receipt_long</span>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">No delivery logs for this view</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Try a different business or clear the current filters.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">When</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Channel</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Event</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Appointment</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Recipient</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Correlation</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Error</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($notificationDeliveryLogs as $row): ?>
                                <?php
                                    $channelClass = $channelBadgeMap[$row['channel'] ?? 'system'] ?? $channelBadgeMap['system'];
                                    $statusClass = $statusBadgeMap[$row['status'] ?? 'info'] ?? $statusBadgeMap['info'];
                                ?>
                                <tr class="align-top">
                                    <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                        <div class="font-medium"><?= esc((string) ($row['created_at_display'] ?? $row['created_at'] ?? '')) ?></div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= esc((string) ($row['time_ago'] ?? '')) ?></div>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?= $channelClass ?>">
                                            <?= esc((string) ($row['channel_label'] ?? strtoupper((string) ($row['channel'] ?? '')))) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                        <div class="font-medium"><?= esc((string) ($row['event_label'] ?? '')) ?></div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= esc((string) ($row['event_type'] ?? '')) ?></div>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                        <?php if (!empty($row['appointment_id'])): ?>
                                            <div class="font-medium">#<?= esc((string) $row['appointment_id']) ?></div>
                                        <?php else: ?>
                                            <div class="font-medium text-gray-500 dark:text-gray-400">No appointment</div>
                                        <?php endif; ?>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= esc((string) ($row['appointment_context'] ?? '')) ?></div>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                        <div class="font-medium"><?= esc((string) ($row['recipient_masked'] ?? '')) ?></div>
                                        <?php if (!empty($row['provider'])): ?>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Provider: <?= esc((string) $row['provider']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusClass ?>">
                                            <?= esc((string) ($row['status_label'] ?? ucfirst((string) ($row['status'] ?? '')))) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-gray-600 dark:text-gray-400">
                                        <?= esc((string) ($row['correlation_id'] ?? '')) ?>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-gray-600 dark:text-gray-400">
                                        <?= esc((string) ($row['error_message'] ?? '')) ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if (!empty($row['can_resend'])): ?>
                                            <form method="POST" action="<?= base_url('notifications/resend') ?>" class="space-y-2">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="redirect_to" value="<?= esc($currentPageUrl) ?>">
                                                <input type="hidden" name="business_id" value="<?= esc((string) $notificationCurrentBusinessId) ?>">
                                                <input type="hidden" name="appointment_id" value="<?= esc((string) ($row['appointment_id'] ?? '')) ?>">
                                                <input type="hidden" name="channel" value="<?= esc((string) ($row['channel'] ?? '')) ?>">
                                                <input type="hidden" name="event_type" value="<?= esc((string) ($row['event_type'] ?? '')) ?>">
                                                <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition-colors duration-200 hover:bg-blue-700">
                                                    <span class="material-symbols-outlined mr-1 text-sm">refresh</span>
                                                    Resend <?= esc((string) ($row['channel_label'] ?? '')) ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">No quick action</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
