<?php
// Categories list view (manageable)
// ?>
<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Categories" data-page-subtitle="Manage service categories">
  <div class="mb-6 flex justify-between items-center">
    <a href="<?= base_url('/services') ?>" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
      <span class="material-symbols-outlined mr-1">arrow_back</span>
      Back to Services
    </a>
  </div>

  <!-- Replace simple list with manageable table -->
  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Categories</h3>
      <form id="quickCategory" action="/services/categories" method="post" class="flex items-center space-x-2">
        <?= csrf_field() ?>
        <input name="name" placeholder="New category name" class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2" required />
        <input name="color" type="color" value="#3B82F6" class="h-10 w-12 rounded" />
        <button class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg" type="submit">Add</button>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table id="categoriesTable" class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-700">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Color</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <?php if (!empty($categories)) : foreach ($categories as $c): ?>
          <tr data-id="<?= (int)$c['id'] ?>">
            <td class="px-6 py-4">
              <input class="nameInput w-full bg-transparent text-sm text-gray-900 dark:text-gray-100 border border-transparent focus:border-gray-300 rounded px-2 py-1" value="<?= esc($c['name']) ?>" />
            </td>
            <td class="px-6 py-4">
              <input class="colorInput h-9 w-14" type="color" value="<?= esc($c['color'] ?? '#3B82F6') ?>" />
            </td>
            <td class="px-6 py-4">
              <label class="inline-flex items-center space-x-2 text-sm">
                <input type="checkbox" class="activeInput" <?= !empty($c['active']) ? 'checked' : '' ?> />
                <span class="text-gray-700 dark:text-gray-300"><?= !empty($c['active']) ? 'Active' : 'Inactive' ?></span>
              </label>
            </td>
            <td class="px-6 py-4 text-right">
              <button class="saveBtn inline-flex items-center px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">Save</button>
              <button class="deleteBtn inline-flex items-center px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-sm ml-2">Delete/Deactivate</button>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr>
            <td colspan="4" class="px-6 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No categories yet. Create one using the form above.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
  (function(){
    const table = document.getElementById('categoriesTable');
    const quick = document.getElementById('quickCategory');

    if (quick) quick.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(quick);
      const body = new URLSearchParams({ name: fd.get('name') || '', color: fd.get('color') || '#3B82F6' });
  const res = await fetch('/services/categories', {
        method: 'POST',
        headers: {
          'X-Requested-With':'XMLHttpRequest',
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body
      }).catch(err => ({ ok: false }));
      if (!res || !res.ok) return;
      const data = await res.json().catch(() => null);
      if (data && data.success) location.reload();
    });

    if (!table) return;
    table.querySelectorAll('tr[data-id]').forEach(row => {
      const id = row.getAttribute('data-id');
      row.querySelector('.saveBtn').addEventListener('click', async () => {
        const name = row.querySelector('.nameInput').value.trim();
        const color = row.querySelector('.colorInput').value;
        const active = row.querySelector('.activeInput').checked ? 1 : 0;
  const res = await fetch('/services/categories/' + id, {
          method: 'POST',
          headers: {
            'X-Requested-With':'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: new URLSearchParams({ name, color, active })
        }).catch(err => ({ ok: false }));
        if (!res || !res.ok) return;
        const data = await res.json().catch(() => null);
        if (data && data.success) location.reload();
      });
      row.querySelector('.deleteBtn').addEventListener('click', async () => {
  const res = await fetch('/services/categories/' + id + '/delete', {
          method: 'POST',
          headers: {
            'X-Requested-With':'XMLHttpRequest',
            'Accept': 'application/json'
          }
        }).catch(err => ({ ok: false }));
        if (!res || !res.ok) return;
        const data = await res.json().catch(() => null);
        if (data && data.success) location.reload();
      });
    });
  })();
</script>
<?= $this->endSection() ?>
