<?php
/**
 * Form Select Component
 * 
 * Standardized select dropdown with label, help text, and error handling.
 * 
 * Props:
 * @param string $name Required. Select name attribute
 * @param array $options Required. Array of options [['value' => '', 'label' => ''], ...]
 * @param string $label Optional. Field label
 * @param string $value Optional. Currently selected value
 * @param string $placeholder Optional. Placeholder option text
 * @param string $help Optional. Help text below select
 * @param string $error Optional. Error message
 * @param bool $required Optional. Whether field is required
 * @param bool $disabled Optional. Whether field is disabled
 * @param bool $multiple Optional. Allow multiple selections
 * @param string $icon Optional. Leading icon
 * @param string $class Optional. Additional select classes
 * @param string $id Optional. Custom ID (defaults to name)
 * @param array $attrs Optional. Additional HTML attributes
 * 
 * Usage:
 * <?= $this->include('components/ui/select', [
 *     'name' => 'status',
 *     'label' => 'Status',
 *     'options' => [
 *         ['value' => 'active', 'label' => 'Active'],
 *         ['value' => 'inactive', 'label' => 'Inactive']
 *     ],
 *     'value' => 'active',
 *     'placeholder' => 'Select status...'
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$name = $name ?? '';
$options = $options ?? [];
$label = $label ?? null;
$value = $value ?? '';
$placeholder = $placeholder ?? null;
$help = $help ?? null;
$error = $error ?? null;
$required = $required ?? false;
$disabled = $disabled ?? false;
$multiple = $multiple ?? false;
$icon = $icon ?? null;
$class = $class ?? '';
$id = $id ?? $name;
$attrs = $attrs ?? [];

// Build additional attributes string
$attrString = '';
foreach ($attrs as $key => $val) {
    $attrString .= ' ' . esc($key) . '="' . esc($val) . '"';
}

// Select classes based on state
$selectClasses = 'block w-full rounded-lg border transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0 appearance-none cursor-pointer';

if ($error) {
    $selectClasses .= ' border-red-300 dark:border-red-600 text-red-900 dark:text-red-200 focus:ring-red-500 focus:border-red-500 bg-red-50 dark:bg-red-900/20';
} else {
    $selectClasses .= ' border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-800';
}

if ($disabled) {
    $selectClasses .= ' bg-gray-100 dark:bg-gray-700 cursor-not-allowed opacity-60';
}

// Padding based on icon
if ($icon) {
    $selectClasses .= ' pl-10 pr-10 py-2.5';
} else {
    $selectClasses .= ' pl-4 pr-10 py-2.5';
}

$selectClasses .= ' text-sm ' . $class;
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
        
        <select 
            id="<?= esc($id) ?>"
            name="<?= esc($name) ?><?= $multiple ? '[]' : '' ?>"
            class="<?= $selectClasses ?>"
            <?php if ($required): ?>required<?php endif; ?>
            <?php if ($disabled): ?>disabled<?php endif; ?>
            <?php if ($multiple): ?>multiple<?php endif; ?>
            <?php if ($error): ?>aria-invalid="true" aria-describedby="<?= esc($id) ?>-error"<?php endif; ?>
            <?= $attrString ?>
        >
            <?php if ($placeholder && !$multiple): ?>
            <option value="" <?= empty($value) ? 'selected' : '' ?> disabled><?= esc($placeholder) ?></option>
            <?php endif; ?>
            
            <?php foreach ($options as $option): ?>
                <?php if (isset($option['group'])): ?>
                <optgroup label="<?= esc($option['group']) ?>">
                    <?php foreach ($option['options'] as $groupOption): ?>
                    <option 
                        value="<?= esc($groupOption['value']) ?>"
                        <?php if ($multiple && is_array($value)): ?>
                            <?= in_array($groupOption['value'], $value) ? 'selected' : '' ?>
                        <?php else: ?>
                            <?= $value == $groupOption['value'] ? 'selected' : '' ?>
                        <?php endif; ?>
                        <?= !empty($groupOption['disabled']) ? 'disabled' : '' ?>
                    >
                        <?= esc($groupOption['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php else: ?>
                <option 
                    value="<?= esc($option['value']) ?>"
                    <?php if ($multiple && is_array($value)): ?>
                        <?= in_array($option['value'], $value) ? 'selected' : '' ?>
                    <?php else: ?>
                        <?= $value == $option['value'] ? 'selected' : '' ?>
                    <?php endif; ?>
                    <?= !empty($option['disabled']) ? 'disabled' : '' ?>
                >
                    <?= esc($option['label']) ?>
                </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        
        <!-- Dropdown arrow -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
            <span class="material-symbols-outlined text-lg <?= $error ? 'text-red-400 dark:text-red-500' : 'text-gray-400 dark:text-gray-500' ?>">expand_more</span>
        </div>
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
