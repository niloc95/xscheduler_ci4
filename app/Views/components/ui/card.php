<?php
/**
 * Card Component
 * 
 * Standardized card container for content sections.
 * All content on data pages should be wrapped in cards.
 * 
 * Props:
 * @param string $title Optional. Card header title
 * @param string $subtitle Optional. Card header subtitle
 * @param string $icon Optional. Material icon name for header
 * @param string $headerActions Optional. HTML for header action buttons
 * @param string $padding Optional. Padding class (default: 'p-6')
 * @param string $class Optional. Additional CSS classes
 * @param bool $noPadding Optional. Remove body padding
 * @param string $footer Optional. Footer content HTML
 * 
 * Usage:
 * <?php $this->startSection('card_content') ?>
 *     <p>Card body content here</p>
 * <?php $this->endSection() ?>
 * 
 * <?= $this->include('components/ui/card', [
 *     'title' => 'Recent Activity',
 *     'icon' => 'history',
 *     'headerActions' => '<button class="btn btn-sm">View All</button>'
 * ]) ?>
 * 
 * OR use the simpler slot approach:
 * 
 * <div class="xs-card">
 *     <div class="xs-card-header">...</div>
 *     <div class="xs-card-body">...</div>
 * </div>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$title = $title ?? null;
$subtitle = $subtitle ?? null;
$icon = $icon ?? null;
$headerActions = $headerActions ?? null;
$padding = $padding ?? 'p-6';
$class = $class ?? '';
$noPadding = $noPadding ?? false;
$footer = $footer ?? null;
$content = $content ?? null;

$hasHeader = $title || $icon || $headerActions;
$bodyPadding = $noPadding ? '' : $padding;
?>

<div class="xs-card bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden <?= esc($class) ?>">
    <?php if ($hasHeader): ?>
    <div class="xs-card-header px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <?php if ($icon): ?>
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <span class="material-symbols-outlined text-blue-600 dark:text-blue-400"><?= esc($icon) ?></span>
            </div>
            <?php endif; ?>
            <div class="min-w-0">
                <?php if ($title): ?>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                    <?= esc($title) ?>
                </h3>
                <?php endif; ?>
                <?php if ($subtitle): ?>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                    <?= esc($subtitle) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($headerActions): ?>
        <div class="flex items-center gap-2 flex-shrink-0">
            <?= $headerActions ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="xs-card-body <?= esc($bodyPadding) ?>">
        <?= $content ?? $this->renderSection('card_content') ?>
    </div>
    
    <?php if ($footer): ?>
    <div class="xs-card-footer px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
        <?= $footer ?>
    </div>
    <?php endif; ?>
</div>
