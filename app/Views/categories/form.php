<?php
// Shared Categories Create/Edit view
// Expects: $action_url (string), $data (array)
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?php
    $isEdit = !empty($data) && isset($data['id']);
    $title = $isEdit ? 'Edit Category' : 'Create Category';
    $subtitle = $isEdit ? 'Update category details' : 'Add a new category';
?>

<?= $this->section('header_title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('header_subtitle') ?><?= esc($subtitle) ?><?= $this->endSection() ?>
<?= $this->section('header_primary_action') ?>hidden<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Back link -->
<div class="mb-4">
    <a href="<?= base_url('/services?tab=categories') ?>" class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
        <span class="material-symbols-outlined text-base">arrow_back</span>
        Back to Categories
    </a>
</div>

<div class="max-w-xl">
    <div class="xs-card">
        <div class="xs-card-header">
            <div class="xs-card-header-content">
                <h3 class="xs-card-title"><?= esc($title) ?></h3>
            </div>
        </div>
        <div class="xs-card-body">
            <form action="<?= esc($action_url) ?>" method="post">
            <?= csrf_field() ?>
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
            <?php endif; ?>

            <div class="space-y-4">
                <div>
                    <?= view('components/input', [
                        'name' => 'name',
                        'label' => 'Name',
                        'value' => old('name', $data['name'] ?? ''),
                        'required' => true,
                    ]) ?>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea name="description" rows="3" class="form-input"><?= esc(old('description', $data['description'] ?? '')) ?></textarea>
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
                <?= view('components/button', [
                    'tag'     => 'a',
                    'href'    => base_url('/services?tab=categories'),
                    'label'   => 'Cancel',
                    'variant' => 'outlined',
                ]) ?>
                <?= view('components/button', [
                    'type'    => 'submit',
                    'label'   => $isEdit ? 'Save Changes' : 'Create Category',
                    'variant' => 'filled',
                ]) ?>
            </div>
        </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
