import { getBaseUrl, withBaseUrl } from '../../utils/url-helpers.js';

function getAppointmentForm() {
    return document.querySelector('[data-appointment-form="true"]')
        || document.querySelector('form[action*="/appointments/store"], form[action*="/appointments/update"]');
}

function getBrowserTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
}

function getTimezoneOffset() {
    return new Date().getTimezoneOffset();
}

function attachTimezoneHeaders() {
    return {
        'X-Client-Timezone': getBrowserTimezone(),
        'X-Client-Offset': String(getTimezoneOffset()),
    };
}

function getCsrfContext(form) {
    const csrfInput = form?.querySelector('input[name]');
    const namedInput = form?.querySelector('input[name="csrf_test_name"]')
        || form?.querySelector('input[name^="csrf_"]');
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const headerName = document.querySelector('meta[name="csrf-header"]')?.getAttribute('content') || 'X-CSRF-TOKEN';
    const tokenName = namedInput?.name || 'csrf_test_name';
    const tokenValue = metaToken || namedInput?.value || window.__CSRF_TOKEN__ || '';

    return {
        headerName,
        tokenName,
        tokenValue,
        input: namedInput || csrfInput || null,
    };
}

function syncCsrfIntoForm(form) {
    const csrf = getCsrfContext(form);
    if (csrf.input && csrf.tokenValue) {
        csrf.input.value = csrf.tokenValue;
    }

    return csrf;
}

function updateCsrfFromResponse(response, form) {
    const nextToken = response?.headers?.get('X-CSRF-TOKEN') || response?.headers?.get('x-csrf-token');
    if (!nextToken) {
        return;
    }

    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        metaToken.setAttribute('content', nextToken);
    }

    window.__CSRF_TOKEN__ = nextToken;

    const csrf = getCsrfContext(form);
    if (csrf.input) {
        csrf.input.value = nextToken;
    }
}

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
 * Initialize the appointment booking form.
 */
export async function initAppointmentForm() {
    const form = getAppointmentForm();
    if (!form) return;

    initializeAppointmentFormUi(form);

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
        const originalButtonHtml = submitButton?.innerHTML;

        try {
            if (submitButton) {
                submitButton.disabled = true;
                const isUpdate = form.getAttribute('action')?.includes('/update');
                submitButton.innerHTML = `<span>${isUpdate ? 'Updating appointment...' : 'Creating appointment...'}</span>`;
            }

            const formData = new FormData(form);
            const formActionUrl = form.getAttribute('action');
            const csrf = syncCsrfIntoForm(form);

            if (csrf.tokenValue) {
                formData.set(csrf.tokenName, csrf.tokenValue);
            }

            const response = await fetch(formActionUrl, {
                method: 'POST',
                headers: {
                    ...attachTimezoneHeaders(),
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrf.tokenValue ? { [csrf.headerName]: csrf.tokenValue } : {}),
                },
                credentials: 'same-origin',
                body: formData,
            });

            updateCsrfFromResponse(response, form);

            if (!response.ok) {
                const errorPayload = await parseErrorPayload(response);

                if (response.status === 403) {
                    throw new Error("You don't have permission to perform this action.");
                }

                if (errorPayload?.errors || errorPayload?.message || response.status === 409) {
                    const errors = normalizeServerErrors(errorPayload, response.status);
                    if (errors.length > 0) {
                        showValidationErrors(errors);
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalButtonHtml;
                        }
                        return;
                    }

                    throw new Error(errorPayload?.message || 'Unable to save the appointment.');
                }

                throw new Error(`Server error (${response.status}). Please try again.`);
            }

            const contentType = response.headers.get('content-type');

            if (contentType && contentType.includes('application/json')) {
                const result = await response.json();
                if (result.success || result.data) {
                    showNotification('success', 'Appointment booked successfully!');
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
                submitButton.innerHTML = originalButtonHtml;
            }
        }

        return false;
    });
}

