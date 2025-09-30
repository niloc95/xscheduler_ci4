<?php
// Shared Categories Create/Edit view
// Expects: $action_url (string), $data (array)
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?php
    $isEdit = !empty($data) && isset($data['id']);
    $title = $isEdit ? 'Edit Category' : 'Create Category';
    $subtitle = $isEdit ? 'Update category details' : 'Add a new category';
?>

<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?><?= esc($subtitle) ?><?= $this->endSection() ?>

<?= $this->section('dashboard_content_top') ?>
    <a href="<?= base_url('/services/categories') ?>" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
        <span class="material-symbols-outlined mr-1">arrow_back</span>
        Back to Categories
    </a>
    <?php if ($message = session()->getFlashdata('message')): ?>
        <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-600/40 dark:bg-emerald-900/30 dark:text-emerald-200">
            <?= esc($message) ?>
        </div>
    <?php endif; ?>
    <?php if ($error = session()->getFlashdata('error')): ?>
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-600/50 dark:bg-red-900/30 dark:text-red-200">
            <?= esc($error) ?>
        </div>
    <?php endif; ?>
    <?php $validationErrors = session()->getFlashdata('errors') ?? []; ?>
    <?php if (!empty($validationErrors)): ?>
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-600/50 dark:bg-red-900/30 dark:text-red-200">
            <ul class="list-disc pl-5 space-y-1">
                <?php foreach ((array)$validationErrors as $field => $errorText): ?>
                    <li><?= esc(is_array($errorText) ? implode(', ', $errorText) : $errorText) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <div class="max-w-xl">
        <form action="<?= esc($action_url) ?>" method="post" class="mb-4 p-4 rounded-lg shadow-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
            <?php endif; ?>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" name="name" value="<?= esc(old('name', $data['name'] ?? '')) ?>" required class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"><?= esc(old('description', $data['description'] ?? '')) ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
                    <input type="color" name="color" value="<?= esc(old('color', $data['color'] ?? '#3B82F6')) ?>" class="mt-1 h-10 w-16 rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700" />
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="active" value="0" />
                    <input id="activeCheckbox" type="checkbox" name="active" value="1" <?= old('active', !isset($data['active']) || $data['active']) ? 'checked' : '' ?> class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <label for="activeCheckbox" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="<?= base_url('/services?tab=categories') ?>" class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"><?= $isEdit ? 'Save Changes' : 'Create Category' ?></button>
            </div>
        </form>
    </div>
<?= $this->endSection() ?>
