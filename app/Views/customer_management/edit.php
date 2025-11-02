<?php
/**
 * Customer Management - Edit Customer View
 *
 * Form for updating existing customer records in the database.
 * Part of the admin-focused customer CRUD operations.
 * 
 * Access: Admin role only
 * Related: index.php (list), create.php (new)
 */
?>
<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'customer-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Edit Customer<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Edit Customer">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>

    <div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <form method="post" action="<?= base_url('customer-management/update/' . esc($customer['hash'] ?? '')) ?>" class="space-y-6">
            <?= csrf_field() ?>
            
            <!-- Dynamic field rendering based on booking settings -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php if ($fieldConfig['first_name']['display'] ?? false): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            First name
                            <?php if ($fieldConfig['first_name']['required'] ?? false): ?>
                                <span class="text-red-500">*</span>
                            <?php endif; ?>
                        </label>
                        <input 
                            type="text" 
                            name="first_name" 
                            value="<?= esc(old('first_name', $customer['first_name'] ?? '')) ?>" 
                            <?php if ($fieldConfig['first_name']['required'] ?? false): ?>required<?php endif; ?>
                            class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" 
                        />
                    </div>
                <?php endif; ?>

                <?php if ($fieldConfig['last_name']['display'] ?? false): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Last name
                            <?php if ($fieldConfig['last_name']['required'] ?? false): ?>
                                <span class="text-red-500">*</span>
                            <?php endif; ?>
                        </label>
                        <input 
                            type="text" 
                            name="last_name" 
                            value="<?= esc(old('last_name', $customer['last_name'] ?? '')) ?>" 
                            <?php if ($fieldConfig['last_name']['required'] ?? false): ?>required<?php endif; ?>
                            class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" 
                        />
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($fieldConfig['email']['display'] ?? false): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email
                        <?php if ($fieldConfig['email']['required'] ?? false): ?>
                            <span class="text-red-500">*</span>
                        <?php endif; ?>
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        value="<?= esc(old('email', $customer['email'] ?? '')) ?>" 
                        <?php if ($fieldConfig['email']['required'] ?? false): ?>required<?php endif; ?>
                        class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" 
                    />
                </div>
            <?php endif; ?>

            <?php if ($fieldConfig['phone']['display'] ?? false): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Phone
                        <?php if ($fieldConfig['phone']['required'] ?? false): ?>
                            <span class="text-red-500">*</span>
                        <?php endif; ?>
                    </label>
                    <input 
                        type="text" 
                        name="phone" 
                        value="<?= esc(old('phone', $customer['phone'] ?? '')) ?>" 
                        <?php if ($fieldConfig['phone']['required'] ?? false): ?>required<?php endif; ?>
                        class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" 
                    />
                </div>
            <?php endif; ?>

            <?php if ($fieldConfig['address']['display'] ?? false): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Address
                        <?php if ($fieldConfig['address']['required'] ?? false): ?>
                            <span class="text-red-500">*</span>
                        <?php endif; ?>
                    </label>
                    <input 
                        type="text" 
                        name="address" 
                        value="<?= esc(old('address', $customer['address'] ?? '')) ?>" 
                        <?php if ($fieldConfig['address']['required'] ?? false): ?>required<?php endif; ?>
                        class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" 
                    />
                </div>
            <?php endif; ?>

            <?php if ($fieldConfig['notes']['display'] ?? false): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Notes
                        <?php if ($fieldConfig['notes']['required'] ?? false): ?>
                            <span class="text-red-500">*</span>
                        <?php endif; ?>
                    </label>
                    <textarea 
                        name="notes" 
                        rows="3" 
                        <?php if ($fieldConfig['notes']['required'] ?? false): ?>required<?php endif; ?>
                        class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                    ><?= esc(old('notes', $customer['notes'] ?? '')) ?></textarea>
                </div>
            <?php endif; ?>

            <?php if (!empty($customFields)): ?>
                <div class="space-y-4">
                    <?php foreach ($customFields as $fieldName => $customField): ?>
                        <?php $existingValue = $customFieldValues[$fieldName] ?? ''; ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                <?= esc($customField['title']) ?>
                                <?php if (!empty($customField['required'])): ?>
                                    <span class="text-red-500">*</span>
                                <?php endif; ?>
                            </label>
                            <?php if (($customField['type'] ?? '') === 'textarea'): ?>
                                <textarea
                                    name="<?= esc($fieldName) ?>"
                                    rows="3"
                                    <?php if (!empty($customField['required'])): ?>required<?php endif; ?>
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                                ><?= esc(old($fieldName, $existingValue)) ?></textarea>
                            <?php else: ?>
                                <input
                                    type="text"
                                    name="<?= esc($fieldName) ?>"
                                    value="<?= esc(old($fieldName, $existingValue)) ?>"
                                    <?php if (!empty($customField['required'])): ?>required<?php endif; ?>
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                                />
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                    <span class="material-symbols-outlined">save</span>
                    Save
                </button>
                <a href="<?= base_url('customer-management') ?>" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
