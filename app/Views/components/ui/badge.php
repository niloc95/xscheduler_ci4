<?php
/**
 * Badge Component
 * 
 * Standardized badge/tag for status indicators, labels, and counts.
 * 
 * Props:
 * @param string $label Required. Badge text
 * @param string $variant Optional. Color variant (default, primary, success, warning, danger, info)
 * @param string $size Optional. Size variant (sm, md, lg)
 * @param string $icon Optional. Leading icon
 * @param bool $dot Optional. Show status dot instead of/with icon
 * @param bool $pill Optional. Pill-shaped badge (more rounded)
 * @param string $class Optional. Additional badge classes
 * 
 * Usage:
 * <?= $this->include('components/ui/badge', [
 *     'label' => 'Active',
 *     'variant' => 'success',
 *     'dot' => true
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$label = $label ?? '';
$variant = $variant ?? 'default';
$size = $size ?? 'md';
$icon = $icon ?? null;
$dot = $dot ?? false;
$pill = $pill ?? false;
$class = $class ?? '';

// Variant configurations
$variants = [
    'default' => [
        'bg' => 'bg-gray-100 dark:bg-gray-700',
        'text' => 'text-gray-700 dark:text-gray-300',
        'dot' => 'bg-gray-500'
    ],
    'primary' => [
        'bg' => 'bg-primary-100 dark:bg-primary-900/30',
        'text' => 'text-primary-700 dark:text-primary-300',
        'dot' => 'bg-primary-500'
    ],
    'success' => [
        'bg' => 'bg-green-100 dark:bg-green-900/30',
        'text' => 'text-green-700 dark:text-green-300',
        'dot' => 'bg-green-500'
    ],
    'warning' => [
        'bg' => 'bg-amber-100 dark:bg-amber-900/30',
        'text' => 'text-amber-700 dark:text-amber-300',
        'dot' => 'bg-amber-500'
    ],
    'danger' => [
        'bg' => 'bg-red-100 dark:bg-red-900/30',
        'text' => 'text-red-700 dark:text-red-300',
        'dot' => 'bg-red-500'
    ],
    'info' => [
        'bg' => 'bg-blue-100 dark:bg-blue-900/30',
        'text' => 'text-blue-700 dark:text-blue-300',
        'dot' => 'bg-blue-500'
    ],
];

$variantConfig = $variants[$variant] ?? $variants['default'];

// Size configurations
$sizes = [
    'sm' => [
        'padding' => 'px-2 py-0.5',
        'text' => 'text-xs',
        'icon' => 'text-xs',
        'dot' => 'w-1.5 h-1.5'
    ],
    'md' => [
        'padding' => 'px-2.5 py-1',
        'text' => 'text-xs',
        'icon' => 'text-sm',
        'dot' => 'w-2 h-2'
    ],
    'lg' => [
        'padding' => 'px-3 py-1.5',
        'text' => 'text-sm',
        'icon' => 'text-base',
        'dot' => 'w-2.5 h-2.5'
    ],
];

$sizeConfig = $sizes[$size] ?? $sizes['md'];

// Build classes
$badgeClasses = 'xs-badge inline-flex items-center gap-1.5 font-medium ' .
    $sizeConfig['padding'] . ' ' .
    $sizeConfig['text'] . ' ' .
    $variantConfig['bg'] . ' ' .
    $variantConfig['text'] . ' ' .
    ($pill ? 'rounded-full' : 'rounded-md') . ' ' .
    $class;
?>

<span class="<?= $badgeClasses ?>">
    <?php if ($dot): ?>
    <span class="<?= $sizeConfig['dot'] ?> <?= $variantConfig['dot'] ?> rounded-full flex-shrink-0"></span>
    <?php elseif ($icon): ?>
    <span class="material-symbols-outlined <?= $sizeConfig['icon'] ?> flex-shrink-0"><?= esc($icon) ?></span>
    <?php endif; ?>
    <?= esc($label) ?>
</span>
