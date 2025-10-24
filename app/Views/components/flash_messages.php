<?php
/**
 * Flash Messages Component
 * 
 * Displays success, error, warning, and info flash messages
 * Usage: <?= $this->include('components/flash_messages') ?>
 */
?>
<?php if (session()->getFlashdata('success')): ?>
    <div class="mb-4 p-3 rounded-lg border border-green-300/60 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200">
        <?= esc(session()->getFlashdata('success')) ?>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
        <?= esc(session()->getFlashdata('error')) ?>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('warning')): ?>
    <div class="mb-4 p-3 rounded-lg border border-yellow-300/60 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200">
        <?= esc(session()->getFlashdata('warning')) ?>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('info')): ?>
    <div class="mb-4 p-3 rounded-lg border border-blue-300/60 bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-200">
        <?= esc(session()->getFlashdata('info')) ?>
    </div>
<?php endif; ?>
