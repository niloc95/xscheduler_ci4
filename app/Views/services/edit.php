<?php
// Service Edit view
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Edit Service<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- XS Debug: output goes to browser console -->

<div class="mb-6">
        <?= view('components/button', [
            'label' => 'Back to Services',
            'tag' => 'a',
            'href' => base_url('/services'),
            'variant' => 'text',
            'size' => 'sm',
            'icon' => 'arrow_back'
        ]) ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
      <form id="editServiceForm" action="<?= base_url('services/update/' . (int)$service['id']) ?>" method="post" data-no-spa="true" class="card card-spacious">
        <?= csrf_field() ?>
                <input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>">
                <div class="card-header flex-col items-start gap-2">
          <h2 class="card-title text-xl">Service Details</h2>
          <p class="card-subtitle">Update pricing, providers, and visibility.</p>
        </div>
        <div class="card-body space-y-6">
                <?= $this->include('services/_form', [
                  'service' => $service,
                  'categories' => $categories,
                  'providers' => $providers,
                  'linkedProviders' => $linkedProviders ?? [],
                  'slugLocked' => $slugLocked ?? true,
                  'canUnlockSlug' => $canUnlockSlug ?? false,
                ]) ?>
        </div>

        <div class="card-footer flex flex-wrap justify-end gap-3">
          <?= view('components/button', [
              'type'    => 'button',
              'id'      => 'openCategoryModal',
              'label'   => 'New Category',
              'icon'    => 'add',
              'variant' => 'outlined',
          ]) ?>
          <?= view('components/button', [
              'type'    => 'submit',
              'id'      => 'saveServiceButton',
              'label'   => 'Save Changes',
              'icon'    => 'save',
              'variant' => 'filled',
          ]) ?>
        </div>
            </form>
        </div>
        <div class="lg:col-span-1">
            <div class="xs-card">
                <div class="xs-card-header">
                    <div class="xs-card-header-content">
                        <h3 class="xs-card-title">Tips</h3>
                    </div>
                </div>
                <div class="xs-card-body">
                    <ul class="list-disc text-sm text-gray-600 dark:text-gray-300 ml-5 space-y-1">
                        <li>Change category or add a new one inline.</li>
                        <li>Manage providers by selecting multiple entries.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<!-- Category Modal (same as create view) -->
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

