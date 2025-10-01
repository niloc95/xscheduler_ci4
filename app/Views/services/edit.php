<?php
// Service Edit view
?>
<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'services']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Edit Service" data-page-subtitle="Update service details">
    <div class="mb-6">
        <a href="<?= base_url('/services') ?>" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
            <span class="material-symbols-outlined mr-1">arrow_back</span>
            Back to Services
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
      <form id="editServiceForm" action="/services/update/<?= (int)$service['id'] ?>" method="post" class="card card-spacious">
        <?= csrf_field() ?>
                <input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>">
                <div class="card-header flex-col items-start gap-2">
          <h2 class="card-title text-xl">Service Details</h2>
          <p class="card-subtitle">Update pricing, providers, and visibility.</p>
        </div>
        <div class="card-body space-y-6">
                <?= $this->include('services/_form', ['service' => $service, 'categories' => $categories, 'providers' => $providers, 'linkedProviders' => $linkedProviders ?? []]) ?>
        </div>

        <div class="card-footer flex justify-end">
          <button type="submit" class="btn btn-primary">
            <span class="material-symbols-outlined">save</span>
                        Save Changes
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
                    <li>Change category or add a new one inline.</li>
                    <li>Manage providers by selecting multiple entries.</li>
                </ul>
        </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Modal (same as create view) -->
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
(function(){
  const form = document.getElementById('editServiceForm');
  const serviceId = form.service_id.value;
  const openCategoryModal = document.getElementById('openCategoryModal');
  const categoryModal = document.getElementById('categoryModal');
  const cancelCategoryModal = document.getElementById('cancelCategoryModal');
  const createCategoryForm = document.getElementById('createCategoryForm');
  const categorySelect = document.getElementById('categorySelect');
  const resultModal = document.getElementById('resultModal');
  const resultTitle = document.getElementById('resultTitle');
  const resultMessage = document.getElementById('resultMessage');
  const closeResultModal = document.getElementById('closeResultModal');

  const show = (el) => { el.classList.remove('hidden'); el.classList.add('flex'); };
  const hide = (el) => { el.classList.add('hidden'); el.classList.remove('flex'); };

  openCategoryModal.addEventListener('click', () => show(categoryModal));
  cancelCategoryModal.addEventListener('click', () => hide(categoryModal));

  createCategoryForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(createCategoryForm);
  const res = await fetch('/services/categories', {
      method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams({ name: fd.get('new_category_name'), color: fd.get('new_category_color') || '#3B82F6' })
    });
    const data = await res.json();
    if (data && data.success) {
      const opt = document.createElement('option');
      opt.value = data.id; opt.textContent = fd.get('new_category_name');
      categorySelect.appendChild(opt);
      categorySelect.value = String(data.id);
      hide(categoryModal);
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    
    // Debug: Log form data
    console.log('Form data being sent:');
    for (let [key, value] of fd.entries()) {
      console.log(`${key}: ${value}`);
    }
    
    const res = await fetch('/services/update/' + serviceId, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: fd
    }).catch(err => {
      console.error('Fetch error:', err);
      return { ok: false, status: 0 };
    });
    
    console.log('Response status:', res.status);
    
    if (!res.ok) {
      const errorText = await res.text().catch(() => 'Failed to read error response');
      console.error('Response error:', errorText);
      resultTitle.textContent = 'Update failed';
      resultMessage.textContent = `HTTP ${res.status}: ${errorText}`;
      show(resultModal);
      return;
    }
    
    const data = await res.json().catch(err => {
      console.error('JSON parse error:', err);
      return null;
    });
    
    console.log('Response data:', data);
    
    if (data && data.success) {
      resultTitle.textContent = 'Service updated successfully';
      resultMessage.textContent = 'Your changes have been saved.';
      show(resultModal);
    } else {
      resultTitle.textContent = 'Update failed';
      resultMessage.textContent = (data && (data.error || (data.details && JSON.stringify(data.details)))) || 'Unknown error.';
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
