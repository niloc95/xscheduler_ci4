<?php
/**
 * Dashboard Metrics Card Component
 * 
 * Displays a metric card with icon, value, label, and optional trend.
 * 
 * Props:
 * - title: Card title/label
 * - value: Main metric value
 * - icon: Material icon name
 * - color: Background color (primary, secondary, tertiary, success, warning, error)
 * - trend: (optional) Array with 'direction' (up/down/neutral), 'percentage', 'label'
 * - id: (optional) HTML id for the value element (for real-time updates)
 */

$title = $title ?? 'Metric';
$value = $value ?? 0;
$icon = $icon ?? 'analytics';
$color = $color ?? 'primary';
$trend = $trend ?? null;
$id = $id ?? null;

// Color mapping
$colorMap = [
    'primary' => 'rgb(59, 130, 246)',
    'secondary' => 'rgb(255, 152, 0)',
    'tertiary' => 'rgb(255, 87, 34)',
    'success' => 'rgb(34, 197, 94)',
    'warning' => 'rgb(251, 191, 36)',
    'error' => 'rgb(239, 68, 68)',
];

$bgColor = $colorMap[$color] ?? $colorMap['primary'];
?>

<md-outlined-card class="metrics-card p-5 md:p-6 text-white transition-all duration-300 rounded-lg shadow-sm hover:shadow-md" style="background-color: <?= $bgColor ?>;">
    <div class="flex items-start justify-between mb-3">
        <div class="flex-1 min-w-0">
            <p class="opacity-90 text-xs md:text-sm font-medium mb-2"><?= esc($title) ?></p>
            <p class="text-2xl md:text-3xl font-bold truncate">
                <?php if ($id): ?>
                <span id="<?= esc($id) ?>"><?= esc($value) ?></span>
                <?php else: ?>
                <?= esc($value) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="w-10 h-10 md:w-12 md:h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center flex-shrink-0 ml-3">
            <span class="material-symbols-outlined text-white text-xl md:text-2xl"><?= esc($icon) ?></span>
        </div>
    </div>
    
    <?php if ($trend): ?>
    <div class="flex items-center text-sm">
        <?php
        $direction = $trend['direction'] ?? 'neutral';
        $percentage = $trend['percentage'] ?? 0;
        $label = $trend['label'] ?? '';
        
        if ($direction === 'up') {
            $trendIcon = 'trending_up';
            $colorClass = 'text-green-200';
            $prefix = '+';
        } elseif ($direction === 'down') {
            $trendIcon = 'trending_down';
            $colorClass = 'text-red-200';
            $prefix = '-';
        } else {
            $trendIcon = 'trending_flat';
            $colorClass = 'text-gray-200';
            $prefix = '';
        }
        ?>
        <span class="material-symbols-outlined mr-1 <?= $colorClass ?> text-base"><?= $trendIcon ?></span>
        <span class="opacity-80"><?= $prefix ?><?= esc($percentage) ?>% <?= esc($label) ?></span>
    </div>
    <?php endif; ?>
</md-outlined-card>
