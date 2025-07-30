<?= $this->extend('components/layout') ?>

<?= $this->section('content') ?>
<div class="content-wrapper">
    <div class="content-main space-y-8">
        
        <!-- Buttons Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Buttons</h3>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <h4 class="text-lg font-medium mb-3">Button Types</h4>
                    <div class="flex gap-4 mb-4">
                        <?= ui_button('Primary Button', null, 'primary') ?>
                        <?= ui_button('Secondary Button', null, 'secondary') ?>
                    </div>
                    <div class="bg-gray-100 p-4 rounded-md">
                        <code class="text-sm">
                            &lt;?= ui_button('Primary Button', null, 'primary') ?&gt;<br>
                            &lt;?= ui_button('Secondary Button', null, 'secondary') ?&gt;
                        </code>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Alerts</h3>
            </div>
            <div class="card-body space-y-4">
                <?= ui_alert('This is an info alert', 'info', 'Information') ?>
                <?= ui_alert('This is a success alert', 'success', 'Success') ?>
                <?= ui_alert('This is a warning alert', 'warning', 'Warning') ?>
                <?= ui_alert('This is an error alert', 'error', 'Error') ?>
                
                <div class="bg-gray-100 p-4 rounded-md">
                    <code class="text-sm">
                        &lt;?= ui_alert('Message', 'info', 'Title') ?&gt;
                    </code>
                </div>
            </div>
        </div>
        
        <!-- Cards Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Cards</h3>
            </div>
            <div class="card-body">
                <?= ui_card(
                    'Sample Card Title',
                    '<p class="text-gray-600">This is the card content area. You can put any content here.</p>'
                ) ?>
                
                <div class="bg-gray-100 p-4 rounded-md mt-4">
                    <code class="text-sm">
                        &lt;?= ui_card('Title', 'Content', 'Footer') ?&gt;
                    </code>
                </div>
            </div>
        </div>
        
    </div>
</div>
<?= $this->endSection() ?>