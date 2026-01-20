<?php
/**
 * Form Input Component
 * 
 * Standardized text input field with label, help text, and error handling.
 * 
 * Props:
 * @param string $name Required. Input name attribute
 * @param string $label Optional. Field label
 * @param string $type Optional. Input type (text, email, password, number, tel, url)
 * @param string $value Optional. Current value
 * @param string $placeholder Optional. Placeholder text
 * @param string $help Optional. Help text below input
 * @param string $error Optional. Error message
 * @param bool $required Optional. Whether field is required
 * @param bool $disabled Optional. Whether field is disabled
 * @param bool $readonly Optional. Whether field is readonly
 * @param string $icon Optional. Leading icon
 * @param string $iconRight Optional. Trailing icon
 * @param string $class Optional. Additional input classes
 * @param string $id Optional. Custom ID (defaults to name)
 * @param array $attrs Optional. Additional HTML attributes
 * @param string $autocomplete Optional. Autocomplete attribute
 * 
 * Usage:
 * <?= $this->include('components/ui/input', [
 *     'name' => 'email',
 *     'label' => 'Email Address',
 *     'type' => 'email',
 *     'placeholder' => 'you@example.com',
 *     'required' => true,
 *     'icon' => 'mail'
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$name = $name ?? '';
$label = $label ?? null;
$type = $type ?? 'text';
$value = $value ?? '';
$placeholder = $placeholder ?? '';
$help = $help ?? null;
$error = $error ?? null;
$required = $required ?? false;
$disabled = $disabled ?? false;
$readonly = $readonly ?? false;
$icon = $icon ?? null;
$iconRight = $iconRight ?? null;
$class = $class ?? '';
$id = $id ?? $name;
$attrs = $attrs ?? [];
$autocomplete = $autocomplete ?? null;

// Build additional attributes string
$attrString = '';
foreach ($attrs as $key => $val) {
    $attrString .= ' ' . esc($key) . '="' . esc($val) . '"';
}

// Input classes based on state
$inputClasses = 'block w-full rounded-lg border transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0';

if ($error) {
    $inputClasses .= ' border-red-300 dark:border-red-600 text-red-900 dark:text-red-200 placeholder-red-300 dark:placeholder-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50 dark:bg-red-900/20';
} else {
    $inputClasses .= ' border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-800';
}

if ($disabled) {
    $inputClasses .= ' bg-gray-100 dark:bg-gray-700 cursor-not-allowed opacity-60';
}

// Padding based on icons
if ($icon && $iconRight) {
    $inputClasses .= ' pl-10 pr-10 py-2.5';
} elseif ($icon) {
    $inputClasses .= ' pl-10 pr-4 py-2.5';
} elseif ($iconRight) {
    $inputClasses .= ' pl-4 pr-10 py-2.5';
} else {
    $inputClasses .= ' px-4 py-2.5';
}

$inputClasses .= ' text-sm ' . $class;
?>

<div class="xs-form-group">
    <?php if ($label): ?>
    <label for="<?= esc($id) ?>" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
        <?= esc($label) ?>
        <?php if ($required): ?>
        <span class="text-red-500 dark:text-red-400">*</span>
        <?php endif; ?>
    </label>
    <?php endif; ?>
    
    <div class="relative">
        <?php if ($icon): ?>
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <span class="material-symbols-outlined text-lg <?= $error ? 'text-red-400 dark:text-red-500' : 'text-gray-400 dark:text-gray-500' ?>"><?= esc($icon) ?></span>
        </div>
        <?php endif; ?>
        
        <input 
            type="<?= esc($type) ?>"
            id="<?= esc($id) ?>"
            name="<?= esc($name) ?>"
            value="<?= esc($value) ?>"
            placeholder="<?= esc($placeholder) ?>"
            class="<?= $inputClasses ?>"
            <?php if ($required): ?>required<?php endif; ?>
            <?php if ($disabled): ?>disabled<?php endif; ?>
            <?php if ($readonly): ?>readonly<?php endif; ?>
            <?php if ($autocomplete): ?>autocomplete="<?= esc($autocomplete) ?>"<?php endif; ?>
            <?php if ($error): ?>aria-invalid="true" aria-describedby="<?= esc($id) ?>-error"<?php endif; ?>
            <?= $attrString ?>
        >
        
        <?php if ($iconRight): ?>
        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
            <span class="material-symbols-outlined text-lg <?= $error ? 'text-red-400 dark:text-red-500' : 'text-gray-400 dark:text-gray-500' ?>"><?= esc($iconRight) ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($error): ?>
    <p id="<?= esc($id) ?>-error" class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
        <span class="material-symbols-outlined text-sm">error</span>
        <?= esc($error) ?>
    </p>
    <?php elseif ($help): ?>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?= esc($help) ?></p>
    <?php endif; ?>
</div>
