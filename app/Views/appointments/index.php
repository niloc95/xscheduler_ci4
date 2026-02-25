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

<?php // Filters section empty — controls are in the fixed header now ?>
<?= $this->section('dashboard_filters') ?><?= $this->endSection() ?>

<?php // Controls injected into the fixed header bar so they never scroll away ?>
<?= $this->section('header_controls') ?>
    <?php $pendingCount = $stats['pending'] ?? 0; ?>
    <?php $confirmedCount = $stats['confirmed'] ?? 0; ?>
    <?php $completedCount = $stats['completed'] ?? 0; ?>
    <?php $cancelledCount = $stats['cancelled'] ?? 0; ?>
    <?php $noshowCount = $stats['noshow'] ?? 0; ?>

    <!-- Row 1: View Toggles | Centered Date Nav | Filters + New -->
    <div class="flex items-center gap-2">
        <!-- View Toggle Buttons -->
        <div class="flex items-center gap-1.5" data-status-filter-container data-active-status="<?= esc($activeStatusFilter ?? '') ?>">
            <button type="button" data-calendar-action="today" class="px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">Today</button>
            <button type="button" data-calendar-action="week" class="view-toggle-btn px-3 py-1.5 rounded-lg font-medium text-sm bg-blue-600 text-white shadow-sm hover:bg-blue-700 transition-colors" data-view="week">Week</button>
            <button type="button" data-calendar-action="month" class="view-toggle-btn px-3 py-1.5 rounded-lg font-medium text-sm bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors" data-view="month">Month</button>
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
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-200">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                <span class="font-medium"><?= $pendingCount ?></span> Pending
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-600 dark:text-blue-200">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                <span class="font-medium"><?= $confirmedCount ?></span> Confirmed
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-600 dark:text-emerald-200">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span class="font-medium"><?= $completedCount ?></span> Done
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-600 dark:text-red-200">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                <span class="font-medium"><?= $cancelledCount ?></span> Cancelled
            </span>
            <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs bg-gray-50 border-gray-200 text-gray-600 dark:bg-gray-700/50 dark:border-gray-600 dark:text-gray-300">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                <span class="font-medium"><?= $noshowCount ?></span> No-Show
            </span>
        </div>
        <div class="flex-1"></div>
        <!-- Provider Legend (populated by JavaScript) -->
        <div id="provider-legend" class="hidden md:flex items-center gap-2 flex-wrap text-xs"></div>
    </div>

    <!-- Advanced Filter Panel (collapsible) -->
    <div id="advanced-filter-panel" class="hidden mt-2 p-3 bg-gray-50/80 dark:bg-gray-700/30 rounded-lg border border-gray-200 dark:border-gray-600">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Status</label>
                <select id="filter-status" class="w-full px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm">
                    <option value="">All</option>
                    <option value="pending" <?= ($currentFilters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= ($currentFilters['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= ($currentFilters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= ($currentFilters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="no-show" <?= ($currentFilters['status'] ?? '') === 'no-show' ? 'selected' : '' ?>>No Show</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Provider</label>
                <select id="filter-provider" class="w-full px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm">
                    <option value="">All</option>
                    <?php foreach ($allProviders ?? [] as $provider): ?>
                        <option value="<?= esc($provider['id']) ?>" <?= ($currentFilters['provider_id'] ?? '') == $provider['id'] ? 'selected' : '' ?>><?= esc($provider['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Service</label>
                <select id="filter-service" class="w-full px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm">
                    <option value="">All</option>
                    <?php foreach ($allServices ?? [] as $service): ?>
                        <option value="<?= esc($service['id']) ?>" <?= ($currentFilters['service_id'] ?? '') == $service['id'] ? 'selected' : '' ?>><?= esc($service['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Location</label>
                <select id="filter-location" class="w-full px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm">
                    <option value="">All</option>
                    <?php foreach ($allLocations ?? [] as $location): ?>
                        <option value="<?= esc($location['id']) ?>" <?= ($currentFilters['location_id'] ?? '') == $location['id'] ? 'selected' : '' ?>><?= esc($location['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end gap-2 col-span-2 md:col-span-1 lg:col-span-2">
                <button type="button" id="apply-filters-btn" class="flex-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium inline-flex items-center justify-center gap-1 transition-colors">
                    <span class="material-symbols-outlined text-base">filter_alt</span>
                    Apply
                </button>
                <button type="button" id="clear-filters-btn" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-md text-sm font-medium transition-colors">
                    Clear
                </button>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?php // Calendar and appointments content (scrollable area below fixed header) ?>
<?= $this->section('dashboard_content') ?>
    <!-- Calendar Container -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div
            id="appointments-inline-calendar"
            class="w-full"
            data-initial-date="<?= esc($selectedDate ?? date('Y-m-d')) ?>"
            data-active-status="<?= esc($activeStatusFilter ?? '') ?>"
        ></div>
    </div>

    <!-- Daily Appointments Section -->
    <div id="daily-provider-appointments" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-8 text-center">
            <div class="loading-spinner mx-auto mb-3"></div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Loading appointments...</p>
        </div>
    </div>
<?= $this->endSection() ?>

<?php if (!empty($calendarPrototype['enabled']) && !empty($calendarPrototype['bootstrap'])): ?>
<?= $this->section('scripts') ?>
<script>
    window.__calendarPrototype = {
        enabled: true,
        feature: <?= json_encode($calendarPrototype['featureKey'] ?? 'calendar_prototype') ?>,
        endpoints: <?= json_encode($calendarPrototype['endpoints'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    };
    window.__calendarBootstrap = <?= json_encode($calendarPrototype['bootstrap'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    <?php if (!empty($calendarPrototypeAssets['js'])): ?>
    // Dynamic import — works in both regular page loads and SPA-injected scripts
    (async function initCalendarPrototype() {
        try {
            const module = await import('<?= esc($calendarPrototypeAssets['js']) ?>');
            if (typeof module.default === 'function') module.default();
            console.log('[Calendar] Prototype module loaded');
        } catch (err) {
            console.warn('[Calendar] Prototype module unavailable:', err.message);
        }
    })();
    <?php endif; ?>
</script>
<?= $this->endSection() ?>
<?php endif; ?>
