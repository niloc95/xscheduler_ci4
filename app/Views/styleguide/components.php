<?= $this->extend('components/layout') ?>

<?= $this->section('content') ?>
<div class="content-wrapper">
    <div class="content-main space-y-8">
        
        <!-- Buttons Section -->
        <div class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Buttons</h3>
            </div>
            <div class="card-body p-4 space-y-4 text-gray-700 dark:text-gray-300">
                <div>
                    <h4 class="text-lg font-medium mb-3">Button Types</h4>
                    <div class="flex gap-4 mb-4">
                        <?= ui_button('Primary Button', null, 'primary') ?>
                        <?= ui_button('Secondary Button', null, 'secondary') ?>
                    </div>
                    <div class="bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4 rounded-md overflow-x-auto text-gray-800 dark:text-gray-200">
                        <code class="text-sm">
                            &lt;?= ui_button('Primary Button', null, 'primary') ?&gt;<br>
                            &lt;?= ui_button('Secondary Button', null, 'secondary') ?&gt;
                        </code>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts Section -->
        <div class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Alerts</h3>
            </div>
            <div class="card-body p-4 space-y-4 text-gray-700 dark:text-gray-300">
                <?= ui_alert('This is an info alert', 'info', 'Information') ?>
                <?= ui_alert('This is a success alert', 'success', 'Success') ?>
                <?= ui_alert('This is a warning alert', 'warning', 'Warning') ?>
                <?= ui_alert('This is an error alert', 'error', 'Error') ?>
                
                <div class="bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4 rounded-md overflow-x-auto text-gray-800 dark:text-gray-200">
                    <code class="text-sm">
                        &lt;?= ui_alert('Message', 'info', 'Title') ?&gt;
                    </code>
                </div>
            </div>
        </div>
        
        <!-- Cards Section -->
        <div class="card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand">
            <div class="card-header px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="card-title text-lg font-semibold text-gray-900 dark:text-gray-100">Cards</h3>
            </div>
            <div class="card-body p-4 text-gray-700 dark:text-gray-300">
                <?= ui_card(
                    'Sample Card Title',
                    '<p class="text-gray-600">This is the card content area. You can put any content here.</p>'
                ) ?>
                
                <div class="bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4 rounded-md mt-4 overflow-x-auto text-gray-800 dark:text-gray-200">
                    <code class="text-sm">
                        &lt;?= ui_card('Title', 'Content', 'Footer') ?&gt;
                    </code>
                </div>
            </div>
        </div>
        
    </div>
</div>
<?= $this->endSection() ?>