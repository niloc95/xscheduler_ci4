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

    <!-- Two-tier toolbar.
         Mobile: everything stacks — nav controls, then status pills as a full-width wrapping row.
         Desktop md+:
           Tier 1 = Today + view switcher + date navigation (left)  ·  Filters (right)
           Tier 2 = status filter chips (left)  ·  provider legend (right)
         Both tiers share the same left edge for a clean, aligned toolbar. -->
    <div class="appointments-toolbar flex flex-col gap-3"
         data-status-filter-container data-active-status="<?= esc($activeStatusFilter ?? '') ?>">

        <!-- Tier 1: navigation + view controls (left) · Filters (right) -->
        <div class="flex flex-col gap-1.5 md:flex-row md:items-center md:justify-between md:gap-3">

        <!-- Nav cluster: 2-row column on mobile, horizontal row on desktop -->
        <div class="appointments-toolbar__primary flex flex-col gap-1.5 min-w-0 md:flex-row md:items-center md:gap-2">

            <!-- Row 1 (mobile): Today + view switcher -->
            <div class="flex items-center gap-2">
                <button type="button" data-calendar-action="today"
                        class="appointments-toolbar__today-btn inline-flex items-center justify-center px-4 py-2 rounded-full border border-gray-300 dark:border-gray-600 bg-white/95 dark:bg-gray-800 text-sm font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap shadow-sm hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-700 dark:hover:text-primary-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all duration-150">Today</button>

                <div class="appointments-toolbar__view-switcher inline-flex items-center rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 p-0.5 shadow-sm"
                     role="group" aria-label="Calendar view">
                    <button type="button" data-calendar-action="day"
                            class="appointments-toolbar__view-btn view-toggle-btn px-3.5 py-2 rounded-full text-sm font-semibold whitespace-nowrap text-gray-600 dark:text-gray-400 transition-all duration-150"
                            data-view="day">Day</button>
                    <button type="button" data-calendar-action="week"
                            class="appointments-toolbar__view-btn view-toggle-btn px-3.5 py-2 rounded-full text-sm font-semibold whitespace-nowrap text-gray-600 dark:text-gray-400 transition-all duration-150"
                            data-view="week">Week</button>
                    <button type="button" data-calendar-action="month"
                            class="appointments-toolbar__view-btn view-toggle-btn px-3.5 py-2 rounded-full text-sm font-semibold whitespace-nowrap text-gray-600 dark:text-gray-400 transition-all duration-150"
                            data-view="month">Month</button>
                </div>
            </div>

            <!-- Row 2 (mobile): date navigation -->
            <div class="appointments-toolbar__date-cluster inline-flex items-center gap-0.5 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-1.5 py-1 shadow-sm"
                 role="group" aria-label="Date navigation">
                <button type="button" data-calendar-action="prev"
                        class="p-1.5 rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                        title="Previous">
                    <span class="material-symbols-outlined text-base leading-none" style="font-variation-settings:'wght' 600;">chevron_left</span>
                </button>
                <div id="scheduler-date-display"
                     class="appointments-toolbar__date-display px-1.5 text-sm font-semibold text-gray-900 dark:text-white min-w-[104px] text-center select-none tracking-tight">
                    <?= date('F Y') ?>
                </div>
                <button type="button" data-calendar-action="next"
                        class="p-1.5 rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                        title="Next">
                    <span class="material-symbols-outlined text-base leading-none" style="font-variation-settings:'wght' 600;">chevron_right</span>
                </button>
                <input type="date" id="scheduler-date-input" class="sr-only" aria-label="Jump to date" tabindex="-1">
                <button type="button" data-calendar-action="open-datepicker"
                        class="p-1.5 rounded-full flex items-center text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                        title="Jump to date">
                    <span class="material-symbols-outlined text-base leading-none">event</span>
                </button>
            </div>

        </div>
        <!-- /Nav cluster -->

            <!-- Filters: desktop only, pinned to the right edge of Tier 1 -->
            <button type="button" id="advanced-filter-toggle" data-advanced-filter-toggle="true"
                    class="appointments-toolbar__filter hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium flex-shrink-0 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-700 dark:hover:text-primary-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all duration-150"
                    title="Advanced Filters">
                <span class="material-symbols-outlined text-sm leading-none">tune</span>
                <span>Filters</span>
                <span class="material-symbols-outlined text-sm leading-none transition-transform duration-200" data-filter-toggle-icon="true">expand_more</span>
            </button>

        </div>
        <!-- /Tier 1 -->

        <!-- Tier 2: status filter chips (left) · provider legend (right) -->
        <div class="appointments-toolbar__secondary flex flex-col gap-2 md:flex-row md:items-center md:justify-between md:gap-3">

            <div id="scheduler-stats-bar"
                 class="appointments-toolbar__status-rail flex flex-row flex-wrap items-center gap-1.5 md:gap-1"
                 data-initial-pending="<?= $pendingCount ?>"
                 data-initial-confirmed="<?= $confirmedCount ?>"
                 data-initial-completed="<?= $completedCount ?>"
                 data-initial-cancelled="<?= $cancelledCount ?>"
                 data-initial-noshow="<?= $noshowCount ?>">
                <?= view('components/status_badge', ['status' => 'pending',   'label' => 'Pending',   'count' => $pendingCount]) ?>
                <?= view('components/status_badge', ['status' => 'confirmed', 'label' => 'Confirmed', 'count' => $confirmedCount]) ?>
                <?= view('components/status_badge', ['status' => 'completed', 'label' => 'Done',      'count' => $completedCount]) ?>
                <?= view('components/status_badge', ['status' => 'cancelled', 'label' => 'Cancelled', 'count' => $cancelledCount]) ?>
                <?= view('components/status_badge', ['status' => 'noshow',    'label' => 'No-Show',   'count' => $noshowCount]) ?>
            </div>

            <!-- Provider legend: hidden on mobile, right-aligned on desktop; JS populates & reveals -->
            <div id="provider-legend" class="hidden md:flex md:flex-shrink-0 items-center justify-end gap-2 flex-wrap text-xs"></div>

        </div>
        <!-- /Tier 2 -->

    </div>
    <!-- /appointments-toolbar -->

    <!-- Advanced Filter Panel (collapsible) -->
    <!-- Grid: 1 col on mobile (selects full-width), 2 on sm, 3 on md, 6 on lg -->
    <div id="advanced-filter-panel" class="hidden mt-2 p-3 bg-gray-50/80 dark:bg-gray-700/30 rounded-lg border border-gray-200 dark:border-gray-600">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
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
            <!-- Apply/Clear: full-width on mobile, spans 2 cols on sm, 1 on md, 2 on lg -->
            <div class="flex items-end gap-2 sm:col-span-2 md:col-span-1 lg:col-span-2">
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