function initializeAppointmentFormUi(form) {
    if (form.dataset.appointmentUiWired === 'true') {
        return;
    }

    const isEditMode = form.dataset.isEditMode === '1';
    const expectsTimeSlots = form.dataset.expectsTimeSlots === '1';
    const supportsTimeSlots = Boolean(document.getElementById('time-slots-grid'));

    if (expectsTimeSlots && !supportsTimeSlots) {
        const retryCount = Number(form.dataset.initRetryCount || '0');
        if (retryCount < 6) {
            form.dataset.initRetryCount = String(retryCount + 1);
            setTimeout(() => initializeAppointmentFormUi(form), 50);
            return;
        }
    }

    form.dataset.appointmentUiWired = 'true';
    delete form.dataset.initRetryCount;

    syncClientTimezoneFields(form);

    const currencySymbol = form.dataset.currencySymbol || '';
    window.appCurrencySymbol = currencySymbol;

    const updateSummary = createSummaryUpdater(form);
    initTimeSlots(form, isEditMode, expectsTimeSlots, supportsTimeSlots, updateSummary);

    if (!isEditMode) {
        initCustomerModeControls(form);
        updateSummary();
    }
}

function initTimeSlots(form, isEditMode, expectsTimeSlots, supportsTimeSlots, updateSummary) {
    if (form.dataset.timeSlotsWired === 'true') {
        return;
    }

    if (supportsTimeSlots && window.initTimeSlotsUI) {
        window.initTimeSlotsUI({
            providerSelectId: 'provider_id',
            serviceSelectId: 'service_id',
            locationSelectId: 'location_id',
            dateInputId: 'appointment_date',
            timeInputId: 'appointment_time',
            excludeAppointmentId: form.dataset.excludeAppointmentId || null,
            preselectServiceId: form.dataset.preselectServiceId || '',
            preselectLocationId: form.dataset.preselectLocationId || '',
            initialTime: form.dataset.initialTime || '',
            onTimeSelected: isEditMode ? null : () => updateSummary(),
        });
        form.dataset.timeSlotsWired = 'true';
        return;
    }

    if (expectsTimeSlots && !supportsTimeSlots) {
        console.warn('[Appointment Form] Time slots UI unavailable after retries');
    } else if (expectsTimeSlots) {
        console.error('[Appointment Form] initTimeSlotsUI not available');
    }
}

