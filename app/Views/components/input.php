<?php
/**
 * Reusable Input Component
 */

$name = $name ?? '';
$id = $id ?? $name;
$type = $type ?? 'text';
$label = $label ?? null;
$value = $value ?? '';
$placeholder = $placeholder ?? '';
$required = (bool) ($required ?? false);
$disabled = (bool) ($disabled ?? false);
$error = $error ?? null;
$hint = $hint ?? null;
$class = $class ?? '';
$attrs = $attrs ?? [];

$inputClasses = trim('w-full px-3 py-2 border rounded-md bg-surface text-on-surface text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary ' .
    ($error ? 'border-error ' : 'border-outline ') .
    ($disabled ? 'opacity-60 cursor-not-allowed ' : '') .
    $class);

$attrParts = [];
foreach ($attrs as $key => $val) {
    if ($val === null || $val === false) {
        continue;
    }

    if ($val === true) {
        $attrParts[] = esc((string) $key, 'attr');
        continue;
    }

    $attrParts[] = sprintf('%s="%s"', esc((string) $key, 'attr'), esc((string) $val, 'attr'));
}

$attrString = implode(' ', $attrParts);
?>

<div>
    <?php if ($label): ?>
    <label for="<?= esc($id) ?>" class="block text-sm font-medium text-on-surface mb-2">
        <?= esc($label) ?><?= $required ? ' *' : '' ?>
    </label>
    <?php endif; ?>

    <input
        type="<?= esc($type) ?>"
        id="<?= esc($id) ?>"
        name="<?= esc($name) ?>"
        value="<?= esc($value) ?>"
        placeholder="<?= esc($placeholder) ?>"
        class="<?= esc($inputClasses) ?>"
        <?= $required ? 'required' : '' ?>
        <?= $disabled ? 'disabled' : '' ?>
        <?= $attrString ?>
    >

    <?php if ($error): ?>
    <div class="mt-1 text-sm text-error"><?= esc($error) ?></div>
    <?php elseif ($hint): ?>
    <div class="mt-1 text-xs text-on-surface-variant"><?= esc($hint) ?></div>
    <?php endif; ?>
</div>
