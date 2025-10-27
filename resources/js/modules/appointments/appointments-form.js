import { attachTimezoneHeaders, getBrowserTimezone, getTimezoneOffset } from '/resources/js/utils/timezone-helper.js';

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
 */
export async function initAppointmentForm() {
    const form = document.querySelector('form[action*="/appointments/store"]');
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
    });

    // Event: Date changes → Check availability
    dateInput.addEventListener('change', function() {
        formState.date = this.value;
        checkAvailability(formState, availabilityFeedback);
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
        
        if (formState.isAvailable === false) {
            alert('This time slot is not available. Please choose a different time.');
            return false;
        }

        if (formState.isChecking) {
            alert('Please wait while we check availability...');
            return false;
        }
        
        // AJAX form submission
        console.log('[appointments-form] ========== FORM SUBMISSION START ==========');
        console.log('[appointments-form] Form data being submitted:', {
            provider_id: formState.provider_id,
            service_id: formState.service_id,
            date: formState.date,
            time: formState.time,
            duration: formState.duration,
            timezone: getBrowserTimezone(),
            offset: getTimezoneOffset()
        });
        
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.textContent;
        
        try {
            // Disable submit button and show loading state
            submitButton.disabled = true;
            submitButton.textContent = '⏳ Creating appointment...';
            
            // Submit form via AJAX
            const formData = new FormData(form);
            
            console.log('[appointments-form] Sending POST request to:', form.action);
            
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    ...attachTimezoneHeaders(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            
            console.log('[appointments-form] Server response status:', response.status);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('[appointments-form] Server error response:', errorText);
                throw new Error(`Server returned ${response.status}`);
            }
            
            // Check if response is JSON (API) or HTML (redirect)
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                const result = await response.json();
                console.log('[appointments-form] JSON response:', result);
                
                if (result.success || result.data) {
                    console.log('[appointments-form] ✅ Appointment created successfully!');
                    console.log('[appointments-form] Appointment ID:', result.data?.id || result.id);
                    
                    // Show success message
                    alert('✅ Appointment booked successfully!');
                    
                    // Redirect to appointments page so calendar can refresh
                    console.log('[appointments-form] Redirecting to /appointments...');
                    window.location.href = '/appointments';
                } else {
                    throw new Error(result.error || 'Unknown error occurred');
                }
            } else {
                // HTML response (redirect) - follow it
                console.log('[appointments-form] ✅ Appointment created (redirect response)');
                console.log('[appointments-form] Redirecting to /appointments...');
                window.location.href = '/appointments';
            }
            
        } catch (error) {
            console.error('[appointments-form] ❌ Form submission error:', error);
            alert('❌ Failed to create appointment: ' + error.message);
            
            // Re-enable submit button
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        }
        
        console.log('[appointments-form] ========================================');
        
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

        const response = await fetch(`/api/v1/providers/${providerId}/services`, {
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
                option.textContent = `${service.name} - ${service.duration} min - $${parseFloat(service.price).toFixed(2)}`;
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
    if (!formState.provider_id || !formState.service_id || !formState.date || !formState.time) {
        clearAvailabilityCheck(feedbackElement);
        return;
    }

    formState.isChecking = true;
    showAvailabilityChecking(feedbackElement);

    try {
        // Combine date and time into ISO format start_time
        const startTime = `${formState.date} ${formState.time}:00`;
        
        const response = await fetch('/api/appointments/check-availability', {
            method: 'POST',
            headers: {
                ...attachTimezoneHeaders(),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                provider_id: parseInt(formState.provider_id),
                service_id: parseInt(formState.service_id),
                start_time: startTime,
                timezone: getBrowserTimezone(),
                offset: getTimezoneOffset()
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        
        formState.isAvailable = data.available === true;
        
        if (formState.isAvailable) {
            showAvailabilitySuccess(feedbackElement, 'Time slot available');
        } else {
            // Build detailed error message
            let message = 'Time slot not available';
            
            if (data.businessHoursViolation) {
                message = data.businessHoursViolation;
            } else if (data.conflicts && data.conflicts.length > 0) {
                message = `Conflicts with ${data.conflicts.length} existing appointment(s)`;
            } else if (data.blockedTimeConflicts > 0) {
                message = 'Time slot is blocked';
            }
            
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
