import { attachTimezoneHeaders, getBrowserTimezone, getTimezoneOffset } from '../../utils/timezone-helper.js';
import { getBaseUrl, withBaseUrl } from '../../utils/url-helpers.js';

/**
 * Appointments Booking Form Module
 * 
 * Handles dynamic interactions for the appointment booking form:
 * - AJAX cascading dropdowns (provider → services filter)
 * - Real-time availability checking
 * - Auto-calculated end times from service duration
 * - Form validation and user feedback
 * 
 * @module appointments-form
 */

/**
 * Initialize the appointment booking form
 * Works for both create (/appointments/store) and edit (/appointments/update/X) forms
 */
export async function initAppointmentForm() {
    // Match both create and edit forms
    const form = document.querySelector('form[action*="/appointments/store"], form[action*="/appointments/update"]');
    if (!form) return;

    const providerSelect = document.getElementById('provider_id');
    const serviceSelect = document.getElementById('service_id');
    const dateInput = document.getElementById('appointment_date');
    const timeInput = document.getElementById('appointment_time');
    syncClientTimezoneFields(form);

    if (!providerSelect || !serviceSelect || !dateInput || !timeInput) {
        console.warn('Appointment form elements not found');
        return;
    }

    // State management
    const formState = {
        provider_id: null,
        service_id: null,
        date: null,
        time: null,
        duration: null,
        isChecking: false,
        isAvailable: null
    };

    // Initialize service dropdown as disabled (provider must be selected first)
    serviceSelect.disabled = true;
    serviceSelect.classList.add('bg-gray-100', 'dark:bg-gray-800', 'cursor-not-allowed');

    // Create availability feedback element
    const availabilityFeedback = createAvailabilityFeedback();
    timeInput.parentNode.appendChild(availabilityFeedback);

    // Create end time display
    const endTimeDisplay = createEndTimeDisplay();
    timeInput.parentNode.appendChild(endTimeDisplay);

    // Create slot suggestions container
    const slotSuggestions = createSlotSuggestions();
    timeInput.parentNode.appendChild(slotSuggestions);

    // Event: Provider selection changes → Load their services
    providerSelect.addEventListener('change', async function() {
        const providerId = this.value;
        formState.provider_id = providerId;

        if (!providerId) {
            // Disable service dropdown
            serviceSelect.disabled = true;
            serviceSelect.innerHTML = '<option value="">Select a provider first...</option>';
            serviceSelect.classList.add('bg-gray-100', 'dark:bg-gray-800', 'cursor-not-allowed');
            clearAvailabilityCheck();
            return;
        }

        // Fetch services for selected provider
        await loadProviderServices(providerId, serviceSelect, formState);
        
        // Clear previous selection and recheck availability
        formState.service_id = null;
        clearAvailabilityCheck();
        clearSlotSuggestions(slotSuggestions);
    });

    // Event: Service selection changes → Update duration and check availability
    serviceSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        formState.service_id = this.value;
        
        if (this.value) {
            formState.duration = parseInt(selectedOption.dataset.duration) || 0;
            updateEndTime(formState, endTimeDisplay);
        } else {
            formState.duration = null;
            clearEndTime(endTimeDisplay);
        }

        checkAvailability(formState, availabilityFeedback);
        fetchAndRenderSlots(formState, slotSuggestions);
    });

    // Event: Date changes → Check availability
    dateInput.addEventListener('change', function() {
        formState.date = this.value;
        checkAvailability(formState, availabilityFeedback);
        fetchAndRenderSlots(formState, slotSuggestions);
    });

    // Event: Time changes → Update end time and check availability
    timeInput.addEventListener('change', function() {
        formState.time = this.value;
        updateEndTime(formState, endTimeDisplay);
        checkAvailability(formState, availabilityFeedback);
    });

    // Event: Form submission validation
    form.addEventListener('submit', async function(e) {
        e.preventDefault(); // Prevent default form submission
        
        // Clear previous validation errors
        clearAllFieldErrors(form);
        
        // Validate form fields
        const validationErrors = validateAppointmentForm(form);
        if (validationErrors.length > 0) {
            // Show field errors and focus on first invalid field
            showValidationErrors(validationErrors);
            return false;
        }
        
        if (formState.isAvailable === false) {
            showNotification('error', 'This time slot is not available. Please choose a different time.');
            const timeInput = document.getElementById('appointment_time');
            if (timeInput) {
                timeInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }

        if (formState.isChecking) {
            showNotification('info', 'Please wait while we check availability...');
            return false;
        }
        
        // AJAX form submission
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.textContent;
        
        try {
            // Disable submit button and show loading state
            submitButton.disabled = true;
            submitButton.textContent = '⏳ Creating appointment...';
            
            // Submit form via AJAX
            const formData = new FormData(form);
            
            // Get form action URL (use getAttribute to avoid conflict with button named "action")
            const formActionUrl = form.getAttribute('action');
            
            const response = await fetch(formActionUrl, {
                method: 'POST',
                headers: {
                    ...attachTimezoneHeaders(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('[appointments-form] Server error response:', errorText);
                
                // Try to parse validation errors from JSON response
                if (response.status === 422) {
                    try {
                        const errorData = JSON.parse(errorText);
                        if (errorData.errors) {
                            const errors = Object.entries(errorData.errors).map(([field, messages]) => ({
                                field,
                                message: Array.isArray(messages) ? messages[0] : messages
                            }));
                            showValidationErrors(errors);
                            return;
                        }
                    } catch (parseError) {
                        // Fall through to generic message
                    }
                    throw new Error('Please fill in all required fields before submitting.');
                }
                
                throw new Error(`Server error (${response.status}). Please try again.`);
            }
            
            // Check if response is JSON (API) or HTML (redirect)
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                const result = await response.json();
                
                if (result.success || result.data) {
                    // Show success notification
                    showNotification('success', 'Appointment booked successfully!');

                    if (typeof window !== 'undefined') {
                        const detail = { source: 'appointment-form', action: 'create-or-update' };
                        if (typeof window.emitAppointmentsUpdated === 'function') {
                            window.emitAppointmentsUpdated(detail);
                        } else {
                            window.dispatchEvent(new CustomEvent('appointments-updated', { detail }));
                        }
                    }
                    
                    // Redirect to appointments page after short delay
                    setTimeout(() => {
                        window.location.href = withBaseUrl('/appointments');
                    }, 500);
                } else {
                    throw new Error(result.error || 'Unknown error occurred');
                }
            } else {
                // HTML response (redirect) - follow it
                if (typeof window !== 'undefined') {
                    const detail = { source: 'appointment-form', action: 'create-or-update' };
                    if (typeof window.emitAppointmentsUpdated === 'function') {
                        window.emitAppointmentsUpdated(detail);
                    } else {
                        window.dispatchEvent(new CustomEvent('appointments-updated', { detail }));
                    }
                }
                window.location.href = withBaseUrl('/appointments');
            }
            
        } catch (error) {
            console.error('[appointments-form] ❌ Form submission error:', error);
            showNotification('error', error.message || 'Failed to create appointment. Please try again.');
            
            // Re-enable submit button
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        }
        
        return false;
    });
}

/**
 * Fetch services for a specific provider via AJAX
 */
async function loadProviderServices(providerId, serviceSelect, formState) {
    try {
        // Show loading state
        serviceSelect.disabled = true;
        serviceSelect.classList.add('bg-gray-100', 'dark:bg-gray-800');
        serviceSelect.innerHTML = '<option value="">🔄 Loading services...</option>';

        const response = await fetch(withBaseUrl(`/api/v1/providers/${providerId}/services`), {
            method: 'GET',
            headers: {
                ...attachTimezoneHeaders(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        
        // API returns { data: [...], meta: {...} }
        if (data.data && Array.isArray(data.data) && data.data.length > 0) {
            const services = data.data;
            
            // Update service select with fetched services
            serviceSelect.innerHTML = '<option value="">Select a service...</option>';
            
            services.forEach(service => {
                const option = document.createElement('option');
                option.value = service.id;
                const currencySymbol = window.appCurrencySymbol || '$';
                option.textContent = `${service.name} - ${service.duration} min - ${currencySymbol}${parseFloat(service.price).toFixed(2)}`;
                option.dataset.duration = service.duration;
                option.dataset.price = service.price;
                serviceSelect.appendChild(option);
            });

            // Enable the dropdown
            serviceSelect.disabled = false;
            serviceSelect.classList.remove('bg-gray-100', 'dark:bg-gray-800', 'cursor-not-allowed');

        } else {
            // No services found for this provider
            serviceSelect.innerHTML = '<option value="">No services available for this provider</option>';
            serviceSelect.disabled = true; // Keep disabled if no services
        }

    } catch (error) {
        console.error('Error loading provider services:', error);
        serviceSelect.innerHTML = '<option value="">⚠️ Error loading services. Please try again.</option>';
        serviceSelect.disabled = true;
        
        // Show error for 3 seconds, then reset
        setTimeout(() => {
            serviceSelect.innerHTML = '<option value="">Select a provider first...</option>';
        }, 3000);
    }
}

/**
 * Check availability for selected appointment slot
 */
async function checkAvailability(formState, feedbackElement) {
    // Need all required fields
    if (!formState.provider_id || !formState.service_id || !formState.date || !formState.time || !formState.duration) {
        clearAvailabilityCheck(feedbackElement);
        return;
    }

    formState.isChecking = true;
    showAvailabilityChecking(feedbackElement);

    try {
        // Calculate start and end times
        const startTime = `${formState.date} ${formState.time}:00`;
        const startDate = new Date(`${formState.date}T${formState.time}:00`);
        const endDate = new Date(startDate.getTime() + formState.duration * 60000);
        const endTime = endDate.toISOString().slice(0, 19).replace('T', ' ');
        
        // Use the correct availability API endpoint
        const response = await fetch(withBaseUrl('/api/availability/check'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                provider_id: parseInt(formState.provider_id),
                start_time: startTime,
                end_time: endTime,
                timezone: getBrowserTimezone()
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        const data = result.data || result;
        
        formState.isAvailable = data.available === true;
        
        if (formState.isAvailable) {
            showAvailabilitySuccess(feedbackElement, '✓ Time slot available');
        } else {
            // Build detailed error message from the reason
            const message = data.reason || 'Time slot not available';
            showAvailabilityError(feedbackElement, message);
        }

    } catch (error) {
        console.error('Error checking availability:', error);
        formState.isAvailable = null;
        showAvailabilityWarning(feedbackElement, 'Unable to verify availability');
    } finally {
        formState.isChecking = false;
    }
}

/**
 * Calculate and update end time display
 */
function updateEndTime(formState, endTimeElement) {
    if (!formState.time || !formState.duration) {
        clearEndTime(endTimeElement);
        return;
    }

    try {
        // Parse start time
        const [hours, minutes] = formState.time.split(':').map(Number);
        const startDate = new Date();
        startDate.setHours(hours, minutes, 0, 0);

        // Add duration in minutes
        const endDate = new Date(startDate.getTime() + formState.duration * 60000);
        
        // Format end time
        const endHours = String(endDate.getHours()).padStart(2, '0');
        const endMinutes = String(endDate.getMinutes()).padStart(2, '0');
        const endTime = `${endHours}:${endMinutes}`;

        endTimeElement.textContent = `Ends at: ${endTime}`;
        endTimeElement.classList.remove('hidden');

    } catch (error) {
        console.error('Error calculating end time:', error);
        clearEndTime(endTimeElement);
    }
}

/**
 * Clear end time display
 */
function clearEndTime(endTimeElement) {
    endTimeElement.textContent = '';
    endTimeElement.classList.add('hidden');
}

/**
 * Clear availability check state
 */
function clearAvailabilityCheck(feedbackElement) {
    if (feedbackElement) {
        feedbackElement.textContent = '';
        feedbackElement.className = 'mt-2 text-sm hidden';
    }
}

/**
 * Fetch available slots for the selected provider/service/date and render quick-pick buttons
 */
async function fetchAndRenderSlots(formState, slotContainer) {
    // Need provider, service, date
    if (!formState.provider_id || !formState.service_id || !formState.date) {
        clearSlotSuggestions(slotContainer);
        return;
    }

    // Show loading state
    if (slotContainer) {
        slotContainer.innerHTML = '<div class="mt-2 text-sm text-gray-600 dark:text-gray-400">Loading available time slots...</div>';
        slotContainer.classList.remove('hidden');
    }

    try {
        const params = new URLSearchParams({
            provider_id: formState.provider_id,
            service_id: formState.service_id,
            date: formState.date,
            timezone: getBrowserTimezone()
        });

        const response = await fetch(withBaseUrl(`/api/availability/slots?${params.toString()}`), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        const slots = result.data?.slots || [];

        // Render slot buttons
        renderSlotSuggestions(slotContainer, slots, formState);

    } catch (error) {
        console.error('Error fetching available slots:', error);
        if (slotContainer) {
            slotContainer.innerHTML = '<div class="mt-2 text-sm text-red-600 dark:text-red-400">Unable to load available time slots.</div>';
            slotContainer.classList.remove('hidden');
        }
    }
}

function renderSlotSuggestions(slotContainer, slots, formState) {
    if (!slotContainer) return;

    if (!slots.length) {
        slotContainer.innerHTML = '<div class="mt-2 text-sm text-gray-500 dark:text-gray-400">No available time slots for this day.</div>';
        slotContainer.classList.remove('hidden');
        return;
    }

    // Build buttons
    slotContainer.innerHTML = `
        <div class="mt-2 text-sm text-gray-700 dark:text-gray-300 font-medium">Available time slots</div>
        <div class="mt-2 flex flex-wrap gap-2" role="list">
            ${slots.map(slot => {
                const start = slot.start || slot.startTime?.slice(11, 16);
                const end = slot.end || slot.endTime?.slice(11, 16);
                return `<button type="button" class="slot-btn px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-100 hover:border-blue-500 hover:text-blue-700 dark:hover:text-blue-300" data-slot-start="${start}" title="${start} - ${end}">${start}</button>`;
            }).join('')}
        </div>
    `;
    slotContainer.classList.remove('hidden');

    // Wire buttons
    slotContainer.querySelectorAll('.slot-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const start = btn.dataset.slotStart;
            const timeInput = document.getElementById('appointment_time');
            if (timeInput) {
                timeInput.value = start;
                // Update form state and trigger checks
                formState.time = start;
                timeInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });
}

function clearSlotSuggestions(slotContainer) {
    if (slotContainer) {
        slotContainer.innerHTML = '';
        slotContainer.classList.add('hidden');
    }
}

function syncClientTimezoneFields(form) {
    const timezoneField = form?.querySelector('#client_timezone');
    const offsetField = form?.querySelector('#client_offset');

    if (timezoneField) {
        timezoneField.value = getBrowserTimezone();
    }

    if (offsetField) {
        offsetField.value = getTimezoneOffset();
    }
}

if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            const form = document.querySelector('form[action*="/appointments/store"]');
            if (form) {
                syncClientTimezoneFields(form);
            }
        }
    });
}

/**
 * Show availability checking state
 */
function showAvailabilityChecking(feedbackElement) {
    feedbackElement.textContent = 'Checking availability...';
    feedbackElement.className = 'mt-2 text-sm text-gray-600 dark:text-gray-400';
}

/**
 * Show availability success state
 */
function showAvailabilitySuccess(feedbackElement, message) {
    feedbackElement.innerHTML = `
        <span class="inline-flex items-center">
            <span class="material-symbols-outlined text-base mr-1">check_circle</span>
            ${message}
        </span>
    `;
    feedbackElement.className = 'mt-2 text-sm text-green-600 dark:text-green-400';
}

/**
 * Show availability error state
 */
function showAvailabilityError(feedbackElement, message) {
    feedbackElement.innerHTML = `
        <span class="inline-flex items-center">
            <span class="material-symbols-outlined text-base mr-1">cancel</span>
            ${message}
        </span>
    `;
    feedbackElement.className = 'mt-2 text-sm text-red-600 dark:text-red-400';
}

/**
 * Show availability warning state
 */
function showAvailabilityWarning(feedbackElement, message) {
    feedbackElement.innerHTML = `
        <span class="inline-flex items-center">
            <span class="material-symbols-outlined text-base mr-1">warning</span>
            ${message}
        </span>
    `;
    feedbackElement.className = 'mt-2 text-sm text-amber-600 dark:text-amber-400';
}

/**
 * Create availability feedback element
 */
function createAvailabilityFeedback() {
    const div = document.createElement('div');
    div.className = 'mt-2 text-sm hidden';
    div.setAttribute('role', 'status');
    div.setAttribute('aria-live', 'polite');
    return div;
}

/**
 * Create end time display element
 */
function createEndTimeDisplay() {
    const div = document.createElement('div');
    div.className = 'mt-2 text-sm text-gray-600 dark:text-gray-400 hidden';
    return div;
}

/**
 * Create slot suggestions container
 */
function createSlotSuggestions() {
    const div = document.createElement('div');
    div.className = 'mt-2 text-sm hidden';
    div.setAttribute('aria-live', 'polite');
    return div;
}

/**
 * Validate the appointment form and return validation errors
 * @param {HTMLFormElement} form - The form element
 * @returns {Array} Array of {field, message} objects
 */
function validateAppointmentForm(form) {
    const errors = [];
    
    // Check if in search mode or create mode
    const searchSection = document.getElementById('customer-search-section');
    const createSection = document.getElementById('customer-create-section');
    const isSearchMode = searchSection && !searchSection.classList.contains('hidden');
    const isCreateMode = createSection && !createSection.classList.contains('hidden');
    
    // Customer validation (only in create mode, not edit)
    const customerIdInput = form.querySelector('input[name="customer_id"]');
    const hasSelectedCustomer = customerIdInput && customerIdInput.value;
    
    if (isSearchMode && !hasSelectedCustomer) {
        errors.push({
            field: 'customer_search',
            message: 'Please search and select an existing customer, or switch to "Create New" to enter customer details'
        });
    }
    
    // If in create mode, check required customer fields
    if (isCreateMode && !hasSelectedCustomer) {
        const firstNameInput = document.getElementById('customer_first_name');
        const emailInput = document.getElementById('customer_email');
        
        // Check first name (commonly required)
        if (firstNameInput && firstNameInput.dataset.originalRequired === '1' && !firstNameInput.value.trim()) {
            errors.push({
                field: 'customer_first_name',
                message: 'First name is required'
            });
        }
        
        // Check email (commonly required)
        if (emailInput && emailInput.dataset.originalRequired === '1' && !emailInput.value.trim()) {
            errors.push({
                field: 'customer_email',
                message: 'Email is required'
            });
        }
    }
    
    // Provider validation
    const providerSelect = document.getElementById('provider_id');
    if (!providerSelect || !providerSelect.value) {
        errors.push({
            field: 'provider_id',
            message: 'Please select a service provider'
        });
    }
    
    // Service validation
    const serviceSelect = document.getElementById('service_id');
    if (!serviceSelect || !serviceSelect.value) {
        errors.push({
            field: 'service_id',
            message: 'Please select a service'
        });
    }
    
    // Date validation
    const dateInput = document.getElementById('appointment_date');
    if (!dateInput || !dateInput.value) {
        errors.push({
            field: 'appointment_date',
            message: 'Please select an appointment date'
        });
    }
    
    // Time validation
    const timeInput = document.getElementById('appointment_time');
    if (!timeInput || !timeInput.value) {
        errors.push({
            field: 'appointment_time',
            message: 'Please select an appointment time'
        });
    }
    
    return errors;
}

/**
 * Show validation errors with field highlighting and focus
 * @param {Array} errors - Array of {field, message} objects
 */
function showValidationErrors(errors) {
    if (!errors || errors.length === 0) return;
    
    let firstErrorField = null;
    
    errors.forEach((error, index) => {
        const fieldElement = document.getElementById(error.field);
        if (fieldElement) {
            // Add error styling to field
            fieldElement.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
            fieldElement.classList.remove('border-gray-300', 'dark:border-gray-600');
            
            // Create or update error message element
            let errorElement = document.getElementById(`${error.field}_error`);
            if (!errorElement) {
                errorElement = document.createElement('p');
                errorElement.id = `${error.field}_error`;
                errorElement.className = 'mt-1 text-sm text-red-600 dark:text-red-400 flex items-center gap-1';
                fieldElement.parentNode.appendChild(errorElement);
            }
            errorElement.innerHTML = `
                <span class="material-symbols-outlined text-sm">error</span>
                ${error.message}
            `;
            errorElement.classList.remove('hidden');
            
            // Track first error field for focus
            if (index === 0) {
                firstErrorField = fieldElement;
            }
        }
    });
    
    // Focus and scroll to first error field
    if (firstErrorField) {
        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => {
            firstErrorField.focus();
        }, 300);
    }
    
    // Show summary notification
    showNotification('error', `Please correct ${errors.length} error${errors.length > 1 ? 's' : ''} below`);
}

/**
 * Clear all field errors from the form
 * @param {HTMLFormElement} form - The form element
 */
function clearAllFieldErrors(form) {
    // Remove error styling from all fields
    form.querySelectorAll('.border-red-500').forEach(el => {
        el.classList.remove('border-red-500', 'ring-2', 'ring-red-500/20');
        el.classList.add('border-gray-300', 'dark:border-gray-600');
    });
    
    // Hide all error messages
    form.querySelectorAll('[id$="_error"]').forEach(el => {
        el.classList.add('hidden');
    });
    
    // Remove dynamically created error elements
    document.querySelectorAll('.field-error-dynamic').forEach(el => el.remove());
}

/**
 * Show a notification toast
 * @param {string} type - 'success', 'error', 'info', 'warning'
 * @param {string} message - The message to display
 */
function showNotification(type, message) {
    // Remove any existing notifications
    document.querySelectorAll('.appointment-notification').forEach(n => n.remove());
    
    const colors = {
        success: 'bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 border-green-200 dark:border-green-800',
        error: 'bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 border-red-200 dark:border-red-800',
        warning: 'bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-800',
        info: 'bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800'
    };
    
    const icons = {
        success: 'check_circle',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };
    
    const notification = document.createElement('div');
    notification.className = `appointment-notification fixed top-4 right-4 z-50 max-w-md p-4 rounded-lg shadow-lg border ${colors[type] || colors.info} transform transition-all duration-300 translate-x-full`;
    
    notification.innerHTML = `
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-xl flex-shrink-0">${icons[type] || icons.info}</span>
            <div class="flex-1 text-sm font-medium">${message}</div>
            <button type="button" class="flex-shrink-0 text-current opacity-50 hover:opacity-100 transition-opacity" aria-label="Close">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    requestAnimationFrame(() => {
        notification.classList.remove('translate-x-full');
        notification.classList.add('translate-x-0');
    });
    
    // Close button handler
    const closeBtn = notification.querySelector('button');
    closeBtn.addEventListener('click', () => {
        notification.classList.remove('translate-x-0');
        notification.classList.add('translate-x-full');
        setTimeout(() => notification.remove(), 300);
    });
    
    // Auto-dismiss after 5 seconds (longer for errors)
    const duration = type === 'error' ? 7000 : 5000;
    setTimeout(() => {
        if (notification.parentNode) {
            notification.classList.remove('translate-x-0');
            notification.classList.add('translate-x-full');
            setTimeout(() => notification.remove(), 300);
        }
    }, duration);
}
