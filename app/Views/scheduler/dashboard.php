<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
  <?= $this->include('components/admin-sidebar', ['current_page' => 'schedule']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
  <div class="main-content" data-page-title="Schedule" data-page-subtitle="Manage appointments, slots, and availability">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand p-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service</label>
          <select id="service" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            <?php foreach (($services ?? []) as $s): ?>
              <option value="<?= esc($s['id']) ?>"><?= esc($s['name']) ?> (<?= esc($s['duration_min']) ?> min)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Provider</label>
          <input id="provider" type="number" value="1" min="1" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
          <input id="date" type="date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
        </div>
      </div>

      <div id="slots" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3"></div>
    </div>
  </div>
  <script>
    function initSchedulerDashboard() {
      const service = document.getElementById('service');
      const provider = document.getElementById('provider');
      const dateEl = document.getElementById('date');
      const slotsEl = document.getElementById('slots');
      if (!service || !provider || !dateEl || !slotsEl) return;
      async function loadSlots() {
        slotsEl.innerHTML = '';
        const params = new URLSearchParams({
          service_id: service.value,
          provider_id: provider.value,
          date: dateEl.value
        });
        const res = await fetch(`<?= base_url('api/slots') ?>?${params.toString()}`);
        const data = await res.json();
        (data.slots || []).forEach(s => {
          const div = document.createElement('div');
          div.className = 'p-3 rounded-lg border dark:border-gray-700 bg-white dark:bg-gray-800 flex items-center justify-between';
          const span = document.createElement('span');
          span.className = 'text-sm text-gray-700 dark:text-gray-200';
          span.textContent = `${s.start} - ${s.end}`;
          const bookBtn = document.createElement('button');
          bookBtn.className = 'px-3 py-1 rounded-md text-white';
          bookBtn.style.backgroundColor = 'var(--md-sys-color-primary)';
          bookBtn.textContent = 'Hold';
          bookBtn.addEventListener('click', () => alert(`Hold ${s.start}-${s.end}`));
          div.append(span, bookBtn);
          slotsEl.appendChild(div);
        });
      }
      service.addEventListener('change', loadSlots);
      provider.addEventListener('change', loadSlots);
      dateEl.addEventListener('change', loadSlots);
      loadSlots();
    }
    document.addEventListener('DOMContentLoaded', initSchedulerDashboard);
    document.addEventListener('spa:navigated', initSchedulerDashboard);
  </script>
<?= $this->endSection() ?>
