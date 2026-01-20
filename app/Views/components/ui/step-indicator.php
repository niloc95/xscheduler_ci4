<?php
/**
 * Step Indicator Component
 * 
 * Visual progress indicator for multi-step processes (setup wizard, forms).
 * 
 * Props:
 * @param array $steps Required. Array of step definitions
 *   [['label' => 'Database', 'icon' => 'database'], ...]
 * @param int $currentStep Required. Current step (1-indexed)
 * @param string $class Optional. Additional container classes
 * 
 * Usage:
 * <?= $this->include('components/ui/step-indicator', [
 *     'steps' => [
 *         ['label' => 'Database', 'icon' => 'database'],
 *         ['label' => 'Business', 'icon' => 'storefront'],
 *         ['label' => 'Admin', 'icon' => 'person'],
 *         ['label' => 'Complete', 'icon' => 'check_circle']
 *     ],
 *     'currentStep' => 2
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$steps = $steps ?? [];
$currentStep = (int)($currentStep ?? 1);
$class = $class ?? '';
$totalSteps = count($steps);
?>

<div class="xs-step-indicator <?= esc($class) ?>">
    <div class="flex items-center justify-between">
        <?php foreach ($steps as $index => $step): ?>
            <?php 
            $stepNumber = $index + 1;
            $isCompleted = $stepNumber < $currentStep;
            $isCurrent = $stepNumber === $currentStep;
            $isPending = $stepNumber > $currentStep;
            ?>
            
            <!-- Step -->
            <div class="flex flex-col items-center <?= $stepNumber < $totalSteps ? 'flex-1' : '' ?>">
                <div class="flex items-center w-full">
                    <!-- Step Circle -->
                    <div class="relative flex items-center justify-center">
                        <?php if ($isCompleted): ?>
                        <!-- Completed -->
                        <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center">
                            <span class="material-symbols-outlined text-white">check</span>
                        </div>
                        <?php elseif ($isCurrent): ?>
                        <!-- Current -->
                        <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center ring-4 ring-primary-100 dark:ring-primary-900/50">
                            <?php if (!empty($step['icon'])): ?>
                            <span class="material-symbols-outlined text-white text-lg"><?= esc($step['icon']) ?></span>
                            <?php else: ?>
                            <span class="text-white font-semibold"><?= $stepNumber ?></span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <!-- Pending -->
                        <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                            <?php if (!empty($step['icon'])): ?>
                            <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-lg"><?= esc($step['icon']) ?></span>
                            <?php else: ?>
                            <span class="text-gray-500 dark:text-gray-400 font-semibold"><?= $stepNumber ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Connector Line -->
                    <?php if ($stepNumber < $totalSteps): ?>
                    <div class="flex-1 h-0.5 mx-2 <?= $isCompleted ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700' ?>"></div>
                    <?php endif; ?>
                </div>
                
                <!-- Step Label -->
                <span class="mt-2 text-xs font-medium <?= $isCurrent ? 'text-primary-600 dark:text-primary-400' : ($isCompleted ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400') ?>">
                    <?= esc($step['label'] ?? 'Step ' . $stepNumber) ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
