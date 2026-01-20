<?php
/**
 * Dashboard Upcoming Appointments List Component
 * 
 * Displays upcoming appointments (next 7 days).
 * 
 * Props:
 * - upcoming: Array of upcoming appointments
 *   [['id', 'date', 'time', 'customer', 'provider', 'service', 'status'], ...]
 * - maxItems: Maximum items to display (default: 10)
 */

$upcoming = $upcoming ?? [];
$maxItems = $maxItems ?? 10;

$displayItems = array_slice($upcoming, 0, $maxItems);
?>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden" data-upcoming-list>
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                Upcoming Appointments
            </h3>
            <a href="<?= base_url('/appointments') ?>" 
               class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 flex items-center">
                View All
                <span class="material-symbols-outlined ml-1 text-sm">arrow_forward</span>
            </a>
        </div>
    </div>
    
    <?php if (empty($displayItems)): ?>
    <div class="px-6 py-12 text-center">
        <span class="material-symbols-outlined text-gray-400 text-4xl mb-2">event_busy</span>
        <p class="text-gray-500 dark:text-gray-400 text-sm">No upcoming appointments</p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php foreach ($displayItems as $appt): ?>
            <?php
            $statusColors = [
                'pending' => 'text-amber-600 dark:text-amber-400',
                'confirmed' => 'text-green-600 dark:text-green-400',
            ];
            
            $statusClass = $statusColors[$appt['status']] ?? $statusColors['pending'];
            ?>
            <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors duration-150">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center space-x-3 mb-2">
                            <span class="material-symbols-outlined text-gray-400 text-sm">calendar_today</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                <?= date('M j, Y', strtotime($appt['date'])) ?>
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                at <?= esc($appt['time']) ?>
                            </span>
                        </div>
                        
                        <div class="ml-6">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                <?= esc($appt['customer']) ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?= esc($appt['service']) ?>
                                <?php if (isset($appt['provider']) && $appt['provider']): ?>
                                <span class="mx-1">•</span>
                                <?= esc($appt['provider']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="ml-4 flex items-center space-x-2">
                        <span class="material-symbols-outlined text-sm <?= $statusClass ?>">
                            <?= $appt['status'] === 'confirmed' ? 'check_circle' : 'schedule' ?>
                        </span>
                        <a href="<?= base_url('/appointments/' . $appt['id']) ?>" 
                           class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (count($upcoming) > $maxItems): ?>
    <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 text-center">
        <a href="<?= base_url('/appointments') ?>" 
           class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
            +<?= count($upcoming) - $maxItems ?> more appointments
        </a>
    </div>
    <?php endif; ?>
</div>
