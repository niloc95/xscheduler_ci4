<?php

// Create Service form view
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Create Service<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $validationErrors = session()->getFlashdata('errors') ?? []; ?>
<?php if ($message = session()->getFlashdata('message')): ?>
    <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-600/40 dark:bg-emerald-900/30 dark:text-emerald-200">
        <?= esc($message) ?>
    </div>
<?php endif; ?>
<?php if ($error = session()->getFlashdata('error')): ?>
    <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-600/50 dark:bg-red-900/30 dark:text-red-200">
        <?= esc($error) ?>
    </div>
<?php endif; ?>
<?php if (!empty($validationErrors)): ?>
    <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-600/50 dark:bg-red-900/30 dark:text-red-200">
        <ul class="list-disc space-y-1 pl-5">
            <?php foreach ((array) $validationErrors as $field => $errorText): ?>
                <li><?= esc(is_array($errorText) ? implode(', ', $errorText) : $errorText) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<div class="mb-6">
        <?= view('components/button', [
            'label' => 'Back to Services',
            'href' => base_url('/services'),
            'tag' => 'a',
            'variant' => 'text',
            'size' => 'sm',
            'icon' => 'arrow_back'
        ]) ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <form id="createServiceForm" action="<?= base_url('services/store') ?>" method="post" data-no-spa="true" class="card card-spacious">
                <?= csrf_field() ?>
                <div class="card-header flex-col items-start gap-2">
                    <h2 class="card-title text-xl">Service Details</h2>
                    <p class="card-subtitle">Fill out core information and assign providers.</p>
                </div>

                <div class="card-body space-y-6">
                    <?= $this->include('services/_form', ['categories' => $categories, 'providers' => $providers]) ?>
                </div>

                <div class="card-footer flex flex-wrap justify-end gap-3">
                    <button id="openCategoryModal"
                            type="button"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 px-4 py-2 text-sm border border-outline text-on-surface hover:bg-surface-variant">
                        <span class="material-symbols-outlined text-base">add</span>
                        <span>New Category</span>
                    </button>
                    <button id="saveServiceButton"
                            type="submit"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 px-4 py-2 text-sm bg-primary text-on-primary hover:bg-primary-600 shadow-sm">
                        <span class="material-symbols-outlined text-base">save</span>
                        <span>Save Service</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="lg:col-span-1">
            <div class="card card-spacious">
                <div class="card-header">
                    <h3 class="card-title">Tips</h3>
                </div>
                <div class="card-body">
                <ul class="list-disc text-sm text-gray-600 dark:text-gray-300 ml-5 space-y-1">
                    <li>Providers list only shows users with role = provider.</li>
                    <li>Use "New Category" to add missing categories inline.</li>
                    <li>On save, you'll see a confirmation modal; errors show in it too.</li>
                </ul>
                </div>
            </div>
        </div>
    </div>

<!-- Category Modal -->
<div id="categoryModal" class="fixed inset-0 hidden items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Create Category</h3>
        <form id="createCategoryForm" action="<?= base_url('services/categories') ?>" method="post" data-no-spa="true">
            <?= csrf_field() ?>
            <div class="space-y-3">
                <div>
                    <label class="form-label">Name</label>
                    <input type="text" name="name" required class="form-input" />
                </div>
                <div>
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="2" class="form-input"></textarea>
                </div>
                <div>
                    <label class="form-label">Color</label>
                    <input type="color" name="color" value="#3B82F6" class="mt-1 h-10 w-16 rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700" />
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <?= view('components/button', [
                    'label' => 'Cancel',
                    'type' => 'button',
                    'attrs' => ['id' => 'cancelCategoryModal'],
                    'variant' => 'text',
                    'size' => 'md'
                ]) ?>
                <?= view('components/button', [
                    'label' => 'Create',
                    'type' => 'submit',
                    'variant' => 'filled',
                    'size' => 'md'
                ]) ?>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('services/_category_modal_script') ?>
<?= $this->endSection() ?>

