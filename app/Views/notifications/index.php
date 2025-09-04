<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'notifications']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Notifications">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white transition-colors duration-300">
                    Notifications
                </h1>
                <p class="mt-2 text-gray-600 dark:text-gray-300">
                    Stay updated with your latest activity
                </p>
            </div>
            
            <div class="mt-4 sm:mt-0 flex space-x-3">
                     <?php if ($unread_count > 0): ?>
                     <a href="<?= base_url('/notifications/mark-all-read') ?>" 
                         class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 font-medium rounded-lg transition-colors duration-200">
                          <span class="material-symbols-rounded mr-2 text-base align-middle">done_all</span>
                          Mark All Read
                     </a>
                     <?php endif; ?>
                <a href="<?= base_url('/notifications/settings') ?>" 
                         class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                          <span class="material-symbols-rounded mr-2 text-base align-middle">settings</span>
                    Settings
                </a>
            </div>
        </div>
    </div>

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
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white <?= !$notification['read'] ? 'font-semibold' : '' ?>">
                                        <?= esc($notification['title']) ?>
                                    </h4>
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
                                    <button class="text-xs text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                                            onclick="deleteNotification(<?= $notification['id'] ?>)">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-12 text-center">
                <span class="material-symbols-rounded w-16 h-16 mx-auto text-gray-400 dark:text-gray-500 mb-4 text-5xl block">notifications_off</span>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No notifications</h3>
                <p class="text-gray-600 dark:text-gray-400">You're all caught up! No new notifications to show.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteNotification(notificationId) {
    if (confirm('Are you sure you want to delete this notification?')) {
        window.location.href = `<?= base_url('/notifications/delete/') ?>${notificationId}`;
    }
}
</script>
<?= $this->endSection() ?>
