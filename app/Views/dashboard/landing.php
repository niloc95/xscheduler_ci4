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
 * - $booking_status: Booking system status (admin only)
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
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" id="metrics-container">
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
                    
                    $isWorking = ($status === 'working');
                    $statusConfig = [
                        'working' => ['icon' => 'check_circle', 'color' => 'text-green-500', 'bg' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'],
                        'on_break' => ['icon' => 'coffee', 'color' => 'text-amber-500', 'bg' => 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800'],
                        'off' => ['icon' => 'cancel', 'color' => 'text-gray-400', 'bg' => 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700'],
                    ];
                    $cfg = $statusConfig[$status] ?? $statusConfig['off'];
                    
                    // Build quick book URL
                    $bookUrl = null;
                    if ($isWorking && $providerId) {
                        $params = ['provider_id' => $providerId, 'date' => $nextSlotDate ?: date('Y-m-d')];
                        if ($nextSlotTime) $params['time'] = $nextSlotTime;
                        $bookUrl = base_url('/appointments/create') . '?' . http_build_query($params);
                    }
                    ?>
                    <div class="p-3 rounded-lg border <?= $cfg['bg'] ?> <?= $isWorking ? 'hover:shadow-md transition-shadow' : '' ?>">
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
                            <?= esc(implode(', ', array_slice($services, 0, 2))) ?><?= count($services) > 2 ? ' +' . (count($services) - 2) : '' ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Status / Next Slot -->
                        <div class="flex items-center justify-between">
                            <?php if ($hasSlot && $nextSlotLabel): ?>
                                <span class="text-xs text-gray-600 dark:text-gray-400">
                                    <?php if ($noSlotsToday): ?>
                                        <span class="block">No slots available today</span>
                                        <span class="block">Next: <span class="font-medium"><?= esc($nextSlotLabel) ?></span></span>
                                    <?php else: ?>
                                        Next: <span class="font-medium"><?= esc($nextSlotLabel) ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    <?= $status === 'on_break' ? 'On break' : 'Off today' ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($bookUrl): ?>
                            <a href="<?= esc($bookUrl) ?>" 
                               class="text-[10px] font-medium text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-0.5"
                               title="Quick book with <?= esc($provider['name']) ?>">
                                <span class="material-symbols-outlined text-xs">add</span>Book
                            </a>
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
            
            <?php if (!empty($schedule)): ?>
            <div class="max-h-[400px] overflow-y-auto">
                <?php foreach ($schedule as $providerName => $appointments): ?>
                    <!-- Provider Section -->
                    <div class="bg-gray-50 dark:bg-gray-900 px-4 py-2 border-b border-gray-200 dark:border-gray-700 sticky top-0">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300"><?= esc($providerName) ?></span>
                            <span class="text-[10px] text-gray-500 dark:text-gray-400 bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded">
                                <?= count($appointments) ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php foreach ($appointments as $appt): ?>
                        <?php
                        $statusColors = [
                            'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                            'confirmed' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                            'completed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                            'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                        ];
                        $statusClass = $statusColors[$appt['status']] ?? $statusColors['pending'];
                        
                        // Use hash for URL, fallback to id
                        $appointmentUrl = base_url('/appointments/view/' . ($appt['hash'] ?? $appt['id']));
                        ?>
                        <div class="appt-row">
                            <!-- Time -->
                            <div class="text-xs font-medium text-gray-900 dark:text-white">
                                <?= esc($appt['start_time']) ?>
                            </div>
                            
                            <!-- Customer & Service -->
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    <?= esc($appt['customer_name'] ?: 'No customer') ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    <?= esc($appt['service_name'] ?: 'No service') ?>
                                </p>
                            </div>
                            
                            <!-- Status (hidden on mobile) -->
                            <div class="status-badge">
                                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-medium <?= $statusClass ?>">
                                    <?= ucfirst(esc($appt['status'])) ?>
                                </span>
                            </div>
                            
                            <!-- Action -->
                            <a href="<?= esc($appointmentUrl) ?>" 
                               data-no-spa="true"
                               class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                               title="View appointment">
                                <span class="material-symbols-outlined text-base">chevron_right</span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-8 text-center">
                <span class="material-symbols-outlined text-4xl text-gray-300 dark:text-gray-600 mb-2">event_available</span>
                <p class="text-sm text-gray-500 dark:text-gray-400">No appointments scheduled for today</p>
                <a href="<?= base_url('/appointments/create') ?>" class="text-xs text-blue-600 dark:text-blue-400 hover:underline mt-2 inline-block">
                    Create one now →
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div><!-- /.xs-page-body -->

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Dashboard metrics refresh - SPA compatible
(function() {
    // Prevent duplicate initialization
    const container = document.getElementById('metrics-container');
    if (!container || container.dataset.refreshInitialized === 'true') return;
    container.dataset.refreshInitialized = 'true';
    
    const REFRESH_INTERVAL = 15000;
    const RETRY_DELAY = 10000;
    let refreshTimer = null;
    let isRefreshing = false;
    
    function refreshMetrics() {
        if (document.hidden || isRefreshing) return;
        
        // Check if we're still on dashboard
        const metricsEl = document.getElementById('metrics-container');
        if (!metricsEl) {
            cleanup();
            return;
        }
        
        isRefreshing = true;
        metricsEl.classList.add('loading-pulse');
        
        fetch('<?= base_url('/dashboard/api/metrics') ?>', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.ok ? r.json() : Promise.reject(r.statusText))
        .then(data => {
            const m = data.data || data;
            updateValue('metric-total', m.total);
            updateValue('metric-pending', m.pending);
            updateValue('metric-confirmed', m.confirmed);
            metricsEl.classList.remove('loading-pulse');
            isRefreshing = false;
            scheduleRefresh(REFRESH_INTERVAL);
        })
        .catch(err => {
            console.error('Dashboard refresh failed:', err);
            metricsEl.classList.remove('loading-pulse');
            isRefreshing = false;
            scheduleRefresh(RETRY_DELAY);
        });
    }
    
    function updateValue(id, val) {
        const el = document.getElementById(id);
        if (el && parseInt(el.textContent) !== val) {
            el.textContent = val;
        }
    }
    
    function scheduleRefresh(delay) {
        if (refreshTimer) clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refreshMetrics, delay);
    }
    
    function cleanup() {
        if (refreshTimer) {
            clearTimeout(refreshTimer);
            refreshTimer = null;
        }
    }
    
    // Handle visibility changes
    function onVisibilityChange() {
        if (!document.getElementById('metrics-container')) {
            cleanup();
            document.removeEventListener('visibilitychange', onVisibilityChange);
            return;
        }
        if (!document.hidden) {
            refreshMetrics();
        } else {
            cleanup();
        }
    }
    
    // Handle SPA navigation away from dashboard
    function onSpaNavigated(e) {
        if (!document.getElementById('metrics-container')) {
            cleanup();
            document.removeEventListener('spa:navigated', onSpaNavigated);
            document.removeEventListener('visibilitychange', onVisibilityChange);
        }
    }
    
    document.addEventListener('visibilitychange', onVisibilityChange);
    document.addEventListener('spa:navigated', onSpaNavigated);
    
    // Initial fetch + start polling
    refreshMetrics();
    
    console.log('[Dashboard] Metrics refresh initialized');
})();
</script>
<?= $this->endSection() ?>
