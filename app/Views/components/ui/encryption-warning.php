<?php
/**
 * Encryption Key Mismatch Warning
 *
 * Usage:
 *   $this->include('components/ui/encryption-warning', [
 *       'condition' => $decryptError === 'encryption_key_mismatch',
 *       'message'   => 'Please re-enter your SMTP credentials and save to restore email functionality.',
 *   ]);
 *
 * @var bool   $condition Whether to show the warning
 * @var string $message   Context-specific instruction text
 */
if (empty($condition)) return;
?>
<div class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
    <div class="flex items-start gap-3">
        <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-xl">warning</span>
        <div>
            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Encryption Key Mismatch</p>
            <p class="text-xs text-amber-700 dark:text-amber-300 mt-1"><?= esc($message) ?></p>
        </div>
    </div>
</div>
