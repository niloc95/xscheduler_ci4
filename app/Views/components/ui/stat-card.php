<?php
/**
 * Stat Card Component
 * 
 * Displays a key metric with icon, value, label, and optional trend indicator.
 * Used on dashboards and overview pages.
 * 
 * Props:
 * @param string $label Required. Metric label
 * @param mixed $value Required. Metric value
 * @param string $icon Optional. Material icon name
 * @param string $color Optional. Color theme (blue, green, amber, red, indigo, purple)
 * @param array $trend Optional. ['direction' => 'up'|'down'|'neutral', 'value' => '12%', 'label' => 'vs last week']
 * @param string $href Optional. Link URL for clickable card
 * @param string $id Optional. HTML id for real-time updates
 * 
 * Usage:
 * <?= $this->include('components/ui/stat-card', [
 *     'label' => 'Total Customers',
 *     'value' => 1234,
 *     'icon' => 'people',
 *     'color' => 'blue',
 *     'trend' => ['direction' => 'up', 'value' => '12%', 'label' => 'vs last month'],
 *     'href' => '/customers'
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$label = $label ?? 'Metric';
$value = $value ?? 0;
$icon = $icon ?? 'analytics';
$color = $color ?? 'blue';
$trend = $trend ?? null;
$href = $href ?? null;
$id = $id ?? null;

// Color configurations
$colors = [
    'blue' => [
        'bg' => 'bg-blue-50 dark:bg-blue-900/20',
        'icon_bg' => 'bg-blue-100 dark:bg-blue-900/40',
        'icon' => 'text-blue-600 dark:text-blue-400',
        'value' => 'text-blue-700 dark:text-blue-300',
        'border' => 'border-blue-100 dark:border-blue-800/50'
    ],
    'green' => [
        'bg' => 'bg-green-50 dark:bg-green-900/20',
        'icon_bg' => 'bg-green-100 dark:bg-green-900/40',
        'icon' => 'text-green-600 dark:text-green-400',
        'value' => 'text-green-700 dark:text-green-300',
        'border' => 'border-green-100 dark:border-green-800/50'
    ],
    'amber' => [
        'bg' => 'bg-amber-50 dark:bg-amber-900/20',
        'icon_bg' => 'bg-amber-100 dark:bg-amber-900/40',
        'icon' => 'text-amber-600 dark:text-amber-400',
        'value' => 'text-amber-700 dark:text-amber-300',
        'border' => 'border-amber-100 dark:border-amber-800/50'
    ],
    'red' => [
        'bg' => 'bg-red-50 dark:bg-red-900/20',
        'icon_bg' => 'bg-red-100 dark:bg-red-900/40',
        'icon' => 'text-red-600 dark:text-red-400',
        'value' => 'text-red-700 dark:text-red-300',
        'border' => 'border-red-100 dark:border-red-800/50'
    ],
    'indigo' => [
        'bg' => 'bg-indigo-50 dark:bg-indigo-900/20',
        'icon_bg' => 'bg-indigo-100 dark:bg-indigo-900/40',
        'icon' => 'text-indigo-600 dark:text-indigo-400',
        'value' => 'text-indigo-700 dark:text-indigo-300',
        'border' => 'border-indigo-100 dark:border-indigo-800/50'
    ],
    'purple' => [
        'bg' => 'bg-purple-50 dark:bg-purple-900/20',
        'icon_bg' => 'bg-purple-100 dark:bg-purple-900/40',
        'icon' => 'text-purple-600 dark:text-purple-400',
        'value' => 'text-purple-700 dark:text-purple-300',
        'border' => 'border-purple-100 dark:border-purple-800/50'
    ],
];

$c = $colors[$color] ?? $colors['blue'];
$tag = $href ? 'a' : 'div';
$linkAttrs = $href ? 'href="' . esc($href) . '"' : '';
$idAttr = $id ? 'id="' . esc($id) . '"' : '';
?>

<<?= $tag ?> <?= $linkAttrs ?> <?= $idAttr ?> class="xs-stat-card group block <?= $c['bg'] ?> <?= $c['border'] ?> border rounded-xl p-5 transition-all duration-200 <?= $href ? 'hover:shadow-md hover:-translate-y-0.5 cursor-pointer' : '' ?>">
    <div class="flex items-start justify-between gap-4">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">
                <?= esc($label) ?>
            </p>
            <p class="text-3xl font-bold stat-value <?= $c['value'] ?> truncate">
                <?= esc($value) ?>
            </p>
            
            <?php if ($trend): ?>
            <div class="mt-2 flex items-center gap-1.5 text-sm">
                <?php
                $trendDirection = $trend['direction'] ?? 'neutral';
                $trendIcon = $trendDirection === 'up' ? 'trending_up' : ($trendDirection === 'down' ? 'trending_down' : 'trending_flat');
                $trendColor = $trendDirection === 'up' ? 'text-green-600 dark:text-green-400' : 
                             ($trendDirection === 'down' ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400');
                ?>
                <span class="material-symbols-outlined text-base <?= $trendColor ?>"><?= $trendIcon ?></span>
                <span class="<?= $trendColor ?> font-medium"><?= esc($trend['value'] ?? '') ?></span>
                <?php if (isset($trend['label'])): ?>
                <span class="text-gray-500 dark:text-gray-400"><?= esc($trend['label']) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="<?= $c['icon_bg'] ?> rounded-xl p-3 flex-shrink-0">
            <span class="material-symbols-outlined text-2xl <?= $c['icon'] ?>"><?= esc($icon) ?></span>
        </div>
    </div>
</<?= $tag ?>>
