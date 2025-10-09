<?php
/**
 * Customer Management - Create Customer View
 *
 * Form for creating new customer records in the database.
 * Part of the admin-focused customer CRUD operations.
 * 
 * Access: Admin role only
 * Related: index.php (list), edit.php (update)
 */
?>
<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'customer-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Create Customer<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Create Customer">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>

    <div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <form method="post" action="<?= base_url('customer-management/store') ?>" class="space-y-6">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">First name</label>
                    <input type="text" name="first_name" value="<?= esc(old('first_name')) ?>" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last name</label>
                    <input type="text" name="last_name" value="<?= esc(old('last_name')) ?>" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input type="email" name="email" required value="<?= esc(old('email')) ?>" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                <input type="text" name="phone" value="<?= esc(old('phone')) ?>" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                <input type="text" name="address" value="<?= esc(old('address')) ?>" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"><?= esc(old('notes')) ?></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg"><span class="material-symbols-outlined">save</span>Save</button>
                <a href="<?= base_url('customer-management') ?>" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
