<?php
/**
 * Customer Management - Index View
 *
 * Admin-focused CRUD interface for managing customer records in the database.
 * This is NOT for appointment interactions (see user_management/customers.php for that).
 * 
 * Purpose: Create, Read, Update, Delete customer profiles and contact information
 * Access: Admin role only
 * Actions: View list, Create new, Edit existing, Delete customers
 * 
 * Related: app/Views/user_management/customers.php handles booking interactions
 */
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'customer-management']) ?>
<?= $this->endSection() ?>

<?= $this->section('page_title') ?>Customer Management<?= $this->endSection() ?>
<?= $this->section('page_subtitle') ?>Manage customers and their contact details<?= $this->endSection() ?>

<?= $this->section('dashboard_content_top') ?>
    <?php if (session()->getFlashdata('success')): ?>
        <div class="mb-4 p-3 rounded-lg border border-green-300/60 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200">
            <?= esc(session()->getFlashdata('success')) ?>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('dashboard_content') ?>
    <div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-200">Customers</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">View and manage customers</p>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <div class="flex flex-1 md:flex-none gap-2 relative">
                    <input 
                        type="search" 
                        id="customerSearch" 
                        name="q" 
                        value="<?= esc($q ?? '') ?>" 
                        placeholder="Search name or email..." 
                        autocomplete="off"
                        class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" 
                    />
                    <div id="searchSpinner" class="hidden absolute right-3 top-3">
                        <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
                <a href="<?= base_url('customer-management/create') ?>" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg inline-flex items-center gap-1"><span class="material-symbols-outlined">person_add</span><span class="hidden sm:inline">New</span></a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 dark:text-gray-300 uppercase border-b border-gray-200 dark:border-gray-600">
                    <tr>
                        <th class="px-6 py-4 font-semibold">Customer</th>
                        <th class="px-6 py-4 font-semibold">Email</th>
                        <th class="px-6 py-4 font-semibold">Phone</th>
                        <th class="px-6 py-4 font-semibold">Created</th>
                        <th class="px-6 py-4 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                <?php if (!empty($customers)): foreach ($customers as $c): ?>
                    <tr class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm mr-3">
                                    <?= strtoupper(substr(($c['first_name'] ?? ($c['name'] ?? 'C')), 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-medium"><?= esc($c['name'] ?? trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></div>
                                    <?php if (!empty($c['address'])): ?><div class="text-xs text-gray-500 dark:text-gray-400"><?= esc($c['address']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= esc($c['email'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= esc($c['phone'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= !empty($c['created_at']) ? date('M j, Y', strtotime($c['created_at'])) : '—' ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="<?= base_url('customer-management/edit/' . esc($c['hash'] ?? '')) ?>" class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" title="Edit Customer">
                                    <span class="material-symbols-outlined">edit</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No customers found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('extra_js') ?>
<script>
// Initialize search functionality - called on page load AND on SPA navigation
function initCustomerSearch() {
    const searchInput = document.getElementById('customerSearch');
    const tableBody = document.getElementById('customersTableBody');
    const spinner = document.getElementById('searchSpinner');
    
    if (!searchInput || !tableBody) {
        console.error('Customer search elements not found');
        return;
    }
    
    // Prevent duplicate initialization
    if (searchInput.dataset.searchInitialized === 'true') {
        console.log('Customer search already initialized');
        return;
    }
    searchInput.dataset.searchInitialized = 'true';
    console.log('Initializing customer search');
    
    let searchTimeout = null;

    // Function to perform search
    async function performSearch(query) {
        try {
            // Show spinner
            if (spinner) spinner.classList.remove('hidden');

            const url = `<?= base_url('customer-management/search') ?>?q=${encodeURIComponent(query)}`;
            console.log('Searching:', url);
            
            const response = await fetch(url);
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`Search failed: ${response.status}`);
            }

            // Get response as text first (in case debug toolbar is present)
            const text = await response.text();
            
            // Try to extract JSON from response (handles debug toolbar contamination)
            let data;
            try {
                // First try parsing as-is
                data = JSON.parse(text);
            } catch (e) {
                console.log('Initial parse failed, trying extraction...');
                
                // Strategy 1: Look for JSON object pattern with success field
                const jsonMatch = text.match(/\{["']success["']:\s*(?:true|false)[\s\S]*?\}(?=\s*<|$)/);
                if (jsonMatch) {
                    try {
                        data = JSON.parse(jsonMatch[0]);
                        console.log('Extracted JSON successfully');
                    } catch (e2) {
                        console.error('Strategy 1 failed');
                    }
                }
                
                // Strategy 2: Find last complete JSON object
                if (!data) {
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
                                data = JSON.parse(text.substring(i + 1, lastBrace + 1));
                                console.log('Extracted JSON using depth strategy');
                            } catch (e3) {
                                console.error('Strategy 2 failed');
                            }
                        }
                    }
                }
                
                if (!data) {
                    console.error('Could not extract JSON from response:', text.substring(0, 500));
                    throw new Error('Invalid JSON response');
                }
            }
            
            console.log('Search results:', data);
            
            if (data.success) {
                updateTable(data.customers);
            } else {
                throw new Error(data.error || 'Unknown error');
            }
        } catch (error) {
            console.error('Search error:', error);
            showError(error.message);
        } finally {
            // Hide spinner
            if (spinner) spinner.classList.add('hidden');
        }
    }

    // Function to update table with results
    function updateTable(customers) {
        if (customers.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No customers found.</td>
                </tr>
            `;
            return;
        }

        let html = '';
        customers.forEach(customer => {
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
                                ${initial}
                            </div>
                            <div>
                                <div class="font-medium">${escapeHtml(fullName)}</div>
                                ${address ? `<div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(address)}</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">${escapeHtml(email)}</td>
                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">${escapeHtml(phone)}</td>
                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">${created}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="<?= base_url('customer-management/edit/') ?>${hash}" class="p-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400" title="Edit Customer">
                                <span class="material-symbols-outlined">edit</span>
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        });

        tableBody.innerHTML = html;
    }

    // Function to show error
    function showError(message) {
        const errorMsg = message || 'Error loading customers. Please try again.';
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="px-6 py-6 text-center text-red-600 dark:text-red-400">
                    ${escapeHtml(errorMsg)}
                </td>
            </tr>
        `;
    }

    // Helper function to format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Event listener for input with debounce
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        // Clear existing timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // Set new timeout (300ms debounce)
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    // Clear search on ESC key
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchInput.value = '';
            performSearch('');
        }
    });
}

// Register this initializer to run on both initial load and SPA navigation
if (window.xsRegisterViewInit) {
    window.xsRegisterViewInit(initCustomerSearch);
} else {
    // Fallback if SPA system not loaded yet
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCustomerSearch);
    } else {
        initCustomerSearch();
    }
}
</script>
<?= $this->endSection() ?>
