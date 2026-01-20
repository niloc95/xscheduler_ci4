<?php
/**
 * Dashboard Schedule Table Component
 * 
 * Displays today's schedule grouped by provider.
 * 
 * Props:
 * - schedule: Array of appointments grouped by provider name
 *   [
 *     'Provider Name' => [
 *       ['id', 'start_time', 'end_time', 'customer_name', 'service_name', 'status'],
 *       ...
 *     ]
 *   ]
 * - userRole: Current user role (for filtering display)
 */

$schedule = $schedule ?? [];
$userRole = $userRole ?? 'admin';

if (empty($schedule)) {
    ?>
    <div class="text-center py-12 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <span class="material-symbols-outlined text-gray-400 text-5xl mb-3">event_available</span>
        <p class="text-gray-500 dark:text-gray-400">No appointments scheduled for today</p>
    </div>
    <?php
    return;
}
?>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden" data-schedule-table>
    <?php foreach ($schedule as $providerName => $appointments): ?>
    <div class="border-b border-gray-200 dark:border-gray-700 last:border-b-0">
        <!-- Provider Header - Compact Mobile First -->
        <div class="bg-gray-50 dark:bg-gray-900 px-3 md:px-4 py-2.5 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-base md:text-lg">person</span>
                    <h3 class="text-sm md:text-base font-semibold text-gray-900 dark:text-white">
                        <?= esc($providerName) ?>
                    </h3>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded-full">
                    <?= count($appointments) ?>
                </span>
            </div>
        </div>
        
        <!-- Appointments List - Mobile Optimized -->
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php foreach ($appointments as $appt): ?>
                <?php
                $statusColors = [
                    'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                    'confirmed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                    'completed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                    'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                ];
                
                $statusClass = $statusColors[$appt['status']] ?? $statusColors['pending'];
                $statusIcon = [
                    'pending' => 'hourglass_empty',
                    'confirmed' => 'check_circle',
                    'completed' => 'done_all',
                    'cancelled' => 'cancel',
                ][$appt['status']] ?? 'hourglass_empty';
                ?>
                <div class="px-3 md:px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors duration-150">
                    <!-- Mobile Layout (< 640px) -->
                    <div class="flex sm:hidden items-start gap-3">
                        <!-- Time -->
                        <div class="flex flex-col items-center gap-0.5 pt-0.5">
                            <span class="text-xs font-bold text-gray-900 dark:text-white">
                                <?= date('g:i', strtotime($appt['start_time'])) ?>
                            </span>
                            <span class="text-[10px] text-gray-500 dark:text-gray-400">
                                <?= date('A', strtotime($appt['start_time'])) ?>
                            </span>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white mb-0.5">
                                <?= esc($appt['customer_name']) ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1.5 line-clamp-1">
                                <?= esc($appt['service_name']) ?>
                            </p>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium <?= $statusClass ?>">
                                <span class="material-symbols-outlined text-xs"><?= $statusIcon ?></span>
                                <?= ucfirst(esc($appt['status'])) ?>
                            </span>
                        </div>
                        
                        <!-- Action Button -->
                        <a href="<?= base_url('/appointments/view/' . ($appt['hash'] ?? $appt['id'])) ?>" 
                           class="flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-blue-600 dark:text-blue-400 transition-colors">
                            <span class="material-symbols-outlined text-lg">arrow_forward</span>
                        </a>
                    </div>
                    
                    <!-- Desktop Layout (>= 640px) -->
                    <div class="hidden sm:flex items-center justify-between gap-4">
                        <!-- Time -->
                        <div class="flex items-center gap-1.5 w-20 flex-shrink-0">
                            <span class="material-symbols-outlined text-gray-400 text-base">schedule</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                <?= esc($appt['start_time']) ?>
                            </span>
                        </div>
                        
                        <!-- Customer & Service -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                <?= esc($appt['customer_name']) ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                <?= esc($appt['service_name']) ?>
                            </p>
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium <?= $statusClass ?>">
                                <span class="material-symbols-outlined text-sm"><?= $statusIcon ?></span>
                                <?= ucfirst(esc($appt['status'])) ?>
                            </span>
                        </div>
                        
                        <!-- Action Button -->
                        <a href="<?= base_url('/appointments/view/' . ($appt['hash'] ?? $appt['id'])) ?>" 
                           class="flex items-center justify-center w-8 h-8 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors flex-shrink-0">
                            <span class="material-symbols-outlined text-lg">arrow_forward</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
