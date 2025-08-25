<?= $this->extend('components/layout') ?>

<?= $this->section('content') ?>
  <main class="page-container py-6 lg:ml-72">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand p-6">
      <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Book an Appointment</h1>
      <p class="text-gray-600 dark:text-gray-300 mb-6">Select a service and a time slot to proceed.</p>

      <form id="booking-form" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service</label>
            <select id="service" name="service_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" required>
              <option value="">Select a service</option>
              <?php foreach (($services ?? []) as $s): ?>
                <option value="<?= esc($s['id']) ?>" data-duration="<?= esc($s['duration_min']) ?>">
                  <?= esc($s['name']) ?> (<?= esc($s['duration_min']) ?> min)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Provider</label>
            <input id="provider" name="provider_id" type="number" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
            <input id="date" name="date" type="date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" required />
          </div>
        </div>

        <div id="slots" class="grid grid-cols-2 md:grid-cols-4 gap-3"></div>

        <input type="hidden" name="start" id="start" />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your Name</label>
            <input name="name" type="text" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input name="email" type="email" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" required />
          </div>
        </div>

        <div class="pt-2">
          <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-white" style="background-color: var(--md-sys-color-primary)">Book</button>
        </div>
      </form>
    </div>
  </main>

  <script>
    function initClientScheduler() {
      const service = document.getElementById('service');
      const provider = document.getElementById('provider');
      const dateEl = document.getElementById('date');
      const slotsEl = document.getElementById('slots');
      const startInput = document.getElementById('start');
      const form = document.getElementById('booking-form');
      if (!service || !provider || !dateEl || !slotsEl || !startInput || !form) return;

      async function loadSlots() {
        slotsEl.innerHTML = '';
        if (!service.value || !provider.value || !dateEl.value) return;
        const params = new URLSearchParams({
          service_id: service.value,
          provider_id: provider.value,
          date: dateEl.value
        });
        const res = await fetch(`<?= base_url('api/slots') ?>?${params.toString()}`);
        const data = await res.json();
        (data.slots || []).forEach(s => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'p-3 rounded-lg border dark:border-gray-700 text-left bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200';
          btn.textContent = `${s.start} - ${s.end}`;
          btn.addEventListener('click', () => {
            startInput.value = s.start;
            document.querySelectorAll('#slots button').forEach(b => b.classList.remove('ring-2','ring-blue-500'));
            btn.classList.add('ring-2','ring-blue-500');
          });
          slotsEl.appendChild(btn);
        });
      }
      service.addEventListener('change', loadSlots);
      provider.addEventListener('change', loadSlots);
      dateEl.addEventListener('change', loadSlots);
      loadSlots();

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!startInput.value) { alert('Please select a time slot.'); return; }
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        const res = await fetch('<?= base_url('api/book') ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.ok) {
          alert('Appointment booked!');
          window.xsSPA ? window.xsSPA.navigate('<?= base_url('/') ?>') : (window.location.href = '<?= base_url('/') ?>');
        } else {
          alert(data.error || 'Booking failed');
          loadSlots();
        }
      });
    }
    document.addEventListener('DOMContentLoaded', initClientScheduler);
    document.addEventListener('spa:navigated', initClientScheduler);
  </script>
<?= $this->endSection() ?>
