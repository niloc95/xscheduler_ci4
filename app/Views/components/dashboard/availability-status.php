<?php
/**
 * Dashboard Provider Availability Status Component
 * 
 * Displays current status and next available slot for providers.
 * 
 * Props:
 * - availability: Array of provider availability data
 *   [['id', 'name', 'status', 'next_slot', 'color'], ...]
 * - compact: (optional) Show compact version
 */

$availability = $availability ?? [];
$compact = $compact ?? false;

if (empty($availability)) {
    return;
}
?>

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden" data-availability-status>
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Provider Availability
        </h3>
    </div>
    
    <div class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php foreach ($availability as $provider): ?>
            <?php
            $status = $provider['status'] ?? 'off';
            $nextSlot = $provider['next_slot'] ?? null;
            $providerColor = $provider['color'] ?? '#3B82F6';
            
            $statusConfig = [
                'working' => [
                    'icon' => 'check_circle',
                    'class' => 'text-green-600 dark:text-green-400',
                    'label' => 'Available',
                    'bgClass' => 'bg-green-50 dark:bg-green-900/20'
                ],
                'on_break' => [
                    'icon' => 'coffee',
                    'class' => 'text-amber-600 dark:text-amber-400',
                    'label' => 'On Break',
                    'bgClass' => 'bg-amber-50 dark:bg-amber-900/20'
                ],
                'off' => [
                    'icon' => 'cancel',
                    'class' => 'text-gray-400 dark:text-gray-500',
                    'label' => 'Off Today',
                    'bgClass' => 'bg-gray-50 dark:bg-gray-900/20'
                ],
            ];
            
            $config = $statusConfig[$status] ?? $statusConfig['off'];
            ?>
            
            <div class="px-4 md:px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors duration-150">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <!-- Provider Color Indicator -->
                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" 
                             style="background-color: <?= esc($providerColor) ?>;"></div>
                        
                        <!-- Provider Name -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                <?= esc($provider['name']) ?>
                            </p>
                            <?php if ($nextSlot): ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Next: <?= esc($nextSlot) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="flex items-center gap-1.5 px-2.5 md:px-3 py-1 md:py-1.5 rounded-full <?= $config['bgClass'] ?> flex-shrink-0">
                            <span class="material-symbols-outlined text-sm <?= $config['class'] ?>">
                                <?= $config['icon'] ?>
                            </span>
                            <?php if (!$compact): ?>
                            <span class="hidden sm:inline text-xs font-medium <?= $config['class'] ?>">
                                <?= $config['label'] ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
