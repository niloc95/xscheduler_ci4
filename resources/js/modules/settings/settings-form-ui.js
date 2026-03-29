const GENERAL_FIELD_MAP = {
    company_name: 'general.company_name',
    company_email: 'general.company_email',
    company_link: 'general.company_link',
    telephone_number: 'general.telephone_number',
    mobile_number: 'general.mobile_number',
    business_address: 'general.business_address',
};

const SETTINGS_TABS = ['localization', 'booking', 'business', 'legal', 'integrations'];

function ensureGlobalHelpers() {
    window.xsDebugLog = window.xsDebugLog || function (...args) {
        try {
            if (window.appConfig && window.appConfig.debug) {
                console.log(...args);
            }
        } catch (_) {
            // no-op
        }
    };

    window.xsGetCsrf = window.xsGetCsrf || function () {
        const header = document.querySelector('meta[name="csrf-header"]')?.getAttribute('content') || 'X-CSRF-TOKEN';
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        return { header, token };
    };
}

function debugLog(...args) {
    if (typeof window.xsDebugLog === 'function') {
        window.xsDebugLog(...args);
    }
}

function updateCsrfFromResponse(response, csrfInput = null) {
    if (!response) {
        return;
    }

    const newToken = response.headers.get('X-CSRF-TOKEN') || response.headers.get('x-csrf-token');
    if (!newToken) {
        return;
    }

    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        metaToken.setAttribute('content', newToken);
    }

    if (csrfInput) {
        csrfInput.value = newToken;
    }

    document.querySelectorAll('input[type="hidden"][name*="csrf"]').forEach((input) => {
        input.value = newToken;
    });
}

function showToast(type, title, message, autoClose = type !== 'error') {
    if (window.XSNotify?.toast) {
        window.XSNotify.toast({
            type,
            title,
            message,
            autoClose,
            duration: autoClose ? 4000 : undefined,
        });
        return;
    }

    window.alert(message);
}

function getApiMessage(payload, fallbackMessage) {
    if (typeof payload?.error?.message === 'string' && payload.error.message.trim() !== '') {
        return payload.error.message;
    }

    if (typeof payload?.message === 'string' && payload.message.trim() !== '') {
        return payload.message;
    }

    if (typeof payload?.data?.message === 'string' && payload.data.message.trim() !== '') {
        return payload.data.message;
    }

    return fallbackMessage;
}

function isApiFailure(payload) {
    return Boolean(payload?.error) || payload?.ok === false;
}

function getApiData(payload) {
    return payload?.data ?? payload;
}

async function buildHttpError(response, fallbackMessage) {
    let errorId = '';

    try {
        const payload = await response.json();
        errorId = payload?.error?.error_id || payload?.error_id || '';
    } catch (_) {
        // ignore non-JSON error responses
    }

    return `${fallbackMessage} (HTTP ${response.status})${errorId ? ` [Error ID: ${errorId}]` : ''}`;
}

function getCsrf(csrfInput = null) {
    const base = typeof window.xsGetCsrf === 'function' ? window.xsGetCsrf() : {};
    return {
        header: base.header || window.appConfig?.csrfHeaderName || 'X-CSRF-TOKEN',
        token: base.token || window.appConfig?.csrfToken || csrfInput?.value || '',
    };
}

