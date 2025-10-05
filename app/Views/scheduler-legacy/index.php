<!--
    ⚠️ DEPRECATED: Legacy Scheduler View (FullCalendar-based)
    
    This view template is being phased out in favor of the new Appointments interface.
    Status: Active but will be replaced
    Timeline: Maintained until new Appointment View reaches feature parity
    
    DO NOT add new features to this template.
    Only bug fixes and security updates will be applied.
    
    See: docs/architecture/LEGACY_SCHEDULER_ARCHITECTURE.md
    Replacement: app/Views/appointments/index.php (In Development)
    
    Last Updated: October 5, 2025
-->
<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'schedule']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Schedule<?= $this->endSection() ?>

<?= $this->section('head') ?>
    <script type="module" src="<?= base_url('build/assets/scheduler-dashboard.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div
    id="scheduler-dashboard"
    class="main-content space-y-6"
    data-page-title="Scheduler"
    data-page-subtitle="Manage appointments, availability, and bookings"
    data-api-base="<?= base_url('api/v1') ?>"
    data-slots-url="<?= base_url('api/slots') ?>"
>
    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Scheduler Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Keep track of today’s workload, manage upcoming commitments, and respond quickly to new requests.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button id="scheduler-new" type="button" class="btn btn-primary">
                <span class="material-symbols-outlined text-base">add</span>
                New Appointment
            </button>
            <button id="scheduler-refresh" type="button" class="btn btn-secondary">
                <span class="material-symbols-outlined text-base">refresh</span>
                Refresh
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3" id="scheduler-summary">
        <div role="button" tabindex="0" data-target-view="day" class="card card-interactive group rounded-2xl border border-transparent bg-gradient-to-r from-blue-600 to-indigo-600 p-5 text-left text-white shadow-lg transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2" aria-label="Show today’s appointments">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide text-white/80">Today’s Appointments</p>
                    <p class="mt-2 text-3xl font-semibold"><span id="scheduler-count-today">0</span></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-white/70 transition group-hover:scale-105">calendar_today</span>
            </div>
            <p class="mt-4 text-xs text-white/70">Click to jump into Day view and review each booking.</p>
        </div>

        <div role="button" tabindex="0" data-target-view="week" class="card card-interactive group rounded-2xl border border-transparent bg-gradient-to-r from-amber-500 to-orange-500 p-5 text-left text-white shadow-lg transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2" aria-label="Show this week’s appointments">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide text-white/80">This Week’s Appointments</p>
                    <p class="mt-2 text-3xl font-semibold"><span id="scheduler-count-week">0</span></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-white/70 transition group-hover:scale-105">calendar_view_week</span>
            </div>
            <p class="mt-4 text-xs text-white/70">Review the weekly mix of services and provider availability.</p>
        </div>

        <div role="button" tabindex="0" data-target-view="month" class="card card-interactive group rounded-2xl border border-transparent bg-gradient-to-r from-purple-600 to-fuchsia-600 p-5 text-left text-white shadow-lg transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2" aria-label="Show this month’s appointments">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide text-white/80">This Month’s Appointments</p>
                    <p class="mt-2 text-3xl font-semibold"><span id="scheduler-count-month">0</span></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-white/70 transition group-hover:scale-105">calendar_month</span>
            </div>
            <p class="mt-4 text-xs text-white/70">Zoom out to evaluate broader trends and pacing.</p>
        </div>
    </div>

    	<div class="card card-spacious">
    	<div class="card-body space-y-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" id="scheduler-filters">
                <label class="flex flex-col text-sm font-medium text-gray-700 dark:text-gray-300">
                    <span class="mb-2 flex items-center gap-2 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <span class="material-symbols-outlined text-base">supervisor_account</span>
                        Provider
                    </span>
                    <select id="scheduler-filter-provider" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">All providers</option>
                        <?php foreach (($providers ?? []) as $provider): ?>
                            <option value="<?= esc($provider['id']) ?>"><?= esc($provider['name'] ?? ($provider['email'] ?? 'Provider #'.$provider['id'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="flex flex-col text-sm font-medium text-gray-700 dark:text-gray-300">
                    <span class="mb-2 flex items-center gap-2 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <span class="material-symbols-outlined text-base">medical_services</span>
                        Service
                    </span>
                    <select id="scheduler-filter-service" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">All services</option>
                        <?php foreach (($services ?? []) as $service): ?>
                            <option value="<?= esc($service['id']) ?>"><?= esc($service['name'] ?? 'Service #'.$service['id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="flex flex-col text-sm font-medium text-gray-700 dark:text-gray-300">
                    <span class="mb-2 flex items-center gap-2 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <span class="material-symbols-outlined text-base">event</span>
                        Focus Date
                    </span>
                    <input id="scheduler-focus-date" type="date" value="<?= esc(date('Y-m-d')) ?>" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                </label>

            </div>

            <div class="flex flex-wrap gap-2">
                <button id="scheduler-clear" type="button" class="btn btn-secondary">
                    <span class="material-symbols-outlined text-base">backspace</span>
                    Clear Filters
                </button>
                <button id="scheduler-apply" type="button" class="btn btn-primary">
                    <span class="material-symbols-outlined text-base">filter_alt</span>
                    Apply Filters
                </button>
            </div>
        </div>
        <p id="scheduler-filter-feedback" class="hidden text-sm text-gray-500 dark:text-gray-400"></p>
        </div>
    </div>

	<div class="card card-spacious">
        <div class="card-header flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <span class="material-symbols-outlined text-base">schedule</span>
                    <span id="scheduler-active-range">Loading current range…</span>
                </div>
                <h2 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">Calendar</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <button type="button" id="scheduler-prev" class="btn btn-ghost btn-sm px-3 py-2">
                        <span class="material-symbols-outlined text-base">chevron_left</span>
                    </button>
                    <button type="button" id="scheduler-today" class="btn btn-secondary btn-sm px-3 py-2">Today</button>
                    <button type="button" id="scheduler-next" class="btn btn-ghost btn-sm px-3 py-2">
                        <span class="material-symbols-outlined text-base">chevron_right</span>
                    </button>
                </div>
                <div class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <button type="button" data-view="day" class="scheduler-view btn btn-ghost btn-sm px-4 py-2">Day</button>
                    <button type="button" data-view="week" class="scheduler-view btn btn-ghost btn-sm px-4 py-2">Week</button>
                    <button type="button" data-view="month" class="scheduler-view btn btn-ghost btn-sm px-4 py-2">Month</button>
                </div>
            </div>
        </div>

        <div class="card-body space-y-4">
        <div id="scheduler-calendar" class="min-h-[520px] rounded-2xl border border-dashed border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"></div>

        <div id="scheduler-status" class="hidden">
            <div id="scheduler-status-alert" class="card card-compact card-muted flex items-start gap-3 border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                <span class="material-symbols-outlined text-lg text-gray-400 dark:text-gray-500" data-status-icon>info</span>
                <div class="flex flex-col gap-1">
                    <span data-status-label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</span>
                    <span id="scheduler-status-message" class="text-sm font-medium text-gray-700 dark:text-gray-200"></span>
                </div>
            </div>
        </div>
        </div>
    </div>

    <div class="card card-spacious">
        <div class="card-header flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Quick Slots</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Snapshot of next openings based on the filters above.</p>
            </div>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium uppercase tracking-wide text-gray-600 dark:border-gray-600 dark:bg-gray-900/60 dark:text-gray-300">
                <span class="material-symbols-outlined text-sm">update</span>
                <span id="scheduler-slots-caption">Awaiting filters…</span>
            </div>
        </div>

        <div class="card-body space-y-4">
        <div id="scheduler-slots" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3"></div>
        <div id="scheduler-slots-empty" class="card card-compact card-muted border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300">
            Choose a provider and service, then pick Apply Filters to load availability.
        </div>
        </div>
    </div>
</div>

<div
    id="scheduler-modal"
    class="fixed inset-0 z-[320] hidden items-center justify-center bg-black/60 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="scheduler-modal-title"
    aria-hidden="true"
    tabindex="-1"
>
    <div class="w-full max-w-xl rounded-3xl bg-white shadow-2xl dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
            <h3 id="scheduler-modal-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Appointment</h3>
            <button type="button" id="scheduler-modal-close" class="rounded-full p-2 text-gray-500 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 dark:text-gray-300 dark:hover:bg-gray-800">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>
        <div id="scheduler-modal-body" class="max-h-[70vh] overflow-y-auto p-4"></div>
        <div id="scheduler-modal-footer" class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 p-4 dark:border-gray-700"></div>
    </div>
</div>

<template id="scheduler-slot-template">
    <article class="card card-compact card-interactive flex items-center justify-between border border-gray-200 bg-white p-4 transition hover:border-blue-400 dark:border-gray-700 dark:bg-gray-900">
        <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100" data-slot-time>--:-- – --:--</p>
            <p class="text-xs text-gray-500 dark:text-gray-400" data-slot-notes></p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-slot-book>
            <span class="material-symbols-outlined text-sm">event_available</span>
            Book
        </button>
    </article>
</template>

<template id="scheduler-summary-template">
    <div class="flex items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-600 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
        <span class="material-symbols-outlined text-base">info</span>
        <span data-summary-text>Summary</span>
    </div>
</template>

<?= $this->endSection() ?>

