<?php
/**
 * Unified Appointment Form (Create/Edit)
 *
 * This form handles both creating new appointments and editing existing ones.
 * Mode is determined by presence of $appointment data.
 * 
 * Features:
 * - Dynamic customer fields based on BookingSettingsService configuration
 * - Custom fields support (up to 6 configurable fields)
 * - Settings-driven show/hide and required/optional behavior
 * - Customer search for staff/admin (create mode only)
 * - Time slots UI with availability checking
 * - Localization support
 */

$isEditMode = !empty($appointment['id']) || !empty($appointment['appointment_id']);
$formAction = $isEditMode 
    ? base_url('/appointments/update/' . esc($appointment['hash'])) 
    : base_url('/appointments/store');
$pageTitle = $isEditMode ? 'Edit Appointment' : ($title ?? 'Book Appointment');
$pageSubtitle = $isEditMode 
    ? 'Update appointment details and customer information'
    : ($user_role === 'customer' 
        ? 'Select a service, date, and time for your appointment' 
        : 'Create a new appointment for a customer');
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'appointments']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?><?= esc($pageTitle) ?><?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?><?= esc($pageSubtitle) ?><?= $this->endSection() ?>

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
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                <?= $isEditMode ? 'Update the information below' : 'Fill in the information below to book an appointment' ?>
            </p>
        </div>

        <form action="<?= $formAction ?>" method="POST" class="p-6">
            <?= csrf_field() ?>
            <?php if ($isEditMode): ?>
            <input type="hidden" name="_method" value="PUT">
            <?php endif; ?>
            <input type="hidden" name="client_timezone" id="client_timezone" value="">
            <input type="hidden" name="client_offset" id="client_offset" value="">

            <div class="space-y-6">
                <?php if ($isEditMode): ?>
                <!-- EDIT MODE: Show Customer Information (Read-Only Display) -->
                <div class="space-y-4">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white">Customer Information</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- First Name -->
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

                        <!-- Last Name -->
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

                        <!-- Email -->
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

                        <!-- Phone -->
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

                        <!-- Address -->
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

                        <!-- Custom Fields -->
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

                <?php else: ?>
                <!-- CREATE MODE: Customer Search/Create (for staff/admin only) -->
                <?php if (in_array($user_role, ['admin', 'provider', 'staff'])): ?>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white">Customer</h4>
                        <div class="inline-flex rounded-lg bg-gray-100 dark:bg-gray-700 p-1">
                            <button type="button" 
                                    class="customer-mode-btn px-3 py-1 text-sm font-medium bg-white dark:bg-gray-600 text-gray-900 dark:text-white rounded-md shadow-sm transition-colors" 
                                    data-customer-mode="search">
                                Search Existing
                            </button>
                            <button type="button" 
                                    class="customer-mode-btn px-3 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors" 
                                    data-customer-mode="create">
                                Create New
                            </button>
                        </div>
                    </div>

                    <!-- Customer Search Section -->
                    <div id="customer-search-section" class="space-y-3">
                        <div class="relative">
                            <input type="text" 
                                   id="customer_search" 
                                   placeholder="Search by name, email, or phone..."
                                   class="block w-full pl-10 pr-10 py-2 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400">search</span>
                            <button type="button" id="clear_search_btn" class="hidden absolute right-2 top-2 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <span class="material-symbols-outlined text-sm">close</span>
                            </button>
                            <div id="customer_search_results" class="hidden absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl max-h-64 overflow-y-auto"></div>
                        </div>
                        <div id="customer_search_spinner" class="hidden text-center py-2">
                            <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                        </div>
                        <div id="selected_customer_display" class="hidden items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div id="selected_customer_avatar" class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 text-white font-semibold text-sm">U</div>
                                <div>
                                    <p id="selected_customer_name" class="text-sm font-medium text-gray-900 dark:text-white"></p>
                                    <p id="selected_customer_email" class="text-xs text-gray-600 dark:text-gray-400"></p>
                                    <p id="selected_customer_phone" class="text-xs text-gray-600 dark:text-gray-400"></p>
                                </div>
                            </div>
                            <button type="button" id="clear_customer_btn" class="p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                <span class="material-symbols-outlined text-lg">close</span>
                            </button>
                        </div>
                    </div>

                    <!-- Customer Create Section -->
                    <div id="customer-create-section" class="hidden space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php if ($fieldConfig['first_name']['display']): ?>
                            <div>
                                <label for="customer_first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    First Name <?= $fieldConfig['first_name']['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                                </label>
                                <input type="text" id="customer_first_name" name="customer_first_name" 
                                       data-original-required="<?= $fieldConfig['first_name']['required'] ? '1' : '0' ?>"
                                       class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <?php endif; ?>

                            <?php if ($fieldConfig['last_name']['display']): ?>
                            <div>
                                <label for="customer_last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Last Name <?= $fieldConfig['last_name']['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                                </label>
                                <input type="text" id="customer_last_name" name="customer_last_name"
                                       data-original-required="<?= $fieldConfig['last_name']['required'] ? '1' : '0' ?>"
                                       class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <?php endif; ?>

                            <?php if ($fieldConfig['email']['display']): ?>
                            <div>
                                <label for="customer_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Email <?= $fieldConfig['email']['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                                </label>
                                <input type="email" id="customer_email" name="customer_email"
                                       data-original-required="<?= $fieldConfig['email']['required'] ? '1' : '0' ?>"
                                       class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <?php endif; ?>

                            <?php if ($fieldConfig['phone']['display']): ?>
                            <div>
                                <label for="customer_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Phone <?= $fieldConfig['phone']['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                                </label>
                                <input type="tel" id="customer_phone" name="customer_phone"
                                       data-original-required="<?= $fieldConfig['phone']['required'] ? '1' : '0' ?>"
                                       class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <?php endif; ?>

                            <?php if ($fieldConfig['address']['display']): ?>
                            <div class="md:col-span-2">
                                <label for="customer_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Address <?= $fieldConfig['address']['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                                </label>
                                <input type="text" id="customer_address" name="customer_address"
                                       data-original-required="<?= $fieldConfig['address']['required'] ? '1' : '0' ?>"
                                       class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <?php endif; ?>

                            <?php foreach ($customFields as $fieldKey => $fieldMeta): ?>
                            <div class="<?= $fieldMeta['type'] === 'textarea' ? 'md:col-span-2' : '' ?>">
                                <label for="<?= esc($fieldKey) ?>" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    <?= esc($fieldMeta['title']) ?> <?= $fieldMeta['required'] ? '<span class="text-red-500">*</span>' : '' ?>
                                </label>
                                
                                <?php if ($fieldMeta['type'] === 'textarea'): ?>
                                    <textarea id="<?= esc($fieldKey) ?>" 
                                              name="<?= esc($fieldKey) ?>" 
                                              rows="3"
                                              data-original-required="<?= $fieldMeta['required'] ? '1' : '0' ?>"
                                              class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                
                                <?php elseif ($fieldMeta['type'] === 'checkbox'): ?>
                                    <div class="flex items-center">
                                        <input type="checkbox" 
                                               id="<?= esc($fieldKey) ?>" 
                                               name="<?= esc($fieldKey) ?>" 
                                               value="1"
                                               data-original-required="<?= $fieldMeta['required'] ? '1' : '0' ?>"
                                               class="w-4 h-4 text-blue-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500" />
                                        <label for="<?= esc($fieldKey) ?>" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                            Check if applicable
                                        </label>
                                    </div>
                                
                                <?php else: ?>
                                    <input type="text" 
                                           id="<?= esc($fieldKey) ?>" 
                                           name="<?= esc($fieldKey) ?>"
                                           data-original-required="<?= $fieldMeta['required'] ? '1' : '0' ?>"
                                           class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Hidden field for selected customer ID -->
                    <input type="hidden" id="selected_customer_id" name="customer_id" value="" />
                </div>
                <hr class="border-gray-200 dark:border-gray-700" />
                <?php endif; ?>
                <?php endif; ?>

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
                            <option value="<?= $provider['id'] ?>" 
                                    <?= old('provider_id', $appointment['provider_id'] ?? '') == $provider['id'] ? 'selected' : '' ?>>
                                <?= esc($provider['name']) ?> - <?= esc($provider['speciality']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Service Selection -->
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

                <!-- Date & Time Selection -->
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
                        <input type="hidden" 
                               id="appointment_time" 
                               name="appointment_time" 
                               value="<?= esc(old('appointment_time', $appointment['time'] ?? '')) ?>"
                               required />

                        <!-- Time Slots Container -->
                        <div id="time-slots-container" class="mt-2">
                            <div id="time-slots-loading" class="hidden">
                                <div class="flex items-center justify-center py-8 text-gray-500 dark:text-gray-400">
                                    <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading available time slots...
                                </div>
                            </div>
                            <div id="time-slots-empty" class="hidden">
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <span class="material-symbols-outlined text-4xl mb-2 opacity-50">event_busy</span>
                                    <p class="text-sm">No available time slots for this date</p>
                                    <p class="text-xs mt-1">Try selecting a different date or provider</p>
                                </div>
                            </div>
                            <div id="time-slots-error" class="hidden">
                                <div class="text-center py-8 text-red-600 dark:text-red-400">
                                    <span class="material-symbols-outlined text-4xl mb-2 opacity-50">error</span>
                                    <p class="text-sm" id="time-slots-error-message">Failed to load time slots</p>
                                </div>
                            </div>
                            <div id="time-slots-prompt" class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
                                Select a service, provider, and date to see available time slots
                            </div>
                            <div id="time-slots-grid" class="hidden"></div>
                        </div>
                    </div>
                </div>

                <?php if ($isEditMode): ?>
                <!-- Status (Edit mode only) -->
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
                <?php endif; ?>

                <!-- Notes -->
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

                <?php if (!$isEditMode): ?>
                <!-- Appointment Summary (Create mode only) -->
                <div id="appointment-summary" class="hidden rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4">
                    <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">event_available</span>
                        Appointment Summary
                    </h4>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <dt class="text-gray-600 dark:text-gray-400">Service:</dt>
                        <dd id="summary-service" class="font-medium text-gray-900 dark:text-gray-100">-</dd>
                        
                        <dt class="text-gray-600 dark:text-gray-400">Provider:</dt>
                        <dd id="summary-provider" class="font-medium text-gray-900 dark:text-gray-100">-</dd>
                        
                        <dt class="text-gray-600 dark:text-gray-400">Date & Time:</dt>
                        <dd id="summary-datetime" class="font-medium text-gray-900 dark:text-gray-100">-</dd>
                        
                        <dt class="text-gray-600 dark:text-gray-400">Duration:</dt>
                        <dd id="summary-duration" class="font-medium text-gray-900 dark:text-gray-100">-</dd>
                        
                        <dt class="text-gray-600 dark:text-gray-400">Price:</dt>
                        <dd id="summary-price" class="font-medium text-gray-900 dark:text-gray-100">-</dd>
                    </dl>
                </div>
                <?php endif; ?>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex items-center justify-<?= $isEditMode ? 'between' : 'end' ?> gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                <a href="<?= base_url('appointments') ?>" 
                   class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-500 focus:ring-opacity-50 transition-colors">
                    <?= $isEditMode ? 'Save Changes' : 'Book Appointment' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Unified form initialization
document.addEventListener('DOMContentLoaded', function() {
    const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
    
    // Populate timezone fields
    const clientTimezoneField = document.getElementById('client_timezone');
    const clientOffsetField = document.getElementById('client_offset');
    
    if (clientTimezoneField) {
        try {
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
            clientTimezoneField.value = tz;
        } catch (e) {
            clientTimezoneField.value = 'UTC';
            console.warn('[Appointment Form] Timezone detection failed:', e);
        }
    }
    
    if (clientOffsetField) {
        clientOffsetField.value = new Date().getTimezoneOffset();
    }

    // Common elements
    const serviceSelect = document.getElementById('service_id');
    const providerSelect = document.getElementById('provider_id');
    const dateInput = document.getElementById('appointment_date');
    const timeInput = document.getElementById('appointment_time');
    const summaryDiv = document.getElementById('appointment-summary');

    // Initialize time slots UI
    const currentAptId = isEditMode ? "<?= esc($appointment['id'] ?? $appointment['appointment_id'] ?? '') ?>" : null;
    const currentServiceId = "<?= esc(old('service_id', $appointment['service_id'] ?? '')) ?>";
    const currentTime = "<?= esc(old('appointment_time', $appointment['time'] ?? '')) ?>";
    
    if (window.initTimeSlotsUI) {
        window.initTimeSlotsUI({
            providerSelectId: 'provider_id',
            serviceSelectId: 'service_id',
            dateInputId: 'appointment_date',
            timeInputId: 'appointment_time',
            excludeAppointmentId: currentAptId,
            preselectServiceId: currentServiceId,
            initialTime: currentTime,
            onTimeSelected: isEditMode ? null : () => updateSummary()
        });
    } else {
        console.error('[Appointment Form] initTimeSlotsUI not available');
    }

    // CREATE MODE ONLY: Customer search and appointment summary
    if (!isEditMode) {
        // Customer search functionality (same as create.php)
        const customerSearchInput = document.getElementById('customer_search');
        const customerSearchResults = document.getElementById('customer_search_results');
        const customerSearchSpinner = document.getElementById('customer_search_spinner');
        const selectedCustomerDisplay = document.getElementById('selected_customer_display');
        const clearCustomerBtn = document.getElementById('clear_customer_btn');
        const customerSearchSection = document.getElementById('customer-search-section');
        const customerCreateSection = document.getElementById('customer-create-section');
        const selectedCustomerIdInput = document.getElementById('selected_customer_id');

        let searchTimeout = null;
        let selectedCustomer = null;

        // Utility function for escaping HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Customer mode toggle
        document.querySelectorAll('.customer-mode-btn')?.forEach(btn => {
            btn.addEventListener('click', function() {
                const mode = this.dataset.customerMode;
                
                document.querySelectorAll('.customer-mode-btn').forEach(b => {
                    if (b.dataset.customerMode === mode) {
                        b.classList.add('bg-white', 'dark:bg-gray-600', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                        b.classList.remove('text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
                    } else {
                        b.classList.remove('bg-white', 'dark:bg-gray-600', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                        b.classList.add('text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
                    }
                });

                if (mode === 'search') {
                    customerSearchSection?.classList.remove('hidden');
                    customerCreateSection?.classList.add('hidden');
                    disableCustomerFieldValidation();
                    if (selectedCustomer) clearCustomerFormFields();
                } else {
                    customerSearchSection?.classList.add('hidden');
                    customerCreateSection?.classList.remove('hidden');
                    enableCustomerFieldValidation();
                    clearCustomerSelection();
                }
            });
        });

        // Customer search implementation (reuse from create.php)
        if (customerSearchInput) {
            // Close search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!customerSearchInput.contains(e.target) && 
                    !customerSearchResults.contains(e.target) &&
                    !e.target.closest('#customer-search-section')) {
                    customerSearchResults.classList.add('hidden');
                }
            });
            
            // Clear search button functionality
            const clearSearchBtn = document.getElementById('clear_search_btn');
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    customerSearchInput.value = '';
                    customerSearchResults.classList.add('hidden');
                    clearSearchBtn.classList.add('hidden');
                    customerSearchInput.focus();
                });
            }
            
            customerSearchInput.addEventListener('input', function() {
                const query = this.value.trim();

                // Toggle clear button visibility
                const clearBtn = document.getElementById('clear_search_btn');
                if (clearBtn) {
                    if (query.length > 0) {
                        clearBtn.classList.remove('hidden');
                    } else {
                        clearBtn.classList.add('hidden');
                    }
                }

                if (searchTimeout) clearTimeout(searchTimeout);

                if (query.length < 2) {
                    customerSearchResults.classList.add('hidden');
                    customerSearchSpinner.classList.add('hidden');
                    return;
                }

                // Show loading state
                customerSearchSpinner.classList.remove('hidden');
                customerSearchResults.innerHTML = `<div class="p-4 text-center text-gray-500 dark:text-gray-400">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-blue-600 border-t-transparent mb-2"></div>
                    <p class="text-sm">Searching customers...</p>
                </div>`;
                customerSearchResults.classList.remove('hidden');

                searchTimeout = setTimeout(async () => {
                    try {
                        const res = await fetch(`<?= base_url('dashboard/search') ?>?q=${encodeURIComponent(query)}`);
                        if (!res.ok) throw new Error('Search failed');
                        
                        // Handle potential debug toolbar in response
                        const text = await res.text();
                        let result;
                        try {
                            result = JSON.parse(text);
                        } catch (e) {
                            // If JSON parsing fails, try to extract JSON from HTML
                            const jsonMatch = text.match(/\{[\s\S]*"success"[\s\S]*\}/);
                            if (jsonMatch) {
                                result = JSON.parse(jsonMatch[0]);
                            } else {
                                throw new Error('Invalid response format');
                            }
                        }
                        
                        const customers = result.customers || result.data || [];
                        displayCustomerResults(customers, query);
                    } catch (error) {
                        console.error('Customer search error:', error);
                        customerSearchResults.innerHTML = `<div class="p-4 text-center text-red-600 dark:text-red-400">
                            <span class="material-symbols-outlined text-2xl mb-2 block">error</span>
                            <p class="text-sm font-medium mb-1">Search failed</p>
                            <p class="text-xs opacity-75">Please check your connection and try again</p>
                        </div>`;
                        customerSearchResults.classList.remove('hidden');
                    } finally {
                        customerSearchSpinner.classList.add('hidden');
                    }
                }, 300);
            });
        }

        function displayCustomerResults(customers, query) {
            if (customers.length === 0) {
                customerSearchResults.innerHTML = `<div class="p-4 text-center text-gray-500 dark:text-gray-400">
                    <span class="material-symbols-outlined text-3xl mb-2 block opacity-50">person_search</span>
                    <p class="text-sm font-medium mb-1">No customers found</p>
                    <p class="text-xs opacity-75">for "${escapeHtml(query)}"</p>
                </div>`;
                customerSearchResults.classList.remove('hidden');
                return;
            }

            const resultsHTML = customers.slice(0, 5).map(customer => {
                const fullName = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || 'Unknown Customer';
                const initial = fullName.substring(0, 1).toUpperCase();
                const email = customer.email || '';
                const phone = customer.phone || customer.phone_number || '';
                const contactInfo = [email, phone].filter(Boolean).join(' • ');
                
                return `<button type="button" class="customer-result-item w-full text-left px-4 py-3 hover:bg-blue-50 dark:hover:bg-blue-900/20 flex items-center gap-3 transition-colors border-b border-gray-100 dark:border-gray-700 last:border-0" data-customer='${JSON.stringify(customer).replace(/'/g, "&#39;")}'>
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 text-white font-semibold text-sm flex-shrink-0">${escapeHtml(initial)}</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">${escapeHtml(fullName)}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 truncate">${escapeHtml(contactInfo || 'No contact info')}</p>
                    </div>
                    <span class="material-symbols-outlined text-gray-400 text-sm">chevron_right</span>
                </button>`;
            }).join('');

            customerSearchResults.innerHTML = `<div class="divide-y divide-gray-100 dark:divide-gray-700">${resultsHTML}</div>`;
            customerSearchResults.classList.remove('hidden');

            customerSearchResults.querySelectorAll('.customer-result-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const customerData = JSON.parse(this.dataset.customer.replace(/&#39;/g, "'"));
                    selectCustomer(customerData);
                });
            });
        }

        function selectCustomer(customer) {
            selectedCustomer = customer;
            const fullName = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || 'Unknown Customer';
            const initial = fullName.substring(0, 1).toUpperCase();
            const email = customer.email || '';
            const phone = customer.phone || customer.phone_number || '';

            // Update customer preview display
            const avatarEl = document.getElementById('selected_customer_avatar');
            const nameEl = document.getElementById('selected_customer_name');
            const emailEl = document.getElementById('selected_customer_email');
            const phoneEl = document.getElementById('selected_customer_phone');
            
            if (avatarEl) avatarEl.textContent = initial;
            if (nameEl) nameEl.textContent = fullName;
            if (emailEl) emailEl.textContent = email || 'No email provided';
            if (phoneEl) phoneEl.textContent = phone || 'No phone provided';
            
            // Set the selected customer ID
            if (selectedCustomerIdInput) {
                selectedCustomerIdInput.value = customer.id || customer.customer_id || customer.hash || '';
            }
            
            // Show the customer preview and hide search results
            if (selectedCustomerDisplay) {
                selectedCustomerDisplay.classList.remove('hidden');
                selectedCustomerDisplay.classList.add('flex');
            }
            if (customerSearchResults) customerSearchResults.classList.add('hidden');
            if (customerSearchInput) {
                customerSearchInput.value = '';
                // Hide clear button
                const clearBtn = document.getElementById('clear_search_btn');
                if (clearBtn) clearBtn.classList.add('hidden');
            }
            
            clearCustomerFormFields();
        }

        // Clear customer selection
        if (clearCustomerBtn) {
            clearCustomerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                clearCustomerSelection();
            });
        } else {
            console.error('[Appointment Form] Clear customer button not found!');
        }

        function clearCustomerSelection() {
            selectedCustomer = null;
            selectedCustomerIdInput.value = '';
            if (selectedCustomerDisplay) {
                selectedCustomerDisplay.classList.add('hidden');
                selectedCustomerDisplay.classList.remove('flex');
            }
            customerSearchInput.value = '';
            customerSearchResults.classList.add('hidden');
            
            // Hide clear button
            const clearBtn = document.getElementById('clear_search_btn');
            if (clearBtn) clearBtn.classList.add('hidden');
        }

        function clearCustomerFormFields() {
            ['customer_first_name', 'customer_last_name', 'customer_email', 'customer_phone', 'customer_address'].forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) field.value = '';
            });
        }

        function enableCustomerFieldValidation() {
            document.querySelectorAll('[data-original-required="1"]').forEach(field => {
                field.required = true;
            });
        }

        function disableCustomerFieldValidation() {
            document.querySelectorAll('[data-original-required]').forEach(field => {
                field.required = false;
            });
        }

        disableCustomerFieldValidation();

        // Appointment summary
        function updateSummary() {
            const service = serviceSelect.options[serviceSelect.selectedIndex];
            const provider = providerSelect.options[providerSelect.selectedIndex];
            const date = dateInput.value;
            const time = timeInput.value;

            if (serviceSelect.value && providerSelect.value && date && time) {
                summaryDiv.classList.remove('hidden');
                
                document.getElementById('summary-service').textContent = service.text.split(' - ')[0] || '-';
                document.getElementById('summary-provider').textContent = provider.text.split(' - ')[0] || '-';
                
                if (date && time) {
                    const dt = new Date(date + 'T' + time);
                    const timeFormat = '<?= esc($localization['time_format'] ?? '12h') ?>';
                    const options = {
                        weekday: 'short',
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: timeFormat === '12h'
                    };
                    document.getElementById('summary-datetime').textContent = dt.toLocaleString('en-US', options);
                } else {
                    document.getElementById('summary-datetime').textContent = '-';
                }
                
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

        serviceSelect.addEventListener('change', updateSummary);
        dateInput.addEventListener('change', updateSummary);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
});
</script>
<?= $this->endSection() ?>
