<?php
/**
 * Dashboard Schedule Fragment
 *
 * Partial view for the Today's Schedule card body.
 * Rendered server-side and swapped into #dashboard-schedule-body via
 * GET /dashboard/api/schedule for polling and event-driven refresh.
 *
 * Variables:
 * - $schedule: array — today's appointments grouped by provider name
 *              (shape produced by DashboardService::getTodaySchedule())
 */
$schedule = $schedule ?? [];
?>
<?php if (!empty($schedule)): ?>
<div class="dashboard-schedule-scroll">
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
                'pending'   => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                'confirmed' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                'completed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
            ];
            $statusClass = $statusColors[$appt['status']] ?? $statusColors['pending'];

            // Canonical deep-link opens AppointmentDetailsModal on scheduler page.
            $appointmentRef = (string) ($appt['hash'] ?? $appt['id']);
            $appointmentUrl = base_url('/appointments?open=' . rawurlencode($appointmentRef));
            ?>
            <div class="appt-row">
                <!-- Time -->
                <div class="text-xs font-medium text-gray-900 dark:text-white">
                    <?= esc($appt['start_at']) ?>
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
