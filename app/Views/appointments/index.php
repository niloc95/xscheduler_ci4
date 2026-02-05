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

$calendarPrototypeAssets = null;
if (!empty($calendarPrototype['enabled']) && !empty($calendarPrototype['bootstrap'])) {
    $manifestPath = FCPATH . 'build/.vite/manifest.json';
    if (is_file($manifestPath)) {
        helper('vite');
        $prototypeEntry = 'resources/js/calendar-prototype.js';
        try {
            $calendarPrototypeAssets = [
                'js' => vite_js($prototypeEntry),
                'css' => vite_css($prototypeEntry),
            ];
        } catch (\Throwable $exception) {
            log_message('error', 'Calendar prototype assets unavailable: ' . $exception->getMessage());
            $calendarPrototypeAssets = null;
        }
    }
}
?>
<?= $this->extend('layouts/dashboard') ?>

<?php if (!empty($calendarPrototypeAssets['css'])): ?>
<?= $this->section('head') ?>
    <?php foreach ($calendarPrototypeAssets['css'] as $href): ?>
        <link rel="stylesheet" href="<?= esc($href) ?>">
    <?php endforeach; ?>
<?= $this->endSection() ?>
<?php endif; ?>

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
    <?php $upcomingCount = ($stats['pending'] ?? 0) + ($stats['confirmed'] ?? 0) + ($stats['today'] ?? 0); ?>
    <?php $completedCount = $stats['completed'] ?? 0; ?>
    <?php $pendingCount = $stats['pending'] ?? 0; ?>
    <?php $confirmedCount = $stats['confirmed'] ?? 0; ?>
    <?php $cancelledCount = $stats['cancelled'] ?? 0; ?>
    <?php $noshowCount = $stats['noshow'] ?? 0; ?>

    <!-- Stats Bar Container - Rendered by Stats Engine (JS) -->
    <!-- Initial server-rendered values, replaced by JS on load -->
    <div id="scheduler-stats-bar" 
         class="mb-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden"
         data-initial-pending="<?= $pendingCount ?>"
         data-initial-confirmed="<?= $confirmedCount ?>"
         data-initial-completed="<?= $completedCount ?>"
         data-initial-cancelled="<?= $cancelledCount ?>"
         data-initial-noshow="<?= $noshowCount ?>">
        <!-- Fallback content before JS loads -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">calendar_today</span>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Today's Summary</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= date('l, F j, Y') ?></p>
                </div>
            </div>
            <span class="text-2xl font-bold text-gray-900 dark:text-white"><?= $upcomingCount + $completedCount ?></span>
        </div>
        <div class="p-4 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border bg-amber-100 border-amber-300 text-amber-900 dark:bg-amber-900/30 dark:border-amber-500 dark:text-amber-100">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span class="text-xs font-medium">Pending</span>
                <span class="text-xs font-bold"><?= $pendingCount ?></span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border bg-blue-100 border-blue-300 text-blue-900 dark:bg-blue-900/30 dark:border-blue-500 dark:text-blue-100">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span class="text-xs font-medium">Confirmed</span>
                <span class="text-xs font-bold"><?= $confirmedCount ?></span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border bg-emerald-100 border-emerald-300 text-emerald-900 dark:bg-emerald-900/30 dark:border-emerald-500 dark:text-emerald-100">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-xs font-medium">Completed</span>
                <span class="text-xs font-bold"><?= $completedCount ?></span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border bg-red-100 border-red-300 text-red-900 dark:bg-red-900/30 dark:border-red-500 dark:text-red-100">
                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                <span class="text-xs font-medium">Cancelled</span>
                <span class="text-xs font-bold"><?= $cancelledCount ?></span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border bg-gray-100 border-gray-300 text-gray-900 dark:bg-gray-700/50 dark:border-gray-500 dark:text-gray-100">
                <span class="w-2 h-2 rounded-full bg-gray-500"></span>
                <span class="text-xs font-medium">No-Show</span>
                <span class="text-xs font-bold"><?= $noshowCount ?></span>
            </span>
        </div>
    </div>

    <!-- View Controls and Action Button Row -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <!-- View Toggle Buttons (Day/Week/Month) - HIDDEN: Week view only for now -->
        <div class="flex flex-wrap items-center gap-2" data-status-filter-container data-active-status="<?= esc($activeStatusFilter ?? '') ?>">
            <?php /* View toggle buttons temporarily hidden - Week view is default
            <button type="button" data-calendar-action="today" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm">Today</button>
            <button type="button" data-calendar-action="day" class="view-toggle-btn px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm" data-view="day">Day</button>
            <button type="button" data-calendar-action="week" class="view-toggle-btn px-3 py-1.5 rounded-lg font-medium text-sm bg-blue-600 text-white shadow-sm hover:bg-blue-700 hover:shadow-md transition-all duration-200" data-view="week">Week</button>
            <button type="button" data-calendar-action="month" class="view-toggle-btn px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm" data-view="month">Month</button>
            */ ?>
            
            <!-- Advanced Filter Toggle -->
            <button type="button" 
                    id="advanced-filter-toggle"
                    class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all duration-200 hover:shadow-sm inline-flex items-center gap-1"
                    title="Advanced Filters">
                <span class="material-symbols-outlined text-base">filter_alt</span>
                Filters
                <span class="material-symbols-outlined text-base transition-transform duration-200" id="filter-toggle-icon">expand_more</span>
            </button>
        </div>

        <?php if (has_role(['customer', 'staff', 'provider', 'admin'])): ?>
        <a href="<?= base_url('/appointments/create') ?>"
            class="btn btn-primary inline-flex items-center justify-center gap-2 px-4 py-2">
            <span class="material-symbols-outlined text-base">add</span>
            <?= $user_role === 'customer' ? 'Book Appointment' : 'New Appointment' ?>
        </a>
        <?php endif; ?>
    </div>

    <!-- Advanced Filter Panel -->
    <div id="advanced-filter-panel" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 hidden">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                <select id="filter-status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= ($currentFilters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= ($currentFilters['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= ($currentFilters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= ($currentFilters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="no-show" <?= ($currentFilters['status'] ?? '') === 'no-show' ? 'selected' : '' ?>>No Show</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Provider</label>
                <select id="filter-provider" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm">
                    <option value="">All Providers</option>
                    <?php foreach ($allProviders ?? [] as $provider): ?>
                        <option value="<?= esc($provider['id']) ?>" <?= ($currentFilters['provider_id'] ?? '') == $provider['id'] ? 'selected' : '' ?>><?= esc($provider['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service</label>
                <select id="filter-service" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm">
                    <option value="">All Services</option>
                    <?php foreach ($allServices ?? [] as $service): ?>
                        <option value="<?= esc($service['id']) ?>" <?= ($currentFilters['service_id'] ?? '') == $service['id'] ? 'selected' : '' ?>><?= esc($service['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="button" id="apply-filters-btn" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium inline-flex items-center justify-center gap-1 transition-colors">
                    <span class="material-symbols-outlined text-base">filter_alt</span>
                    Apply
                </button>
                <button type="button" id="clear-filters-btn" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-800 dark:text-gray-100 rounded-lg text-sm font-medium transition-colors">
                    Clear
                </button>
            </div>
        </div>
    </div>

    <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <!-- Calendar Toolbar -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4">
            <!-- Navigation Controls -->
            <div class="flex items-center gap-2">
                <button type="button" data-calendar-action="prev" 
                        class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Previous">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                
                <!-- Current Date Display (dynamically updated by JavaScript) -->
                <div id="scheduler-date-display" class="text-lg font-semibold text-gray-900 dark:text-white">
                    <?= date('F Y') ?>
                </div>
                
                <button type="button" data-calendar-action="next" 
                        class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Next">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>

            <!-- Provider Legend (will be populated by JavaScript) - Hidden on mobile -->
            <div id="provider-legend" class="hidden md:flex items-center gap-2 flex-wrap">
                <!-- Dynamically populated -->
            </div>
        </div>
        
        <!-- Calendar Container -->
        <div
            id="appointments-inline-calendar"
            class="w-full"
            data-initial-date="<?= esc($selectedDate ?? date('Y-m-d')) ?>"
            data-active-status="<?= esc($activeStatusFilter ?? '') ?>"
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

<?php if (!empty($calendarPrototype['enabled']) && !empty($calendarPrototype['bootstrap'])): ?>
<?= $this->section('extra_js') ?>
<script>
    window.__calendarPrototype = {
        enabled: true,
        feature: <?= json_encode($calendarPrototype['featureKey'] ?? 'calendar_prototype') ?>,
        endpoints: <?= json_encode($calendarPrototype['endpoints'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    };
    window.__calendarBootstrap = <?= json_encode($calendarPrototype['bootstrap'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php if (!empty($calendarPrototypeAssets['js'])): ?>
<script type="module" src="<?= esc($calendarPrototypeAssets['js']) ?>"></script>
<?php endif; ?>
<?= $this->endSection() ?>
<?php endif; ?>
