<?php
/**
 * Button Component
 * 
 * Standardized button styles with variants.
 * 
 * Props:
 * @param string $label Required. Button text
 * @param string $variant Optional. Button variant (primary, secondary, success, danger, ghost, outline)
 * @param string $size Optional. Button size (sm, md, lg)
 * @param string $icon Optional. Material icon name (left side)
 * @param string $iconRight Optional. Material icon name (right side)
 * @param string $href Optional. If provided, renders as <a> tag
 * @param string $type Optional. Button type (button, submit, reset) - default: button
 * @param bool $disabled Optional. Disabled state
 * @param bool $loading Optional. Loading state
 * @param string $class Optional. Additional CSS classes
 * @param string $id Optional. HTML id
 * @param array $attrs Optional. Additional HTML attributes as ['data-action' => 'save']
 * 
 * Usage:
 * <?= $this->include('components/ui/button', [
 *     'label' => 'Save Changes',
 *     'variant' => 'primary',
 *     'icon' => 'save',
 *     'type' => 'submit'
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$label = $label ?? 'Button';
$variant = $variant ?? 'primary';
$size = $size ?? 'md';
$icon = $icon ?? null;
$iconRight = $iconRight ?? null;
$href = $href ?? null;
$type = $type ?? 'button';
$disabled = $disabled ?? false;
$loading = $loading ?? false;
$class = $class ?? '';
$id = $id ?? null;
$attrs = $attrs ?? [];

// Size classes
$sizes = [
    'sm' => 'px-3 py-1.5 text-sm gap-1.5',
    'md' => 'px-4 py-2 text-sm gap-2',
    'lg' => 'px-6 py-3 text-base gap-2.5',
];

$iconSizes = [
    'sm' => 'text-base',
    'md' => 'text-lg',
    'lg' => 'text-xl',
];

// Variant classes
$variants = [
    'primary' => 'bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white border border-transparent shadow-sm',
    'secondary' => 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600',
    'success' => 'bg-green-600 hover:bg-green-700 active:bg-green-800 text-white border border-transparent shadow-sm',
    'danger' => 'bg-red-600 hover:bg-red-700 active:bg-red-800 text-white border border-transparent shadow-sm',
    'warning' => 'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white border border-transparent shadow-sm',
    'ghost' => 'bg-transparent hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border border-transparent',
    'outline' => 'bg-transparent hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600',
    'outline-primary' => 'bg-transparent hover:bg-blue-50 dark:hover:bg-blue-900/20 text-blue-600 dark:text-blue-400 border border-blue-300 dark:border-blue-700',
    'outline-danger' => 'bg-transparent hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-300 dark:border-red-700',
];

$sizeClass = $sizes[$size] ?? $sizes['md'];
$variantClass = $variants[$variant] ?? $variants['primary'];
$iconSize = $iconSizes[$size] ?? $iconSizes['md'];

// Build classes
$baseClass = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed';
$btnClass = trim("$baseClass $sizeClass $variantClass $class");

// Build additional attributes
$attrString = '';
if ($id) $attrString .= ' id="' . esc($id) . '"';
if ($disabled || $loading) $attrString .= ' disabled';
foreach ($attrs as $key => $val) {
    $attrString .= ' ' . esc($key) . '="' . esc($val) . '"';
}

$tag = $href ? 'a' : 'button';
$tagAttrs = $href ? 'href="' . esc($href) . '"' : 'type="' . esc($type) . '"';
?>

<<?= $tag ?> <?= $tagAttrs ?> class="<?= esc($btnClass) ?>"<?= $attrString ?>>
    <?php if ($loading): ?>
        <svg class="animate-spin <?= $iconSize ?>" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    <?php elseif ($icon): ?>
        <span class="material-symbols-outlined <?= $iconSize ?>"><?= esc($icon) ?></span>
    <?php endif; ?>
    
    <span><?= esc($label) ?></span>
    
    <?php if ($iconRight && !$loading): ?>
        <span class="material-symbols-outlined <?= $iconSize ?>"><?= esc($iconRight) ?></span>
    <?php endif; ?>
</<?= $tag ?>>
