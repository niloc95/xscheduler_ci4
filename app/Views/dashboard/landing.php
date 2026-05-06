<?php
/**
 * Dashboard Landing View
 * 
 * Compact, information-dense dashboard for admins.
 * Displays metrics, provider grid, and today's schedule.
 * 
 * Data from Controller:
 * - $context: User and system context
 * - $metrics: Today's appointment metrics
 * - $schedule: Today's schedule grouped by provider
 * - $alerts: Actionable alerts
 * - $upcoming: Upcoming appointments (next 7 days)
 * - $availability: Provider availability status
 * - $provider_scope: Provider ID for filtering (null = admin)
 */

$context = $context ?? [];
$metrics = $metrics ?? [];
$schedule = $schedule ?? [];
$alerts = $alerts ?? [];
$upcoming = $upcoming ?? [];
$availability = $availability ?? [];
$userName = $context['user_name'] ?? 'User';

// Calculate additional metrics
$pendingCount = $metrics['pending'] ?? 0;
$confirmedCount = $metrics['confirmed'] ?? 0;
$cancelledCount = $metrics['cancelled'] ?? 0;
$totalProviders = count($availability);
$workingProviders = count(array_filter($availability, fn($p) => ($p['status'] ?? '') === 'working'));
?>

<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'dashboard']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Quick Actions -->
<div class="xs-page-actions">
    <a href="<?= base_url('/appointments/create') ?>" 
       class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
        <span class="material-symbols-outlined text-sm mr-1.5">add</span>
        New Appointment
    </a>
    <a href="<?= base_url('/appointments') ?>" 
       class="inline-flex items-center px-3 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 transition-colors">
        <span class="material-symbols-outlined text-sm mr-1.5">calendar_month</span>
        Calendar
    </a>
</div>

