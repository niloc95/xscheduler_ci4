<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'notifications']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Notifications<?= $this->endSection() ?>

<?= $this->section('header_actions') ?>
     <?php if ($unread_count > 0): ?>
     <a href="<?= base_url('/notifications/mark-all-read') ?>" 
         class="xs-btn xs-btn-secondary">
          <span class="material-symbols-rounded">done_all</span>
          Mark All Read
     </a>
     <?php endif; ?>
    <a href="<?= base_url('/settings#notifications') ?>" 
         class="xs-btn xs-btn-primary">
          <span class="material-symbols-rounded">settings</span>
        Settings
    </a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

    <!-- Notification Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                        <span class="material-symbols-rounded text-blue-600 dark:text-blue-400 text-2xl">notifications_active</span>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= count($notifications) ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900 rounded-lg flex items-center justify-center">
                        <span class="material-symbols-rounded text-amber-600 dark:text-amber-400 text-2xl">mark_email_unread</span>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Unread</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $unread_count ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                        <span class="material-symbols-rounded text-green-600 dark:text-green-400 text-2xl">done_all</span>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Read</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= count($notifications) - $unread_count ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                        <span class="material-symbols-rounded text-purple-600 dark:text-purple-400 text-2xl">today</span>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                        <?= count(array_filter($notifications, function($n) { return strpos($n['time'], 'minutes ago') !== false || strpos($n['time'], 'hours ago') !== false; })) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <a href="?filter=all" class="<?= $filter === 'all' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                All
            </a>
            <a href="?filter=unread" class="<?= $filter === 'unread' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                Unread
            </a>
            <a href="?filter=appointments" class="<?= $filter === 'appointments' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                Appointments
            </a>
            <a href="?filter=system" class="<?= $filter === 'system' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                System
            </a>
        </nav>
    </div>

    <!-- Notifications List -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <?php if (!empty($notifications)): ?>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($notifications as $notification): ?>
                    <div class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200 <?= !$notification['read'] ? 'bg-blue-50 dark:bg-blue-900/10' : '' ?>">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center
                                    <?php if ($notification['color'] === 'blue'): ?>bg-blue-100 dark:bg-blue-900
                                    <?php elseif ($notification['color'] === 'green'): ?>bg-green-100 dark:bg-green-900
                                    <?php elseif ($notification['color'] === 'amber'): ?>bg-amber-100 dark:bg-amber-900
                                    <?php elseif ($notification['color'] === 'red'): ?>bg-red-100 dark:bg-red-900
                                    <?php elseif ($notification['color'] === 'purple'): ?>bg-purple-100 dark:bg-purple-900
                                    <?php elseif ($notification['color'] === 'pink'): ?>bg-pink-100 dark:bg-pink-900
                                    <?php elseif ($notification['color'] === 'indigo'): ?>bg-indigo-100 dark:bg-indigo-900
                                    <?php else: ?>bg-gray-100 dark:bg-gray-900<?php endif; ?>">
                                    
                                    <?php if ($notification['icon'] === 'calendar'): ?>
                                        <span class="material-symbols-rounded text-<?= $notification['color'] ?>-600 dark:text-<?= $notification['color'] ?>-400 text-lg">calendar_month</span>
                                    <?php elseif ($notification['icon'] === 'bell'): ?>
                                        <span class="material-symbols-rounded text-<?= $notification['color'] ?>-600 dark:text-<?= $notification['color'] ?>-400 text-lg">notifications</span>
                                    <?php elseif ($notification['icon'] === 'x-circle'): ?>
                                        <span class="material-symbols-rounded text-<?= $notification['color'] ?>-600 dark:text-<?= $notification['color'] ?>-400 text-lg">cancel</span>
                                    <?php elseif ($notification['icon'] === 'credit-card'): ?>
                                        <span class="material-symbols-rounded text-<?= $notification['color'] ?>-600 dark:text-<?= $notification['color'] ?>-400 text-lg">credit_card</span>
                                    <?php elseif ($notification['icon'] === 'star'): ?>
                                        <span class="material-symbols-rounded text-<?= $notification['color'] ?>-600 dark:text-<?= $notification['color'] ?>-400 text-lg">star</span>
                                    <?php elseif ($notification['icon'] === 'users'): ?>
                                        <span class="material-symbols-rounded text-<?= $notification['color'] ?>-600 dark:text-<?= $notification['color'] ?>-400 text-lg">group</span>
                                    <?php elseif ($notification['icon'] === 'gift'): ?>
                                        <span class="material-symbols-rounded text-<?= $notification['color'] ?>-600 dark:text-<?= $notification['color'] ?>-400 text-lg">card_giftcard</span>
                                    <?php else: ?>
                                        <span class="material-symbols-rounded text-<?= $notification['color'] ?>-600 dark:text-<?= $notification['color'] ?>-400 text-lg">settings</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white <?= !$notification['read'] ? 'font-semibold' : '' ?>">
                                            <?= esc($notification['title']) ?>
                                        </h4>
                                        <?php if (!empty($notification['channel']) && $notification['channel'] !== 'system'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                <?php if ($notification['channel'] === 'email'): ?>bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                                <?php elseif ($notification['channel'] === 'sms'): ?>bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                <?php elseif ($notification['channel'] === 'whatsapp'): ?>bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200
                                                <?php else: ?>bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200<?php endif; ?>">
                                                <?= strtoupper(esc($notification['channel'])) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($notification['status']) && $notification['status'] !== 'info'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                <?php if ($notification['status'] === 'success'): ?>bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                <?php elseif ($notification['status'] === 'failed'): ?>bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                                <?php elseif ($notification['status'] === 'queued'): ?>bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                                <?php elseif ($notification['status'] === 'cancelled'): ?>bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                                <?php else: ?>bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200<?php endif; ?>">
                                                <?= ucfirst(esc($notification['status'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <?php if (!$notification['read']): ?>
                                            <div class="w-2 h-2 bg-blue-600 rounded-full"></div>
                                        <?php endif; ?>
                                        <span class="text-xs text-gray-500 dark:text-gray-400"><?= $notification['time'] ?></span>
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    <?= esc($notification['message']) ?>
                                </p>
                                
                                <div class="mt-3 flex items-center space-x-3">
                                    <?php if (!$notification['read']): ?>
                                        <a href="<?= base_url('/notifications/mark-as-read/' . $notification['id']) ?>" 
                                           class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                                            Mark as read
                                        </a>
                                    <?php endif; ?>
                                    <?php if (strpos($notification['id'], 'queue_') === 0): ?>
                                        <button class="text-xs text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                                                onclick="deleteNotification('<?= esc($notification['id']) ?>')">
                                            Cancel
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-12 text-center">
                <span class="material-symbols-rounded w-16 h-16 mx-auto text-gray-400 dark:text-gray-500 mb-4 text-5xl block">notifications_off</span>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No notifications yet</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Notifications will appear here when appointments are confirmed, reminded, cancelled, or rescheduled.</p>
                <a href="<?= base_url('/settings#notifications') ?>" class="inline-flex items-center text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium">
                    <span class="material-symbols-rounded mr-1 text-base">settings</span>
                    Configure notification settings
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteNotification(notificationId) {
    const action = notificationId.startsWith('queue_') ? 'cancel this pending notification' : 'delete this notification';
    if (confirm('Are you sure you want to ' + action + '?')) {
        window.location.href = `<?= base_url('/notifications/delete/') ?>${notificationId}`;
    }
}
</script>
<?= $this->endSection() ?>
