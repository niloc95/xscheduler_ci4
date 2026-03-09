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

<?php // Filters section empty — controls are in the fixed header now ?>
<?= $this->section('dashboard_filters') ?><?= $this->endSection() ?>

<?php // Controls injected into the fixed header bar so they never scroll away ?>
<?= $this->section('header_controls') ?>
    <?php $pendingCount = $stats['pending'] ?? 0; ?>
    <?php $confirmedCount = $stats['confirmed'] ?? 0; ?>
    <?php $completedCount = $stats['completed'] ?? 0; ?>
    <?php $cancelledCount = $stats['cancelled'] ?? 0; ?>
    <?php $noshowCount = $stats['noshow'] ?? 0; ?>
    <?php
    $statusOptions = [
        ['value' => '', 'label' => 'All'],
        ['value' => 'pending', 'label' => 'Pending'],
        ['value' => 'confirmed', 'label' => 'Confirmed'],
        ['value' => 'completed', 'label' => 'Completed'],
        ['value' => 'cancelled', 'label' => 'Cancelled'],
        ['value' => 'no-show', 'label' => 'No Show'],
    ];

    $providerOptions = [['value' => '', 'label' => 'All']];
    foreach ($allProviders ?? [] as $provider) {
        $providerOptions[] = [
            'value' => (string) ($provider['id'] ?? ''),
            'label' => (string) ($provider['name'] ?? ''),
        ];
    }

    $serviceOptions = [['value' => '', 'label' => 'All']];
    foreach ($allServices ?? [] as $service) {
        $serviceOptions[] = [
            'value' => (string) ($service['id'] ?? ''),
            'label' => (string) ($service['name'] ?? ''),
        ];
    }

    $locationOptions = [['value' => '', 'label' => 'All']];
    foreach ($allLocations ?? [] as $location) {
        $locationOptions[] = [
            'value' => (string) ($location['id'] ?? ''),
            'label' => (string) ($location['name'] ?? ''),
        ];
    }
    ?>

    <!-- Row 1: View Toggles | Centered Date Nav | Filters + New -->
    <div class="flex items-center gap-2">
        <!-- View Toggle Buttons -->
        <div class="flex items-center p-1 bg-surface-1 dark:bg-gray-800/50 rounded-xl" data-status-filter-container data-active-status="<?= esc($activeStatusFilter ?? '') ?>">
            <button type="button" data-calendar-action="today" class="px-3 py-1.5 rounded-lg font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-surface-2 dark:hover:bg-gray-700 transition-colors">Today</button>
            <div class="w-px h-4 bg-gray-300 dark:bg-gray-600 mx-1"></div>
            <button type="button" data-calendar-action="day" class="view-toggle-btn px-3 py-1.5 rounded-lg font-medium text-sm bg-surface-0 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-surface-2 dark:hover:bg-gray-600 transition-colors" data-view="day">Day</button>
            <button type="button" data-calendar-action="week" class="view-toggle-btn px-3 py-1.5 rounded-lg font-medium text-sm bg-primary-600 text-white shadow-sm hover:bg-primary-700 transition-colors" data-view="week">Week</button>
            <button type="button" data-calendar-action="month" class="view-toggle-btn px-3 py-1.5 rounded-lg font-medium text-sm bg-surface-0 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-surface-2 dark:hover:bg-gray-600 transition-colors" data-view="month">Month</button>
        </div>

        <!-- Centered Date Navigation -->
        <div class="flex-1 flex items-center justify-center gap-1">
            <button type="button" data-calendar-action="prev"
                    class="p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    title="Previous">
                <span class="material-symbols-outlined text-xl">chevron_left</span>
            </button>
            <div id="scheduler-date-display" class="text-sm font-semibold text-gray-900 dark:text-white min-w-[160px] text-center select-none">
                <?= date('F Y') ?>
            </div>
            <button type="button" data-calendar-action="next"
                    class="p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    title="Next">
                <span class="material-symbols-outlined text-xl">chevron_right</span>
            </button>
        </div>

        <!-- Right: Filters -->
        <div class="flex items-center gap-2">
            <button type="button"
                    id="advanced-filter-toggle"
                    class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors inline-flex items-center gap-1.5"
                    title="Advanced Filters">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                <span class="hidden sm:inline">Filters</span>
                <span class="material-symbols-outlined text-base transition-transform duration-200" id="filter-toggle-icon">expand_more</span>
            </button>
        </div>
    </div>

    <!-- Row 2: Stats Badges + Provider Legend -->
    <div class="flex items-center gap-2 mt-2">
        <div id="scheduler-stats-bar"
             class="flex flex-wrap items-center gap-1.5"
             data-initial-pending="<?= $pendingCount ?>"
             data-initial-confirmed="<?= $confirmedCount ?>"
             data-initial-completed="<?= $completedCount ?>"
             data-initial-cancelled="<?= $cancelledCount ?>"
             data-initial-noshow="<?= $noshowCount ?>">
            <?= view('components/status_badge', ['status' => 'pending', 'label' => 'Pending', 'count' => $pendingCount]) ?>
            <?= view('components/status_badge', ['status' => 'confirmed', 'label' => 'Confirmed', 'count' => $confirmedCount]) ?>
            <?= view('components/status_badge', ['status' => 'completed', 'label' => 'Done', 'count' => $completedCount]) ?>
            <?= view('components/status_badge', ['status' => 'cancelled', 'label' => 'Cancelled', 'count' => $cancelledCount]) ?>
            <?= view('components/status_badge', ['status' => 'noshow', 'label' => 'No-Show', 'count' => $noshowCount, 'class' => 'hidden sm:inline-flex']) ?>
        </div>
        <div class="flex-1"></div>
        <!-- Provider Legend (populated by JavaScript) -->
        <div id="provider-legend" class="hidden md:flex items-center gap-2 flex-wrap text-xs"></div>
    </div>

    <!-- Advanced Filter Panel (collapsible) -->
    <div id="advanced-filter-panel" class="hidden mt-2 p-3 bg-gray-50/80 dark:bg-gray-700/30 rounded-lg border border-gray-200 dark:border-gray-600">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
            <div>
                <?= view('components/select', [
                    'id' => 'filter-status',
                    'name' => 'filter_status',
                    'label' => 'Status',
                    'options' => $statusOptions,
                    'value' => (string) ($currentFilters['status'] ?? ''),
                ]) ?>
            </div>
            <div>
                <?= view('components/select', [
                    'id' => 'filter-provider',
                    'name' => 'filter_provider',
                    'label' => 'Provider',
                    'options' => $providerOptions,
                    'value' => (string) ($currentFilters['provider_id'] ?? ''),
                ]) ?>
            </div>
            <div>
                <?= view('components/select', [
                    'id' => 'filter-service',
                    'name' => 'filter_service',
                    'label' => 'Service',
                    'options' => $serviceOptions,
                    'value' => (string) ($currentFilters['service_id'] ?? ''),
                ]) ?>
            </div>
            <div>
                <?= view('components/select', [
                    'id' => 'filter-location',
                    'name' => 'filter_location',
                    'label' => 'Location',
                    'options' => $locationOptions,
                    'value' => (string) ($currentFilters['location_id'] ?? ''),
                ]) ?>
            </div>
            <div class="flex items-end gap-2 col-span-2 md:col-span-1 lg:col-span-2">
                <?= view('components/button', [
                    'label' => 'Apply',
                    'icon' => 'filter_alt',
                    'variant' => 'filled',
                    'size' => 'sm',
                    'class' => 'flex-1',
                    'attrs' => ['id' => 'apply-filters-btn'],
                ]) ?>
                <?= view('components/button', [
                    'label' => 'Clear',
                    'variant' => 'tonal',
                    'size' => 'sm',
                    'attrs' => ['id' => 'clear-filters-btn'],
                ]) ?>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?php // Split-panel scheduler content (new layout) ?>
