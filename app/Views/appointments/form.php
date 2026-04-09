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
$isPastAppointment = $isPastAppointment ?? false;
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
        <?= view('components/button', [
            'tag' => 'a',
            'label' => 'Back to Appointments',
            'href' => base_url('/appointments'),
            'variant' => 'text',
            'size' => 'sm',
            'icon' => 'arrow_back'
        ], ['saveData' => false]) ?>
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

    <?php if ($isEditMode && $isPastAppointment): ?>
    <!-- Past Appointment Warning -->
    <div class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
        <div class="flex items-start">
            <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 mr-3">history</span>
            <div class="flex-1">
                <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Past Appointment</h3>
                <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                    This appointment has already passed. You can only update the status and notes. 
                    Date, time, provider, and service cannot be changed.
                </p>
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

          <form action="<?= $formAction ?>" method="POST" class="p-6"
              data-appointment-form="true"
              data-is-edit-mode="<?= $isEditMode ? '1' : '0' ?>"
              data-expects-time-slots="<?= (!$isEditMode || !$isPastAppointment) ? '1' : '0' ?>"
              data-exclude-appointment-id="<?= esc($appointment['id'] ?? $appointment['appointment_id'] ?? '') ?>"
              data-preselect-service-id="<?= esc(old('service_id', $appointment['service_id'] ?? '')) ?>"
              data-preselect-location-id="<?= esc(old('location_id', $appointment['location_id'] ?? '')) ?>"
              data-initial-time="<?= esc(old('appointment_time', $appointment['time'] ?? '')) ?>"
              data-search-url="<?= esc(base_url('dashboard/search')) ?>"
              data-currency-symbol="<?= esc(get_app_currency_symbol()) ?>"
              data-time-format="<?= esc($localization['time_format'] ?? '12h') ?>">
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
                            <label for="customer_first_name" class="form-label <?= $fieldConfig['first_name']['required'] ? 'required' : '' ?>">First Name</label>
                            <input type="text" 
                                   id="customer_first_name" 
                                   name="customer_first_name" 
                                   value="<?= esc(old('customer_first_name', $appointment['customer_first_name'] ?? '')) ?>"
                                   <?= $fieldConfig['first_name']['required'] ? 'required' : '' ?>
                                   class="form-input" />
                        </div>
                        <?php endif; ?>

                        <!-- Last Name -->
                        <?php if ($fieldConfig['last_name']['display']): ?>
                        <div>
                            <label for="customer_last_name" class="form-label <?= $fieldConfig['last_name']['required'] ? 'required' : '' ?>">Last Name</label>
                            <input type="text" 
                                   id="customer_last_name" 
                                   name="customer_last_name" 
                                   value="<?= esc(old('customer_last_name', $appointment['customer_last_name'] ?? '')) ?>"
                                   <?= $fieldConfig['last_name']['required'] ? 'required' : '' ?>
                                   class="form-input" />
                        </div>
                        <?php endif; ?>

                        <!-- Email -->
                        <?php if ($fieldConfig['email']['display']): ?>
                        <div>
                            <label for="customer_email" class="form-label <?= $fieldConfig['email']['required'] ? 'required' : '' ?>">Email</label>
                            <input type="email" 
                                   id="customer_email" 
                                   name="customer_email" 
                                   value="<?= esc(old('customer_email', $appointment['customer_email'] ?? '')) ?>"
                                   <?= $fieldConfig['email']['required'] ? 'required' : '' ?>
                                   class="form-input" />
                        </div>
                        <?php endif; ?>

                        <!-- Phone -->
                        <?php if ($fieldConfig['phone']['display']): ?>
                        <div>
                            <label for="customer_phone" class="form-label <?= $fieldConfig['phone']['required'] ? 'required' : '' ?>">Phone</label>
                            <input type="tel" 
                                   id="customer_phone" 
                                   name="customer_phone" 
                                   value="<?= esc(old('customer_phone', $appointment['customer_phone'] ?? '')) ?>"
                                   <?= $fieldConfig['phone']['required'] ? 'required' : '' ?>
                                   class="form-input" />
                        </div>
                        <?php endif; ?>

                        <!-- Address -->
                        <?php if ($fieldConfig['address']['display']): ?>
                        <div class="md:col-span-2">
                            <label for="customer_address" class="form-label <?= $fieldConfig['address']['required'] ? 'required' : '' ?>">Address</label>
                            <input type="text" 
                                   id="customer_address" 
                                   name="customer_address" 
                                   value="<?= esc(old('customer_address', $appointment['customer_address'] ?? '')) ?>"
                                   <?= $fieldConfig['address']['required'] ? 'required' : '' ?>
                                   class="form-input" />
                        </div>
                        <?php endif; ?>

                        <!-- Custom Fields -->
                        <?php foreach ($customFields as $fieldKey => $fieldMeta): ?>
                        <div class="<?= $fieldMeta['type'] === 'textarea' ? 'md:col-span-2' : '' ?>">
                            <label for="<?= esc($fieldKey) ?>" class="form-label <?= $fieldMeta['required'] ? 'required' : '' ?>"><?= esc($fieldMeta['title']) ?></label>
                            
                            <?php if ($fieldMeta['type'] === 'textarea'): ?>
                                <textarea id="<?= esc($fieldKey) ?>" 
                                          name="<?= esc($fieldKey) ?>" 
                                          rows="3"
                                          <?= $fieldMeta['required'] ? 'required' : '' ?>
                                          class="form-input"><?= esc(old($fieldKey, $appointment[$fieldKey] ?? '')) ?></textarea>
                            
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
                                       class="form-input" />
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
                                class="form-input !pl-14 !pr-12" />
                            <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-gray-400">search</span>
                            <button type="button" id="clear_search_btn" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
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
                                <label for="customer_first_name" class="form-label <?= $fieldConfig['first_name']['required'] ? 'required' : '' ?>">First Name</label>
                                <input type="text" id="customer_first_name" name="customer_first_name" 
                                       data-original-required="<?= $fieldConfig['first_name']['required'] ? '1' : '0' ?>"
                                       class="form-input" />
                            </div>
                            <?php endif; ?>

                            <?php if ($fieldConfig['last_name']['display']): ?>
                            <div>
                                <label for="customer_last_name" class="form-label <?= $fieldConfig['last_name']['required'] ? 'required' : '' ?>">Last Name</label>
                                <input type="text" id="customer_last_name" name="customer_last_name"
                                       data-original-required="<?= $fieldConfig['last_name']['required'] ? '1' : '0' ?>"
                                       class="form-input" />
                            </div>
                            <?php endif; ?>

                            <?php if ($fieldConfig['email']['display']): ?>
                            <div>
                                <label for="customer_email" class="form-label <?= $fieldConfig['email']['required'] ? 'required' : '' ?>">Email</label>
                                <input type="email" id="customer_email" name="customer_email"
                                       data-original-required="<?= $fieldConfig['email']['required'] ? '1' : '0' ?>"
                                       class="form-input" />
                            </div>
                            <?php endif; ?>

                            <?php if ($fieldConfig['phone']['display']): ?>
                            <div>
                                <label for="customer_phone" class="form-label <?= $fieldConfig['phone']['required'] ? 'required' : '' ?>">Phone</label>
                                <input type="tel" id="customer_phone" name="customer_phone"
                                       data-original-required="<?= $fieldConfig['phone']['required'] ? '1' : '0' ?>"
                                       class="form-input" />
                            </div>
                            <?php endif; ?>

                            <?php if ($fieldConfig['address']['display']): ?>
                            <div class="md:col-span-2">
                                <label for="customer_address" class="form-label <?= $fieldConfig['address']['required'] ? 'required' : '' ?>">Address</label>
                                <input type="text" id="customer_address" name="customer_address"
                                       data-original-required="<?= $fieldConfig['address']['required'] ? '1' : '0' ?>"
                                       class="form-input" />
                            </div>
                            <?php endif; ?>

                            <?php foreach ($customFields as $fieldKey => $fieldMeta): ?>
                            <div class="<?= $fieldMeta['type'] === 'textarea' ? 'md:col-span-2' : '' ?>">
                                <label for="<?= esc($fieldKey) ?>" class="form-label <?= $fieldMeta['required'] ? 'required' : '' ?>"><?= esc($fieldMeta['title']) ?></label>
                                
                                <?php if ($fieldMeta['type'] === 'textarea'): ?>
                                    <textarea id="<?= esc($fieldKey) ?>" 
                                              name="<?= esc($fieldKey) ?>" 
                                              rows="3"
                                              data-original-required="<?= $fieldMeta['required'] ? '1' : '0' ?>"
                                              class="form-input"></textarea>
                                
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
                                           class="form-input" />
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
                    <label for="provider_id" class="form-label required">Provider</label>
                    <select id="provider_id" 
                            name="provider_id" 
                            required 
                            <?= ($isEditMode && $isPastAppointment) ? 'disabled' : '' ?>
                            class="form-input">
                        <option value="">Select a provider...</option>
                        <?php foreach ($providers as $provider): ?>
                            <option value="<?= $provider['id'] ?>" 
                                    <?= old('provider_id', $appointment['provider_id'] ?? '') == $provider['id'] ? 'selected' : '' ?>>
                                <?= esc($provider['name']) ?> - <?= esc($provider['speciality']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEditMode && $isPastAppointment): ?>
                    <input type="hidden" name="provider_id" value="<?= esc($appointment['provider_id'] ?? '') ?>" />
                    <?php endif; ?>
                </div>

                <!-- Service Selection -->
                <div>
                    <label for="service_id" class="form-label required">Service</label>
                    <select id="service_id" 
                            name="service_id" 
                            required 
                            <?= ($isEditMode && $isPastAppointment) ? 'disabled' : '' ?>
                            class="form-input">
                        <option value="">Select a provider first...</option>
                    </select>
                    <?php if ($isEditMode && $isPastAppointment): ?>
                    <input type="hidden" name="service_id" value="<?= esc($appointment['service_id'] ?? '') ?>" />
                    <?php endif; ?>
                </div>

                <!-- Location Selection (loaded dynamically based on provider) -->
                <div id="location-selection-wrapper" class="hidden">
                    <label for="location_id" class="form-label">Location</label>
                    <select id="location_id" 
                            name="location_id" 
                            <?= ($isEditMode && $isPastAppointment) ? 'disabled' : '' ?>
                            class="form-input">
                        <option value="">Select a provider first...</option>
                    </select>
                    <?php if ($isEditMode && $isPastAppointment): ?>
                    <input type="hidden" name="location_id" value="<?= esc($appointment['location_id'] ?? '') ?>" />
                    <?php endif; ?>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        <span class="material-symbols-outlined text-xs align-middle">info</span>
                        Where this appointment will take place
                    </p>
                </div>

                <!-- Date & Time Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="appointment_date" class="form-label required">Date</label>
                        <input type="date" 
                               id="appointment_date" 
                               name="appointment_date" 
                               value="<?= esc(old('appointment_date', $appointment['date'] ?? '')) ?>"
                               required 
                               <?= ($isEditMode && $isPastAppointment) ? 'disabled' : '' ?>
                               class="form-input" />
                        <?php if ($isEditMode && $isPastAppointment): ?>
                        <input type="hidden" name="appointment_date" value="<?= esc($appointment['date'] ?? '') ?>" />
                        <?php endif; ?>
                        <!-- Available dates hint -->
                        <div id="available-dates-hint" class="hidden mt-2">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                <span class="material-symbols-outlined text-xs align-middle">event_available</span>
                                Available dates:
                            </p>
                            <div id="available-dates-pills" class="flex flex-wrap gap-1"></div>
                        </div>
                        <div id="no-availability-warning" class="hidden mt-2 p-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded text-xs text-amber-700 dark:text-amber-300">
                            <span class="material-symbols-outlined text-xs align-middle">warning</span>
                            No availability found in the next 60 days for this provider/service combination.
                        </div>
                    </div>
                    <div>
                        <label for="appointment_time" class="form-label required">Time</label>
                        <input type="hidden" 
                               id="appointment_time" 
                               name="appointment_time" 
                               value="<?= esc(old('appointment_time', $appointment['time'] ?? '')) ?>"
                               required />

                        <?php if ($isEditMode && $isPastAppointment): ?>
                        <!-- Past appointment: Show read-only time display -->
                        <div class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white px-4 py-2 opacity-60">
                            <?= esc($appointment['time'] ?? 'N/A') ?>
                        </div>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isEditMode): ?>
                <!-- Status (Edit mode only) -->
                <div>
                    <label for="status" class="form-label required">Status</label>
                    <select id="status" 
                            name="status" 
                            required 
                            class="form-input">
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
                    <label for="notes" class="form-label <?= $fieldConfig['notes']['required'] ? 'required' : '' ?>">Notes <?= $fieldConfig['notes']['required'] ? '' : '(Optional)' ?></label>
                    <textarea id="notes" 
                              name="notes" 
                              rows="4" 
                              <?= $fieldConfig['notes']['required'] ? 'required' : '' ?>
                              placeholder="Any special requests or information..."
                              class="form-input"><?= esc(old('notes', $appointment['notes'] ?? '')) ?></textarea>
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
                        
                        <dt id="summary-location-label" class="text-gray-600 dark:text-gray-400 hidden">Location:</dt>
                        <dd id="summary-location" class="font-medium text-gray-900 dark:text-gray-100 hidden">-</dd>
                        
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
                <?= view('components/button', [
                    'tag' => 'a',
                    'label' => 'Cancel',
                    'href' => base_url('appointments'),
                    'variant' => 'outlined',
                    'size' => 'md',
                    'icon' => null,
                ], ['saveData' => false]) ?>
                <?= view('components/button', [
                    'label' => $isEditMode ? 'Save Changes' : 'Book Appointment',
                    'type' => 'submit',
                    'variant' => 'filled',
                    'size' => 'md',
                    'icon' => null,
                ], ['saveData' => false]) ?>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
