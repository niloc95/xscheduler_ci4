import { extractJSON } from '../search/global-search.js';
import { escapeHtml } from '../../utils/html.js';
import { getBaseUrl } from '../../utils/url-helpers.js';

function formatDate(dateString) {
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

function renderEmptyState(tableBody) {
    tableBody.innerHTML = `
        <tr>
            <td colspan="5" class="px-6 py-8 text-center">
                <div class="flex flex-col items-center gap-2 text-gray-500 dark:text-gray-400">
                    <span class="material-symbols-outlined text-4xl">person_off</span>
                    <p>No customers found</p>
                </div>
            </td>
        </tr>
    `;
}

function renderError(tableBody, message) {
    tableBody.innerHTML = `
        <tr>
            <td colspan="5" class="px-6 py-8 text-center text-red-600 dark:text-red-400">
                ${escapeHtml(message || 'Error loading customers. Please try again.')}
            </td>
        </tr>
    `;
}

function renderCustomers(tableBody, customers) {
    if (!Array.isArray(customers) || customers.length === 0) {
        renderEmptyState(tableBody);
        return;
    }

    const baseUrl = getBaseUrl();
    let html = '';

    customers.forEach((customer) => {
        const initial = (customer.first_name || customer.name || 'C').charAt(0).toUpperCase();
        const fullName = customer.name || `${customer.first_name || ''} ${customer.last_name || ''}`.trim();
        const email = customer.email || '—';
        const phone = customer.phone || '—';
        const address = customer.address || '';
        const created = customer.created_at ? formatDate(customer.created_at) : '—';
        const hash = customer.hash || '';

        html += `
            <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">
                            ${escapeHtml(initial)}
                        </div>
                        <div>
                            <div class="font-medium">${escapeHtml(fullName)}</div>
                            ${address ? `<div class="xs-text-small">${escapeHtml(address)}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">${escapeHtml(email)}</td>
                <td class="px-6 py-4">${escapeHtml(phone)}</td>
                <td class="px-6 py-4">
                    <span class="xs-text-small">${escapeHtml(created)}</span>
                </td>
                <td class="px-6 py-4">
                    <div class="xs-actions-container">
                        <a href="${baseUrl}/customer-management/history/${encodeURIComponent(hash)}" class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" title="View History">
                            <span class="material-symbols-outlined">history</span>
                        </a>
                        <a href="${baseUrl}/customer-management/edit/${encodeURIComponent(hash)}" class="xs-btn xs-btn-sm xs-btn-ghost xs-btn-icon" title="Edit Customer">
                            <span class="material-symbols-outlined">edit</span>
                        </a>
                    </div>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

export function initCustomerManagementSearch() {
    const searchInput = document.getElementById('customerSearch');
    const tableBody = document.getElementById('customersTableBody');
    const spinner = document.getElementById('searchSpinner');

    if (!searchInput || !tableBody) {
        return;
    }

    if (searchInput.dataset.searchInitialized === 'true') {
        return;
    }
    searchInput.dataset.searchInitialized = 'true';

    let searchTimeout = null;
    let activeController = null;

    const performSearch = async (query) => {
        if (activeController) {
            activeController.abort();
        }

        activeController = new AbortController();

        try {
            if (spinner) {
                spinner.classList.remove('hidden');
            }

            const baseUrl = getBaseUrl();
            const response = await fetch(`${baseUrl}/customer-management/search?q=${encodeURIComponent(query)}`, {
                headers: {
                    Accept: 'application/json'
                },
                signal: activeController.signal
            });

            if (!response.ok) {
                throw new Error(`Search failed: ${response.status}`);
            }

            const text = await response.text();
            const data = extractJSON(text);

            if (!data) {
                throw new Error('Invalid JSON response');
            }

            if (data.success === false) {
                throw new Error(data.error || 'Unknown error');
            }

            renderCustomers(tableBody, data.customers || []);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error('Customer search error:', error);
            renderError(tableBody, error.message);
        } finally {
            if (spinner) {
                spinner.classList.add('hidden');
            }
        }
    };

    searchInput.addEventListener('input', (event) => {
        const query = event.target.value.trim();

        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        searchInput.value = '';
        performSearch('');
    });
}