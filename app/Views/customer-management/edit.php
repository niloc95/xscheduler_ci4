<?php
/**
 * Customer Management - Edit Customer View
 *
 * Form for updating existing customer records in the database.
 * Part of the customer-management CRUD operations.
 * 
 * Access: Admin/provider/staff for scoped records; admin-only deletion
 * Related: index.php (list), create.php (new)
 */
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'customer-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Edit Customer<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('components/ui/flash-messages') ?>

    <div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <form method="post" action="<?= base_url('customer-management/update/' . esc($customerIdentifier ?? ($customer['hash'] ?? $customer['id'] ?? ''))) ?>" class="space-y-6">
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

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                    <span class="material-symbols-outlined">save</span>
                    Save
                </button>
                <a href="<?= base_url('customer-management') ?>" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">
                    Cancel
                </a>
            </div>
        </form>

        <?php if (!empty($canDeleteCustomers)): ?>
        <div class="mt-4 flex flex-wrap gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
            <form action="<?= base_url('customer-management/delete/' . esc($customerIdentifier ?? ($customer['hash'] ?? $customer['id'] ?? ''))) ?>" method="post" class="inline-flex">
                <?= csrf_field() ?>
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg disabled:opacity-60 disabled:cursor-not-allowed"
                    onclick="return confirm('Delete this customer? This cannot be undone. Customers with appointment history cannot be deleted.');"
                    <?= !empty($appointmentCount) ? 'disabled aria-disabled="true" title="Customer has appointment history and cannot be deleted"' : '' ?>
                >
                    <span class="material-symbols-outlined">delete</span>
                    Delete
                </button>
            </form>
            <?php if (!empty($appointmentCount)): ?>
            <p class="self-center text-sm text-amber-600 dark:text-amber-400">
                This customer has <?= esc((string) $appointmentCount) ?> appointment<?= (int) $appointmentCount === 1 ? '' : 's' ?> and cannot be deleted.
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
