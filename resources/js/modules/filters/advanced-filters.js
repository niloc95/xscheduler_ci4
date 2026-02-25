/**
 * Advanced Filters Module
 *
 * Handles advanced filter panel interactions for scheduler views.
 *
 * @module filters/advanced-filters
 */

import { getBaseUrl } from '../../utils/url-helpers.js';

/**
 * Update the filter indicator badge on the toggle button
 * @param {HTMLElement} toggleBtn - Toggle button element
 * @param {boolean} hasActiveFilters - Whether any filters are active
 */
export function updateFilterIndicator(toggleBtn, hasActiveFilters) {
    if (!toggleBtn) return;

    // Remove existing indicator
    const existingIndicator = toggleBtn.querySelector('.filter-active-indicator');
    if (existingIndicator) {
        existingIndicator.remove();
    }

    if (hasActiveFilters) {
        const indicator = document.createElement('span');
        indicator.className = 'filter-active-indicator absolute -top-1 -right-1 w-3 h-3 bg-blue-500 rounded-full border-2 border-white dark:border-gray-800';
        toggleBtn.classList.add('relative');
        toggleBtn.appendChild(indicator);
    }
}

/**
 * Setup the advanced filter panel toggle and apply/clear handlers
 * @param {object} scheduler - Scheduler instance
 * @param {object} options - Additional options
 * @param {Function} options.renderProviderLegend - Callback to render provider legend
 */
export function setupAdvancedFilterPanel(scheduler, { renderProviderLegend } = {}) {
    const toggleBtn = document.getElementById('advanced-filter-toggle');
    const filterPanel = document.getElementById('advanced-filter-panel');
    const toggleIcon = document.getElementById('filter-toggle-icon');
    const applyBtn = document.getElementById('apply-filters-btn');
    const clearBtn = document.getElementById('clear-filters-btn');

    // Filter dropdowns
    const statusSelect = document.getElementById('filter-status');
    const providerSelect = document.getElementById('filter-provider');
    const serviceSelect = document.getElementById('filter-service');
    const locationSelect = document.getElementById('filter-location');

    // Store all services for "All Providers" view
    const allServicesOptions = serviceSelect ? serviceSelect.innerHTML : '';

    if (!toggleBtn || !filterPanel) {
        return; // Panel elements not present
    }

    // Dynamic service loading when provider changes
    if (providerSelect && serviceSelect) {
        providerSelect.addEventListener('change', async () => {
            const providerId = providerSelect.value;

            if (!providerId) {
                // No provider selected - show all services
                serviceSelect.innerHTML = allServicesOptions;
                serviceSelect.disabled = false;
                return;
            }

            // Show loading state
            serviceSelect.innerHTML = '<option value="">Loading services...</option>';
            serviceSelect.disabled = true;

            try {
                const response = await fetch(`${getBaseUrl()}/api/v1/providers/${providerId}/services`);
                if (!response.ok) throw new Error('Failed to load services');

                const result = await response.json();
                const services = result.data || [];

                // Rebuild service dropdown with provider-specific services
                let optionsHtml = '<option value="">All Services</option>';
                services.forEach(service => {
                    optionsHtml += `<option value="${service.id}">${service.name}</option>`;
                });

                serviceSelect.innerHTML = optionsHtml;
                serviceSelect.disabled = false;
            } catch (error) {
                console.error('Failed to load provider services:', error);
                // Fallback to all services on error
                serviceSelect.innerHTML = allServicesOptions;
                serviceSelect.disabled = false;
            }
        });
    }

    // Toggle panel visibility
    toggleBtn.addEventListener('click', () => {
        const isHidden = filterPanel.classList.toggle('hidden');

        // Rotate icon
        if (toggleIcon) {
            toggleIcon.classList.add('transition-transform', 'duration-200');
            toggleIcon.classList.toggle('rotate-180', !isHidden);
        }

        // Update toggle button styling to indicate active state
        if (!isHidden) {
            toggleBtn.classList.add('bg-blue-100', 'dark:bg-blue-900/30', 'text-blue-700', 'dark:text-blue-300');
            toggleBtn.classList.remove('bg-slate-100', 'dark:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
        } else {
            toggleBtn.classList.remove('bg-blue-100', 'dark:bg-blue-900/30', 'text-blue-700', 'dark:text-blue-300');
            toggleBtn.classList.add('bg-slate-100', 'dark:bg-slate-700', 'text-slate-700', 'dark:text-slate-300');
        }
    });

    // Apply filters
    if (applyBtn) {
        applyBtn.addEventListener('click', async () => {
            const status = statusSelect?.value || '';
            const providerId = providerSelect?.value || '';
            const serviceId = serviceSelect?.value || '';
            const locationId = locationSelect?.value || '';

            try {
                await scheduler.setFilters({ status, providerId, serviceId, locationId });

                // Re-render provider legend to reflect filter state
                if (typeof renderProviderLegend === 'function') {
                    renderProviderLegend(scheduler);
                }

                // Show success feedback
                applyBtn.textContent = 'Applied!';
                setTimeout(() => {
                    applyBtn.innerHTML = '<span class="material-symbols-outlined text-base">filter_alt</span> Apply';
                }, 1000);

                // Update filter indicator on toggle button
                const hasActiveFilters = status || providerId || serviceId || locationId;
                updateFilterIndicator(toggleBtn, hasActiveFilters);
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
                await scheduler.setFilters({ status: '', providerId: '', serviceId: '', locationId: '' });

                // Re-render provider legend to reflect cleared state
                if (typeof renderProviderLegend === 'function') {
                    renderProviderLegend(scheduler);
                }

                updateFilterIndicator(toggleBtn, false);
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
        updateFilterIndicator(toggleBtn, true);
    }
}
