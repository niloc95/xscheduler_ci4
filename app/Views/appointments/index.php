<?php
/**
 * Appointments Dashboard View
 *
 * This file renders the main appointments dashboard for all user roles (admin, provider, staff, customer).
 * It provides:
 *   - Sidebar navigation
 *   - Page title and subtitle
 *   - Action buttons (e.g., create/book appointment)
 *   - Stats summary cards
 *   - Filter controls
 *   - Appointment list with status, actions, and notes
 *
 * Sections are injected into the main dashboard layout.
 */
?>
<?= $this->extend('layouts/dashboard') ?>

<?php // Override stats grid to two responsive columns ?>
<?= $this->section('dashboard_stats_class') ?>grid grid-cols-1 md:grid-cols-2 gap-6<?= $this->endSection() ?>

<?php // Sidebar navigation: highlights the Appointments section ?>
<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'appointments']) ?>
<?= $this->endSection() ?>

<?php // Page title and subtitle: dynamic based on user role ?>
<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?><?= $user_role === 'customer' ? 'View and manage your upcoming and past appointments' : 'Manage appointments for your business' ?><?= $this->endSection() ?>

<?php // Action button handled with filters; no standalone actions block ?>
<?= $this->section('dashboard_actions') ?><?= $this->endSection() ?>

<?php // Stats summary cards: condensed to Upcoming and Completed ?>
<?= $this->section('dashboard_stats') ?><?= $this->endSection() ?>

<?php // Filter controls and primary action with date picker alignment ?>
<?= $this->section('dashboard_filters') ?>
    <?php $upcomingCount = ($stats['pending'] ?? 0) + ($stats['today'] ?? 0); ?>
    <?php $completedCount = $stats['completed'] ?? 0; ?>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-wrap items-center gap-4 w-full lg:w-auto">
            <div class="stat-card min-w-[12rem] rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Upcoming Appointments</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"><?= $upcomingCount ?></p>
            </div>
            <div class="stat-card min-w-[12rem] rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed Appointments</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"><?= $completedCount ?></p>
            </div>
        </div>

        <div class="flex w-full flex-col gap-3 items-stretch lg:flex-1 lg:items-end">
            <div class="flex flex-wrap items-center gap-2 justify-start lg:justify-end">
                <!-- <button type="button" data-calendar-action="all" class="px-4 py-2 rounded-lg font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">All</button> -->
                <button type="button" data-calendar-action="today" class="px-4 py-2 rounded-lg font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Today</button>
                <button type="button" data-calendar-action="day" class="px-4 py-2 rounded-lg font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Day</button>
                <button type="button" data-calendar-action="week" class="px-4 py-2 rounded-lg font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Week</button>
                <button type="button" data-calendar-action="month" class="px-4 py-2 rounded-lg font-medium bg-blue-600 text-white shadow-sm hover:bg-blue-700 transition-colors">Month</button>
                <button class="px-4 py-2 rounded-lg font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Pending</button>
                <button class="px-4 py-2 rounded-lg font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Completed</button>
            </div>

                <?php if (has_role(['customer', 'staff', 'provider', 'admin'])): ?>
                <a href="<?= base_url('/appointments/create') ?>"
                    class="btn btn-primary inline-flex items-center justify-center gap-2 px-4 py-2 w-full sm:w-auto lg:self-end">
                <span class="material-symbols-outlined text-base">add</span>
                <?= $user_role === 'customer' ? 'Book Appointment' : 'New Appointment' ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <!-- Calendar Toolbar Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between" data-calendar-toolbar>
            <!-- Provider Legend -->
            <?php if (!empty($activeProviders) && count($activeProviders) > 0): ?>
            <div class="flex items-center gap-4 flex-wrap">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Providers:</span>
                <?php foreach ($activeProviders as $provider): ?>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-600" 
                              style="background-color: <?= esc($provider['color'] ?? '#3B82F6') ?>;"></span>
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            <?= esc($provider['first_name'] . ' ' . $provider['last_name']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div></div> <!-- Empty div for flex spacing when no providers -->
            <?php endif; ?>
            
            <!-- Navigation Controls -->
            <div class="flex items-center gap-2">
                <button type="button" data-calendar-action="prev"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                    <span class="material-symbols-outlined text-xl">chevron_left</span>
                </button>
                <h3 id="appointments-inline-calendar-title"
                    class="min-w-[12rem] text-center text-base font-semibold text-gray-900 dark:text-gray-100">
                    <?= esc(date('F Y', strtotime($selectedDate ?? date('Y-m-01')))) ?>
                </h3>
                <button type="button" data-calendar-action="next"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                    <span class="material-symbols-outlined text-xl">chevron_right</span>
                </button>
            </div>
        </div>

        <!-- Calendar Container -->
        <div
            id="appointments-inline-calendar"
            class="w-full"
            data-initial-date="<?= esc($selectedDate ?? date('Y-m-d')) ?>"
        ></div>
    </div>
<?= $this->endSection() ?>

<?php // Main content: appointment list with status, actions, and notes ?>
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
