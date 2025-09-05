<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
  <?= $this->include('components/unified-sidebar', ['current_page' => 'schedule']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
  <div class="main-content" data-page-title="Schedule" data-page-subtitle="Modern calendar with Day, Week, and Month views">
    <!-- Material-style Calendar Card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <!-- Header Bar -->
      <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-3">
        <div class="flex items-center justify-between">
          <!-- Title & Navigation -->
          <div class="flex items-center space-x-4">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              <time id="calTitle" datetime="">Schedule</time>
            </h1>
            <div class="flex items-center gap-2">
              <button id="calPrev" type="button" class="btn btn-icon" aria-label="Previous">
                <span class="material-symbols-outlined">chevron_left</span>
              </button>
              <button id="calToday" type="button" class="btn btn-secondary btn-sm">
                <span class="material-symbols-outlined text-base mr-1">calendar_today</span>
                Today
              </button>
              <button id="calNext" type="button" class="btn btn-icon" aria-label="Next">
                <span class="material-symbols-outlined">chevron_right</span>
              </button>
            </div>
          </div>

          <!-- View Switcher & Actions -->
          <div class="flex items-center space-x-4">
            <!-- Filters -->
      <div class="hidden md:flex items-center gap-3">
              <div>
                <label for="filterService" class="sr-only">Service</label>
        <select id="filterService" class="px-2.5 py-1.5 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                  <option value="">All Services</option>
                  <?php foreach (($services ?? []) as $s): ?>
                    <option value="<?= esc($s['id']) ?>"><?= esc($s['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="filterProvider" class="sr-only">Provider</label>
        <select id="filterProvider" class="px-2.5 py-1.5 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                  <option value="">All Providers</option>
                  <?php foreach (($providers ?? []) as $p): ?>
                    <option value="<?= esc($p['id']) ?>"><?= esc($p['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <!-- View Tabs -->
            <div class="flex items-center gap-2">
              <button id="viewDay" data-view="day" class="btn btn-outline btn-sm">Day</button>
              <button id="viewWeek" data-view="week" class="btn btn-outline btn-sm">Week</button>
              <button id="viewMonth" data-view="month" class="btn btn-outline btn-sm">Month</button>
            </div>
            
            <!-- Add Button -->
            <button id="calAdd" type="button" class="btn btn-primary btn-sm inline-flex items-center gap-2">
              <span class="material-symbols-outlined text-base">add</span>
              Add Appointment
            </button>
          </div>
        </div>
      </header>

      <!-- Calendar Content -->
      <div class="p-4">
        <!-- Mobile filters -->
        <div class="md:hidden mb-3 flex items-center gap-3">
          <select id="filterServiceMobile" class="flex-1 px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <option value="">All Services</option>
            <?php foreach (($services ?? []) as $s): ?>
              <option value="<?= esc($s['id']) ?>"><?= esc($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="filterProviderMobile" class="flex-1 px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <option value="">All Providers</option>
            <?php foreach (($providers ?? []) as $p): ?>
              <option value="<?= esc($p['id']) ?>"><?= esc($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="calendarRoot" class="min-h-[600px]" data-service="" data-provider=""></div>
      </div>
    </div>
  </div>
  <!-- Event Modal -->
  <div id="eventModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"></div>
      <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100" id="modal-title">Event Details</h3>
            <button id="closeModal" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div id="modalContent"></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.__BASE_URL__ = '<?= base_url() ?>';
  </script>
  <script type="module" src="<?= base_url('build/assets/custom-cal.js') ?>"></script>
<?= $this->endSection() ?>
