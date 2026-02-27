import { attachTimezoneHeaders, getBrowserTimezone, getTimezoneOffset } from '../../utils/timezone-helper.js';
import { getBaseUrl, withBaseUrl } from '../../utils/url-helpers.js';

/**
 * Appointments Booking Form Module
 *
 * Handles AJAX submission and validation for the appointment booking form.
 * Field population (provider → service cascade, date, time-slot grid) is
 * managed exclusively by time-slots-ui.js — this module does NOT attach
 * its own change listeners to those fields.
 *
 * @module appointments-form
 */

/**
 * Initialize the appointment booking form (submission + validation only).
 */
export async function initAppointmentForm() {
    const form = document.querySelector(
        'form[action*="/appointments/store"], form[action*="/appointments/update"]'
    );
    if (!form) return;

    // Prevent double-init from SPA re-navigation
    if (form.dataset.formSubmitWired === 'true') return;
    form.dataset.formSubmitWired = 'true';

    syncClientTimezoneFields(form);
    attachVisibilityRefresh();

    // ── Form submission handler ───────────────────────────────────────
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        clearAllFieldErrors(form);

        const validationErrors = validateAppointmentForm(form);
        if (validationErrors.length > 0) {
            showValidationErrors(validationErrors);
            return false;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton?.textContent;

        try {
            if (submitButton) {
                submitButton.disabled = true;
                const isUpdate = form.getAttribute('action')?.includes('/update');
                submitButton.textContent = isUpdate ? '⏳ Updating appointment...' : '⏳ Creating appointment...';
            }

            const formData = new FormData(form);
            const formActionUrl = form.getAttribute('action');

            const response = await fetch(formActionUrl, {
                method: 'POST',
                headers: {
                    ...attachTimezoneHeaders(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('[appointments-form] Server error:', errorText);

                if (response.status === 422) {
                    try {
                        const errorData = JSON.parse(errorText);
                        if (errorData.errors) {
                            const errors = Object.entries(errorData.errors).map(
                                ([field, messages]) => ({
                                    field,
                                    message: Array.isArray(messages) ? messages[0] : messages,
                                })
                            );
                            showValidationErrors(errors);
                            return;
                        }
                    } catch (_) {
                        /* fall through */
                    }
                    throw new Error('Please fill in all required fields before submitting.');
                }
                throw new Error(`Server error (${response.status}). Please try again.`);
            }

            const contentType = response.headers.get('content-type');

            if (contentType && contentType.includes('application/json')) {
                const result = await response.json();
                if (result.success || result.data) {
                    showNotification('success', 'Appointment booked successfully!');
                    emitAppointmentsUpdated('create-or-update');
                    setTimeout(() => {
                        const url = withBaseUrl('/appointments');
                        if (window.xsSPA) {
                            window.xsSPA.navigate(url);
                        } else {
                            window.location.href = url;
                        }
                    }, 500);
                } else {
                    throw new Error(result.error || 'Unknown error occurred');
                }
            } else {
                emitAppointmentsUpdated('create-or-update');
                const url = withBaseUrl('/appointments');
                if (window.xsSPA) {
                    window.xsSPA.navigate(url);
                } else {
                    window.location.href = url;
                }
            }
        } catch (error) {
            console.error('[appointments-form] ❌ Submission error:', error);
            showNotification('error', error.message || 'Failed to create appointment. Please try again.');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            }
        }

        return false;
    });
}

// ── Helpers ───────────────────────────────────────────────────────────

function emitAppointmentsUpdated(action) {
    if (typeof window === 'undefined') return;
    const detail = { source: 'appointment-form', action };
    if (typeof window.emitAppointmentsUpdated === 'function') {
        window.emitAppointmentsUpdated(detail);
    } else {
        window.dispatchEvent(new CustomEvent('appointments-updated', { detail }));
    }
}

function syncClientTimezoneFields(form) {
    const tzField = form?.querySelector('#client_timezone');
    const offsetField = form?.querySelector('#client_offset');
    if (tzField) tzField.value = getBrowserTimezone();
    if (offsetField) offsetField.value = getTimezoneOffset();
}

function attachVisibilityRefresh() {
    if (typeof document === 'undefined') return;
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            const form = document.querySelector('form[action*="/appointments/store"]');
            if (form) syncClientTimezoneFields(form);
        }
    }, { once: false });
}

