<?php
/**
 * Toggle/Switch Component
 * 
 * Standardized toggle switch for boolean settings.
 * 
 * Props:
 * @param string $name Required. Input name attribute
 * @param string $label Optional. Field label (displayed to the right)
 * @param string $description Optional. Description text below label
 * @param bool $checked Optional. Whether toggle is on
 * @param bool $disabled Optional. Whether toggle is disabled
 * @param string $onLabel Optional. Label when on (e.g., "Enabled")
 * @param string $offLabel Optional. Label when off (e.g., "Disabled")
 * @param string $size Optional. Size variant (sm, md, lg)
 * @param string $color Optional. Active color (primary, green, red, blue)
 * @param string $class Optional. Additional container classes
 * @param string $id Optional. Custom ID (defaults to name)
 * @param array $attrs Optional. Additional HTML attributes
 * 
 * Usage:
 * <?= $this->include('components/ui/toggle', [
 *     'name' => 'notifications',
 *     'label' => 'Enable Notifications',
 *     'description' => 'Receive email notifications for new appointments',
 *     'checked' => true
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$name = $name ?? '';
$label = $label ?? null;
$description = $description ?? null;
$checked = $checked ?? false;
$disabled = $disabled ?? false;
$onLabel = $onLabel ?? null;
$offLabel = $offLabel ?? null;
$size = $size ?? 'md';
$color = $color ?? 'primary';
$class = $class ?? '';
$id = $id ?? $name;
$attrs = $attrs ?? [];

// Build additional attributes string
$attrString = '';
foreach ($attrs as $key => $val) {
    $attrString .= ' ' . esc($key) . '="' . esc($val) . '"';
}

// Size configurations
$sizes = [
    'sm' => [
        'track' => 'w-8 h-4',
        'knob' => 'w-3 h-3',
        'translate' => 'translate-x-4',
        'left' => 'left-0.5',
    ],
    'md' => [
        'track' => 'w-11 h-6',
        'knob' => 'w-5 h-5',
        'translate' => 'translate-x-5',
        'left' => 'left-0.5',
    ],
    'lg' => [
        'track' => 'w-14 h-7',
        'knob' => 'w-6 h-6',
        'translate' => 'translate-x-7',
        'left' => 'left-0.5',
    ],
];

$sizeConfig = $sizes[$size] ?? $sizes['md'];

// Color configurations (for checked state)
$colors = [
    'primary' => 'peer-checked:bg-primary-600',
    'green' => 'peer-checked:bg-green-600',
    'red' => 'peer-checked:bg-red-600',
    'blue' => 'peer-checked:bg-blue-600',
];

$colorClass = $colors[$color] ?? $colors['primary'];
?>

<div class="xs-toggle flex items-start gap-3 <?= esc($class) ?>">
    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 <?= $disabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
        <input 
            type="checkbox"
            id="<?= esc($id) ?>"
            name="<?= esc($name) ?>"
            value="1"
            class="sr-only peer"
            <?php if ($checked): ?>checked<?php endif; ?>
            <?php if ($disabled): ?>disabled<?php endif; ?>
            <?= $attrString ?>
        >
        
        <!-- Track -->
        <div class="<?= $sizeConfig['track'] ?> bg-gray-200 dark:bg-gray-700 rounded-full peer peer-focus:ring-2 peer-focus:ring-offset-2 peer-focus:ring-primary-500 dark:peer-focus:ring-offset-gray-800 <?= $colorClass ?> transition-colors duration-200"></div>
        
        <!-- Knob -->
        <div class="absolute <?= $sizeConfig['left'] ?> top-0.5 <?= $sizeConfig['knob'] ?> bg-white rounded-full shadow-sm transition-transform duration-200 peer-checked:<?= $sizeConfig['translate'] ?>"></div>
    </label>
    
    <?php if ($label || $description || $onLabel || $offLabel): ?>
    <div class="flex-1">
        <?php if ($label): ?>
        <label for="<?= esc($id) ?>" class="text-sm font-medium text-gray-900 dark:text-white cursor-pointer <?= $disabled ? 'cursor-not-allowed' : '' ?>">
            <?= esc($label) ?>
        </label>
        <?php endif; ?>
        
        <?php if ($description): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5"><?= esc($description) ?></p>
        <?php endif; ?>
        
        <?php if ($onLabel && $offLabel): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
            <span class="toggle-status-text">
                <?= $checked ? esc($onLabel) : esc($offLabel) ?>
            </span>
        </p>
        <script>
            (function() {
                var el = document.getElementById('<?= esc($id) ?>');
                if (!el || el.dataset.toggleBound === 'true') return;
                el.dataset.toggleBound = 'true';
                el.addEventListener('change', function() {
                    this.closest('.xs-toggle').querySelector('.toggle-status-text').textContent = 
                        this.checked ? '<?= esc($onLabel) ?>' : '<?= esc($offLabel) ?>';
                });
            })();
        </script>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
