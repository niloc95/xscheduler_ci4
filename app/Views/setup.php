<?= $this->extend('components/layout') ?>

<?= $this->section('content') ?>
<div class="content-wrapper">
    <div class="content-main">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">xScheduler Setup</h4>
            </div>
            <div class="card-body">
                <p class="text-gray-600 mb-6">Welcome to xScheduler! Let's get you set up.</p>
                
                <?= ui_alert(
                    'This setup wizard will help you configure your scheduling application.',
                    'info',
                    'Getting Started'
                ) ?>
                
                <hr class="border-gray-200 mb-6">
                
                <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                    <?= ui_button('Back to Home', base_url('/'), 'secondary') ?>
                    <?= ui_button('Continue Setup', null, 'primary', ['onclick' => 'startSetup()']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function startSetup() {
    // Add your setup logic here
    alert('Starting setup wizard...');
}
</script>
<?= $this->endSection() ?>