<!-- Page Body -->
<div class="xs-page-body">
    <!-- Metrics Row: Compact horizontal strip -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3" id="metrics-container">
        <div class="metric-mini metric-mini-blue">
            <span class="text-2xl font-bold text-blue-700 dark:text-blue-300" id="metric-total"><?= $metrics['total'] ?? 0 ?></span>
            <span class="text-xs text-blue-600 dark:text-blue-400">Today</span>
        </div>
        <div class="metric-mini metric-mini-yellow">
            <span class="text-2xl font-bold text-amber-700 dark:text-amber-300" id="metric-pending"><?= $pendingCount ?></span>
            <span class="text-xs text-amber-600 dark:text-amber-400">Pending</span>
        </div>
        <div class="metric-mini metric-mini-green">
            <span class="text-2xl font-bold text-green-700 dark:text-green-300" id="metric-confirmed"><?= $confirmedCount ?></span>
            <span class="text-xs text-green-600 dark:text-green-400">Confirmed</span>
        </div>
        <div class="metric-mini metric-mini-red">
            <span class="text-2xl font-bold text-red-700 dark:text-red-300" id="metric-cancelled"><?= $cancelledCount ?></span>
            <span class="text-xs text-red-600 dark:text-red-400">Cancelled</span>
        </div>
        <div class="metric-mini metric-mini-indigo">
            <span class="text-2xl font-bold text-indigo-700 dark:text-indigo-300"><?= $workingProviders ?>/<?= $totalProviders ?></span>
            <span class="text-xs text-indigo-600 dark:text-indigo-400">Providers Active</span>
        </div>
    </div>
    
    <!-- Pending Alert Banner (if any) -->
    <?php if ($pendingCount > 0): ?>
    <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-amber-600 dark:text-amber-400">pending_actions</span>
            <span class="text-sm font-medium text-amber-800 dark:text-amber-200">
                <?= $pendingCount ?> appointment<?= $pendingCount !== 1 ? 's' : '' ?> awaiting confirmation
            </span>
        </div>
        <a href="<?= base_url('/appointments?status=pending') ?>" 
           class="text-xs font-medium text-amber-700 dark:text-amber-300 hover:underline">
            Review →
        </a>
    </div>
    <?php endif; ?>

    <!-- Main Grid: Providers + Schedule -->
    <div class="dashboard-grid">
        
        <!-- Left: Provider Availability Grid -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Providers</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400"><?= $workingProviders ?> available</span>
            </div>
            
            <?php if (!empty($availability)): ?>
            <div class="p-3 provider-grid">
                <?php foreach ($availability as $provider): ?>
                    <?php
                    $status = $provider['status'] ?? 'off';
                    $nextSlot = $provider['next_slot'] ?? null;
                    $nextSlotLabel = is_array($nextSlot) ? ($nextSlot['label'] ?? null) : $nextSlot;
                    $nextSlotDate = is_array($nextSlot) ? ($nextSlot['date'] ?? null) : null;
                    $nextSlotTime = is_array($nextSlot) ? ($nextSlot['time'] ?? null) : (is_string($nextSlot) ? $nextSlot : null);
                    $noSlotsToday = is_array($nextSlot) ? (bool) ($nextSlot['no_slots_today'] ?? false) : false;
                    $hasSlot = is_array($nextSlot) ? (bool) ($nextSlot['has_slot'] ?? false) : !empty($nextSlot);
                    $providerColor = $provider['color'] ?? '#3B82F6';
                    $providerId = $provider['id'] ?? null;
                    $services = $provider['services'] ?? [];
                    $serviceOptions = is_array($provider['service_options'] ?? null) ? $provider['service_options'] : [];
                    $locationOptions = is_array($provider['location_options'] ?? null) ? $provider['location_options'] : [];
                    $defaultServiceId = $provider['default_service_id'] ?? ($serviceOptions[0]['id'] ?? null);
                    $slotsDate = $provider['slots_date'] ?? date('Y-m-d');
                    $slotsForDate = is_array($provider['slots_for_date'] ?? null) ? $provider['slots_for_date'] : [];
                    
                    $isWorking = ($status === 'working');
                    $statusConfig = [
                        'working' => ['icon' => 'check_circle', 'color' => 'text-green-500', 'bg' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'],
                        'on_break' => ['icon' => 'coffee', 'color' => 'text-amber-500', 'bg' => 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800'],
                        'off' => ['icon' => 'cancel', 'color' => 'text-gray-400', 'bg' => 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700'],
                    ];
                    $cfg = $statusConfig[$status] ?? $statusConfig['off'];

                    $cardClasses = 'provider-card p-3 rounded-lg border ' . $cfg['bg'];
                    ?>
                    <div
                        class="<?= esc($cardClasses) ?>"
                        data-provider-card="true"
                        data-provider-id="<?= esc((string) $providerId) ?>"
                        data-default-service-id="<?= esc((string) ($defaultServiceId ?? '')) ?>"
                        data-initial-date="<?= esc($slotsDate) ?>">
                        <!-- Provider Header -->
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0 provider-color-dot" data-color="<?= esc($providerColor) ?>"></div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white truncate flex-1">
                                <?= esc($provider['name']) ?>
                            </span>
                            <span class="material-symbols-outlined text-sm <?= $cfg['color'] ?>"><?= $cfg['icon'] ?></span>
                        </div>
                        
                        <!-- Services (compact) -->
                        <?php if (!empty($services)): ?>
                        <div class="text-[10px] text-gray-500 dark:text-gray-400 mb-2 truncate" title="<?= esc(implode(', ', $services)) ?>">
                            <?= esc(implode(', ', array_slice($services, 0, 2))) ?><?= count($services) > 2 ? ' and ' . (count($services) - 2) . ' more' : '' ?>
                        </div>
                        <?php endif; ?>

                        <div class="provider-card-filters mb-2">
                            <label class="provider-card-filter">
                                <span>Date</span>
                                <input
                                    type="date"
                                    class="provider-card-input"
                                    data-provider-date
                                    value="<?= esc($slotsDate) ?>">
                            </label>

                            <label class="provider-card-filter">
                                <span>Service</span>
                                <select class="provider-card-input" data-provider-service>
                                    <option value="">Default service</option>
                                    <?php foreach ($serviceOptions as $serviceOption): ?>
                                        <option
                                            value="<?= esc((string) ($serviceOption['id'] ?? '')) ?>"
                                            <?= ((string) ($serviceOption['id'] ?? '') === (string) ($defaultServiceId ?? '')) ? 'selected' : '' ?>>
                                            <?= esc($serviceOption['name'] ?? 'Service') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="provider-card-filter">
                                <span>Location</span>
                                <select class="provider-card-input" data-provider-location>
                                    <option value="">All locations</option>
                                    <?php foreach ($locationOptions as $locationOption): ?>
                                        <option value="<?= esc((string) ($locationOption['id'] ?? '')) ?>">
                                            <?= esc($locationOption['name'] ?? 'Location') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        
                        <!-- Slots for selected date -->
                        <div class="provider-card-slots" data-provider-slots-list>
                            <?php if (!empty($slotsForDate) && $providerId): ?>
                                <?php foreach ($slotsForDate as $slot): ?>
                                    <?php
                                    $slotTime = (string) ($slot['time'] ?? '');
                                    if ($slotTime === '') {
                                        continue;
                                    }
                                    $bookParams = [
                                        'provider_id' => $providerId,
                                        'date' => $slotsDate,
                                        'time' => $slotTime,
                                    ];
                                    if ($defaultServiceId) {
                                        $bookParams['service_id'] = $defaultServiceId;
                                    }
                                    $slotBookUrl = base_url('/appointments/create') . '?' . http_build_query($bookParams);
                                    ?>
                                    <a href="<?= esc($slotBookUrl) ?>" class="provider-slot-link no-underline hover:no-underline focus:no-underline">
                                        <span class="provider-slot-time"><?= esc($slotTime) ?></span>
                                        <span class="provider-slot-book">Book</span>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="provider-slot-empty text-xs text-gray-500 dark:text-gray-400">
                                    No slots for this date.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-6 text-center text-gray-500 dark:text-gray-400 text-sm">
                No providers configured
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right: Today's Schedule -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Today's Schedule</h2>
                <a href="<?= base_url('/appointments?filter=today') ?>" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                    View all →
                </a>
            </div>
            <div id="dashboard-schedule-body">
                <?= $this->include('dashboard/_schedule_fragment', ['schedule' => $schedule]) ?>
            </div>
        </div>
    </div>
</div><!-- /.xs-page-body -->

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Dashboard refresh (metrics + schedule) — SPA-compatible, polling + event-driven
(function() {
    // Prevent duplicate initialization on SPA back-nav
    const container = document.getElementById('metrics-container');
    if (!container || container.dataset.refreshInitialized === 'true') return;
    container.dataset.refreshInitialized = 'true';

    const METRICS_INTERVAL  = 15000;
    const SCHEDULE_INTERVAL = 30000;
    const RETRY_DELAY       = 10000;
    const AUTH_RETRY_DELAY  = 3000;
    const MAX_AUTH_RETRIES  = 2;

    let metricsTimer   = null;
    let scheduleTimer  = null;
    let abortController = null;  // guards the in-flight schedule fetch
    let isRefreshingMetrics = false;
    let authFailureCount = 0;

    function handleUnauthorized(message) {
        authFailureCount += 1;

        if (authFailureCount < MAX_AUTH_RETRIES) {
            console.warn(`[Dashboard] ${message}; retrying shortly (${authFailureCount}/${MAX_AUTH_RETRIES - 1})`);
            metricsTimer = setTimeout(refreshMetrics, AUTH_RETRY_DELAY);
            scheduleTimer = setTimeout(refreshSchedule, AUTH_RETRY_DELAY);
            return;
        }

        window.location.href = '<?= base_url('auth/login') ?>';
    }

    // ─── Unified cleanup (called on SPA nav away + tab hide) ────────────────
    function cleanup() {
        if (metricsTimer)  { clearTimeout(metricsTimer);  metricsTimer  = null; }
        if (scheduleTimer) { clearTimeout(scheduleTimer); scheduleTimer = null; }
        if (abortController) { abortController.abort(); abortController = null; }
        window.removeEventListener('appointment:changed', onAppointmentChanged);
    }

    // ─── Metrics polling ────────────────────────────────────────────────────
    function refreshMetrics() {
        if (document.hidden || isRefreshingMetrics) return;
        const metricsEl = document.getElementById('metrics-container');
        if (!metricsEl) { cleanup(); return; }

        isRefreshingMetrics = true;
        metricsEl.classList.add('loading-pulse');

        fetch('<?= base_url('/dashboard/api/metrics') ?>', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async r => {
            if (r.status === 401) {
                throw { status: 401, message: 'metrics endpoint returned 401' };
            }

            if (!r.ok) {
                throw { status: r.status, message: `metrics endpoint returned ${r.status}` };
            }

            return r.json();
        })
        .then(data => {
            authFailureCount = 0;
            const m = data.data || data;
            updateValue('metric-total',     m.total);
            updateValue('metric-pending',   m.pending);
            updateValue('metric-confirmed', m.confirmed);
            updateValue('metric-cancelled', m.cancelled);
            metricsEl.classList.remove('loading-pulse');
            isRefreshingMetrics = false;
            metricsTimer = setTimeout(refreshMetrics, METRICS_INTERVAL);
        })
        .catch(err => {
            if (err?.status === 401) {
                metricsEl.classList.remove('loading-pulse');
                isRefreshingMetrics = false;
                handleUnauthorized(err.message || 'metrics polling unauthorized');
                return;
            }

            console.error('[Dashboard] Metrics refresh failed:', err);
            metricsEl.classList.remove('loading-pulse');
            isRefreshingMetrics = false;
            metricsTimer = setTimeout(refreshMetrics, RETRY_DELAY);
        });
    }

    function updateValue(id, val) {
        const el = document.getElementById(id);
        if (el && parseInt(el.textContent) !== val) { el.textContent = val; }
    }

    // ─── Schedule polling ────────────────────────────────────────────────────
    function refreshSchedule() {
        const body = document.getElementById('dashboard-schedule-body');
        if (!body) { cleanup(); return; }

        // Abort any in-flight request before starting a new one
        if (abortController) { abortController.abort(); }
        abortController = new AbortController();

        body.classList.add('opacity-50', 'transition-opacity', 'duration-150');

        fetch('<?= base_url('/dashboard/api/schedule') ?>', {
            signal: abortController.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async r => {
            if (r.status === 401) {
                throw { status: 401, message: 'schedule endpoint returned 401' };
            }

            if (!r.ok) {
                throw { status: r.status, message: `schedule endpoint returned ${r.status}` };
            }

            return r.text();
        })
        .then(html => {
            authFailureCount = 0;
            // Only swap if content changed — avoids unnecessary repaints
            if (body.innerHTML !== html) { body.innerHTML = html; }
            body.classList.remove('opacity-50');
            abortController = null;
            scheduleTimer = setTimeout(refreshSchedule, SCHEDULE_INTERVAL);
        })
        .catch(err => {
            if (err.name === 'AbortError') return; // intentional — ignore

            if (err?.status === 401) {
                body.classList.remove('opacity-50');
                abortController = null;
                handleUnauthorized(err.message || 'schedule polling unauthorized');
                return;
            }

            console.error('[Dashboard] Schedule refresh failed:', err);
            body.classList.remove('opacity-50');
            scheduleTimer = setTimeout(refreshSchedule, RETRY_DELAY);
        });
    }

    // ─── appointment:changed — immediate schedule refresh ───────────────────
    function onAppointmentChanged() {
        if (scheduleTimer) { clearTimeout(scheduleTimer); scheduleTimer = null; }
        refreshSchedule();
    }
    window.addEventListener('appointment:changed', onAppointmentChanged);

    // ─── Visibility change ───────────────────────────────────────────────────
    function onVisibilityChange() {
        if (!document.getElementById('metrics-container')) {
            // Edge case: metrics-container gone after SPA nav — full teardown
            cleanup();
            document.removeEventListener('visibilitychange', onVisibilityChange);
            return;
        }
        if (!document.hidden) {
            refreshMetrics();
            refreshSchedule();
        } else {
            // Tab hidden — pause timers and abort in-flight requests ONLY.
            // Do NOT remove appointment:changed listener — it must survive tab hide
            // so mutations made on return to tab still trigger immediate refresh.
            if (metricsTimer)    { clearTimeout(metricsTimer);  metricsTimer  = null; }
            if (scheduleTimer)   { clearTimeout(scheduleTimer); scheduleTimer = null; }
            if (abortController) { abortController.abort();     abortController = null; }
        }
    }

    // ─── SPA navigation away from dashboard ─────────────────────────────────
    function onSpaNavigated() {
        if (!document.getElementById('metrics-container')) {
            cleanup(); // full teardown including appointment:changed listener
            document.removeEventListener('spa:navigated', onSpaNavigated);
            document.removeEventListener('visibilitychange', onVisibilityChange);
        }
    }

    document.addEventListener('visibilitychange', onVisibilityChange);
    document.addEventListener('spa:navigated', onSpaNavigated);

    // ─── Bootstrap ──────────────────────────────────────────────────────────
    refreshMetrics();
    refreshSchedule();

    console.log('[Dashboard] Metrics + schedule refresh initialized');
})();
</script>
<?= $this->endSection() ?>
