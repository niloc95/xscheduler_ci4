<?php
/**
 * Empty State Component
 * 
 * Displays a friendly empty state when no data is available.
 * Use for tables, lists, search results, etc.
 * 
 * Props:
 * @param string $icon Required. Material Symbols icon name
 * @param string $title Required. Main empty state message
 * @param string $description Optional. Supporting description text
 * @param string $actionLabel Optional. Primary action button label
 * @param string $actionHref Optional. Primary action button URL
 * @param string $actionIcon Optional. Primary action button icon
 * @param string $secondaryLabel Optional. Secondary action label
 * @param string $secondaryHref Optional. Secondary action URL
 * @param string $class Optional. Additional container classes
 * @param string $size Optional. Size variant (sm, md, lg)
 * 
 * Usage:
 * <?= $this->include('components/ui/empty-state', [
 *     'icon' => 'calendar_today',
 *     'title' => 'No appointments scheduled',
 *     'description' => 'Get started by creating your first appointment.',
 *     'actionLabel' => 'New Appointment',
 *     'actionHref' => '/appointments/new',
 *     'actionIcon' => 'add'
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$icon = $icon ?? 'inbox';
$title = $title ?? 'No data available';
$description = $description ?? null;
$actionLabel = $actionLabel ?? null;
$actionHref = $actionHref ?? '#';
$actionIcon = $actionIcon ?? null;
$secondaryLabel = $secondaryLabel ?? null;
$secondaryHref = $secondaryHref ?? '#';
$class = $class ?? '';
$size = $size ?? 'md';

// Size variants
$sizes = [
    'sm' => [
        'container' => 'py-8',
        'icon' => 'text-4xl',
        'title' => 'text-base',
        'desc' => 'text-sm',
    ],
    'md' => [
        'container' => 'py-12',
        'icon' => 'text-6xl',
        'title' => 'text-lg',
        'desc' => 'text-sm',
    ],
    'lg' => [
        'container' => 'py-16',
        'icon' => 'text-7xl',
        'title' => 'text-xl',
        'desc' => 'text-base',
    ],
];

$sizeConfig = $sizes[$size] ?? $sizes['md'];
?>

<div class="xs-empty-state flex flex-col items-center justify-center text-center <?= $sizeConfig['container'] ?> <?= esc($class) ?>">
    <!-- Icon -->
    <div class="mb-4">
        <span class="material-symbols-outlined <?= $sizeConfig['icon'] ?> text-gray-300 dark:text-gray-600"><?= esc($icon) ?></span>
    </div>
    
    <!-- Title -->
    <h3 class="font-semibold text-gray-900 dark:text-white <?= $sizeConfig['title'] ?> mb-2">
        <?= esc($title) ?>
    </h3>
    
    <!-- Description -->
    <?php if ($description): ?>
    <p class="text-gray-500 dark:text-gray-400 <?= $sizeConfig['desc'] ?> max-w-sm mb-6">
        <?= esc($description) ?>
    </p>
    <?php endif; ?>
    
    <!-- Actions -->
    <?php if ($actionLabel || $secondaryLabel): ?>
    <div class="flex flex-wrap items-center justify-center gap-3">
        <?php if ($actionLabel): ?>
        <a href="<?= esc($actionHref) ?>" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:focus:ring-offset-gray-800">
            <?php if ($actionIcon): ?>
            <span class="material-symbols-outlined text-lg"><?= esc($actionIcon) ?></span>
            <?php endif; ?>
            <?= esc($actionLabel) ?>
        </a>
        <?php endif; ?>
        
        <?php if ($secondaryLabel): ?>
        <a href="<?= esc($secondaryHref) ?>" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:focus:ring-offset-gray-800">
            <?= esc($secondaryLabel) ?>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
