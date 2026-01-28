/**
 * Global Search Module
 * 
 * Handles global search functionality across desktop and mobile interfaces.
 * Searches customers and appointments with real-time results.
 * 
 * Features:
 * - Debounced search (300ms)
 * - Abort controller for request cancellation
 * - Debug toolbar JSON extraction
 * - Mobile and desktop support
 * 
 * @module search/global-search
 */

import { getBaseUrl } from '../../utils/url-helpers.js';

/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Format date/time for display
 * @param {string} value - ISO date string
 * @returns {string} Formatted date
 */
function formatDateTime(value) {
    if (!value) return 'Unknown time';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
}

/**
 * Extract JSON from response text (handles debug toolbar contamination)
 * Uses multiple strategies to find valid JSON in response
 * 
 * @param {string} text - Response text
 * @returns {object|null} Parsed JSON or null
 */
function extractJSON(text) {
    // Strategy 1: Try parsing as-is
    try {
        return JSON.parse(text);
    } catch (e) {
        // Continue to other strategies
    }

    // Strategy 2: Look for JSON object pattern with success field
    const jsonMatch = text.match(/\{["']success["']:\s*(?:true|false)[\s\S]*?\}(?=\s*<|$)/);
    if (jsonMatch) {
        try {
            return JSON.parse(jsonMatch[0]);
        } catch (e) {
            // Continue to strategy 3
        }
    }

    // Strategy 3: Find last complete JSON object
    const lastBrace = text.lastIndexOf('}');
    if (lastBrace > 0) {
        let depth = 1;
        let i = lastBrace - 1;
        while (i >= 0 && depth > 0) {
            if (text[i] === '}') depth++;
            if (text[i] === '{') depth--;
            i--;
        }
        if (depth === 0) {
            try {
                return JSON.parse(text.substring(i + 1, lastBrace + 1));
            } catch (e) {
                // All strategies failed
            }
        }
    }

    return null;
}

/**
 * Render search results HTML
 * @param {HTMLElement} container - Results container element
 * @param {object} data - Search results data
 * @param {string} query - Search query
 */
function renderResults(container, data, query) {
    if (!container) return;
    
    const customers = data.customers || [];
    const appointments = data.appointments || [];

    if (customers.length === 0 && appointments.length === 0) {
        container.innerHTML = `
            <div class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">
                No results for "${escapeHtml(query)}"
            </div>
        `;
        return;
    }

    const baseUrl = getBaseUrl();
    let html = '';

    // Render customers
    if (customers.length > 0) {
        html += `
            <div class="px-3 py-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Customers</div>
        `;
        customers.forEach(customer => {
            const name = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || customer.name || 'Customer';
            const email = customer.email || 'No email';
            const hash = customer.hash || customer.id;
            const url = `${baseUrl}/customer-management/history/${hash}`;

            html += `
                <a href="${url}" class="block px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">${escapeHtml(name)}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(email)}</div>
                </a>
            `;
        });
    }

    // Render appointments
    if (appointments.length > 0) {
        html += `
            <div class="px-3 py-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Appointments</div>
        `;
        appointments.forEach(appt => {
            const customerName = appt.customer_name || 'Unknown customer';
            const serviceName = appt.service_name || 'Appointment';
            const startTime = formatDateTime(appt.start_time);
            const hash = appt.hash || appt.id;
            const url = `${baseUrl}/appointments/edit/${hash}`;

            html += `
                <a href="${url}" class="block px-3 py-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">${escapeHtml(customerName)}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(serviceName)} • ${escapeHtml(startTime)}</div>
                </a>
            `;
        });
    }

    container.innerHTML = html;
}

/**
 * Perform search API call
 * @param {string} query - Search query
 * @param {object} target - Target UI elements {results, content}
 * @param {AbortController} controller - Abort controller for cancellation
 */
async function performSearch(query, target, controller) {
    const { results, content } = target;
    if (!results || !content) return;

    if (!query || query.trim().length < 2) {
        results.classList.add('hidden');
        return;
    }

    try {
        const baseUrl = getBaseUrl();
        const response = await fetch(`${baseUrl}/dashboard/search?q=${encodeURIComponent(query.trim())}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            },
            signal: controller.signal
        });

        if (!response.ok) {
            throw new Error(`Search failed: ${response.status}`);
        }

        // Get response as text (handles debug toolbar)
        const text = await response.text();
        const data = extractJSON(text);

        if (!data) {
            console.error('Global search: Could not extract JSON from response');
            throw new Error('Invalid JSON response');
        }

        if (data.success === false) {
            throw new Error(data?.error || 'Search failed');
        }

        renderResults(content, data, query);
        results.classList.remove('hidden');
    } catch (error) {
        if (error.name === 'AbortError') return;
        
        console.error('Global search error:', error);
        content.innerHTML = `
            <div class="px-3 py-3 text-sm text-red-600 dark:text-red-400">
                Search failed. Try again.
            </div>
        `;
        results.classList.remove('hidden');
    }
}

/**
 * Initialize global search functionality
 * Supports both desktop and mobile search inputs
 */
export function initGlobalSearch() {
    // Find desktop search elements
    const searchInput = document.getElementById('global-search');
    const searchResults = document.getElementById('global-search-results');
    const resultsContent = document.getElementById('global-search-results-content');

    // Find mobile search elements
    const searchInputMobile = document.getElementById('global-search-mobile');
    const searchResultsMobile = document.getElementById('global-search-results-mobile');
    const resultsContentMobile = document.getElementById('global-search-results-content-mobile');

    // Build array of valid search targets
    const inputs = [
        { input: searchInput, results: searchResults, content: resultsContent },
        { input: searchInputMobile, results: searchResultsMobile, content: resultsContentMobile }
    ].filter(item => item.input && item.results && item.content);

    // No search elements found
    if (inputs.length === 0) return;

    // Prevent double initialization
    inputs.forEach(({ input }) => {
        if (input.dataset.searchInitialized === 'true') return;
        input.dataset.searchInitialized = 'true';
    });

    let searchTimeout = null;
    let activeController = null;

    /**
     * Hide all search results dropdowns
     */
    function hideResults() {
        inputs.forEach(({ results }) => {
            results.classList.add('hidden');
        });
    }

    /**
     * Bind events to search input
     * @param {object} target - Target UI elements
     */
    function bindInput(target) {
        const { input } = target;
        if (!input) return;

        // Handle input with debouncing
        input.addEventListener('input', (e) => {
            const query = e.target.value;
            
            // Cancel pending search
            if (searchTimeout) clearTimeout(searchTimeout);
            
            // Debounce search (300ms)
            searchTimeout = setTimeout(() => {
                // Abort previous request
                if (activeController) {
                    activeController.abort();
                }
                
                // Create new abort controller
                activeController = new AbortController();
                
                // Perform search
                performSearch(query, target, activeController);
            }, 300);
        });

        // Show results on focus if query exists
        input.addEventListener('focus', (e) => {
            const query = e.target.value;
            if (query && query.trim().length >= 2) {
                // Abort previous request
                if (activeController) {
                    activeController.abort();
                }
                
                // Create new abort controller
                activeController = new AbortController();
                
                // Perform search
                performSearch(query, target, activeController);
            }
        });
    }

    // Bind events to all search inputs
    inputs.forEach(bindInput);

    // Hide results when clicking outside
    document.addEventListener('click', (e) => {
        const wrappers = [
            document.getElementById('global-search-wrapper'),
            document.getElementById('global-search-wrapper-mobile')
        ].filter(Boolean);

        // Check if click was on a search result link
        const resultLink = e.target.closest('a[href]');
        const isResultInWrapper = wrappers.some(wrapper => 
            wrapper && wrapper.querySelector(`#${wrapper.id.replace('wrapper', 'results-content')}`)?.contains(e.target)
        );

        if (isResultInWrapper && resultLink) {
            // Clear all search inputs when a result is clicked
            inputs.forEach(({ input }) => {
                input.value = '';
            });
            hideResults();
        } else if (!wrappers.some(wrapper => wrapper?.contains(e.target))) {
            // Hide results when clicking outside
            hideResults();
        }
    });
}