function previewImage(imgEl, file) {
    if (!imgEl || !file || !file.type?.startsWith('image/')) {
        return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
        imgEl.src = event.target?.result || '';
        imgEl.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

function wireWhatsAppProviderToggle() {
    const providerSelect = document.getElementById('whatsapp_provider');
    if (!providerSelect || providerSelect.dataset.toggleWired === 'true') {
        return;
    }

    providerSelect.dataset.toggleWired = 'true';

    const sections = {
        link_generator: document.getElementById('wa_link_generator_section'),
        twilio: document.getElementById('wa_twilio_section'),
        meta_cloud: document.getElementById('wa_meta_section'),
    };

    const updateSections = () => {
        const selected = providerSelect.value;
        Object.keys(sections).forEach((key) => {
            if (sections[key]) {
                sections[key].classList.toggle('hidden', key !== selected);
            }
        });
    };

    providerSelect.addEventListener('change', updateSections);
    updateSections();
}

function wireTemplateTabs() {
    const templateTabs = document.querySelectorAll('.template-tab-btn');
    const templatePanels = document.querySelectorAll('.template-panel');

    if (!templateTabs.length) {
        return;
    }

    templateTabs.forEach((tab) => {
        if (tab.dataset.templateTabWired === 'true') {
            return;
        }

        tab.dataset.templateTabWired = 'true';
        tab.addEventListener('click', function () {
            const targetPanel = this.dataset.templateTab;

            templateTabs.forEach((button) => {
                button.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                button.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            });

            this.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            this.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');

            templatePanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.templatePanel !== targetPanel);
            });
        });
    });

    document.querySelectorAll('.sms-template-textarea').forEach((textarea) => {
        const counter = document.querySelector(`.sms-char-counter[data-for="${textarea.name}"] .char-count`);
        if (!counter) {
            return;
        }

        const updateCounter = () => {
            const length = textarea.value.length;
            const wrapper = counter.parentElement;
            counter.textContent = String(length);

            wrapper.classList.remove('text-gray-600', 'dark:text-gray-400', 'text-yellow-600', 'dark:text-yellow-400', 'text-red-600', 'dark:text-red-400');
            if (length > 248) {
                wrapper.classList.add('text-red-600', 'dark:text-red-400');
            } else if (length > 200) {
                wrapper.classList.add('text-yellow-600', 'dark:text-yellow-400');
            } else {
                wrapper.classList.add('text-gray-600', 'dark:text-gray-400');
            }
        };

        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });

    const resetBtn = document.getElementById('reset-templates-btn');
    if (resetBtn && resetBtn.dataset.resetWired !== 'true') {
        resetBtn.dataset.resetWired = 'true';
        resetBtn.addEventListener('click', () => {
            if (window.confirm('Are you sure you want to reset all templates to their default values? This cannot be undone.')) {
                window.location.reload();
            }
        });
    }
}

function wireSidebarBrandSync() {
    const panel = document.getElementById('spa-content')?.querySelector('#panel-general');
    const input = panel?.querySelector('input[name="company_name"]');
    if (!input || input.dataset.brandSync === 'true') {
        return;
    }

    const updateBrandName = (name) => {
        const brandEl = document.getElementById('sidebarBrandName');
        if (!brandEl) {
            return;
        }

        const trimmed = (name || '').trim();
        brandEl.textContent = trimmed !== '' ? trimmed : 'WebScheduler';
    };

    const handler = (event) => updateBrandName(event.target.value);
    input.addEventListener('input', handler);
    input.addEventListener('change', handler);
    input.dataset.brandSync = 'true';
}