// ── Validation ────────────────────────────────────────────────────────

function validateAppointmentForm(form) {
    const errors = [];

    const searchSection = document.getElementById('customer-search-section');
    const createSection = document.getElementById('customer-create-section');
    const isSearchMode = searchSection && !searchSection.classList.contains('hidden');
    const isCreateMode = createSection && !createSection.classList.contains('hidden');

    const customerIdInput = form.querySelector('input[name="customer_id"]');
    const hasSelectedCustomer = customerIdInput && customerIdInput.value;

    if (isSearchMode && !hasSelectedCustomer) {
        errors.push({
            field: 'customer_search',
            message: 'Please search and select an existing customer, or switch to "Create New"',
        });
    }

    if (isCreateMode && !hasSelectedCustomer) {
        const firstNameInput = document.getElementById('customer_first_name');
        const emailInput = document.getElementById('customer_email');
        if (firstNameInput && firstNameInput.dataset.originalRequired === '1' && !firstNameInput.value.trim()) {
            errors.push({ field: 'customer_first_name', message: 'First name is required' });
        }
        if (emailInput && emailInput.dataset.originalRequired === '1' && !emailInput.value.trim()) {
            errors.push({ field: 'customer_email', message: 'Email is required' });
        }
    }

    if (!document.getElementById('provider_id')?.value) {
        errors.push({ field: 'provider_id', message: 'Please select a service provider' });
    }
    if (!document.getElementById('service_id')?.value) {
        errors.push({ field: 'service_id', message: 'Please select a service' });
    }
    if (!document.getElementById('appointment_date')?.value) {
        errors.push({ field: 'appointment_date', message: 'Please select an appointment date' });
    }
    if (!document.getElementById('appointment_time')?.value) {
        errors.push({ field: 'appointment_time', message: 'Please select an appointment time' });
    }

    return errors;
}

function showValidationErrors(errors) {
    if (!errors?.length) return;

    let firstErrorField = null;

    errors.forEach((error, index) => {
        const fieldElement = document.getElementById(error.field);
        if (fieldElement) {
            fieldElement.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
            fieldElement.classList.remove('border-gray-300', 'dark:border-gray-600');

            let errorEl = document.getElementById(`${error.field}_error`);
            if (!errorEl) {
                errorEl = document.createElement('p');
                errorEl.id = `${error.field}_error`;
                errorEl.className = 'mt-1 text-sm text-red-600 dark:text-red-400 flex items-center gap-1 field-error-dynamic';
                fieldElement.parentNode.appendChild(errorEl);
            }
            errorEl.innerHTML = `<span class="material-symbols-outlined text-sm">error</span>${error.message}`;
            errorEl.classList.remove('hidden');

            if (index === 0) firstErrorField = fieldElement;
        }
    });

    if (firstErrorField) {
        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => firstErrorField.focus(), 300);
    }

    showNotification('error', `Please correct ${errors.length} error${errors.length > 1 ? 's' : ''} below`);
}

function clearAllFieldErrors(form) {
    form.querySelectorAll('.border-red-500').forEach((el) => {
        el.classList.remove('border-red-500', 'ring-2', 'ring-red-500/20');
        el.classList.add('border-gray-300', 'dark:border-gray-600');
    });
    form.querySelectorAll('[id$="_error"]').forEach((el) => el.classList.add('hidden'));
    document.querySelectorAll('.field-error-dynamic').forEach((el) => el.remove());
}

// ── Notifications ─────────────────────────────────────────────────────

function showNotification(type, message) {
    // Clean up any legacy notification elements
    document.querySelectorAll('.appointment-notification').forEach((n) => n.remove());
    // Delegate to unified xs:flash system
    document.dispatchEvent(new CustomEvent('xs:flash', { detail: { type, message } }));
}
