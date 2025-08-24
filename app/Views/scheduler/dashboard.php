<!DOCTYPE html>
<html lang="en" class="transition-colors duration-200">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Scheduler - Dashboard</title>
  <link rel="stylesheet" href="<?= base_url('build/assets/style.css') ?>">
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors duration-200">
  <?= $this->include('components/header') ?>

  <main class="page-container py-6">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand p-6">
      <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Scheduling - Admin Dashboard View</h1>
      <p class="text-gray-600 dark:text-gray-300 mb-6">Start with a simple day view. We will wire this up next.</p>

      <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
        <?php for ($i = 9; $i <= 17; $i++): ?>
          <div class="p-3 rounded-lg border dark:border-gray-700 <?php echo ($i % 3 === 0) ? 'bg-gray-100 dark:bg-gray-700 time-slot-booked' : 'bg-white dark:bg-gray-800 time-slot-available'; ?> transition-colors duration-200">
            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-700 dark:text-gray-200">
                <?= sprintf('%02d:00', $i) ?> - <?= sprintf('%02d:00', $i+1) ?>
              </span>
              <span class="text-xs px-2 py-1 rounded-md <?php echo ($i % 3 === 0) ? 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-300' : 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-300'; ?>">
                <?php echo ($i % 3 === 0) ? 'Booked' : 'Available'; ?>
              </span>
            </div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </main>

  <?= $this->include('components/footer') ?>
  <script type="module" src="<?= base_url('build/assets/dark-mode.js') ?>"></script>
</body>
</html>
