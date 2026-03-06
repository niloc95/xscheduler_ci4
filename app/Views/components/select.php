<?php
/**
 * Reusable Select Component
 */

$name = $name ?? '';
$id = $id ?? $name;
$label = $label ?? null;
$options = $options ?? [];
$value = (string) ($value ?? '');
$required = (bool) ($required ?? false);
$disabled = (bool) ($disabled ?? false);
$error = $error ?? null;
$class = $class ?? '';
$attrs = $attrs ?? [];

$selectClasses = trim('w-full px-2.5 py-1.5 border rounded-md bg-surface text-on-surface text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary ' .
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
    <label for="<?= esc($id) ?>" class="block text-xs font-medium text-on-surface-variant mb-0.5">
        <?= esc($label) ?>
    </label>
    <?php endif; ?>

    <select
        id="<?= esc($id) ?>"
        name="<?= esc($name) ?>"
        class="<?= esc($selectClasses) ?>"
        <?= $required ? 'required' : '' ?>
        <?= $disabled ? 'disabled' : '' ?>
        <?= $attrString ?>
    >
        <?php foreach ($options as $opt): ?>
            <?php
            $optValue = isset($opt['value']) ? (string) $opt['value'] : '';
            $optLabel = isset($opt['label']) ? (string) $opt['label'] : '';
            ?>
            <option value="<?= esc($optValue) ?>" <?= $optValue === $value ? 'selected' : '' ?>>
                <?= esc($optLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($error): ?>
    <div class="mt-1 text-sm text-error"><?= esc($error) ?></div>
    <?php endif; ?>
</div>
