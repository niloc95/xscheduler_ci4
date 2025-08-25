<?= $this->extend('components/layout') ?>

<?= $this->section('content') ?>
<div class="content-wrapper">
    <div class="content-main space-y-8">
        
        <!-- Header -->
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-4">xScheduler Design System</h1>
            <p class="text-lg text-gray-600 dark:text-gray-300">A comprehensive guide to our UI components and patterns</p>
        </div>
        
        <!-- Navigation -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="<?= base_url('styleguide/components') ?>" class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand hover:shadow-lg transition-shadow">
                <div class="card-body p-6 text-center">
                    <h3 class="text-xl font-semibold mb-2">Components</h3>
                    <p class="text-gray-600 dark:text-gray-300">Buttons, forms, cards, and more</p>
                </div>
            </a>
            
            <a href="<?= base_url('styleguide/scheduler') ?>" class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand hover:shadow-lg transition-shadow">
                <div class="card-body p-6 text-center">
                    <h3 class="text-xl font-semibold mb-2">Scheduler</h3>
                    <p class="text-gray-600 dark:text-gray-300">Time slots, calendars, appointments</p>
                </div>
            </a>
            
            <div class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
                <div class="card-body p-6 text-center">
                    <h3 class="text-xl font-semibold mb-2">Typography</h3>
                    <p class="text-gray-600 dark:text-gray-300">Headings, text, and spacing</p>
                </div>
            </div>
        </div>
        
    </div>
</div>
<?= $this->endSection() ?>