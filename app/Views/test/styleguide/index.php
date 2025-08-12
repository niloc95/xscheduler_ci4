<?= $this->extend('components/layout') ?>

<?= $this->section('content') ?>
<div class="content-wrapper">
    <div class="content-main space-y-8">
        
        <!-- Header -->
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">xScheduler Design System</h1>
            <p class="text-lg text-gray-600">A comprehensive guide to our UI components and patterns</p>
        </div>
        
        <!-- Navigation -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="<?= base_url('styleguide/components') ?>" class="card hover:shadow-md transition-shadow">
                <div class="card-body text-center">
                    <h3 class="text-xl font-semibold mb-2">Components</h3>
                    <p class="text-gray-600">Buttons, forms, cards, and more</p>
                </div>
            </a>
            
            <a href="<?= base_url('styleguide/scheduler') ?>" class="card hover:shadow-md transition-shadow">
                <div class="card-body text-center">
                    <h3 class="text-xl font-semibold mb-2">Scheduler</h3>
                    <p class="text-gray-600">Time slots, calendars, appointments</p>
                </div>
            </a>
            
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-xl font-semibold mb-2">Typography</h3>
                    <p class="text-gray-600">Headings, text, and spacing</p>
                </div>
            </div>
        </div>
        
    </div>
</div>
<?= $this->endSection() ?>