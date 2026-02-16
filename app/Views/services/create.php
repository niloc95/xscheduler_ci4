<?php

// Create Service form view
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Create Service<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="mb-6">
        <a href="<?= base_url('/services') ?>" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
            <span class="material-symbols-outlined mr-1">arrow_back</span>
            Back to Services
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <form id="createServiceForm" action="/services/store" method="post" class="card card-spacious">
                <?= csrf_field() ?>
                <div class="card-header flex-col items-start gap-2">
                    <h2 class="card-title text-xl">Service Details</h2>
                    <p class="card-subtitle">Fill out core information and assign providers.</p>
                </div>

                <div class="card-body space-y-6">
                    <?= $this->include('services/_form', ['categories' => $categories, 'providers' => $providers]) ?>
                </div>

                <div class="card-footer flex flex-wrap justify-end gap-3">
                    <button type="button" id="openCategoryModal" class="btn btn-secondary">
                        <span class="material-symbols-outlined">add</span>
                        New Category
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">save</span>
                        Save Service
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
</div>

<!-- Category Modal -->
<div id="categoryModal" class="fixed inset-0 hidden items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Create Category</h3>
        <form id="createCategoryForm">
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" name="new_category_name" required class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea name="new_category_description" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
                    <input type="color" name="new_category_color" value="#3B82F6" class="mt-1 h-10 w-16 rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700" />
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="btn btn-ghost" id="cancelCategoryModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Result Modal -->
<div id="resultModal" class="fixed inset-0 hidden items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg w-full max-w-md p-6">
        <div id="resultIcon" class="mb-3"></div>
        <h3 id="resultTitle" class="text-lg font-semibold text-gray-900 dark:text-white mb-2"></h3>
        <p id="resultMessage" class="text-sm text-gray-700 dark:text-gray-300"></p>
        <div class="mt-6 flex justify-end">
            <button type="button" class="btn btn-primary" id="closeResultModal">Close</button>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function() {
  const form = document.getElementById('createServiceForm');
  const categorySelect = document.getElementById('categorySelect');
  const categoryModal = document.getElementById('categoryModal');
  const openCategoryModal = document.getElementById('openCategoryModal');
  const cancelCategoryModal = document.getElementById('cancelCategoryModal');
  const createCategoryForm = document.getElementById('createCategoryForm');
  const resultModal = document.getElementById('resultModal');
  const resultTitle = document.getElementById('resultTitle');
  const resultMessage = document.getElementById('resultMessage');
  const closeResultModal = document.getElementById('closeResultModal');

  function show(el) { el.classList.remove('hidden'); el.classList.add('flex'); }
  function hide(el) { el.classList.add('hidden'); el.classList.remove('flex'); }

  openCategoryModal.addEventListener('click', () => show(categoryModal));
  cancelCategoryModal.addEventListener('click', () => hide(categoryModal));

  createCategoryForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(createCategoryForm);
    try {
            const res = await fetch('<?= base_url('services/categories') ?>', {
        method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: new URLSearchParams({
          name: fd.get('new_category_name'),
          description: fd.get('new_category_description'),
          color: fd.get('new_category_color') || '#3B82F6',
        })
      });
      const data = await res.json();
      if (data && data.success) {
        const opt = document.createElement('option');
        opt.value = data.id;
        opt.textContent = data.name;
        categorySelect.appendChild(opt);
        categorySelect.value = String(data.id);
        hide(categoryModal);
        return;
      }
      throw new Error((data && data.error) || 'Could not create category');
    } catch (err) {
      resultTitle.textContent = 'Category error';
      resultMessage.textContent = err.message;
      show(resultModal);
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    try {
            const res = await fetch('<?= base_url('services/store') ?>', {
        method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: fd
      });
      const data = await res.json();
      if (data && data.success) {
        resultTitle.textContent = 'Service created';
        resultMessage.textContent = 'Your service was saved successfully.';
        show(resultModal);
      } else {
        resultTitle.textContent = 'Could not save service';
        resultMessage.textContent = (data && (data.error || (data.details && JSON.stringify(data.details)))) || 'Unknown error.';
        show(resultModal);
      }
    } catch (err) {
      resultTitle.textContent = 'Network error';
      resultMessage.textContent = err.message;
      show(resultModal);
    }
  });

  closeResultModal.addEventListener('click', () => {
    hide(resultModal);
    window.location.href = '<?= base_url('/services') ?>';
  });
})();
</script>
<?= $this->endSection() ?>
