/**
 * Appointment Navigation Module
 *
 * Handles appointment creation navigation and form pre-filling.
 * Manages URL parameters for pre-populated form fields.
 *
 * @module appointments/appointment-navigation
 */

/**
 * Navigate to create appointment page with pre-filled slot data
 * @param {Object} slotInfo - Selected slot information from calendar
 */
export function navigateToCreateAppointment(slotInfo) {
    const { start, end, resource } = slotInfo;

    // Format date and time from the selected slot
    const startDate = new Date(start);
    const appointmentDate = startDate.toISOString().split('T')[0]; // YYYY-MM-DD
    const appointmentTime = startDate.toTimeString().slice(0, 5); // HH:MM

    // Build URL with query parameters
    const params = new URLSearchParams({
        date: appointmentDate,
        time: appointmentTime
    });

    // Add provider ID if available (from resource or event)
    if (resource) {
        params.append('provider_id', resource.id);
    }

    // Navigate to create page
    const url = `/appointments/create?${params.toString()}`;
    window.location.href = url;
}

/**
 * Pre-fill appointment form with URL parameters
 * Supports: date, time, provider_id, available_providers (from scheduler slot booking)
 */
export function prefillAppointmentForm() {
    // Only run on create appointment page
    const form = document.querySelector('form[action*="/appointments/store"]');
    if (!form) return;

    // Parse URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const date = urlParams.get('date');
    const time = urlParams.get('time');
    const providerId = urlParams.get('provider_id');
    const availableProviders = urlParams.get('available_providers'); // Comma-separated IDs from scheduler

    // Pre-fill date field
    if (date) {
        const dateInput = document.getElementById('appointment_date');
        if (dateInput) {
            dateInput.value = date;

            // Trigger change event to update form state
            dateInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // Pre-fill time field
    if (time) {
        const timeInput = document.getElementById('appointment_time');
        if (timeInput) {
            timeInput.value = time;

            // Trigger change event to update form state
            timeInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // Pre-select provider if specified
    if (providerId) {
        const providerSelect = document.getElementById('provider_id');
        if (providerSelect) {
            providerSelect.value = providerId;

            // Trigger change event to load services
            providerSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // If available_providers is specified (from scheduler slot), filter provider dropdown
    // and auto-select the first available provider if no specific provider was chosen
    if (availableProviders && !providerId) {
        const availableIds = availableProviders.split(',').map(id => parseInt(id.trim(), 10));
        const providerSelect = document.getElementById('provider_id');

        if (providerSelect && availableIds.length > 0) {
            // Mark unavailable providers in the dropdown (optional visual indicator)
            Array.from(providerSelect.options).forEach(option => {
                if (option.value && !availableIds.includes(parseInt(option.value, 10))) {
                    // Add visual indicator that this provider is busy at this time
                    if (!option.text.includes('(busy)')) {
                        option.text += ' (busy at this time)';
                        option.classList.add('text-gray-400');
                    }
                }
            });

            // Auto-select the first available provider
            const firstAvailable = availableIds[0];
            if (firstAvailable) {
                providerSelect.value = firstAvailable.toString();

                // Trigger change event to load services
                providerSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }
}

/**
 * Handle appointment click - open details modal
 * @param {object} appointment - Appointment object
 */
export function handleAppointmentClick(appointment) {
    // Open the appointment details modal
    if (window.scheduler?.appointmentDetailsModal) {
        window.scheduler.appointmentDetailsModal.open(appointment);
    } else {
        console.error('[app.js] Appointment details modal not available');
    }
}
