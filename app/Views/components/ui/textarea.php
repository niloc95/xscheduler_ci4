<?php
/**
 * Form Textarea Component
 * 
 * Standardized textarea field with label, help text, and error handling.
 * 
 * Props:
 * @param string $name Required. Textarea name attribute
 * @param string $label Optional. Field label
 * @param string $value Optional. Current value
 * @param string $placeholder Optional. Placeholder text
 * @param string $help Optional. Help text below textarea
 * @param string $error Optional. Error message
 * @param bool $required Optional. Whether field is required
 * @param bool $disabled Optional. Whether field is disabled
 * @param bool $readonly Optional. Whether field is readonly
 * @param int $rows Optional. Number of visible rows (default: 4)
 * @param int $maxlength Optional. Maximum character length
 * @param bool $showCount Optional. Show character count
 * @param string $class Optional. Additional textarea classes
 * @param string $id Optional. Custom ID (defaults to name)
 * @param array $attrs Optional. Additional HTML attributes
 * 
 * Usage:
 * <?= $this->include('components/ui/textarea', [
 *     'name' => 'notes',
 *     'label' => 'Notes',
 *     'placeholder' => 'Enter additional notes...',
 *     'rows' => 5,
 *     'maxlength' => 500,
 *     'showCount' => true
 * ]) ?>
 * 
 * @package WebSchedulr
 * @since 2.0.0
 */

$name = $name ?? '';
$label = $label ?? null;
$value = $value ?? '';
$placeholder = $placeholder ?? '';
$help = $help ?? null;
$error = $error ?? null;
$required = $required ?? false;
$disabled = $disabled ?? false;
$readonly = $readonly ?? false;
$rows = $rows ?? 4;
$maxlength = $maxlength ?? null;
$showCount = $showCount ?? false;
$class = $class ?? '';
$id = $id ?? $name;
$attrs = $attrs ?? [];

// Build additional attributes string
$attrString = '';
foreach ($attrs as $key => $val) {
    $attrString .= ' ' . esc($key) . '="' . esc($val) . '"';
}

// Textarea classes based on state
$textareaClasses = 'block w-full rounded-lg border transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0 px-4 py-2.5 text-sm resize-y';

if ($error) {
    $textareaClasses .= ' border-red-300 dark:border-red-600 text-red-900 dark:text-red-200 placeholder-red-300 dark:placeholder-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50 dark:bg-red-900/20';
} else {
    $textareaClasses .= ' border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-800';
}

if ($disabled) {
    $textareaClasses .= ' bg-gray-100 dark:bg-gray-700 cursor-not-allowed opacity-60';
}

$textareaClasses .= ' ' . $class;

// Generate unique ID for character count
$countId = $id . '-count';
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
    
    <textarea 
        id="<?= esc($id) ?>"
        name="<?= esc($name) ?>"
        placeholder="<?= esc($placeholder) ?>"
        rows="<?= (int)$rows ?>"
        class="<?= $textareaClasses ?>"
        <?php if ($required): ?>required<?php endif; ?>
        <?php if ($disabled): ?>disabled<?php endif; ?>
        <?php if ($readonly): ?>readonly<?php endif; ?>
        <?php if ($maxlength): ?>maxlength="<?= (int)$maxlength ?>"<?php endif; ?>
        <?php if ($error): ?>aria-invalid="true" aria-describedby="<?= esc($id) ?>-error"<?php endif; ?>
        <?php if ($showCount && $maxlength): ?>
        oninput="document.getElementById('<?= $countId ?>').textContent = this.value.length"
        <?php endif; ?>
        <?= $attrString ?>
    ><?= esc($value) ?></textarea>
    
    <div class="flex items-start justify-between mt-2 gap-4">
        <div class="flex-1">
            <?php if ($error): ?>
            <p id="<?= esc($id) ?>-error" class="text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">error</span>
                <?= esc($error) ?>
            </p>
            <?php elseif ($help): ?>
            <p class="text-sm text-gray-500 dark:text-gray-400"><?= esc($help) ?></p>
            <?php endif; ?>
        </div>
        
        <?php if ($showCount && $maxlength): ?>
        <p class="text-sm text-gray-400 dark:text-gray-500 flex-shrink-0">
            <span id="<?= esc($countId) ?>"><?= strlen($value) ?></span> / <?= (int)$maxlength ?>
        </p>
        <?php endif; ?>
    </div>
</div>
