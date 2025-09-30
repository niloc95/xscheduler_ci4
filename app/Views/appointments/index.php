<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'appointments']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?><?= $user_role === 'customer' ? 'View and manage your upcoming and past appointments' : 'Manage appointments for your business' ?><?= $this->endSection() ?>

<?= $this->section('dashboard_actions') ?>
    <?php if (has_role(['customer', 'staff', 'provider', 'admin'])): ?>
    <a href="<?= base_url('/appointments/create') ?>"
       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
        <span class="material-symbols-outlined mr-2">add</span>
        <?= $user_role === 'customer' ? 'Book Appointment' : 'New Appointment' ?>
    </a>
    <?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('dashboard_stats') ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-2xl">calendar_month</span>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['total'] ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-2xl">check_circle</span>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Completed</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['completed'] ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-2xl">schedule</span>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['pending'] ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-2xl">today</span>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $stats['today'] ?></p>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('dashboard_filters') ?>
    <div class="flex flex-wrap gap-2">
        <button class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium">All</button>
        <button class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-200 dark:hover:bg-gray-600">Today</button>
        <button class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-200 dark:hover:bg-gray-600">This Week</button>
        <button class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-200 dark:hover:bg-gray-600">Pending</button>
        <button class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-200 dark:hover:bg-gray-600">Completed</button>
    </div>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Appointments</h3>
        </div>

        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php if (!empty($appointments)): ?>
                <?php foreach ($appointments as $appointment): ?>
                    <div class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-400 text-2xl">person</span>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-white">
                                        <?= esc($appointment['customer_name']) ?>
                                    </h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        <?= esc($appointment['service']) ?> with <?= esc($appointment['provider']) ?>
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-500">
                                        <?= date('F j, Y', strtotime($appointment['date'])) ?> at <?= date('g:i A', strtotime($appointment['time'])) ?>
                                        (<?= $appointment['duration'] ?> min)
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <span class="px-3 py-1 text-xs font-medium rounded-full
                                    <?php if ($appointment['status'] === 'confirmed'): ?>
                                        bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                    <?php elseif ($appointment['status'] === 'pending'): ?>
                                        bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200
                                    <?php elseif ($appointment['status'] === 'completed'): ?>
                                        bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                    <?php else: ?>
                                        bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200
                                    <?php endif; ?>">
                                    <?= ucfirst($appointment['status']) ?>
                                </span>

                                <div class="flex items-center space-x-1">
                                    <a href="<?= base_url('/appointments/view/' . $appointment['id']) ?>"
                                       class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200"
                                       title="View Details">
                                        <span class="material-symbols-outlined">visibility</span>
                                    </a>

                                    <?php if (has_role(['admin', 'provider', 'staff'])): ?>
                                    <button class="p-2 text-gray-400 hover:text-green-600 dark:hover:text-green-400 transition-colors duration-200"
                                            title="Edit Appointment">
                                        <span class="material-symbols-outlined">edit</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($appointment['notes'])): ?>
                        <div class="mt-3 pl-16">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Notes:</span> <?= esc($appointment['notes']) ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-6xl mb-4">event_busy</span>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No appointments found</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        <?= $user_role === 'customer' ? 'You don\'t have any appointments yet.' : 'No appointments match your current filters.' ?>
                    </p>
                    <?php if ($user_role === 'customer'): ?>
                    <a href="<?= base_url('/appointments/create') ?>"
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200">
                        <span class="material-symbols-outlined mr-2">add</span>
                        Book Your First Appointment
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?= $this->endSection() ?>
