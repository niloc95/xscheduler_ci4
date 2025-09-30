<?php
// Categories list view (manageable)
// ?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?>Categories<?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?>Manage service categories<?= $this->endSection() ?>

<?= $this->section('dashboard_content_top') ?>
<div class="flex justify-between items-center">
    <a href="<?= base_url('/services') ?>" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
        <span class="material-symbols-outlined mr-1">arrow_back</span>
        Back to Services
    </a>
</div>
<?php if ($message = session()->getFlashdata('message')): ?>
  <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-600/50 dark:bg-emerald-900/30 dark:text-emerald-200">
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
  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Categories</h3>
          <p class="text-sm text-gray-600 dark:text-gray-300">Manage the collections your services belong to and control their availability.</p>
        </div>
        <a href="<?= site_url('services/categories/create') ?>" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
          <span class="material-symbols-outlined mr-1 text-base">add</span>
          New Category
        </a>
      </div>

      <form action="<?= site_url('services/categories') ?>" method="post" class="mt-4 flex flex-col gap-3 rounded-lg border border-dashed border-gray-300 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-900/40 md:flex-row md:items-center md:gap-4">
        <?= csrf_field() ?>
        <input type="hidden" name="active" value="1" />
        <div class="flex-1">
          <label for="quickCategoryName" class="sr-only">Category name</label>
          <input id="quickCategoryName" name="name" placeholder="Quick add category" value="<?= esc(old('name', '')) ?>" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required />
        </div>
        <div class="flex items-center gap-2">
          <label for="quickCategoryColor" class="text-sm font-medium text-gray-600 dark:text-gray-300">Color</label>
          <input id="quickCategoryColor" name="color" type="color" value="<?= esc(old('color', '#3B82F6')) ?>" class="h-10 w-12 rounded-md border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800" />
        </div>
        <div class="flex justify-end md:justify-start">
          <button type="submit" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
            Add Quick Category
          </button>
        </div>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900/40">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Category</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Services</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <?php if (!empty($categories)) : foreach ($categories as $c): ?>
            <tr>
              <td class="px-6 py-4">
                <div class="flex items-start gap-3">
                  <span class="mt-1 inline-flex h-4 w-4 rounded-full border border-gray-200" style="background-color: <?= esc($c['color'] ?? '#3B82F6') ?>"></span>
                  <div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100"><?= esc($c['name']) ?></div>
                    <?php if (!empty($c['description'])): ?>
                      <p class="mt-1 text-sm text-gray-600 dark:text-gray-300"><?= esc($c['description']) ?></p>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                <?= (int)($c['services_count'] ?? 0) ?>
              </td>
              <td class="px-6 py-4">
                <?php if (!empty($c['active'])): ?>
                  <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">Active</span>
                <?php else: ?>
                  <span class="inline-flex items-center rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex flex-wrap items-center justify-end gap-2">
                  <a href="<?= site_url('services/categories/edit/' . (int)$c['id']) ?>" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <span class="material-symbols-outlined mr-1 text-sm">edit</span>
                    Edit
                  </a>

                  <?php if (!empty($c['active'])): ?>
                    <form action="<?= site_url('services/categories/' . (int)$c['id'] . '/deactivate') ?>" method="post" class="inline-flex">
                      <?= csrf_field() ?>
                      <button type="submit" class="inline-flex items-center rounded-md bg-amber-500 px-3 py-2 text-sm font-medium text-white hover:bg-amber-600" onclick="return confirm('Deactivate this category? Services will remain but marked inactive.');">
                        <span class="material-symbols-outlined mr-1 text-sm">pause</span>
                        Deactivate
                      </button>
                    </form>
                  <?php else: ?>
                    <form action="<?= site_url('services/categories/' . (int)$c['id'] . '/activate') ?>" method="post" class="inline-flex">
                      <?= csrf_field() ?>
                      <button type="submit" class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700" onclick="return confirm('Activate this category?');">
                        <span class="material-symbols-outlined mr-1 text-sm">play_arrow</span>
                        Activate
                      </button>
                    </form>
                  <?php endif; ?>

                  <form action="<?= site_url('services/categories/' . (int)$c['id'] . '/delete') ?>" method="post" class="inline-flex">
                    <?= csrf_field() ?>
                    <button type="submit" class="inline-flex items-center rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700" onclick="return confirm('Delete this category? Any linked services will become uncategorized.');">
                      <span class="material-symbols-outlined mr-1 text-sm">delete</span>
                      Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr>
              <td colspan="4" class="px-6 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No categories yet. Create one using the quick add form or the New Category button.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?= $this->endSection() ?>
