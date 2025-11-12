/**
 * Appointment Creation Modal
 * 
 * Dynamic modal with form field awareness that:
 * - Renders fields based on booking settings (enabled_fields, required_fields)
 * - Validates provider availability using SettingsManager
 * - Handles timezone display and conversion
 * - Integrates with scheduler for date/time selection
 * - Supports drag-to-create from calendar cells
 */

import { DateTime } from 'luxon';

export class AppointmentModal {
    constructor(scheduler, settingsManager) {
        this.scheduler = scheduler;
        this.settings = settingsManager;
        this.modal = null;
        this.form = null;
        this.selectedDate = null;
        this.selectedTime = null;
        this.selectedProviderId = null;
        this.availableSlots = [];
        this.selectedCustomer = null;
        this.customerSearchTimeout = null;
        this.showCustomerForm = false;
        
        this.init();
    }
    
    init() {
        this.createModal();
        this.attachEventListeners();
    }
    
    /**
     * Create modal HTML structure
     */
    createModal() {
        const modalHTML = `
            <div id="appointment-modal" class="scheduler-modal hidden" role="dialog" aria-labelledby="modal-title" aria-modal="true">
                <div class="scheduler-modal-backdrop" data-modal-close></div>
                <div class="scheduler-modal-dialog">
                    <div class="scheduler-modal-panel">
                        <!-- Header -->
                        <div class="scheduler-modal-header">
                            <h3 id="modal-title" class="text-xl font-semibold text-gray-900 dark:text-white">
                                Create Appointment
                            </h3>
                            <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                <span class="material-symbols-outlined text-2xl">close</span>
                            </button>
                        </div>
                        
                        <!-- Body -->
                        <div class="scheduler-modal-body">
                            <form id="appointment-form" class="space-y-4">
                                <!-- Dynamic fields will be inserted here -->
                            </form>
                        </div>
                        
                        <!-- Footer -->
                        <div class="scheduler-modal-footer">
                            <button type="button" data-modal-close class="btn btn-secondary">
                                Cancel
                            </button>
                            <button type="submit" form="appointment-form" class="btn btn-primary">
                                <span class="material-symbols-outlined text-base">add</span>
                                Create Appointment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Insert modal into DOM
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('appointment-modal');
        this.form = document.getElementById('appointment-form');
    }
    
    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Close modal on backdrop/close button click
        this.modal.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', () => this.close());
        });
        
        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.modal.classList.contains('hidden')) {
                this.close();
            }
        });
        
        // Form submission
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });
    }
    
    /**
     * Open modal with optional pre-filled data
     * @param {Object} options - { date, time, providerId }
     */
    async open(options = {}) {
        console.log('[AppointmentModal] Opening modal with options:', options);
        console.log('[AppointmentModal] Modal element:', this.modal);
        console.log('[AppointmentModal] Modal exists in DOM?', document.body.contains(this.modal));
        
        this.selectedDate = options.date || DateTime.now().toISODate();
        this.selectedTime = options.time || null;
        this.selectedProviderId = options.providerId || null;
        
        // Show modal with fade-in animation
        this.modal.classList.remove('hidden');
        
        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';
        
        console.log('[AppointmentModal] Removed hidden class, current classes:', this.modal.className);
        
        requestAnimationFrame(() => {
            this.modal.classList.add('scheduler-modal-open');
            console.log('[AppointmentModal] Added scheduler-modal-open, final classes:', this.modal.className);
            console.log('[AppointmentModal] Modal computed styles:', {
                position: window.getComputedStyle(this.modal).position,
                zIndex: window.getComputedStyle(this.modal).zIndex,
                display: window.getComputedStyle(this.modal).display,
                top: window.getComputedStyle(this.modal).top,
                left: window.getComputedStyle(this.modal).left
            });
        });
        
        // Load and render form
        await this.renderForm();
        
        // Load available slots if provider selected
        if (this.selectedProviderId) {
            await this.loadAvailableSlots();
        }
    }
    
    /**
     * Close modal
     */
    close() {
        this.modal.classList.remove('scheduler-modal-open');
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        setTimeout(() => {
            this.modal.classList.add('hidden');
            this.form.reset();
            this.selectedDate = null;
            this.selectedTime = null;
            this.selectedProviderId = null;
            this.availableSlots = [];
        }, 300); // Match CSS transition duration
    }
    
    /**
     * Render form fields dynamically based on booking settings
     */
    async renderForm() {
        // Ensure settings are loaded
        if (!this.settings.isCacheValid()) {
            await this.settings.refresh();
        }

        const enabledFields = await this.settings.getEnabledFields();
        const timezone = await this.settings.getTimezone();
        
        let formHTML = '';
        
        // Customer Section (Search or Create)
        formHTML += this.renderCustomerSection(enabledFields);
        
        // Always show date/time selection
        formHTML += this.renderDateTimeFields(timezone);
        
        // Provider selection (always required)
        formHTML += this.renderProviderField();
        
        // Service selection (always required)
        formHTML += this.renderServiceField();
        
        // Notes field if enabled
        if (enabledFields.includes('notes')) {
            formHTML += this.renderTextareaField('notes', 'Notes', 'note');
        }
        
        if (enabledFields.includes('location')) {
            formHTML += this.renderTextField('location', 'Location', 'location_on');
        }
        
        // Inject form HTML
        this.form.innerHTML = formHTML;
        
        // Attach field-specific listeners
        this.attachFieldListeners();
        this.attachCustomerSectionListeners();
    }
    
    /**
     * Render customer section with search and create options
     */
    renderCustomerSection(enabledFields) {
        const selectedCustomerDisplay = this.selectedCustomer ? `
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold">
                            ${this.selectedCustomer.first_name?.charAt(0) || 'C'}
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">
                                ${this.escapeHtml(this.selectedCustomer.first_name || '')} ${this.escapeHtml(this.selectedCustomer.last_name || '')}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                ${this.escapeHtml(this.selectedCustomer.email || this.selectedCustomer.phone_number || '')}
                            </div>
                        </div>
                    </div>
                    <button type="button" data-action="clear-customer" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <input type="hidden" name="customer_id" value="${this.selectedCustomer.id || this.selectedCustomer.hash}" />
            </div>
        ` : '';

        return `
            <!-- Customer Section -->
            <div class="form-section border-b border-gray-200 dark:border-gray-700 pb-4 mb-4">
                <button type="button" 
                        class="flex items-center justify-between w-full text-left"
                        data-toggle="customer-section">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-xl">person</span>
                        <h4 class="font-medium text-gray-900 dark:text-white">Customer Information</h4>
                        ${this.selectedCustomer ? '<span class="ml-2 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 px-2 py-0.5 rounded-full">Selected</span>' : ''}
                    </div>
                    <span class="material-symbols-outlined transition-transform" data-icon="expand">
                        ${this.showCustomerForm ? 'expand_less' : 'expand_more'}
                    </span>
                </button>
                
                <div id="customer-section-content" class="${this.showCustomerForm ? '' : 'hidden'} mt-4 space-y-4">
                    ${selectedCustomerDisplay}
                    
                    ${!this.selectedCustomer ? `
                        <!-- Toggle between Search and Create -->
                        <div class="flex gap-2 mb-4">
                            <button type="button" 
                                    data-customer-mode="search"
                                    class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-colors bg-blue-600 text-white">
                                <span class="material-symbols-outlined text-base mr-1">search</span>
                                Search Existing
                            </button>
                            <button type="button" 
                                    data-customer-mode="create"
                                    class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-colors bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <span class="material-symbols-outlined text-base mr-1">person_add</span>
                                Create New
                            </button>
                        </div>
                        
                        <!-- Search Customer -->
                        <div id="customer-search-section" class="space-y-3">
                            <div class="relative">
                                <input type="text" 
                                       id="customer-search-input"
                                       placeholder="Search by name, email, or phone..." 
                                       class="form-input pl-10" />
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                                <div id="customer-search-spinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                                    <div class="animate-spin h-5 w-5 border-2 border-blue-600 border-t-transparent rounded-full"></div>
                                </div>
                            </div>
                            <div id="customer-search-results" class="hidden max-h-60 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg"></div>
                        </div>
                        
                        <!-- Create Customer Form -->
                        <div id="customer-create-section" class="hidden space-y-4">
                            ${this.renderCustomerFormFields(enabledFields)}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Render customer form fields based on settings
     */
    renderCustomerFormFields(enabledFields) {
        let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
        
        // First Name
        if (enabledFields.includes('customer_first_name') || enabledFields.includes('first_name')) {
            const isRequired = this.settings.isFieldRequired('customer_first_name') || this.settings.isFieldRequired('first_name');
            html += `
                <div class="form-field-group">
                    <label class="form-label ${isRequired ? 'required' : ''}">First Name</label>
                    <input type="text" name="customer_first_name" ${isRequired ? 'required' : ''} class="form-input" />
                </div>
            `;
        }
        
        // Last Name
        if (enabledFields.includes('customer_last_name') || enabledFields.includes('last_name')) {
            const isRequired = this.settings.isFieldRequired('customer_last_name') || this.settings.isFieldRequired('last_name');
            html += `
                <div class="form-field-group">
                    <label class="form-label ${isRequired ? 'required' : ''}">Last Name</label>
                    <input type="text" name="customer_last_name" ${isRequired ? 'required' : ''} class="form-input" />
                </div>
            `;
        }
        
        html += '</div>';
        
        // Email
        if (enabledFields.includes('customer_email') || enabledFields.includes('email')) {
            const isRequired = this.settings.isFieldRequired('customer_email') || this.settings.isFieldRequired('email');
            html += `
                <div class="form-field-group">
                    <label class="form-label ${isRequired ? 'required' : ''}">Email</label>
                    <input type="email" name="customer_email" ${isRequired ? 'required' : ''} class="form-input" />
                </div>
            `;
        }
        
        // Phone
        if (enabledFields.includes('customer_phone') || enabledFields.includes('phone')) {
            const isRequired = this.settings.isFieldRequired('customer_phone') || this.settings.isFieldRequired('phone');
            html += `
                <div class="form-field-group">
                    <label class="form-label ${isRequired ? 'required' : ''}">Phone</label>
                    <input type="tel" name="customer_phone" ${isRequired ? 'required' : ''} class="form-input" />
                </div>
            `;
        }
        
        // Address
        if (enabledFields.includes('customer_address') || enabledFields.includes('address')) {
            const isRequired = this.settings.isFieldRequired('customer_address') || this.settings.isFieldRequired('address');
            html += `
                <div class="form-field-group">
                    <label class="form-label ${isRequired ? 'required' : ''}">Address</label>
                    <input type="text" name="customer_address" ${isRequired ? 'required' : ''} class="form-input" />
                </div>
            `;
        }
        
        return html;
    }

    /**
     * Attach customer section listeners
     */
    attachCustomerSectionListeners() {
        // Toggle customer section
        const toggleBtn = this.form.querySelector('[data-toggle="customer-section"]');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                this.showCustomerForm = !this.showCustomerForm;
                const content = document.getElementById('customer-section-content');
                const icon = toggleBtn.querySelector('[data-icon="expand"]');
                
                if (this.showCustomerForm) {
                    content.classList.remove('hidden');
                    icon.textContent = 'expand_less';
                } else {
                    content.classList.add('hidden');
                    icon.textContent = 'expand_more';
                }
            });
        }

        // Customer mode toggle (Search vs Create)
        const modeBtns = this.form.querySelectorAll('[data-customer-mode]');
        modeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const mode = btn.dataset.customerMode;
                const searchSection = document.getElementById('customer-search-section');
                const createSection = document.getElementById('customer-create-section');
                
                // Update button states
                modeBtns.forEach(b => {
                    if (b.dataset.customerMode === mode) {
                        b.classList.remove('bg-white', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'border', 'border-gray-300', 'dark:border-gray-600');
                        b.classList.add('bg-blue-600', 'text-white');
                    } else {
                        b.classList.add('bg-white', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300', 'border', 'border-gray-300', 'dark:border-gray-600');
                        b.classList.remove('bg-blue-600', 'text-white');
                    }
                });
                
                // Show/hide sections
                if (mode === 'search') {
                    searchSection.classList.remove('hidden');
                    createSection.classList.add('hidden');
                } else {
                    searchSection.classList.add('hidden');
                    createSection.classList.remove('hidden');
                }
            });
        });

        // Customer search
        const searchInput = document.getElementById('customer-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.customerSearchTimeout);
                const query = e.target.value.trim();
                
                if (query.length < 2) {
                    document.getElementById('customer-search-results').classList.add('hidden');
                    return;
                }
                
                this.customerSearchTimeout = setTimeout(() => {
                    this.performCustomerSearch(query);
                }, 300);
            });
        }

        // Clear customer
        const clearBtn = this.form.querySelector('[data-action="clear-customer"]');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.selectedCustomer = null;
                this.renderForm();
            });
        }
    }

    /**
     * Perform customer search using existing search endpoint
     */
    async performCustomerSearch(query) {
        const resultsContainer = document.getElementById('customer-search-results');
        const spinner = document.getElementById('customer-search-spinner');
        
        try {
            if (spinner) spinner.classList.remove('hidden');
            resultsContainer.classList.remove('hidden');
            
            const response = await fetch(`/dashboard/search?q=${encodeURIComponent(query)}`);
            const text = await response.text();
            
            // Extract JSON from response (handles debug toolbar)
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                const jsonMatch = text.match(/\{["']success["']:\s*(?:true|false)[\s\S]*?\}(?=\s*<|$)/);
                if (jsonMatch) {
                    data = JSON.parse(jsonMatch[0]);
                }
            }
            
            if (data && data.success) {
                this.displayCustomerSearchResults(data.customers || [], query);
            } else {
                this.displayCustomerSearchResults([], query);
            }
        } catch (error) {
            console.error('Customer search error:', error);
            resultsContainer.innerHTML = '<div class="p-4 text-center text-red-600">Error searching customers</div>';
        } finally {
            if (spinner) spinner.classList.add('hidden');
        }
    }

    /**
     * Display customer search results
     */
    displayCustomerSearchResults(customers, query) {
        const resultsContainer = document.getElementById('customer-search-results');
        
        if (customers.length === 0) {
            resultsContainer.innerHTML = `
                <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                    <span class="material-symbols-outlined text-3xl mb-2 block">search_off</span>
                    <p class="text-sm">No customers found for "${this.escapeHtml(query)}"</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="divide-y divide-gray-200 dark:divide-gray-700">';
        customers.slice(0, 5).forEach(customer => {
            const fullName = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || 'Unknown';
            const initial = fullName.charAt(0).toUpperCase();
            
            html += `
                <button type="button" 
                        class="w-full flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left"
                        data-select-customer='${JSON.stringify(customer)}'>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold flex-shrink-0">
                        ${initial}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900 dark:text-white">${this.escapeHtml(fullName)}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 truncate">${this.escapeHtml(customer.email || customer.phone_number || '')}</div>
                    </div>
                    <span class="material-symbols-outlined text-gray-400">chevron_right</span>
                </button>
            `;
        });
        html += '</div>';
        
        resultsContainer.innerHTML = html;
        
        // Attach select customer listeners
        resultsContainer.querySelectorAll('[data-select-customer]').forEach(btn => {
            btn.addEventListener('click', () => {
                const customer = JSON.parse(btn.dataset.selectCustomer);
                this.selectCustomer(customer);
            });
        });
    }

    /**
     * Select a customer
     */
    selectCustomer(customer) {
        this.selectedCustomer = customer;
        this.renderForm();
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    /**
     * Render date and time selection fields
     */
    renderDateTimeFields(timezone) {
        const isRequired = true; // Date/time always required
        const dateValue = this.selectedDate || DateTime.now().toISODate();
        const timeValue = this.selectedTime || '';
        
        return `
            <div class="form-field-group">
                <label class="form-label ${isRequired ? 'required' : ''}">
                    <span class="material-symbols-outlined text-base">calendar_today</span>
                    Date
                </label>
                <input 
                    type="date" 
                    name="appointment_date" 
                    value="${dateValue}"
                    min="${DateTime.now().toISODate()}"
                    required
                    class="form-input"
                />
                <p class="form-help-text">Timezone: ${timezone}</p>
            </div>
            
            <div class="form-field-group">
                <label class="form-label ${isRequired ? 'required' : ''}">
                    <span class="material-symbols-outlined text-base">schedule</span>
                    Time
                </label>
                <div id="time-slot-picker" class="time-slot-grid">
                    <!-- Time slots will be populated dynamically -->
                    <p class="text-sm text-gray-500 dark:text-gray-400">Select a provider to view available times</p>
                </div>
                <input type="hidden" name="appointment_time" value="${timeValue}" required />
            </div>
        `;
    }
    
    /**
     * Render provider selection field
     */
    renderProviderField() {
        return `
            <div class="form-field-group">
                <label class="form-label required">
                    <span class="material-symbols-outlined text-base">person</span>
                    Provider
                </label>
                <select name="provider_id" required class="form-input" data-field="provider">
                    <option value="">Select a provider...</option>
                    <!-- Options will be populated via AJAX -->
                </select>
            </div>
        `;
    }
    
    /**
     * Render service selection field
     */
    renderServiceField() {
        return `
            <div class="form-field-group">
                <label class="form-label required">
                    <span class="material-symbols-outlined text-base">medical_services</span>
                    Service
                </label>
                <select name="service_id" required class="form-input" data-field="service">
                    <option value="">Select a service...</option>
                    <!-- Options will be populated via AJAX -->
                </select>
                <p class="form-help-text" id="service-details"></p>
            </div>
        `;
    }
    
    /**
     * Render text input field
     */
    renderTextField(name, label, icon) {
        const isRequired = this.settings.isFieldRequired(name);
        return `
            <div class="form-field-group">
                <label class="form-label ${isRequired ? 'required' : ''}">
                    <span class="material-symbols-outlined text-base">${icon}</span>
                    ${label}
                </label>
                <input 
                    type="text" 
                    name="${name}" 
                    ${isRequired ? 'required' : ''}
                    class="form-input"
                />
            </div>
        `;
    }
    
    /**
     * Render email input field
     */
    renderEmailField(name, label, icon) {
        const isRequired = this.settings.isFieldRequired(name);
        return `
            <div class="form-field-group">
                <label class="form-label ${isRequired ? 'required' : ''}">
                    <span class="material-symbols-outlined text-base">${icon}</span>
                    ${label}
                </label>
                <input 
                    type="email" 
                    name="${name}" 
                    ${isRequired ? 'required' : ''}
                    class="form-input"
                />
            </div>
        `;
    }
    
    /**
     * Render phone input field
     */
    renderPhoneField(name, label, icon) {
        const isRequired = this.settings.isFieldRequired(name);
        return `
            <div class="form-field-group">
                <label class="form-label ${isRequired ? 'required' : ''}">
                    <span class="material-symbols-outlined text-base">${icon}</span>
                    ${label}
                </label>
                <input 
                    type="tel" 
                    name="${name}" 
                    ${isRequired ? 'required' : ''}
                    class="form-input"
                />
            </div>
        `;
    }
    
    /**
     * Render textarea field
     */
    renderTextareaField(name, label, icon) {
        const isRequired = this.settings.isFieldRequired(name);
        return `
            <div class="form-field-group">
                <label class="form-label ${isRequired ? 'required' : ''}">
                    <span class="material-symbols-outlined text-base">${icon}</span>
                    ${label}
                </label>
                <textarea 
                    name="${name}" 
                    rows="3"
                    ${isRequired ? 'required' : ''}
                    class="form-input"
                ></textarea>
            </div>
        `;
    }
    
    /**
     * Attach field-specific listeners
     */
    attachFieldListeners() {
        // Provider change: load available slots
        const providerField = this.form.querySelector('[name="provider_id"]');
        if (providerField) {
            providerField.addEventListener('change', async (e) => {
                this.selectedProviderId = e.target.value;
                if (this.selectedProviderId) {
                    await this.loadAvailableSlots();
                }
            });
        }
        
        // Date change: reload available slots
        const dateField = this.form.querySelector('[name="appointment_date"]');
        if (dateField) {
            dateField.addEventListener('change', async (e) => {
                this.selectedDate = e.target.value;
                if (this.selectedProviderId) {
                    await this.loadAvailableSlots();
                }
            });
        }
        
        // Service change: show duration and price
        const serviceField = this.form.querySelector('[name="service_id"]');
        if (serviceField) {
            serviceField.addEventListener('change', (e) => {
                this.updateServiceDetails(e.target.value);
            });
        }
        
        // Load providers and services
        this.loadProviders();
        this.loadServices();
    }
    
    /**
     * Load available time slots for selected provider and date
     */
    async loadAvailableSlots() {
        if (!this.selectedProviderId || !this.selectedDate) {
            return;
        }
        
        const picker = document.getElementById('time-slot-picker');
        picker.innerHTML = '<div class="loading-spinner"></div>';
        
        try {
            // Get available slots from settings manager
            const slots = await this.settings.getAvailableSlots(
                this.selectedProviderId,
                this.selectedDate
            );
            
            if (slots.length === 0) {
                picker.innerHTML = `
                    <p class="text-sm text-amber-600 dark:text-amber-400">
                        No available slots for this date. Please select another date.
                    </p>
                `;
                return;
            }
            
            // Render time slot buttons
            const timeFormat = await this.settings.getTimeFormat();
            const slotsHTML = slots.map(slot => {
                const time = DateTime.fromISO(`${this.selectedDate}T${slot}`);
                const displayTime = this.settings.formatTime(time);
                const isSelected = this.selectedTime === slot;
                
                return `
                    <button 
                        type="button" 
                        class="time-slot ${isSelected ? 'selected' : ''}"
                        data-time="${slot}"
                    >
                        ${displayTime}
                    </button>
                `;
            }).join('');
            
            picker.innerHTML = slotsHTML;
            
            // Attach click listeners to time slots
            picker.querySelectorAll('.time-slot').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    // Remove previous selection
                    picker.querySelectorAll('.time-slot').forEach(b => b.classList.remove('selected'));
                    
                    // Mark as selected
                    btn.classList.add('selected');
                    this.selectedTime = btn.dataset.time;
                    
                    // Update hidden input
                    this.form.querySelector('[name="appointment_time"]').value = this.selectedTime;
                });
            });
            
        } catch (error) {
            console.error('Error loading time slots:', error);
            picker.innerHTML = `
                <p class="text-sm text-red-600 dark:text-red-400">
                    Error loading available times. Please try again.
                </p>
            `;
        }
    }
    
    /**
     * Load providers from API
     */
    async loadProviders() {
        const select = this.form.querySelector('[name="provider_id"]');
        if (!select) return;
        
        try {
            const response = await fetch('/api/v1/providers');
            const result = await response.json();
            const providers = result.data || result || [];
            
            providers.forEach(provider => {
                const option = document.createElement('option');
                option.value = provider.id;
                option.textContent = provider.name;
                if (this.selectedProviderId == provider.id) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading providers:', error);
        }
    }
    
    /**
     * Load services from API
     */
    async loadServices() {
        const select = this.form.querySelector('[name="service_id"]');
        if (!select) return;
        
        try {
            const response = await fetch('/api/v1/services');
            const result = await response.json();
            const services = result.data || result || [];
            
            services.forEach(service => {
                const option = document.createElement('option');
                option.value = service.id;
                option.textContent = service.name;
                option.dataset.duration = service.durationMin || service.duration_min || service.duration;
                option.dataset.price = service.price;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading services:', error);
        }
    }
    
    /**
     * Update service details display
     */
    updateServiceDetails(serviceId) {
        const select = this.form.querySelector('[name="service_id"]');
        const detailsEl = document.getElementById('service-details');
        
        if (!serviceId || !detailsEl) return;
        
        const option = select.querySelector(`option[value="${serviceId}"]`);
        if (option) {
            const duration = option.dataset.duration;
            const price = option.dataset.price;
            detailsEl.textContent = `Duration: ${duration} min • Price: $${price}`;
        }
    }
    
    /**
     * Handle form submission
     */
    async handleSubmit() {
        // Validate form
        if (!this.form.checkValidity()) {
            this.form.reportValidity();
            return;
        }
        
        // Validate time slot selection
        if (!this.selectedTime) {
            this.showToast('Please select a time slot', 'error');
            return;
        }
        
        // Gather form data
        const formData = new FormData(this.form);
        const rawData = Object.fromEntries(formData.entries());
        
        // Transform data to match API expectations (camelCase)
        const data = {
            name: rawData.customer_name || '',
            email: rawData.customer_email || '',
            phone: rawData.customer_phone || '',
            providerId: parseInt(rawData.provider_id) || null,
            serviceId: parseInt(rawData.service_id) || null,
            date: rawData.appointment_date || '',
            start: rawData.appointment_time || '',
            notes: rawData.notes || '',
            location: rawData.location || ''
        };
        
        // Show loading state
        const submitBtn = this.modal.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading-spinner"></span> Creating...';
        
        try {
            // Send to API with timezone header
            const timezone = await this.settings.getTimezone();
            const response = await fetch('/api/appointments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Client-Timezone': timezone
                },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                const errorMessage = errorData?.error?.message || 'Failed to create appointment';
                throw new Error(errorMessage);
            }
            
            const result = await response.json();
            
            // Success!
            this.showToast('Appointment created successfully', 'success');
            this.close();
            
            // Refresh scheduler
            this.scheduler.refresh();
            
        } catch (error) {
            console.error('Error creating appointment:', error);
            this.showToast(error.message || 'Failed to create appointment. Please try again.', 'error');
            
            // Restore button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
    
    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        // Use the drag-drop manager's toast system if available
        if (this.scheduler.dragDropManager) {
            this.scheduler.dragDropManager.showToast(message, type);
        } else {
            alert(message);
        }
    }
}