function initGeneralSettingsForm(root) {
    const form = document.getElementById('general-settings-form');
    if (!form || form.dataset.apiWired === 'true') {
        return;
    }

    const panel = form.querySelector('#panel-general') || document.getElementById('panel-general');
    const saveBtn = document.getElementById('save-general-btn');
    const logoInput = document.getElementById('company_logo');
    const logoImg = document.getElementById('company_logo_preview_img');
    const iconInput = document.getElementById('company_icon');
    const iconImg = document.getElementById('company_icon_preview_img');
    const csrfInput = form.querySelector('input[type="hidden"][name*="csrf"]');
    const apiEndpoint = root.dataset.settingsApiUrl;
    const logoEndpoint = root.dataset.settingsLogoApiUrl;
    const iconEndpoint = root.dataset.settingsIconApiUrl;

    if (!panel || !saveBtn || !apiEndpoint || !logoEndpoint || !iconEndpoint) {
        console.warn('[Settings] General settings init skipped due to missing elements or endpoints');
        return;
    }

    let hasChanges = false;

    const updateSaveButtonState = () => {
        saveBtn.disabled = !hasChanges;
        saveBtn.classList.toggle('opacity-60', !hasChanges);
        saveBtn.classList.toggle('cursor-not-allowed', !hasChanges);
    };

    const setSavingState = (isSaving) => {
        if (isSaving) {
            if (!saveBtn.dataset.originalLabel) {
                saveBtn.dataset.originalLabel = saveBtn.innerHTML;
            }
            saveBtn.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Saving...</span>';
            saveBtn.disabled = true;
            saveBtn.classList.add('opacity-60', 'cursor-not-allowed');
            return;
        }

        if (saveBtn.dataset.originalLabel) {
            saveBtn.innerHTML = saveBtn.dataset.originalLabel;
            delete saveBtn.dataset.originalLabel;
        }

        updateSaveButtonState();
    };

    Array.from(panel.querySelectorAll('input:not([type="hidden"]), textarea, select')).forEach((input) => {
        input.addEventListener('input', () => {
            hasChanges = true;
            updateSaveButtonState();
        });
        input.addEventListener('change', () => {
            hasChanges = true;
            updateSaveButtonState();
        });
    });

    if (logoInput) {
        logoInput.addEventListener('change', (event) => {
            const file = event.target?.files?.[0];
            if (file) {
                previewImage(logoImg, file);
            }
            hasChanges = true;
            updateSaveButtonState();
        });
    }

    if (iconInput) {
        iconInput.addEventListener('change', (event) => {
            const file = event.target?.files?.[0];
            if (file) {
                previewImage(iconImg, file);
            }
            hasChanges = true;
            updateSaveButtonState();
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!form.reportValidity()) {
            form.reportValidity();
            return;
        }

        const { header, token } = getCsrf(csrfInput);
        const payload = {};
        Object.entries(GENERAL_FIELD_MAP).forEach(([name, key]) => {
            const input = form.elements[name];
            if (!input || input.type === 'file') {
                return;
            }
            payload[key] = input.value ?? '';
        });

        debugLog('Saving general settings:', payload);
        setSavingState(true);

        try {
            const response = await fetch(apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { [header]: token } : {}),
                },
                body: JSON.stringify(payload),
            });

            updateCsrfFromResponse(response, csrfInput);
            if (!response.ok) {
                throw new Error(await buildHttpError(response, 'Save failed'));
            }

            const result = await response.json();
            if (isApiFailure(result)) {
                throw new Error(getApiMessage(result, 'Unable to save settings.'));
            }

            const uploadFiles = [
                { input: logoInput, endpoint: logoEndpoint, fieldName: 'company_logo', image: logoImg, label: 'Logo' },
                { input: iconInput, endpoint: iconEndpoint, fieldName: 'company_icon', image: iconImg, label: 'Icon' },
            ];

            for (const upload of uploadFiles) {
                if (!upload.input?.files?.length) {
                    continue;
                }

                const uploadToken = csrfInput?.value || token;
                const formData = new FormData();
                formData.append(upload.fieldName, upload.input.files[0]);
                if (csrfInput?.name && csrfInput.value) {
                    formData.append(csrfInput.name, csrfInput.value);
                }

                const uploadResponse = await fetch(upload.endpoint, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(uploadToken ? { [header]: uploadToken } : {}),
                    },
                    body: formData,
                });

                updateCsrfFromResponse(uploadResponse, csrfInput);
                if (!uploadResponse.ok) {
                    throw new Error(`${upload.label} upload failed (HTTP ${uploadResponse.status})`);
                }

                const uploadResult = await uploadResponse.json();
                if (isApiFailure(uploadResult)) {
                    throw new Error(getApiMessage(uploadResult, `${upload.label} upload failed.`));
                }

                const uploadData = getApiData(uploadResult);
                if (uploadData?.url && upload.image) {
                    upload.image.src = uploadData.url;
                    upload.image.dataset.src = uploadData.url;
                    upload.image.classList.remove('hidden');
                }

                upload.input.value = '';
            }

            hasChanges = false;
            setSavingState(false);
            showToast('success', 'Settings Updated', 'Your general settings were saved successfully.');
        } catch (error) {
            console.error('General settings save failed:', error);
            setSavingState(false);
            showToast('error', 'Save Failed', error?.message || 'Failed to save general settings. Please try again.', false);
        }
    });

    form.dataset.apiWired = 'true';
    debugLog('[Settings] General form initialized');
}

