<?php
/**
 * Create Appointment View
 *
 * This form allows users to book/create new appointments.
 * Available to: customers (booking for themselves), staff, providers, and admins.
 * 
 * Features:
 * - Dynamic customer fields based on BookingSettingsService configuration
 * - Custom fields support (up to 6 configurable fields)
 * - Settings-driven show/hide and required/optional behavior
 * - Localization support for time format display
 */
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'appointments']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?>
    <?= $user_role === 'customer' 
        ? 'Select a service, date, and time for your appointment' 
        : 'Create a new appointment for a customer' ?>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
<div class="max-w-4xl mx-auto">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="<?= base_url('/appointments') ?>" 
           class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition-colors">
            <span class="material-symbols-outlined text-base mr-1">arrow_back</span>
            Back to Appointments
        </a>
    </div>

    <!-- Form Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Appointment Details</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Fill in the information below to book an appointment</p>
        </div>

        <form action="<?= base_url('/appointments/store') ?>" method="POST" class="p-6">
            <?= csrf_field() ?>

            <div class="space-y-6">
                <!-- Customer Information (visible to staff/provider/admin only) -->
                <?php if (in_array($user_role, ['admin', 'provider', 'staff'])): ?>
                <div class="space-y-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white">Customer Information</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- First Name - Dynamic -->
                        <?php if ($fieldConfig['first_name']['display']): ?>
                        <div>
                            <label for="customer_first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                First Name <?= $fieldConfig['first_name']['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                            </label>
                            <input type="text" 
                                   id="customer_first_name" 
                                   name="customer_first_name" 
                                   <?= $fieldConfig['first_name']['required'] ? 'required' : '' ?>
                                   class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                        <?php endif; ?>

                        <!-- Last Name - Dynamic -->
                        <?php if ($fieldConfig['last_name']['display']): ?>
                        <div>
                            <label for="customer_last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Last Name <?= $fieldConfig['last_name']['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                            </label>
                            <input type="text" 
                                   id="customer_last_name" 
                                   name="customer_last_name" 
                                   <?= $fieldConfig['last_name']['required'] ? 'required' : '' ?>
                                   class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                        <?php endif; ?>

                        <!-- Email - Dynamic -->
                        <?php if ($fieldConfig['email']['display']): ?>
                        <div>
                            <label for="customer_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Email <?= $fieldConfig['email']['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                            </label>
                            <input type="email" 
                                   id="customer_email" 
                                   name="customer_email" 
                                   <?= $fieldConfig['email']['required'] ? 'required' : '' ?>
                                   class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                        <?php endif; ?>

                        <!-- Phone - Dynamic -->
                        <?php if ($fieldConfig['phone']['display']): ?>
                        <div>
                            <label for="customer_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Phone <?= $fieldConfig['phone']['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                            </label>
                            <input type="tel" 
                                   id="customer_phone" 
                                   name="customer_phone" 
                                   <?= $fieldConfig['phone']['required'] ? 'required' : '' ?>
                                   class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                        <?php endif; ?>

                        <!-- Address - Dynamic -->
                        <?php if ($fieldConfig['address']['display']): ?>
                        <div class="md:col-span-2">
                            <label for="customer_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Address <?= $fieldConfig['address']['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                            </label>
                            <input type="text" 
                                   id="customer_address" 
                                   name="customer_address" 
                                   <?= $fieldConfig['address']['required'] ? 'required' : '' ?>
                                   class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                        <?php endif; ?>

                        <!-- Custom Fields - Dynamic -->
                        <?php foreach ($customFields as $fieldKey => $fieldMeta): ?>
                        <div class="<?= $fieldMeta['type'] === 'textarea' ? 'md:col-span-2' : '' ?>">
                            <label for="<?= esc($fieldKey) ?>" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                <?= esc($fieldMeta['title']) ?> <?= $fieldMeta['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                            </label>
                            
                            <?php if ($fieldMeta['type'] === 'textarea'): ?>
                                <textarea id="<?= esc($fieldKey) ?>" 
                                          name="<?= esc($fieldKey) ?>" 
                                          rows="3"
                                          <?= $fieldMeta['required'] ? 'required' : '' ?>
                                          class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            
                            <?php elseif ($fieldMeta['type'] === 'checkbox'): ?>
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="<?= esc($fieldKey) ?>" 
                                           name="<?= esc($fieldKey) ?>" 
                                           value="1"
                                           <?= $fieldMeta['required'] ? 'required' : '' ?>
                                           class="w-4 h-4 text-blue-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500" />
                                    <label for="<?= esc($fieldKey) ?>" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                        Check if applicable
                                    </label>
                                </div>
                            
                            <?php else: ?>
                                <input type="<?= $fieldMeta['type'] === 'select' ? 'text' : 'text' ?>" 
                                       id="<?= esc($fieldKey) ?>" 
                                       name="<?= esc($fieldKey) ?>" 
                                       <?= $fieldMeta['required'] ? 'required' : '' ?>
                                       class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <hr class="border-gray-200 dark:border-gray-700" />
                <?php endif; ?>

                <!-- Service Selection -->
                <div>
                    <label for="service_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Service <span class="text-red-500">*</span>
                    </label>
                    <select id="service_id" 
                            name="service_id" 
                            required 
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select a service...</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= $service['id'] ?>" data-duration="<?= $service['duration'] ?>" data-price="<?= $service['price'] ?>">
                                <?= esc($service['name']) ?> - <?= $service['duration'] ?> min - $<?= number_format($service['price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Provider Selection -->
                <div>
                    <label for="provider_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Provider <span class="text-red-500">*</span>
                    </label>
                    <select id="provider_id" 
                            name="provider_id" 
                            required 
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select a provider...</option>
                        <?php foreach ($providers as $provider): ?>
                            <option value="<?= $provider['id'] ?>">
                                <?= esc($provider['name']) ?> - <?= esc($provider['speciality']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date & Time Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="appointment_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               id="appointment_date" 
                               name="appointment_date" 
                               required 
                               min="<?= date('Y-m-d') ?>"
                               class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label for="appointment_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Time <span class="text-red-500">*</span>
                        </label>
                        <input type="time" 
                               id="appointment_time" 
                               name="appointment_time" 
                               required 
                               class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                </div>

                <!-- Notes - Dynamic -->
                <?php if ($fieldConfig['notes']['display']): ?>
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Notes <?= $fieldConfig['notes']['required'] ? '<span class="text-red-500">*</span>' : '(Optional)' ?>
                    </label>
                    <textarea id="notes" 
                              name="notes" 
                              rows="4" 
                              <?= $fieldConfig['notes']['required'] ? 'required' : '' ?>
                              placeholder="Any special requests or information..."
                              class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>
                <?php endif; ?>

                <!-- Appointment Summary (dynamically updated) -->
                <div id="appointment-summary" class="hidden rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4">
                    <h4 class="text-sm font-medium text-blue-900 dark:text-blue-200 mb-2">Appointment Summary</h4>
                    <dl class="space-y-1 text-sm text-blue-700 dark:text-blue-300">
                        <div class="flex justify-between">
                            <dt>Service:</dt>
                            <dd id="summary-service" class="font-medium">-</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Provider:</dt>
                            <dd id="summary-provider" class="font-medium">-</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Date & Time:</dt>
                            <dd id="summary-datetime" class="font-medium">-</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Duration:</dt>
                            <dd id="summary-duration" class="font-medium">-</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Price:</dt>
                            <dd id="summary-price" class="font-medium">-</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex items-center justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                <a href="<?= base_url('/appointments') ?>" 
                   class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-500 focus:ring-opacity-50 transition-colors">
                    <?= $user_role === 'customer' ? 'Book Appointment' : 'Create Appointment' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Update appointment summary dynamically
document.addEventListener('DOMContentLoaded', function() {
    const serviceSelect = document.getElementById('service_id');
    const providerSelect = document.getElementById('provider_id');
    const dateInput = document.getElementById('appointment_date');
    const timeInput = document.getElementById('appointment_time');
    const summaryDiv = document.getElementById('appointment-summary');

    function updateSummary() {
        const service = serviceSelect.options[serviceSelect.selectedIndex];
        const provider = providerSelect.options[providerSelect.selectedIndex];
        const date = dateInput.value;
        const time = timeInput.value;

        // Check if all required fields are filled
        if (serviceSelect.value && providerSelect.value && date && time) {
            summaryDiv.classList.remove('hidden');
            
            document.getElementById('summary-service').textContent = service.text.split(' - ')[0] || '-';
            document.getElementById('summary-provider').textContent = provider.text.split(' - ')[0] || '-';
            document.getElementById('summary-datetime').textContent = date && time 
                ? new Date(date + 'T' + time).toLocaleString('en-US', { 
                    weekday: 'short', 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                  })
                : '-';
            document.getElementById('summary-duration').textContent = service.dataset.duration 
                ? service.dataset.duration + ' minutes' 
                : '-';
            document.getElementById('summary-price').textContent = service.dataset.price 
                ? '$' + parseFloat(service.dataset.price).toFixed(2) 
                : '-';
        } else {
            summaryDiv.classList.add('hidden');
        }
    }

    // Attach event listeners
    serviceSelect.addEventListener('change', updateSummary);
    providerSelect.addEventListener('change', updateSummary);
    dateInput.addEventListener('change', updateSummary);
    timeInput.addEventListener('change', updateSummary);
});
</script>
<?= $this->endSection() ?>
