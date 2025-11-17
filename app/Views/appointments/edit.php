<?php
/**
 * Edit Appointment View
 *
 * This form allows authorized users to edit existing appointments.
 * Available to: staff, providers, and admins.
 * 
 * Features:
 * - Pre-populated form fields with existing appointment data
 * - Dynamic customer fields based on BookingSettingsService configuration
 * - Custom fields support (up to 6 configurable fields)
 * - Settings-driven show/hide and required/optional behavior
 * - Localization support for time format display
 * - Validation and error handling
 */
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'appointments']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?>Edit Appointment<?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?>Update appointment details and customer information<?= $this->endSection() ?>

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

    <!-- Validation Errors -->
    <?php if (session('errors')): ?>
    <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-start">
            <span class="material-symbols-outlined text-red-600 dark:text-red-400 mr-3">error</span>
            <div class="flex-1">
                <h3 class="text-sm font-medium text-red-800 dark:text-red-200 mb-2">Please correct the following errors:</h3>
                <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-300 space-y-1">
                    <?php foreach (session('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif ?>

    <!-- Form Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Appointment Details</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Update the information below</p>
        </div>

        <form action="<?= base_url('/appointments/update/' . esc($appointment['hash'])) ?>" method="POST" class="p-6">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" name="client_timezone" id="client_timezone" value="">
            <input type="hidden" name="client_offset" id="client_offset" value="">

            <div class="space-y-6">
                <!-- Customer Information -->
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
                                   value="<?= esc(old('customer_first_name', $appointment['customer_first_name'] ?? '')) ?>"
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
                                   value="<?= esc(old('customer_last_name', $appointment['customer_last_name'] ?? '')) ?>"
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
                                   value="<?= esc(old('customer_email', $appointment['customer_email'] ?? '')) ?>"
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
                                   value="<?= esc(old('customer_phone', $appointment['customer_phone'] ?? '')) ?>"
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
                                   value="<?= esc(old('customer_address', $appointment['customer_address'] ?? '')) ?>"
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
                                          class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= esc(old($fieldKey, $appointment[$fieldKey] ?? '')) ?></textarea>
                            
                            <?php elseif ($fieldMeta['type'] === 'checkbox'): ?>
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="<?= esc($fieldKey) ?>" 
                                           name="<?= esc($fieldKey) ?>" 
                                           value="1"
                                           <?= old($fieldKey, $appointment[$fieldKey] ?? '') ? 'checked' : '' ?>
                                           <?= $fieldMeta['required'] ? 'required' : '' ?>
                                           class="w-4 h-4 text-blue-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500" />
                                    <label for="<?= esc($fieldKey) ?>" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                        Check if applicable
                                    </label>
                                </div>
                            
                            <?php else: ?>
                                <input type="text" 
                                       id="<?= esc($fieldKey) ?>" 
                                       name="<?= esc($fieldKey) ?>" 
                                       value="<?= esc(old($fieldKey, $appointment[$fieldKey] ?? '')) ?>"
                                       <?= $fieldMeta['required'] ? 'required' : '' ?>
                                       class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <hr class="border-gray-200 dark:border-gray-700" />

                <!-- Appointment Details -->
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
                            <option value="<?= $provider['id'] ?>" <?= old('provider_id', $appointment['provider_id'] ?? '') == $provider['id'] ? 'selected' : '' ?>>
                                <?= esc($provider['name']) ?> - <?= esc($provider['speciality']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="service_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Service <span class="text-red-500">*</span>
                    </label>
                    <select id="service_id" 
                            name="service_id" 
                            required 
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select a provider first...</option>
                    </select>
                </div>

                <!-- Date & Time Selection (Time Slots UI like create.php) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="appointment_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               id="appointment_date" 
                               name="appointment_date" 
                               value="<?= esc(old('appointment_date', $appointment['date'] ?? '')) ?>"
                               required 
                               class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label for="appointment_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Time <span class="text-red-500">*</span>
                        </label>
                        <!-- Hidden input to store selected time -->
                        <input type="hidden" 
                               id="appointment_time" 
                               name="appointment_time" 
                               value="<?= esc(old('appointment_time', $appointment['time'] ?? '')) ?>"
                               required />

                        <!-- Time Slots Container -->
                        <div id="time-slots-container" class="mt-2">
                            <!-- Loading state -->
                            <div id="time-slots-loading" class="hidden">
                                <div class="flex items-center justify-center py-8 text-gray-500 dark:text-gray-400">
                                    <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading available time slots...
                                </div>
                            </div>
                            <!-- Empty state -->
                            <div id="time-slots-empty" class="hidden">
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <span class="material-symbols-outlined text-4xl mb-2 opacity-50">event_busy</span>
                                    <p class="text-sm">No available time slots for this date</p>
                                    <p class="text-xs mt-1">Try selecting a different date or provider</p>
                                </div>
                            </div>
                            <!-- Error state -->
                            <div id="time-slots-error" class="hidden">
                                <div class="text-center py-8 text-red-600 dark:text-red-400">
                                    <span class="material-symbols-outlined text-4xl mb-2 opacity-50">error</span>
                                    <p class="text-sm" id="time-slots-error-message">Failed to load time slots</p>
                                </div>
                            </div>
                            <!-- Initial prompt -->
                            <div id="time-slots-prompt" class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                                Select a service, provider, and date to see available time slots
                            </div>
                            <!-- Time slots grid -->
                            <div id="time-slots-grid" class="hidden"></div>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select id="status" 
                            name="status" 
                            required 
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="pending" <?= old('status', $appointment['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= old('status', $appointment['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="completed" <?= old('status', $appointment['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= old('status', $appointment['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="no-show" <?= old('status', $appointment['status'] ?? '') === 'no-show' ? 'selected' : '' ?>>No Show</option>
                    </select>
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
                              class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= esc(old('notes', $appointment['notes'] ?? '')) ?></textarea>
                </div>
                <?php endif; ?>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex items-center justify-between gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                <a href="<?= base_url('/appointments') ?>" 
                   class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    Cancel
                </a>
                <div class="flex gap-3">
                    <button type="submit" 
                            name="action" 
                            value="save"
                            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-500 focus:ring-opacity-50 transition-colors">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<script>
// Match create.php time slot behavior via shared module
document.addEventListener('DOMContentLoaded', function() {
    const currentAptId = "<?= esc($appointment['id'] ?? $appointment['appointment_id'] ?? '') ?>";
    const currentServiceId = "<?= esc(old('service_id', $appointment['service_id'] ?? '')) ?>";
    const timeInput = document.getElementById('appointment_time');
    const initialTime = timeInput ? timeInput.value : '';

    if (window.initTimeSlotsUI) {
        window.initTimeSlotsUI({
            providerSelectId: 'provider_id',
            serviceSelectId: 'service_id',
            dateInputId: 'appointment_date',
            timeInputId: 'appointment_time',
            excludeAppointmentId: currentAptId,
            preselectServiceId: currentServiceId,
            initialTime
        });
    }
});
</script>
