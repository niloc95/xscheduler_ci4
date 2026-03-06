<?php
/**
 * Reusable Status Badge Component
 */

$status = strtolower((string) ($status ?? 'default'));
$label = $label ?? ucfirst($status);
$count = $count ?? null;
$class = $class ?? '';
$showDot = (bool) ($showDot ?? true);

$map = [
    'pending' => 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-200',
    'confirmed' => 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-600 dark:text-blue-200',
    'completed' => 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-600 dark:text-emerald-200',
    'done' => 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-600 dark:text-emerald-200',
    'cancelled' => 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-600 dark:text-red-200',
    'no-show' => 'bg-gray-50 border-gray-200 text-gray-600 dark:bg-gray-700/50 dark:border-gray-600 dark:text-gray-300',
    'noshow' => 'bg-gray-50 border-gray-200 text-gray-600 dark:bg-gray-700/50 dark:border-gray-600 dark:text-gray-300',
    'active' => 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-600 dark:text-emerald-200',
    'inactive' => 'bg-gray-50 border-gray-200 text-gray-600 dark:bg-gray-700/50 dark:border-gray-600 dark:text-gray-300',
    'default' => 'bg-surface-variant border-outline text-on-surface-variant',
];

$dotMap = [
    'pending' => 'bg-amber-500',
    'confirmed' => 'bg-blue-500',
    'completed' => 'bg-emerald-500',
    'done' => 'bg-emerald-500',
    'cancelled' => 'bg-red-500',
    'no-show' => 'bg-gray-400',
    'noshow' => 'bg-gray-400',
    'active' => 'bg-emerald-500',
    'inactive' => 'bg-gray-400',
    'default' => 'bg-gray-400',
];

$badgeClasses = trim('inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs ' . ($map[$status] ?? $map['default']) . ' ' . $class);
$dotClass = $dotMap[$status] ?? $dotMap['default'];
?>

<span class="<?= esc($badgeClasses) ?>">
    <?php if ($showDot): ?>
    <span class="w-1.5 h-1.5 rounded-full <?= esc($dotClass) ?>"></span>
    <?php endif; ?>
    <?php if ($count !== null): ?>
    <span class="font-medium"><?= esc((string) $count) ?></span>
    <?php endif; ?>
    <?= esc($label) ?>
</span>