<?= $this->section('dashboard_content') ?>
    <!-- Scheduler Shell: Split-panel Layout -->
    <div class="scheduler-shell rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        
        <!-- Content Split: 62% Left (scrollable) + 38% Right (fixed) -->
        <div class="content-split">
            
            <!-- Left Pane: Calendar View (Today/Day/Week/Month) -->
            <div class="cal-pane" id="appointments-inline-calendar"
                 data-initial-date="<?= esc($selectedDate ?? date('Y-m-d')) ?>"
                 data-active-status="<?= esc($activeStatusFilter ?? '') ?>">
                <!-- Calendar content injected by SchedulerCore -->
                <div class="p-8 text-center">
                    <div class="loading-spinner mx-auto mb-3"></div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Loading scheduler...</p>
                </div>
            </div>
            
            <!-- Right Panel: Provider Cards + Slot Grid -->
            <div class="right-panel" id="scheduler-right-panel">
                <!-- Header Section -->
                <div class="rp-header">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white" id="rp-title">
                        Providers
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="rp-subtitle">
                        Select a provider to view availability
                    </p>
                </div>
                
                <!-- Body Section (Scrollable) -->
                <div class="rp-body" id="rp-body">
                    <!-- Provider cards + slot grid injected by RightPanel module -->
                    <div class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">
                        Loading...
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Daily Appointments Section (kept for backward compatibility) -->
    <div id="daily-provider-appointments" class="hidden rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mt-4">
        <div class="p-8 text-center">
            <div class="loading-spinner mx-auto mb-3"></div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Loading appointments...</p>
        </div>
    </div>
<?= $this->endSection() ?>