function initTabForm(root, tabName) {
    const form = document.getElementById(`${tabName}-settings-form`);
    if (!form || form.dataset.apiWired === 'true') {
        return;
    }

    const saveBtn = document.getElementById(`save-${tabName}-btn`);
    const panel = document.getElementById(`panel-${tabName}`);
    const apiEndpoint = root.dataset.settingsApiUrl;

    if (!saveBtn || !panel || !apiEndpoint) {
        console.warn(`[Settings] ${tabName} settings init skipped due to missing elements or endpoint`);
        return;
    }

    const formInputs = Array.from(panel.querySelectorAll('input, textarea, select'));
    let hasChanges = false;

    const updateSaveButtonState = () => {
        saveBtn.disabled = !hasChanges;
        saveBtn.classList.toggle('opacity-60', !hasChanges);
        saveBtn.classList.toggle('cursor-not-allowed', !hasChanges);
    };

    formInputs.forEach((input) => {
        input.addEventListener('input', () => {
            hasChanges = true;
            updateSaveButtonState();
        });
        input.addEventListener('change', () => {
            hasChanges = true;
            updateSaveButtonState();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!form.reportValidity()) {
            form.reportValidity();
            return;
        }

        const csrfInput = form.querySelector('input[type="hidden"][name*="csrf"]');
        const { header, token } = getCsrf(csrfInput);
        const payload = {};

        formInputs.forEach((input) => {
            const name = input.name;
            if (!name || name.includes('csrf') || name === 'form_source' || input.type === 'file') {
                return;
            }

            let value;
            if (input.type === 'checkbox') {
                value = input.checked ? '1' : '0';
            } else if (input.type === 'radio') {
                if (!input.checked) {
                    return;
                }
                value = input.value;
            } else {
                value = input.value ?? '';
            }

            let key = name;
            if (name.startsWith(`${tabName}_`)) {
                key = `${tabName}.${name.substring(tabName.length + 1)}`;
            } else if (!name.startsWith(`${tabName}.`)) {
                key = `${tabName}.${name}`;
            }

            payload[key] = value;
        });

        const originalLabel = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Saving...</span>';
        saveBtn.disabled = true;

        try {
            const response = await fetch(apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { [header]: token } : {}),
                },
                body: JSON.stringify(payload),
            });

            updateCsrfFromResponse(response, csrfInput);
            if (!response.ok) {
                throw new Error(await buildHttpError(response, 'Save failed'));
            }

            const result = await response.json();
            if (isApiFailure(result)) {
                throw new Error(getApiMessage(result, 'Unable to save settings.'));
            }

            hasChanges = false;
            saveBtn.innerHTML = originalLabel;
            updateSaveButtonState();
            document.dispatchEvent(new CustomEvent('settingsSaved', { detail: Object.keys(payload) }));
            showToast('success', 'Settings Updated', `Your ${tabName} settings were saved successfully.`);
        } catch (error) {
            console.error(`${tabName} settings save failed:`, error);
            saveBtn.innerHTML = originalLabel;
            hasChanges = true;
            updateSaveButtonState();
            showToast('error', 'Save Failed', error?.message || `Failed to save ${tabName} settings. Please try again.`, false);
        }
    });

    form.dataset.apiWired = 'true';
}

function initCustomFieldToggles() {
    document.querySelectorAll('.custom-field-toggle').forEach((toggle) => {
        if (toggle.dataset.toggleWired === 'true') {
            return;
        }

        toggle.dataset.toggleWired = 'true';
        toggle.addEventListener('change', function () {
            const container = this.closest('.custom-field-container');
            const settings = container?.querySelector('.custom-field-settings');
            const inputs = settings?.querySelectorAll('.custom-field-input') || [];
            const isEnabled = this.checked;

            settings?.classList.toggle('opacity-50', !isEnabled);
            settings?.classList.toggle('pointer-events-none', !isEnabled);
            inputs.forEach((input) => {
                input.disabled = !isEnabled;
            });
        });

        toggle.dispatchEvent(new Event('change'));
    });
}

export function initSettingsFormEnhancements(root) {
    if (!root) {
        return;
    }

    ensureGlobalHelpers();
    wireWhatsAppProviderToggle();
    wireTemplateTabs();
    wireSidebarBrandSync();
    initGeneralSettingsForm(root);
    SETTINGS_TABS.forEach((tabName) => initTabForm(root, tabName));
    initCustomFieldToggles();
}