<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'settings']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Booking Form Demo<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content space-y-6" data-page-title="Booking Form Demo" data-page-subtitle="Test the configurable booking fields">
    
    <div class="card card-spacious">
        <div class="card-header">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Booking Form Preview</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">This form reflects the current booking field configuration from Settings → Booking tab.</p>
        </div>
        
        <div class="card-body">
            <form class="space-y-4">
                <?php
                // Mock settings for demonstration - in real implementation, these would come from SettingModel
                $mockSettings = [
                    'booking.first_names_display' => '1',
                    'booking.first_names_required' => '1',
                    'booking.surname_display' => '1',
                    'booking.surname_required' => '1',
                    'booking.custom_field_1_enabled' => '1',
                    'booking.custom_field_1_title' => 'Company Name',
                    'booking.custom_field_1_type' => 'text',
                    'booking.custom_field_1_required' => '0',
                    'booking.custom_field_2_enabled' => '1',
                    'booking.custom_field_2_title' => 'Special Requirements',
                    'booking.custom_field_2_type' => 'textarea',
                    'booking.custom_field_2_required' => '0',
                    'booking.custom_field_3_enabled' => '0',
                    'booking.custom_field_4_enabled' => '0',
                    'booking.custom_field_5_enabled' => '0',
                    'booking.custom_field_6_enabled' => '0',
                    'booking.fields' => ['email', 'phone', 'notes']
                ];
                
                // Render the booking fields using our helper
                echo render_booking_fields($mockSettings);
                ?>
                
                <div class="flex justify-end pt-4">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined text-base">event_available</span>
                        Book Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-spacious">
        <div class="card-header">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Field Configuration Summary</h2>
        </div>
        
        <div class="card-body">
            <?php
            $config = get_booking_field_config($mockSettings);
            ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Name Fields</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-2 rounded bg-gray-50 dark:bg-gray-800">
                            <span class="text-sm">First Names</span>
                            <div class="flex gap-2">
                                <span class="text-xs px-2 py-1 rounded <?= $config['name_fields']['first_names']['display'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                    <?= $config['name_fields']['first_names']['display'] ? 'Visible' : 'Hidden' ?>
                                </span>
                                <span class="text-xs px-2 py-1 rounded <?= $config['name_fields']['first_names']['required'] ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600' ?>">
                                    <?= $config['name_fields']['first_names']['required'] ? 'Required' : 'Optional' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between p-2 rounded bg-gray-50 dark:bg-gray-800">
                            <span class="text-sm">Surname</span>
                            <div class="flex gap-2">
                                <span class="text-xs px-2 py-1 rounded <?= $config['name_fields']['surname']['display'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                    <?= $config['name_fields']['surname']['display'] ? 'Visible' : 'Hidden' ?>
                                </span>
                                <span class="text-xs px-2 py-1 rounded <?= $config['name_fields']['surname']['required'] ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600' ?>">
                                    <?= $config['name_fields']['surname']['required'] ? 'Required' : 'Optional' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Custom Fields</h3>
                    <div class="space-y-2">
                        <?php if (empty($config['custom_fields'])): ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No custom fields enabled</p>
                        <?php else: ?>
                            <?php foreach ($config['custom_fields'] as $index => $field): ?>
                                <div class="flex items-center justify-between p-2 rounded bg-gray-50 dark:bg-gray-800">
                                    <div>
                                        <span class="text-sm font-medium"><?= esc($field['title']) ?></span>
                                        <span class="text-xs text-gray-500 ml-2">(<?= ucfirst($field['type']) ?>)</span>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded <?= $field['required'] ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600' ?>">
                                        <?= $field['required'] ? 'Required' : 'Optional' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="mt-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Standard Fields</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($config['standard_fields'] as $field): ?>
                        <span class="text-xs px-3 py-1 rounded-full bg-blue-100 text-blue-800">
                            <?= ucfirst($field) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card card-spacious">
        <div class="card-header">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Instructions</h2>
        </div>
        
        <div class="card-body">
            <div class="prose dark:prose-invert max-w-none">
                <p>To test the booking field configuration:</p>
                <ol>
                    <li>Go to <strong>Settings → Booking</strong> tab</li>
                    <li>Toggle the name fields (First Names, Surname) display and required options</li>
                    <li>Enable custom fields and set their titles and types</li>
                    <li>Save the settings</li>
                    <li>Return to this page to see the updated form</li>
                </ol>
                
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-4">
                    <strong>Note:</strong> In the actual implementation, this form would dynamically reflect the settings from the database. The example above shows mock data to demonstrate the functionality.
                </p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>