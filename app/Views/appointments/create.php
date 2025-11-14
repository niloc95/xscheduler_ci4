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
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Fill in the information below to book an appointment</p>
        </div>

        <form action="<?= base_url('/appointments/store') ?>" method="POST" class="p-6">
            <?= csrf_field() ?>
            <input type="hidden" name="client_timezone" id="client_timezone" value="">
            <input type="hidden" name="client_offset" id="client_offset" value="">

            <div class="space-y-6">
                <!-- Customer Information (visible to staff/provider/admin only) -->
                <?php if (in_array($user_role, ['admin', 'provider', 'staff'])): ?>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white">Customer Information</h4>
                        
                        <!-- Customer Mode Toggle -->
                        <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 p-1">
                            <button type="button" 
                                    data-customer-mode="search"
                                    class="customer-mode-btn px-3 py-1.5 text-xs font-medium rounded-md transition-colors bg-blue-600 text-white">
                                <span class="material-symbols-outlined text-sm mr-1" style="font-size: 14px; vertical-align: middle;">search</span>
                                Search Existing
                            </button>
                            <button type="button" 
                                    data-customer-mode="create"
                                    class="customer-mode-btn px-3 py-1.5 text-xs font-medium rounded-md transition-colors text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <span class="material-symbols-outlined text-sm mr-1" style="font-size: 14px; vertical-align: middle;">person_add</span>
                                Create New
                            </button>
                        </div>
                    </div>

                    <!-- Customer Search Section -->
                    <div id="customer-search-section" class="space-y-3">
                        <div class="relative">
                            <label for="customer_search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Search for existing customer
                            </label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">search</span>
                                <input type="text" 
                                       id="customer_search" 
                                       placeholder="Search by name, email, or phone..."
                                       autocomplete="off"
                                       class="block w-full pl-10 pr-10 py-2.5 rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                                <div id="customer_search_spinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Search Results Dropdown -->
                        <div id="customer_search_results" class="hidden absolute z-50 w-full max-w-xl bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 max-h-96 overflow-y-auto"></div>

                        <!-- Selected Customer Display -->
                        <div id="selected_customer_display" class="hidden rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3 flex-1">
                                    <div id="selected_customer_avatar" class="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold text-lg flex-shrink-0">
                                        ?
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 dark:text-gray-100" id="selected_customer_name">-</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400" id="selected_customer_email">-</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400" id="selected_customer_phone">-</div>
                                    </div>
                                </div>
                                <button type="button" 
                                        id="clear_customer_btn"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                    <span class="material-symbols-outlined text-xl">close</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Create Section -->
                    <div id="customer-create-section" class="hidden space-y-4">
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

                    <!-- Hidden field for selected customer ID -->
                    <input type="hidden" id="selected_customer_id" name="customer_id" value="" />
                </div>
                <hr class="border-gray-200 dark:border-gray-700" />
                <?php endif; ?>

                <!-- Provider Selection (Step 1) -->
                <div>
                    <label for="provider_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Provider <span class="text-red-500">*</span>
                    </label>
                    <select id="provider_id" 
                            name="provider_id" 
                            required 
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select a provider first...</option>
                        <?php foreach ($providers as $provider): ?>
                            <option value="<?= $provider['id'] ?>">
                                <?= esc($provider['name']) ?> - <?= esc($provider['speciality']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Service Selection (Step 2 - Dynamically populated based on provider) -->
                <div>
                    <label for="service_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Service <span class="text-red-500">*</span>
                    </label>
                    <select id="service_id" 
                            name="service_id" 
                            required 
                            disabled
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select a provider first...</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Select a provider above to see available services
                    </p>
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
                        
                        <!-- Hidden input to store selected time -->
                        <input type="hidden" 
                               id="appointment_time" 
                               name="appointment_time" 
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
    // Populate timezone fields immediately
    const clientTimezoneField = document.getElementById('client_timezone');
    const clientOffsetField = document.getElementById('client_offset');
    
    if (clientTimezoneField) {
        try {
            clientTimezoneField.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        } catch (e) {
            clientTimezoneField.value = 'UTC';
        }
    }
    
    if (clientOffsetField) {
        clientOffsetField.value = new Date().getTimezoneOffset();
    }

    const serviceSelect = document.getElementById('service_id');
    const providerSelect = document.getElementById('provider_id');
    const dateInput = document.getElementById('appointment_date');
    const timeInput = document.getElementById('appointment_time');
    const summaryDiv = document.getElementById('appointment-summary');

    // Customer search functionality
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

    // Customer mode toggle
    document.querySelectorAll('.customer-mode-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const mode = this.dataset.customerMode;
            
            // Update button styles
            document.querySelectorAll('.customer-mode-btn').forEach(b => {
                if (b.dataset.customerMode === mode) {
                    b.classList.add('bg-blue-600', 'text-white');
                    b.classList.remove('text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
                } else {
                    b.classList.remove('bg-blue-600', 'text-white');
                    b.classList.add('text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
                }
            });

            // Toggle sections
            if (mode === 'search') {
                customerSearchSection.classList.remove('hidden');
                customerCreateSection.classList.add('hidden');
                // Disable required validation on customer fields when in search mode
                disableCustomerFieldValidation();
                // Clear create form fields when switching to search
                if (selectedCustomer) {
                    clearCustomerFormFields();
                }
            } else {
                customerSearchSection.classList.add('hidden');
                customerCreateSection.classList.remove('hidden');
                // Enable required validation on customer fields when in create mode
                enableCustomerFieldValidation();
                // Clear search when switching to create
                clearCustomerSelection();
            }
        });
    });

    // Customer search with debounce
    if (customerSearchInput) {
        customerSearchInput.addEventListener('input', function() {
            const query = this.value.trim();

            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            if (query.length < 2) {
                customerSearchResults.classList.add('hidden');
                return;
            }

            customerSearchSpinner.classList.remove('hidden');

            searchTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`<?= base_url('dashboard/search') ?>?q=${encodeURIComponent(query)}`);
                    
                    if (!response.ok) {
                        throw new Error('Search failed');
                    }

                    // Handle potential debug toolbar in response
                    const text = await response.text();
                    let data;
                    
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        // Extract JSON from HTML-wrapped response
                        const jsonMatch = text.match(/\{["']success["']:\s*(?:true|false)[\s\S]*?\}(?=\s*<|$)/);
                        if (jsonMatch) {
                            data = JSON.parse(jsonMatch[0]);
                        } else {
                            throw new Error('Invalid JSON response');
                        }
                    }

                    if (data.success && data.customers) {
                        displayCustomerResults(data.customers, query);
                    } else {
                        showNoResults(query);
                    }
                } catch (error) {
                    console.error('Search error:', error);
                    customerSearchResults.innerHTML = `
                        <div class="p-4 text-center text-red-600 dark:text-red-400">
                            <span class="material-symbols-outlined text-2xl mb-2 block">error</span>
                            <p class="text-sm">Error loading results</p>
                        </div>
                    `;
                    customerSearchResults.classList.remove('hidden');
                } finally {
                    customerSearchSpinner.classList.add('hidden');
                }
            }, 300);
        });

        // Close results when clicking outside
        document.addEventListener('click', function(e) {
            if (!customerSearchInput.contains(e.target) && !customerSearchResults.contains(e.target)) {
                customerSearchResults.classList.add('hidden');
            }
        });
    }

    // Display customer search results
    function displayCustomerResults(customers, query) {
        if (customers.length === 0) {
            showNoResults(query);
            return;
        }

        const resultsHTML = customers.slice(0, 5).map(customer => {
            const fullName = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || 'Unknown';
            const email = customer.email || '';
            const phone = customer.phone || customer.phone_number || '';
            const initial = fullName.substring(0, 1).toUpperCase();

            return `
                <button type="button" 
                        class="customer-result-item w-full flex items-center gap-3 p-3 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-left"
                        data-customer='${JSON.stringify(customer).replace(/'/g, "&#39;")}'>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                        ${escapeHtml(initial)}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm text-gray-900 dark:text-gray-100">${escapeHtml(fullName)}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">${escapeHtml(email)}</div>
                        ${phone ? `<div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(phone)}</div>` : ''}
                    </div>
                    <span class="material-symbols-outlined text-gray-400 text-sm">arrow_forward</span>
                </button>
            `;
        }).join('');

        customerSearchResults.innerHTML = `
            <div class="p-2">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 px-2">
                    Found ${customers.length} customer${customers.length !== 1 ? 's' : ''}
                </div>
                ${resultsHTML}
            </div>
        `;
        customerSearchResults.classList.remove('hidden');

        // Attach click handlers to results
        customerSearchResults.querySelectorAll('.customer-result-item').forEach(item => {
            item.addEventListener('click', function() {
                const customerData = JSON.parse(this.dataset.customer.replace(/&#39;/g, "'"));
                selectCustomer(customerData);
            });
        });
    }

    function showNoResults(query) {
        customerSearchResults.innerHTML = `
            <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                <span class="material-symbols-outlined text-3xl mb-2 block">search_off</span>
                <p class="text-sm">No customers found for "${escapeHtml(query)}"</p>
                <p class="text-xs mt-1">Try a different search or create a new customer</p>
            </div>
        `;
        customerSearchResults.classList.remove('hidden');
    }

    // Select a customer from search results
    function selectCustomer(customer) {
        selectedCustomer = customer;
        
        const fullName = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || 'Unknown';
        const initial = fullName.substring(0, 1).toUpperCase();

        // Update display
        document.getElementById('selected_customer_avatar').textContent = initial;
        document.getElementById('selected_customer_name').textContent = fullName;
        document.getElementById('selected_customer_email').textContent = customer.email || 'No email';
        document.getElementById('selected_customer_phone').textContent = customer.phone || customer.phone_number || 'No phone';
        
        // Set hidden field for form submission
        selectedCustomerIdInput.value = customer.id || customer.hash || '';

        // Show selected customer display
        selectedCustomerDisplay.classList.remove('hidden');
        customerSearchResults.classList.add('hidden');
        customerSearchInput.value = '';

        // Clear create form fields (customer info will come from selected customer)
        clearCustomerFormFields();
    }

    // Clear customer selection
    if (clearCustomerBtn) {
        clearCustomerBtn.addEventListener('click', function() {
            clearCustomerSelection();
        });
    }

    function clearCustomerSelection() {
        selectedCustomer = null;
        selectedCustomerIdInput.value = '';
        selectedCustomerDisplay.classList.add('hidden');
        customerSearchInput.value = '';
        customerSearchResults.classList.add('hidden');
    }

    function clearCustomerFormFields() {
        // Clear all customer form fields when a customer is selected from search
        ['customer_first_name', 'customer_last_name', 'customer_email', 'customer_phone', 'customer_address'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) field.value = '';
        });
    }

    // Enable/disable customer field validation
    function enableCustomerFieldValidation() {
        ['customer_first_name', 'customer_last_name', 'customer_email', 'customer_phone', 'customer_address', 
         'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4', 'custom_field_5', 'custom_field_6'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && field.dataset.originalRequired) {
                field.required = true;
            }
        });
    }

    function disableCustomerFieldValidation() {
        ['customer_first_name', 'customer_last_name', 'customer_email', 'customer_phone', 'customer_address',
         'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4', 'custom_field_5', 'custom_field_6'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                // Store original required state
                if (field.required) {
                    field.dataset.originalRequired = 'true';
                }
                field.required = false;
            }
        });
    }

    // Initialize: disable customer field validation on page load (default is search mode)
    disableCustomerFieldValidation();

    // Escape HTML helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Dynamic service filtering based on provider selection
    providerSelect.addEventListener('change', async function() {
        const providerId = this.value;
        
        // Reset service dropdown
        serviceSelect.innerHTML = '<option value="">Loading services...</option>';
        serviceSelect.disabled = true;
        
        if (!providerId) {
            serviceSelect.innerHTML = '<option value="">Select a provider first...</option>';
            updateSummary();
            return;
        }
        
        try {
            // Fetch services for the selected provider
            const response = await fetch(`/api/v1/providers/${providerId}/services`);
            
            if (!response.ok) {
                throw new Error('Failed to load services');
            }
            
            const result = await response.json();
            const services = result.data || [];
            
            // Populate services dropdown
            if (services.length === 0) {
                serviceSelect.innerHTML = '<option value="">No services available for this provider</option>';
            } else {
                serviceSelect.innerHTML = '<option value="">Select a service...</option>';
                services.forEach(service => {
                    const option = document.createElement('option');
                    option.value = service.id;
                    option.textContent = `${service.name} - $${parseFloat(service.price).toFixed(2)}`;
                    option.dataset.duration = service.durationMin || service.duration_min;
                    option.dataset.price = service.price;
                    serviceSelect.appendChild(option);
                });
                serviceSelect.disabled = false;
            }
        } catch (error) {
            console.error('Error loading services:', error);
            serviceSelect.innerHTML = '<option value="">Error loading services. Please try again.</option>';
        }
        
        updateSummary();
    });

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
    serviceSelect.addEventListener('change', function() {
        updateSummary();
        loadTimeSlots(); // Fetch available time slots
    });
    dateInput.addEventListener('change', function() {
        updateSummary();
        loadTimeSlots(); // Fetch available time slots
    });
    providerSelect.addEventListener('change', loadTimeSlots);
    
    // Time slots functionality
    async function loadTimeSlots() {
        const providerId = providerSelect.value;
        const date = dateInput.value;
        const serviceId = serviceSelect.value;
        
        const timeSlotsGrid = document.getElementById('time-slots-grid');
        const timeSlotsLoading = document.getElementById('time-slots-loading');
        const timeSlotsEmpty = document.getElementById('time-slots-empty');
        const timeSlotsError = document.getElementById('time-slots-error');
        const timeSlotsPrompt = document.getElementById('time-slots-prompt');
        
        // Hide all states
        timeSlotsGrid.classList.add('hidden');
        timeSlotsLoading.classList.add('hidden');
        timeSlotsEmpty.classList.add('hidden');
        timeSlotsError.classList.add('hidden');
        timeSlotsPrompt.classList.add('hidden');
        
        // Check if all required fields are filled
        if (!providerId || !date || !serviceId) {
            timeSlotsPrompt.classList.remove('hidden');
            timeInput.value = '';
            return;
        }
        
        // Show loading state
        timeSlotsLoading.classList.remove('hidden');
        
        try {
            const response = await fetch(`/api/availability/slots?provider_id=${providerId}&date=${date}&service_id=${serviceId}`);
            
            if (!response.ok) {
                throw new Error('Failed to load time slots');
            }
            
            const result = await response.json();
            
            if (!result.ok || !result.data || !result.data.slots) {
                throw new Error('Invalid response format');
            }
            
            const slots = result.data.slots;
            
            // Hide loading
            timeSlotsLoading.classList.add('hidden');
            
            // Check if slots are available
            if (slots.length === 0) {
                timeSlotsEmpty.classList.remove('hidden');
                timeInput.value = '';
                return;
            }
            
            // Render time slots
            timeSlotsGrid.innerHTML = '';
            timeSlotsGrid.className = 'grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2';
            
            slots.forEach(slot => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'time-slot-btn px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:border-blue-500 dark:hover:border-blue-500 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500';
                button.textContent = slot.start;
                button.dataset.time = slot.start;
                button.dataset.startTime = slot.startTime;
                button.dataset.endTime = slot.endTime;
                
                button.addEventListener('click', function() {
                    // Remove selected state from all buttons
                    document.querySelectorAll('.time-slot-btn').forEach(btn => {
                        btn.classList.remove('bg-blue-600', 'text-white', 'border-blue-600', 'dark:bg-blue-600', 'dark:border-blue-600');
                        btn.classList.add('bg-white', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'border-gray-300', 'dark:border-gray-600');
                    });
                    
                    // Add selected state to clicked button
                    this.classList.remove('bg-white', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'border-gray-300', 'dark:border-gray-600');
                    this.classList.add('bg-blue-600', 'text-white', 'border-blue-600', 'dark:bg-blue-600', 'dark:border-blue-600');
                    
                    // Update hidden input with selected time
                    timeInput.value = this.dataset.time;
                    
                    // Update summary
                    updateSummary();
                    
                    // Show confirmation feedback
                    const feedback = document.createElement('div');
                    feedback.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 flex items-center gap-2';
                    feedback.innerHTML = `
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        <span>Time slot selected: ${this.dataset.time}</span>
                    `;
                    document.body.appendChild(feedback);
                    setTimeout(() => feedback.remove(), 2000);
                });
                
                timeSlotsGrid.appendChild(button);
            });
            
            timeSlotsGrid.classList.remove('hidden');
            
        } catch (error) {
            console.error('Error loading time slots:', error);
            timeSlotsLoading.classList.add('hidden');
            timeSlotsError.classList.remove('hidden');
            document.getElementById('time-slots-error-message').textContent = error.message || 'Failed to load time slots';
            timeInput.value = '';
        }
    }
});
</script>
<?= $this->endSection() ?>