function initCustomerModeControls(form) {
    if (form.dataset.customerModeWired === 'true') {
        return;
    }

    const customerSearchInput = document.getElementById('customer_search');
    const customerSearchResults = document.getElementById('customer_search_results');
    const customerSearchSpinner = document.getElementById('customer_search_spinner');
    const selectedCustomerDisplay = document.getElementById('selected_customer_display');
    const clearCustomerBtn = document.getElementById('clear_customer_btn');
    const customerSearchSection = document.getElementById('customer-search-section');
    const customerCreateSection = document.getElementById('customer-create-section');
    const selectedCustomerIdInput = document.getElementById('selected_customer_id');
    const clearSearchBtn = document.getElementById('clear_search_btn');
    const searchUrl = form.dataset.searchUrl || `${getBaseUrl().replace(/\/$/, '')}/dashboard/search`;
    const escapeHtml = window.xsEscapeHtml || ((value) => String(value ?? ''));

    let searchTimeout = null;
    let selectedCustomer = null;

    const clearCustomerFormFields = () => {
        ['customer_first_name', 'customer_last_name', 'customer_email', 'customer_phone', 'customer_address'].forEach((fieldId) => {
            const field = document.getElementById(fieldId);
            if (field) field.value = '';
        });
    };

    const enableCustomerFieldValidation = () => {
        document.querySelectorAll('[data-original-required="1"]').forEach((field) => {
            field.required = true;
        });
    };

    const disableCustomerFieldValidation = () => {
        document.querySelectorAll('[data-original-required]').forEach((field) => {
            field.required = false;
        });
    };

    const clearCustomerSelection = () => {
        selectedCustomer = null;
        if (selectedCustomerIdInput) {
            selectedCustomerIdInput.value = '';
        }
        if (selectedCustomerDisplay) {
            selectedCustomerDisplay.classList.add('hidden');
            selectedCustomerDisplay.classList.remove('flex');
        }
        if (customerSearchInput) {
            customerSearchInput.value = '';
        }
        customerSearchResults?.classList.add('hidden');
        clearSearchBtn?.classList.add('hidden');
    };

    const selectCustomer = (customer) => {
        selectedCustomer = customer;

        const fullName = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || 'Unknown Customer';
        const initial = fullName.substring(0, 1).toUpperCase();
        const email = customer.email || '';
        const phone = customer.phone || customer.phone_number || '';

        const avatarEl = document.getElementById('selected_customer_avatar');
        const nameEl = document.getElementById('selected_customer_name');
        const emailEl = document.getElementById('selected_customer_email');
        const phoneEl = document.getElementById('selected_customer_phone');

        if (avatarEl) avatarEl.textContent = initial;
        if (nameEl) nameEl.textContent = fullName;
        if (emailEl) emailEl.textContent = email || 'No email provided';
        if (phoneEl) phoneEl.textContent = phone || 'No phone provided';
        if (selectedCustomerIdInput) {
            selectedCustomerIdInput.value = customer.id || customer.customer_id || customer.hash || '';
        }

        if (selectedCustomerDisplay) {
            selectedCustomerDisplay.classList.remove('hidden');
            selectedCustomerDisplay.classList.add('flex');
        }

        customerSearchResults?.classList.add('hidden');
        if (customerSearchInput) {
            customerSearchInput.value = '';
        }
        clearSearchBtn?.classList.add('hidden');
        clearCustomerFormFields();
    };

    const displayCustomerResults = (customers, query) => {
        if (!customerSearchResults) {
            return;
        }

        if (customers.length === 0) {
            customerSearchResults.innerHTML = `<div class="p-4 text-center text-gray-500 dark:text-gray-400"><span class="material-symbols-outlined text-3xl mb-2 block opacity-50">person_search</span><p class="text-sm font-medium mb-1">No customers found</p><p class="text-xs opacity-75">for "${escapeHtml(query)}"</p></div>`;
            customerSearchResults.classList.remove('hidden');
            return;
        }

        const resultsHTML = customers.slice(0, 5).map((customer) => {
            const fullName = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || 'Unknown Customer';
            const initial = fullName.substring(0, 1).toUpperCase();
            const email = customer.email || '';
            const phone = customer.phone || customer.phone_number || '';
            const contactInfo = [email, phone].filter(Boolean).join(' • ');

            return `<button type="button" class="customer-result-item w-full text-left px-4 py-3 hover:bg-blue-50 dark:hover:bg-blue-900/20 flex items-center gap-3 transition-colors border-b border-gray-100 dark:border-gray-700 last:border-0" data-customer='${JSON.stringify(customer).replace(/'/g, '&#39;')}'><div class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 text-white font-semibold text-sm flex-shrink-0">${escapeHtml(initial)}</div><div class="flex-1 min-w-0"><p class="text-sm font-semibold text-gray-900 dark:text-white truncate">${escapeHtml(fullName)}</p><p class="text-xs text-gray-600 dark:text-gray-400 truncate">${escapeHtml(contactInfo || 'No contact info')}</p></div><span class="material-symbols-outlined text-gray-400 text-sm">chevron_right</span></button>`;
        }).join('');

        customerSearchResults.innerHTML = `<div class="divide-y divide-gray-100 dark:divide-gray-700">${resultsHTML}</div>`;
        customerSearchResults.classList.remove('hidden');

        customerSearchResults.querySelectorAll('.customer-result-item').forEach((item) => {
            item.addEventListener('click', (event) => {
                event.preventDefault();
                const customerData = JSON.parse(item.dataset.customer.replace(/&#39;/g, "'"));
                selectCustomer(customerData);
            });
        });
    };

    document.querySelectorAll('.customer-mode-btn').forEach((button) => {
        if (button.dataset.customerModeBound === 'true') {
            return;
        }

        button.addEventListener('click', function () {
            const mode = this.dataset.customerMode;

            document.querySelectorAll('.customer-mode-btn').forEach((candidate) => {
                if (candidate.dataset.customerMode === mode) {
                    candidate.classList.add('bg-white', 'dark:bg-gray-600', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                    candidate.classList.remove('text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
                } else {
                    candidate.classList.remove('bg-white', 'dark:bg-gray-600', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                    candidate.classList.add('text-gray-700', 'dark:text-gray-300', 'hover:bg-gray-100', 'dark:hover:bg-gray-700');
                }
            });

            if (mode === 'search') {
                customerSearchSection?.classList.remove('hidden');
                customerCreateSection?.classList.add('hidden');
                disableCustomerFieldValidation();
                if (selectedCustomer) {
                    clearCustomerFormFields();
                }
            } else {
                customerSearchSection?.classList.add('hidden');
                customerCreateSection?.classList.remove('hidden');
                enableCustomerFieldValidation();
                clearCustomerSelection();
            }
        });

        button.dataset.customerModeBound = 'true';
    });

    if (customerSearchInput && customerSearchInput.dataset.searchBound !== 'true') {
        if (document.body?.dataset.customerSearchDismissBound !== 'true') {
            document.addEventListener('click', (event) => {
                const activeSearchInput = document.getElementById('customer_search');
                const activeSearchResults = document.getElementById('customer_search_results');

                if (!activeSearchInput || !activeSearchResults) {
                    return;
                }

                if (!activeSearchInput.contains(event.target)
                    && !activeSearchResults.contains(event.target)
                    && !event.target.closest('#customer-search-section')) {
                    activeSearchResults.classList.add('hidden');
                }
            });
            document.body.dataset.customerSearchDismissBound = 'true';
        }

        clearSearchBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            customerSearchInput.value = '';
            customerSearchResults?.classList.add('hidden');
            clearSearchBtn.classList.add('hidden');
            customerSearchInput.focus();
        });

        customerSearchInput.addEventListener('input', function () {
            const query = this.value.trim();

            if (clearSearchBtn) {
                clearSearchBtn.classList.toggle('hidden', query.length === 0);
            }

            if (searchTimeout) clearTimeout(searchTimeout);

            if (query.length < 2) {
                customerSearchResults?.classList.add('hidden');
                customerSearchSpinner?.classList.add('hidden');
                return;
            }

            customerSearchSpinner?.classList.remove('hidden');
            if (customerSearchResults) {
                customerSearchResults.innerHTML = `<div class="p-4 text-center text-gray-500 dark:text-gray-400"><div class="inline-block animate-spin rounded-full h-6 w-6 border-2 border-blue-600 border-t-transparent mb-2"></div><p class="text-sm">Searching customers...</p></div>`;
                customerSearchResults.classList.remove('hidden');
            }

            searchTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`);
                    if (!response.ok) throw new Error('Search failed');

                    const text = await response.text();
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch {
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
                    if (customerSearchResults) {
                        customerSearchResults.innerHTML = `<div class="p-4 text-center text-red-600 dark:text-red-400"><span class="material-symbols-outlined text-2xl mb-2 block">error</span><p class="text-sm font-medium mb-1">Search failed</p><p class="text-xs opacity-75">Please check your connection and try again</p></div>`;
                        customerSearchResults.classList.remove('hidden');
                    }
                } finally {
                    customerSearchSpinner?.classList.add('hidden');
                }
            }, 300);
        });

        customerSearchInput.dataset.searchBound = 'true';
    }

    if (clearCustomerBtn && clearCustomerBtn.dataset.customerClearBound !== 'true') {
        clearCustomerBtn.addEventListener('click', (event) => {
            event.preventDefault();
            clearCustomerSelection();
        });
        clearCustomerBtn.dataset.customerClearBound = 'true';
    }

    disableCustomerFieldValidation();
    form.dataset.customerModeWired = 'true';
}

function createSummaryUpdater(form) {
    const serviceSelect = document.getElementById('service_id');
    const providerSelect = document.getElementById('provider_id');
    const locationSelect = document.getElementById('location_id');
    const dateInput = document.getElementById('appointment_date');
    const timeInput = document.getElementById('appointment_time');
    const summaryDiv = document.getElementById('appointment-summary');
    const currencySymbol = form.dataset.currencySymbol || '';
    const timeFormat = form.dataset.timeFormat || '12h';

    const updateSummary = () => {
        if (!summaryDiv || !serviceSelect || !providerSelect || !dateInput || !timeInput) {
            return;
        }

        const service = serviceSelect.options[serviceSelect.selectedIndex];
        const provider = providerSelect.options[providerSelect.selectedIndex];
        const date = dateInput.value;
        const time = timeInput.value;

        if (serviceSelect.value && providerSelect.value && date && time) {
            summaryDiv.classList.remove('hidden');

            document.getElementById('summary-service').textContent = service?.text.split(' - ')[0] || '-';
            document.getElementById('summary-provider').textContent = provider?.text.split(' - ')[0] || '-';

            const locLabel = document.getElementById('summary-location-label');
            const locValue = document.getElementById('summary-location');
            if (locationSelect && locationSelect.value) {
                const locText = locationSelect.options[locationSelect.selectedIndex]?.text || '';
                locLabel?.classList.remove('hidden');
                if (locValue) {
                    locValue.textContent = locText;
                    locValue.classList.remove('hidden');
                }
            } else {
                locLabel?.classList.add('hidden');
                locValue?.classList.add('hidden');
            }

            const summaryDateTime = document.getElementById('summary-datetime');
            if (summaryDateTime) {
                const dateTime = new Date(`${date}T${time}`);
                summaryDateTime.textContent = dateTime.toLocaleString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: timeFormat === '12h',
                });
            }

            document.getElementById('summary-duration').textContent = service?.dataset.duration
                ? `${service.dataset.duration} minutes`
                : '-';
            document.getElementById('summary-price').textContent = service?.dataset.price
                ? (window.currencyFormatter?.format?.(service.dataset.price) || `${currencySymbol}${parseFloat(service.dataset.price).toFixed(2)}`)
                : '-';
        } else {
            summaryDiv.classList.add('hidden');
        }
    };

    if (serviceSelect && serviceSelect.dataset.summaryBound !== 'true') {
        serviceSelect.addEventListener('change', updateSummary);
        serviceSelect.dataset.summaryBound = 'true';
    }
    if (providerSelect && providerSelect.dataset.summaryBound !== 'true') {
        providerSelect.addEventListener('change', updateSummary);
        providerSelect.dataset.summaryBound = 'true';
    }
    if (dateInput && dateInput.dataset.summaryBound !== 'true') {
        dateInput.addEventListener('change', updateSummary);
        dateInput.dataset.summaryBound = 'true';
    }
    if (locationSelect && locationSelect.dataset.summaryBound !== 'true') {
        locationSelect.addEventListener('change', updateSummary);
        locationSelect.dataset.summaryBound = 'true';
    }
    if (timeInput && timeInput.dataset.summaryBound !== 'true') {
        timeInput.addEventListener('change', updateSummary);
        timeInput.dataset.summaryBound = 'true';
    }

    return updateSummary;
}

// ── Helpers ───────────────────────────────────────────────────────────

function syncClientTimezoneFields(form) {
    const tzField = form?.querySelector('#client_timezone');
    const offsetField = form?.querySelector('#client_offset');
    if (tzField) tzField.value = getBrowserTimezone();
    if (offsetField) offsetField.value = getTimezoneOffset();
}

function attachVisibilityRefresh() {
    if (typeof document === 'undefined') return;
    
    // Guard: prevent listener accumulation on SPA navigation
    if (document.body?.dataset.visibilityRefreshBound === 'true') return;
    if (document.body) document.body.dataset.visibilityRefreshBound = 'true';
    
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            const form = getAppointmentForm();
            if (form) syncClientTimezoneFields(form);
        }
    }, { once: false });
}

async function parseErrorPayload(response) {
    const contentType = response.headers.get('content-type') || '';
    const errorText = await response.text();

    if (!errorText) {
        return null;
    }

    if (contentType.includes('application/json')) {
        try {
            return JSON.parse(errorText);
        } catch (_) {
            console.warn('[appointments-form] Failed to parse JSON error payload');
        }
    }

    console.error('[appointments-form] Server error:', errorText);
    return null;
}

function normalizeServerErrors(payload, statusCode) {
    if (!payload) {
        return [];
    }

    if (payload.errors && !Array.isArray(payload.errors)) {
        return Object.entries(payload.errors).map(([field, messages]) => ({
            field,
            message: Array.isArray(messages) ? messages[0] : messages,
        }));
    }

    if (Array.isArray(payload.errors) && payload.errors.length > 0) {
        const message = payload.errors[0]?.message || payload.message;
        return [{ field: 'appointment_time', message }].filter((error) => Boolean(error.message));
    }

    if (statusCode === 409 || Array.isArray(payload.conflicts)) {
        return [{
            field: 'appointment_time',
            message: payload.message || 'This time slot conflicts with an existing appointment.',
        }];
    }

    if (payload.message && statusCode === 422) {
        return [{ field: 'appointment_time', message: payload.message }];
    }

    return [];
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
