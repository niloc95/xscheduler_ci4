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
                <!-- View Selection Buttons - Material Design 3 Styled -->
                <button type="button" data-calendar-action="today" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm">Today</button>
                <button type="button" data-calendar-action="day" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm">Day</button>
                <button type="button" data-calendar-action="week" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm">Week</button>
                <button type="button" data-calendar-action="month" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-blue-600 text-white shadow-sm hover:bg-blue-700 hover:shadow-md transition-all duration-200">Month</button>
                
                <!-- Status Filter Buttons -->
                <button type="button" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm" title="Show pending appointments">Pending</button>
                <button type="button" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm" title="Show completed appointments">Completed</button>
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
        <!-- Calendar Toolbar -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4">
            <!-- Navigation Controls -->
            <div class="flex items-center gap-2">
                <button type="button" data-calendar-action="prev" 
                        class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Previous">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button type="button" data-calendar-action="next" 
                        class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Next">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>

            <!-- Current Date Display -->
            <div id="scheduler-date-display" class="text-lg font-semibold text-gray-900 dark:text-white">
                <?= date('F Y') ?>
            </div>

            <!-- Provider Legend (will be populated by JavaScript) -->
            <div id="provider-legend" class="flex items-center gap-2 flex-wrap">
                <!-- Dynamically populated -->
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

<?php // Main content: Dynamic daily provider appointments (JavaScript-rendered) ?>
<?= $this->section('dashboard_content') ?>
    <!-- Daily Appointments Section - Dynamically populated by JavaScript -->
    <div id="daily-provider-appointments" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Loading State -->
        <div class="p-12 text-center">
            <div class="loading-spinner mx-auto mb-4"></div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Loading appointments...</p>
        </div>
    </div>
<?= $this->endSection() ?>
