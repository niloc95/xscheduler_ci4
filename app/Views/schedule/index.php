<?= $this->extend('components/layout') ?>
<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'schedule']) ?>
<?= $this->endSection() ?>
<?= $this->section('title') ?>Schedule<?= $this->endSection() ?>
<?= $this->section('head') ?>
<script>
// Fallback: detect if core stylesheet failed to apply (rare direct-load issue) and attempt reinjection
document.addEventListener('DOMContentLoaded', function(){
    try {
        const test = window.getComputedStyle(document.body).backgroundColor;
        // If value is transparent or blank (some UA return 'rgba(0, 0, 0, 0)' when no styles applied), try reinject
        if (!test || test === 'transparent' || test === 'rgba(0, 0, 0, 0)') {
            console.warn('[schedule] Detected missing global styles; attempting fallback reinjection');
            if (!document.querySelector('link[href*="build/assets/style.css"]')) {
                const l = document.createElement('link');
                l.rel='stylesheet';
                l.href='<?= base_url('build/assets/style.css') ?>';
                document.head.appendChild(l);
            }
            // Inject minimal critical styles as immediate safety net
            if (!document.getElementById('schedule-critical-fallback')) {
                const s=document.createElement('style');
                s.id='schedule-critical-fallback';
                s.textContent='body{font-family:system-ui,sans-serif;background:#f3f4f6;color:#1f2937;} .main-content{max-width:1600px;margin:0 auto;}';
                document.head.appendChild(s);
            }
        }
    } catch(e){ console.debug('[schedule] style check failed', e); }
});
</script>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="main-content px-6 py-4 space-y-4" id="scheduler-page" data-scheduler-version="1.0.0" data-page-title="Schedule" data-page-subtitle="Manage appointments and calendar">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">Schedule</h1>
        <div class="flex items-center gap-3">
            <div class="text-sm text-gray-500 dark:text-gray-400" id="scheduleStatus"></div>
            <button id="refreshCalendar" class="px-2 py-1 text-xs rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600">Refresh</button>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4 space-y-4">
        <div class="flex flex-wrap gap-3 items-center" id="scheduleFilters">
            <label class="text-sm font-medium text-gray-600 dark:text-gray-300">Service
                <select id="filterService" class="ml-2 border border-gray-300 dark:border-gray-600 rounded px-2 py-1 text-sm bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                    <option value="">All</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= esc($s['id']) ?>"><?= esc($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-gray-600 dark:text-gray-300">Provider
                <select id="filterProvider" class="ml-2 border border-gray-300 dark:border-gray-600 rounded px-2 py-1 text-sm bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                    <option value="">All</option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= esc($p['id']) ?>"><?= esc($p['name'] ?? ($p['first_name'] . ' ' . $p['last_name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button id="todayBtn" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-500">Today</button>
            <div class="inline-flex rounded shadow-sm isolate">
                <button id="prevBtn" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm rounded-l hover:bg-gray-300 dark:hover:bg-gray-600">Prev</button>
                <button id="nextBtn" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm rounded-r hover:bg-gray-300 dark:hover:bg-gray-600">Next</button>
            </div>
            <div class="inline-flex rounded shadow-sm isolate">
                <button data-view="dayGridMonth" class="view-btn px-3 py-1 bg-indigo-50 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-200 text-sm rounded-l hover:bg-indigo-100 dark:hover:bg-indigo-800">Month</button>
                <button data-view="timeGridWeek" class="view-btn px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm hover:bg-gray-300 dark:hover:bg-gray-600">Week</button>
                <button data-view="timeGridDay" class="view-btn px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm rounded-r hover:bg-gray-300 dark:hover:bg-gray-600">Day</button>
            </div>
        </div>
        <div id="calendarContainer" class="relative">
            <div id="calendarLoading" class="absolute inset-0 flex items-center justify-center bg-white/70 dark:bg-gray-900/70 text-gray-600 dark:text-gray-300 text-sm hidden">Loading...</div>
            <div id="calendar" class="fc-reset"></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
