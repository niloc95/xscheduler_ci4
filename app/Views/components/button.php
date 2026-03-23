<?php
/**
 * Reusable Button Component
 *
 * Usage:
 * <?= view('components/button', [
 *   'label' => 'Save',
 *   'variant' => 'filled',
 *   'size' => 'md',
 *   'type' => 'submit',
 *   'icon' => 'save',
 *   'attrs' => ['id' => 'save-btn']
 * ]) ?>
 */

$label = $label ?? '';
$variant = $variant ?? 'filled'; // filled|outlined|text|tonal|danger
$size = $size ?? 'md'; // sm|md|lg
$type = $type ?? 'button';
$icon = $icon ?? null;
$class = $class ?? '';
$attrs = $attrs ?? [];
$tag = $tag ?? 'button'; // button|a
$href = $href ?? '#';
$disabled = (bool) ($disabled ?? false);

// CodeIgniter view data can persist across sequential component renders.
// If a previous button render set tag='a', a later submit button can inherit it.
// A submit/reset control must always render as a native <button>.
if ($tag === 'a' && in_array($type, ['submit', 'reset'], true)) {
    $tag = 'button';
    $href = '#';
}

$base = 'inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500';

$sizeMap = [
    'sm' => 'px-3 py-1.5 text-sm',
    'md' => 'px-4 py-2 text-sm',
    'lg' => 'px-5 py-2.5 text-base',
];

$variantMap = [
    'filled' => 'bg-primary text-on-primary hover:bg-primary-600 shadow-sm',
    'outlined' => 'border border-outline text-on-surface hover:bg-surface-variant',
    'text' => 'text-primary hover:bg-primary-container/30',
    'tonal' => 'bg-surface-variant text-on-surface hover:bg-surface-2',
    'danger' => 'bg-error text-on-error hover:bg-error-600 shadow-sm',
];

$classes = trim($base . ' ' . ($sizeMap[$size] ?? $sizeMap['md']) . ' ' . ($variantMap[$variant] ?? $variantMap['filled']) . ' ' . $class);

$attrParts = [];
foreach ($attrs as $key => $value) {
    if ($value === null || $value === false) {
        continue;
    }

    if ($value === true) {
        $attrParts[] = esc((string) $key, 'attr');
        continue;
    }

    $attrParts[] = sprintf('%s="%s"', esc((string) $key, 'attr'), esc((string) $value, 'attr'));
}

if ($disabled) {
    $attrParts[] = 'disabled';
    $attrParts[] = 'aria-disabled="true"';
}

$attrString = implode(' ', $attrParts);
?>

<?php if ($tag === 'a'): ?>
<a href="<?= esc($href) ?>" class="<?= esc($classes) ?>" <?= $attrString ?>>
    <?php if ($icon): ?>
    <span class="material-symbols-outlined text-base"><?= esc($icon) ?></span>
    <?php endif; ?>
    <span><?= esc($label) ?></span>
</a>
<?php else: ?>
<button type="<?= esc($type) ?>" class="<?= esc($classes) ?>" <?= $attrString ?>>
    <?php if ($icon): ?>
    <span class="material-symbols-outlined text-base"><?= esc($icon) ?></span>
    <?php endif; ?>
    <span><?= esc($label) ?></span>
</button>
<?php endif; ?>
