/**
 * Appointment Navigation Module
 *
 * Handles appointment creation navigation and form pre-filling.
 * Only sets provider_id in the dropdown — all other field population
 * (date, time, service, slots) is handled by time-slots-ui.js.
 *
 * @module appointments/appointment-navigation
 */
import { withBaseUrl } from '../../utils/url-helpers.js';

/**
 * Navigate to create appointment page with pre-filled slot data
 * @param {Object} slotInfo - Selected slot information from calendar
 */
export function navigateToCreateAppointment(slotInfo) {
    const { start, end, resource } = slotInfo;

    const startDate = new Date(start);
    const appointmentDate = startDate.toISOString().split('T')[0];
    const appointmentTime = startDate.toTimeString().slice(0, 5);

    const params = new URLSearchParams({
        date: appointmentDate,
        time: appointmentTime
    });

    if (resource) {
        params.append('provider_id', resource.id);
    }

    const url = withBaseUrl(`/appointments/create?${params.toString()}`);
    window.location.href = url;
}

/**
 * Pre-fill the provider dropdown from URL parameters.
 * Date, time, and service are handled by time-slots-ui.js.
 */
export function prefillAppointmentForm() {
    const form = document.querySelector('form[action*="/appointments/store"]');
    if (!form) return;

    // Guard: only run once per form
    if (form.dataset.prefilled === 'true') return;
    form.dataset.prefilled = 'true';

    const urlParams = new URLSearchParams(window.location.search);
    const providerId = urlParams.get('provider_id');
    const availableProviders = urlParams.get('available_providers');

    const providerSelect = document.getElementById('provider_id');
    if (!providerSelect) return;

    // Case 1: Specific provider_id in URL (from clicking a provider chip)
    if (providerId) {
        providerSelect.value = providerId;
        providerSelect.dispatchEvent(new Event('change', { bubbles: true }));
        return;
    }

    // Case 2: available_providers list — pick first and mark others as busy
    if (availableProviders) {
        const availableIds = availableProviders
            .split(',')
            .map(id => parseInt(id.trim(), 10))
            .filter(Boolean);

        if (availableIds.length > 0) {
            // Mark unavailable providers
            Array.from(providerSelect.options).forEach(option => {
                if (option.value && !availableIds.includes(parseInt(option.value, 10))) {
                    if (!option.text.includes('(busy')) {
                        option.text += ' (busy at this time)';
                        option.classList.add('text-gray-400');
                    }
                }
            });

            // Auto-select first available
            providerSelect.value = availableIds[0].toString();
            providerSelect.dispatchEvent(new Event('change', { bubbles: true }));
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
