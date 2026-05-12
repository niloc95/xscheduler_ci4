/**
 * Advanced Filters Module
 *
 * Handles advanced filter panel interactions for scheduler views.
 *
 * @module filters/advanced-filters
 */

import { getBaseUrl } from '../../utils/url-helpers.js';
import { apiRequest } from '../../core/api.js';

/**
 * Update the filter indicator badge on the toggle button
 * @param {HTMLElement} toggleBtn - Toggle button element
 * @param {boolean} hasActiveFilters - Whether any filters are active
 */
export function updateFilterIndicator(toggleBtn, hasActiveFilters) {
    const toggleButtons = Array.isArray(toggleBtn) ? toggleBtn : [toggleBtn];

    toggleButtons.filter(Boolean).forEach((button) => {
        const existingIndicator = button.querySelector('.filter-active-indicator');
        if (existingIndicator) {
            existingIndicator.remove();
        }

        if (hasActiveFilters) {
            const indicator = document.createElement('span');
            indicator.className = 'filter-active-indicator absolute -top-1 -right-1 w-3 h-3 bg-blue-500 rounded-full border-2 border-white dark:border-gray-800';
            button.classList.add('relative');
            button.appendChild(indicator);
        }
    });
}

/**
 * Setup the advanced filter panel toggle and apply/clear handlers
 * @param {object} scheduler - Scheduler instance
 * @param {object} options - Additional options
 * @param {Function} options.renderProviderLegend - Callback to render provider legend
 */
export function setupAdvancedFilterPanel(scheduler, { renderProviderLegend } = {}) {
    const toggleButtons = Array.from(document.querySelectorAll('[data-advanced-filter-toggle]'));
    const filterPanel = document.getElementById('advanced-filter-panel');
    const applyBtn = document.getElementById('apply-filters-btn');
    const clearBtn = document.getElementById('clear-filters-btn');

    // Guard against double initialization (listeners stacking on SPA navigation)
    if (filterPanel?.dataset.advancedFilterSetup === 'true') {
        return;
    }
    filterPanel?.setAttribute('data-advanced-filter-setup', 'true');

    // Filter dropdowns
    const statusSelect = document.getElementById('filter-status');
    const providerSelect = document.getElementById('filter-provider');
    const serviceSelect = document.getElementById('filter-service');
    const locationSelect = document.getElementById('filter-location');

    // Store all services for "All Providers" view
    const allServicesOptions = serviceSelect ? serviceSelect.innerHTML : '';

    const getFilterValues = () => ({
        status: statusSelect?.value || '',
        providerId: providerSelect?.value || '',
        serviceId: serviceSelect?.value || '',
        locationId: locationSelect?.value || ''
    });

    const applyCurrentFilters = async ({ showFeedback = false } = {}) => {
        const { status, providerId, serviceId, locationId } = getFilterValues();

        await scheduler.setFilters({ status, providerId, serviceId, locationId });

        if (typeof renderProviderLegend === 'function') {
            renderProviderLegend(scheduler);
        }

        const hasActiveFilters = status || providerId || serviceId || locationId;
        updateFilterIndicator(toggleButtons, hasActiveFilters);

        if (showFeedback && applyBtn) {
            applyBtn.textContent = 'Applied!';
            setTimeout(() => {
                applyBtn.innerHTML = '<span class="material-symbols-outlined text-base">filter_alt</span> Apply';
            }, 1000);
        }
    };

    if (!toggleButtons.length || !filterPanel) {
        return; // Panel elements not present
    }

    const syncToggleButtons = (isOpen) => {
        toggleButtons.forEach((toggleBtn) => {
            toggleBtn.classList.toggle('is-open', isOpen);

            toggleBtn.querySelectorAll('[data-filter-toggle-icon]').forEach((icon) => {
                icon.classList.toggle('rotate-180', isOpen);
            });
        });
    };

    // Dynamic service loading when provider changes
    if (providerSelect && serviceSelect) {
        providerSelect.addEventListener('change', async () => {
            const providerId = providerSelect.value;

            if (!providerId) {
                // No provider selected - show all services
                serviceSelect.innerHTML = allServicesOptions;
                serviceSelect.disabled = false;
                await applyCurrentFilters();
                return;
            }

            // Show loading state
            serviceSelect.innerHTML = '<option value="">Loading services...</option>';
            serviceSelect.disabled = true;

            try {
                const { response, payload: result } = await apiRequest(`${getBaseUrl()}/api/v1/providers/${providerId}/services`, {
                    method: 'GET',
                });
                if (!response.ok) throw new Error('Failed to load services');

                const services = result.data || [];

                // Rebuild service dropdown with provider-specific services
                let optionsHtml = '<option value="">All Services</option>';
                services.forEach(service => {
                    optionsHtml += `<option value="${service.id}">${service.name}</option>`;
                });

                serviceSelect.innerHTML = optionsHtml;
                serviceSelect.disabled = false;
                await applyCurrentFilters();
            } catch (error) {
                console.error('Failed to load provider services:', error);
                // Fallback to all services on error
                serviceSelect.innerHTML = allServicesOptions;
                serviceSelect.disabled = false;
                await applyCurrentFilters();
            }
        });
    }

    statusSelect?.addEventListener('change', async () => {
        try {
            await applyCurrentFilters();
        } catch (error) {
            console.error('Failed to apply status filter:', error);
        }
    });

    serviceSelect?.addEventListener('change', async () => {
        try {
            await applyCurrentFilters();
        } catch (error) {
            console.error('Failed to apply service filter:', error);
        }
    });

    locationSelect?.addEventListener('change', async () => {
        try {
            await applyCurrentFilters();
        } catch (error) {
            console.error('Failed to apply location filter:', error);
        }
    });

    // Toggle panel visibility from any trigger
    toggleButtons.forEach((toggleBtn) => {
        if (toggleBtn.dataset.advancedFilterToggleBound === 'true') {
            return;
        }

        toggleBtn.dataset.advancedFilterToggleBound = 'true';

        toggleBtn.addEventListener('click', () => {
            const isHidden = filterPanel.classList.toggle('hidden');
            syncToggleButtons(!isHidden);
        });
    });

    // Apply filters
    if (applyBtn) {
        applyBtn.addEventListener('click', async () => {
            try {
                await applyCurrentFilters({ showFeedback: true });
            } catch (error) {
                console.error('Failed to apply filters:', error);
            }
        });
    }

    // Clear filters
    if (clearBtn) {
        clearBtn.addEventListener('click', async () => {
            // Reset all dropdowns
            if (statusSelect) statusSelect.value = '';
            if (providerSelect) providerSelect.value = '';
            if (locationSelect) locationSelect.value = '';
            if (serviceSelect) {
                // Restore all services when clearing filters
                serviceSelect.innerHTML = allServicesOptions;
                serviceSelect.value = '';
                serviceSelect.disabled = false;
            }

            try {
                await applyCurrentFilters();
            } catch (error) {
                console.error('Failed to clear filters:', error);
            }
        });
    }

    // Check for initial active filters (from URL or server-side)
    const hasActiveFilters =
        (statusSelect?.value && statusSelect.value !== '') ||
        (providerSelect?.value && providerSelect.value !== '') ||
        (serviceSelect?.value && serviceSelect.value !== '') ||
        (locationSelect?.value && locationSelect.value !== '');

    if (hasActiveFilters) {
        updateFilterIndicator(toggleButtons, true);
    }
}
