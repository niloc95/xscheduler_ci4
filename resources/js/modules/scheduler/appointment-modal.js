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
                <!-- Backdrop -->
                <div class="scheduler-modal-backdrop" data-modal-close></div>
                
                <!-- Modal Panel -->
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
        this.selectedDate = options.date || DateTime.now().toISODate();
        this.selectedTime = options.time || null;
        this.selectedProviderId = options.providerId || null;
        
        // Show modal with fade-in animation
        this.modal.classList.remove('hidden');
        requestAnimationFrame(() => {
            this.modal.classList.add('scheduler-modal-open');
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
        
        // Always show date/time selection
        formHTML += this.renderDateTimeFields(timezone);
        
        // Provider selection (always required)
        formHTML += this.renderProviderField();
        
        // Service selection (always required)
        formHTML += this.renderServiceField();
        
        // Customer info fields (dynamic based on settings)
        if (enabledFields.includes('customer_name')) {
            formHTML += this.renderTextField('customer_name', 'Customer Name', 'person');
        }
        
        if (enabledFields.includes('customer_email')) {
            formHTML += this.renderEmailField('customer_email', 'Email Address', 'email');
        }
        
        if (enabledFields.includes('customer_phone')) {
            formHTML += this.renderPhoneField('customer_phone', 'Phone Number', 'phone');
        }
        
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
            const providers = await response.json();
            
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
            const services = await response.json();
            
            services.forEach(service => {
                const option = document.createElement('option');
                option.value = service.id;
                option.textContent = service.name;
                option.dataset.duration = service.duration;
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
