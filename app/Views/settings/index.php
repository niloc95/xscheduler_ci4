<?= $this->extend('layouts/app') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'settings']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Settings<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand p-4 md:p-6 mb-6">

        <?php if (session()->getFlashdata('error')): ?>
            <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
                <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('success')): ?>
            <div id="success-alert" class="mb-4 p-3 rounded-lg border border-green-300/60 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 flex items-center justify-between">
                <span>
                    <?php if (session()->getFlashdata('success_html')): ?>
                        <?= session()->getFlashdata('success') ?>
                    <?php else: ?>
                        <?= esc(session()->getFlashdata('success')) ?>
                    <?php endif; ?>
                </span>
                <button type="button" onclick="document.getElementById('success-alert').remove()" class="ml-4 text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
            <?php if (session()->getFlashdata('success_html')): ?>
            <script>
                // Auto-dismiss WhatsApp link alert when link is clicked
                (function() {
                    const alert = document.getElementById('success-alert');
                    if (alert) {
                        const link = alert.querySelector('a[target="_blank"]');
                        if (link) {
                            link.addEventListener('click', function() {
                                setTimeout(function() {
                                    alert.remove();
                                }, 500);
                            });
                        }
                    }
                })();
            </script>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="overflow-x-auto">
            <nav class="flex gap-2 border-b border-gray-200 dark:border-gray-700" role="tablist" aria-label="Settings Sections">
                <button class="tab-btn active" data-tab="general">General</button>
                <button class="tab-btn" data-tab="localization">Localization</button>
                <button class="tab-btn" data-tab="booking">Booking</button>
                <button class="tab-btn" data-tab="business">Business hours</button>
                <button class="tab-btn" data-tab="legal">Legal Contents</button>
                <button class="tab-btn" data-tab="integrations">Integrations</button>
                <button class="tab-btn" data-tab="notifications">Notifications</button>
                <button class="tab-btn" data-tab="database">Database</button>
            </nav>
        </div>

    <!-- Tab Panels -->
    <div id="settings-content">

    <?= $this->include('settings/tabs/general') ?>

    <?= $this->include('settings/tabs/localization') ?>

    <?= $this->include('settings/tabs/booking') ?>

    <?= $this->include('settings/tabs/business') ?>

    <?= $this->include('settings/tabs/legal') ?>

    <?= $this->include('settings/tabs/integrations') ?>

    <?= $this->include('settings/tabs/notifications') ?>

    <?= $this->include('settings/tabs/database') ?>

        </div>

        <!-- Backup List Modal -->
        <div id="backup-list-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="card card-elevated max-w-2xl w-full mx-4 max-h-[80vh] flex flex-col">
                <div class="card-header flex items-center justify-between">
                    <h3 class="card-title">Database Backups</h3>
                    <button type="button" id="close-backup-modal" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="card-body overflow-y-auto">
                    <div id="backup-list-content">
                        <div class="text-center py-8 text-gray-500">
                            <div class="loading-spinner mx-auto mb-2"></div>
                            Loading backups...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Block Period Modal (Outside all forms) -->
        <div id="block-period-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="card card-elevated max-w-md w-full mx-4">
                <div class="card-header">
                    <h3 class="card-title" id="block-period-modal-title">Add Block Period</h3>
                </div>
                <form id="block-period-form">
                    <div class="card-body space-y-4">
                        <div class="form-field">
                            <label class="form-label">Start Date</label>
                            <input type="date" id="block-period-start" class="form-input" required>
                        </div>
                        <div class="form-field">
                            <label class="form-label">End Date</label>
                            <input type="date" id="block-period-end" class="form-input" required>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Reason/Notes <span class="text-xs text-gray-400 font-normal">(optional)</span></label>
                            <input type="text" id="block-period-notes" class="form-input" maxlength="100" placeholder="e.g., Christmas Holiday, Office Maintenance">
                        </div>
                        <div id="block-period-error" class="text-red-500 text-sm hidden bg-red-50 dark:bg-red-900/20 p-3 rounded-lg border border-red-200 dark:border-red-800"></div>
                    </div>
                    <div class="card-footer">
                        <button type="button" id="block-period-cancel" class="btn btn-ghost">Cancel</button>
                        <button type="submit" id="block-period-save-btn" class="btn btn-primary">Save Period</button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>

        <script>
        // ═══════════════════════════════════════════════════════════
        // Settings Page — Consolidated Script
        // Helpers, WhatsApp toggle, template tabs, blocked periods,
        // main API init, time format, and database tab.
        // ═══════════════════════════════════════════════════════════


        // ─── Shared Helpers ─────────────────────────────────────
        (function () {
            // Gate verbose debug logs behind appConfig.debug
            window.xsDebugLog = window.xsDebugLog || function (...args) {
                try {
                    if (window.appConfig && window.appConfig.debug) {
                        console.log(...args);
                    }
                } catch (_) {
                    // no-op
                }
            };

            // Shared CSRF helper — used by all settings form scripts
            window.xsGetCsrf = window.xsGetCsrf || function () {
                const header = document.querySelector('meta[name="csrf-header"]')?.getAttribute('content') || 'X-CSRF-TOKEN';
                const token  = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                return { header, token };
            };

            // Shared HTML escaper — delegates to global defined in app.js
            window.xsEscapeHtml = window.xsEscapeHtml || function (str) {
                if (!str) return '';
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            };
        })();

        // ─── WhatsApp Provider Toggle ───────────────────────────
                        // Initialize immediately (works on first load and SPA re-navigation)
                        (function initWhatsAppProviderToggle() {
                            const providerSelect = document.getElementById('whatsapp_provider');
                            if (!providerSelect || providerSelect.dataset.toggleWired === 'true') return;
                            providerSelect.dataset.toggleWired = 'true';
                            
                            const sections = {
                                link_generator: document.getElementById('wa_link_generator_section'),
                                twilio: document.getElementById('wa_twilio_section'),
                                meta_cloud: document.getElementById('wa_meta_section')
                            };
                            
                            function updateSections() {
                                const selected = providerSelect.value;
                                Object.keys(sections).forEach(key => {
                                    if (sections[key]) {
                                        sections[key].classList.toggle('hidden', key !== selected);
                                    }
                                });
                            }
                            
                            providerSelect.addEventListener('change', updateSections);
                            // Set initial state
                            updateSections();
                        })();

        // ─── Notification Template Tabs ─────────────────────────
                    // Execute immediately (works on first load and SPA re-navigation)
                    (function initTemplateTabs() {
                        // Template tab switching
                        const templateTabs = document.querySelectorAll('.template-tab-btn');
                        const templatePanels = document.querySelectorAll('.template-panel');
                        
                        if (!templateTabs.length) return;
                        
                        templateTabs.forEach(tab => {
                            if (tab.dataset.templateTabWired === 'true') return;
                            tab.dataset.templateTabWired = 'true';
                            
                            tab.addEventListener('click', function() {
                                const targetPanel = this.dataset.templateTab;
                                
                                // Update tab styles
                                templateTabs.forEach(t => {
                                    t.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                                    t.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
                                });
                                this.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                                this.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
                                
                                // Show/hide panels
                                templatePanels.forEach(panel => {
                                    if (panel.dataset.templatePanel === targetPanel) {
                                        panel.classList.remove('hidden');
                                    } else {
                                        panel.classList.add('hidden');
                                    }
                                });
                            });
                        });
                        
                        // SMS character counter
                        const smsTextareas = document.querySelectorAll('.sms-template-textarea');
                        smsTextareas.forEach(textarea => {
                            const counterSpan = document.querySelector(`.sms-char-counter[data-for="${textarea.name}"] .char-count`);
                            if (counterSpan) {
                                const updateCounter = () => {
                                    const len = textarea.value.length;
                                    counterSpan.textContent = len;
                                    if (len > 248) {
                                        counterSpan.parentElement.classList.add('text-red-600', 'dark:text-red-400');
                                        counterSpan.parentElement.classList.remove('text-gray-600', 'dark:text-gray-400');
                                    } else if (len > 200) {
                                        counterSpan.parentElement.classList.add('text-yellow-600', 'dark:text-yellow-400');
                                        counterSpan.parentElement.classList.remove('text-gray-600', 'dark:text-gray-400', 'text-red-600', 'dark:text-red-400');
                                    } else {
                                        counterSpan.parentElement.classList.add('text-gray-600', 'dark:text-gray-400');
                                        counterSpan.parentElement.classList.remove('text-red-600', 'dark:text-red-400', 'text-yellow-600', 'dark:text-yellow-400');
                                    }
                                };
                                textarea.addEventListener('input', updateCounter);
                                updateCounter(); // Initial count
                            }
                        });
                        
                        // Reset templates button
                        const resetBtn = document.getElementById('reset-templates-btn');
                        if (resetBtn && resetBtn.dataset.resetWired !== 'true') {
                            resetBtn.dataset.resetWired = 'true';
                            resetBtn.addEventListener('click', function() {
                                if (confirm('Are you sure you want to reset all templates to their default values? This cannot be undone.')) {
                                    // Reload page to get defaults
                                    window.location.reload();
                                }
                            });
                        }
                    })();

        // ─── Blocked Periods Structured UI ──────────────────────
        // --- Blocked Periods Structured UI ---
        (function initBlockedPeriodsUI() {
            // Guard against re-initialization
            if (document.getElementById('block-periods-list')?.dataset.initialized === 'true') {
                return;
            }
            
            const container = document.getElementById('block-periods-list');
            if (!container) return;
            
            container.dataset.initialized = 'true';

            // Parse initial value
            let blockPeriods = [];
            try {
                blockPeriods = JSON.parse(document.getElementById('blocked_periods_json')?.value || '[]');
                if (!Array.isArray(blockPeriods)) blockPeriods = [];
            } catch { blockPeriods = []; }

            let editingIndex = null;

            function renderBlockPeriods() {
                const container = document.getElementById('block-periods-list');
                const emptyState = document.getElementById('block-periods-empty');
                
                if (!container || !emptyState) return;
                
                container.innerHTML = '';
                
                if (!blockPeriods.length) {
                    emptyState.classList.remove('hidden');
                    return;
                }
                
                emptyState.classList.add('hidden');
                
                blockPeriods.forEach((period, idx) => {
                    const item = document.createElement('div');
                    item.className = 'flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors';
                    
                    const startDate = period.start ? new Date(period.start).toLocaleDateString() : '';
                    const endDate = period.end ? new Date(period.end).toLocaleDateString() : '';
                    const isMultiDay = period.start !== period.end;
                    
                    item.innerHTML = `
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="material-symbols-outlined text-orange-500">event_busy</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    ${startDate}${isMultiDay ? ' - ' + endDate : ''}
                                </span>
                            </div>
                            ${period.notes ? `<p class="text-sm text-gray-600 dark:text-gray-400 ml-6">${xsEscapeHtml(period.notes)}</p>` : ''}
                        </div>
                        <div class="flex gap-1 ml-4">
                            <button type="button" class="btn btn-ghost btn-sm p-2" data-edit="${idx}" title="Edit period">
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm p-2 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" data-delete="${idx}" title="Delete period">
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </button>
                        </div>
                    `;
                    
                    container.appendChild(item);
                });
            }

            function openModal(mode, index = null) {
                const modal = document.getElementById('block-period-modal');
                const title = document.getElementById('block-period-modal-title');
                const form = document.getElementById('block-period-form');
                const start = document.getElementById('block-period-start');
                const end = document.getElementById('block-period-end');
                const notes = document.getElementById('block-period-notes');
                const error = document.getElementById('block-period-error');
                error.classList.add('hidden');
                error.textContent = '';
                if (mode === 'edit' && index !== null) {
                    title.textContent = 'Edit Block Period';
                    const period = blockPeriods[index];
                    start.value = period.start || '';
                    end.value = period.end || '';
                    notes.value = period.notes || '';
                    editingIndex = index;
                } else {
                    title.textContent = 'Add Block Period';
                    start.value = '';
                    end.value = '';
                    notes.value = '';
                    editingIndex = null;
                }
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function closeModal() {
                const modal = document.getElementById('block-period-modal');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            function saveBlockPeriods() {
                const jsonInput = document.getElementById('blocked_periods_json');
                if (!jsonInput) return;
                jsonInput.value = JSON.stringify(blockPeriods);
                renderBlockPeriods();
            }

            // Add button
            const addBtn = document.getElementById('add-block-period-btn');
            if (addBtn) {
                addBtn.addEventListener('click', () => openModal('add'));
            }

            // Cancel button
            const cancelBtn = document.getElementById('block-period-cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeModal();
                });
            }

            // Form submit
            const form = document.getElementById('block-period-form');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    e.stopPropagation(); // Prevent event bubbling to parent forms
                    
                    const start = document.getElementById('block-period-start').value;
                    const end = document.getElementById('block-period-end').value;
                    const notes = document.getElementById('block-period-notes').value;
                    const error = document.getElementById('block-period-error');
                    const submitBtn = document.getElementById('block-period-save-btn');
                    
                    error.classList.add('hidden');
                    error.textContent = '';
                    
                    // Validation
                    if (!start || !end) {
                        error.textContent = 'Start and end dates are required.';
                        error.classList.remove('hidden');
                        return;
                    }
                    if (end < start) {
                        error.textContent = 'End date cannot be before start date.';
                        error.classList.remove('hidden');
                        return;
                    }
                    
                    // Update local array
                    const period = { start, end, notes };
                    if (editingIndex !== null) {
                        blockPeriods[editingIndex] = period;
                    } else {
                        blockPeriods.push(period);
                    }
                    
                    // Show saving state
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Saving…</span>';
                    submitBtn.disabled = true;
                    
                    xsDebugLog('Saving block period to database:', period);
                    xsDebugLog('Full block periods array:', blockPeriods);
                    
                    try {
                        const { header, token } = xsGetCsrf();
                        
                        // Save to database via API
                        const response = await fetch("<?= base_url('api/v1/settings') ?>", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                ...(token ? { [header]: token } : {})
                            },
                            body: JSON.stringify({
                                'business.blocked_periods': JSON.stringify(blockPeriods)
                            })
                        });
                        
                        xsDebugLog('Block period API Response status:', response.status);
                        
                        if (!response.ok) {
                            let errorId = '';
                            try {
                                const errJson = await response.json();
                                errorId = errJson?.error?.error_id || errJson?.error_id || '';
                            } catch (e) {
                                // ignore non-JSON errors
                            }
                            throw new Error(`Save failed (HTTP ${response.status})${errorId ? ` [Error ID: ${errorId}]` : ''}`);
                        }
                        
                        const result = await response.json();
                        xsDebugLog('Block period API Response data:', result);
                        
                        if (!result?.ok) {
                            throw new Error(result?.message || 'Unable to save block period.');
                        }
                        
                        // Update hidden input and UI
                        saveBlockPeriods();
                        
                        // Close modal
                        closeModal();
                        
                        // Reset button
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                        
                        xsDebugLog('Block period saved successfully!');
                        
                        // Show success toast
                        window.XSNotify?.toast?.({
                            type: 'success',
                            title: '✓ Block Period Saved',
                            message: editingIndex !== null ? 'Block period updated successfully.' : 'Block period added successfully.',
                            autoClose: true,
                            duration: 4000
                        });
                        
                        if (!window.XSNotify?.toast) {
                            alert('✓ Block period saved successfully!');
                        }
                        
                    } catch (saveErr) {
                        console.error('❌ Block period save failed:', saveErr);
                        
                        // Revert local changes on error
                        if (editingIndex !== null) {
                            // Restore previous value (reload from hidden input)
                            try {
                                blockPeriods = JSON.parse(document.getElementById('blocked_periods_json')?.value || '[]');
                            } catch { }
                        } else {
                            // Remove the newly added period
                            blockPeriods.pop();
                        }
                        
                        // Reset button
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                        
                        // Show error in modal
                        error.textContent = saveErr?.message || 'Failed to save block period. Please try again.';
                        error.classList.remove('hidden');
                        
                        // Show error toast
                        window.XSNotify?.toast?.({
                            type: 'error',
                            title: '✗ Save Failed',
                            message: saveErr?.message || 'Failed to save block period. Please try again.',
                            autoClose: false
                        });
                        
                        if (!window.XSNotify?.toast) {
                            alert('✗ Failed to save block period: ' + (saveErr?.message || 'Unknown error'));
                        }
                    }
                });
            }

            // Edit/Delete actions
            const periodsList = document.getElementById('block-periods-list');
            if (periodsList) {
                periodsList.addEventListener('click', async function(e) {
                    if (e.target.closest('[data-edit]')) {
                        const idx = parseInt(e.target.closest('[data-edit]').getAttribute('data-edit'));
                        openModal('edit', idx);
                    } else if (e.target.closest('[data-delete]')) {
                        const idx = parseInt(e.target.closest('[data-delete]').getAttribute('data-delete'));
                        
                        if (!confirm('Are you sure you want to delete this blocked period?')) {
                            return;
                        }
                        
                        xsDebugLog('Deleting block period at index:', idx);
                        
                        // Remove from array
                        const deletedPeriod = blockPeriods[idx];
                        blockPeriods.splice(idx, 1);
                        
                        xsDebugLog('Remaining block periods:', blockPeriods);
                        
                        try {
                            const { header, token } = xsGetCsrf();
                            
                            // Save to database via API
                            const response = await fetch("<?= base_url('api/v1/settings') ?>", {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    ...(token ? { [header]: token } : {})
                                },
                                body: JSON.stringify({
                                    'business.blocked_periods': JSON.stringify(blockPeriods)
                                })
                            });
                            
                            xsDebugLog('Delete block period API Response status:', response.status);
                            
                            if (!response.ok) {
                                let errorId = '';
                                try {
                                    const errJson = await response.json();
                                    errorId = errJson?.error?.error_id || errJson?.error_id || '';
                                } catch (e) {
                                    // ignore non-JSON errors
                                }
                                throw new Error(`Delete failed (HTTP ${response.status})${errorId ? ` [Error ID: ${errorId}]` : ''}`);
                            }
                            
                            const result = await response.json();
                            xsDebugLog('Delete block period API Response data:', result);
                            
                            if (!result?.ok) {
                                throw new Error(result?.message || 'Unable to delete block period.');
                            }
                            
                            // Update hidden input and UI
                            saveBlockPeriods();
                            
                            xsDebugLog('Block period deleted successfully!');
                            
                            // Show success toast
                            window.XSNotify?.toast?.({
                                type: 'success',
                                title: '✓ Block Period Deleted',
                                message: 'Block period deleted successfully.',
                                autoClose: true,
                                duration: 4000
                            });
                            
                            if (!window.XSNotify?.toast) {
                                alert('✓ Block period deleted successfully!');
                            }
                            
                        } catch (error) {
                            console.error('❌ Block period delete failed:', error);
                            
                            // Revert deletion on error
                            blockPeriods.splice(idx, 0, deletedPeriod);
                            saveBlockPeriods();
                            
                            // Show error toast
                            window.XSNotify?.toast?.({
                                type: 'error',
                                title: '✗ Delete Failed',
                                message: error?.message || 'Failed to delete block period. Please try again.',
                                autoClose: false
                            });
                            
                            if (!window.XSNotify?.toast) {
                                alert('✗ Failed to delete block period: ' + (error?.message || 'Unknown error'));
                            }
                        }
                    }
                });
            }

            // Modal close on background click
            const modal = document.getElementById('block-period-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) closeModal();
                });
            }

            // Initial render
            renderBlockPeriods();
        })();

        // ─── Main Settings API Init ─────────────────────────────
        // General tab API integration: load on view and save via POST to /api/v1/settings
        // Robust initialization that works on both first page load and SPA navigation.
        // The inline initSettingsApi() call below handles both cases reliably.

        // Live-update the sidebar brand title when company name changes
        (function wireSidebarBrandSync(){
            function setBrandName(name){
                const el = document.getElementById('sidebarBrandName');
                if (!el) return;
                const trimmed = (name || '').trim();
                el.textContent = trimmed !== '' ? trimmed : 'WebSchedulr';
            }
            function bindInput(){
                const panel = document.getElementById('spa-content')?.querySelector('#panel-general');
                const input = panel?.querySelector('input[name="company_name"]');
                if (!input || input.dataset.brandSync === 'true') return;
                const handler = (e) => setBrandName(e.target.value);
                input.addEventListener('input', handler);
                input.addEventListener('change', handler);
                input.dataset.brandSync = 'true';
            }
            // Bind immediately (works for both first-load and SPA re-execution)
            bindInput();
        })();

        function initSettingsApi() {
            const root = document.getElementById('spa-content');
            xsDebugLog('Settings: initSettingsApi called, root found:', !!root);
            if (!root) return;

            // Initialize General Settings Form
            initGeneralSettingsForm();
            
            // Initialize other tab forms
            initTabForm('localization');
            initTabForm('booking');
            initTabForm('business');
            initTabForm('legal');
            initTabForm('integrations');
            
            initCustomFieldToggles();
        }

        // Initialize immediately — works on both first page load and SPA re-navigation
        // (scripts are re-executed by SPA after content injection).
        initSettingsApi();

        // Safety net A: requestAnimationFrame — handles rare edge cases where the DOM
        // isn't fully settled at inline-script execution time.
        if (!document.getElementById('general-settings-form')?.dataset.apiWired) {
            requestAnimationFrame(function() {
                if (!document.getElementById('general-settings-form')?.dataset.apiWired) {
                    initSettingsApi();
                }
            });
        }

        // Safety net B: spa:navigated — fires after initTabsInSpaContent() completes,
        // guaranteeing the DOM is fully settled. Registered once per page-visit to
        // avoid accumulation across navigations.
        (function registerSettingsSpaListener() {
            if (window._settingsSpaNavListenerKey === window._settingsSpaInitGeneration) return;
            const thisGeneration = (window._settingsSpaInitGeneration || 0) + 1;
            window._settingsSpaInitGeneration = thisGeneration;
            window._settingsSpaNavListenerKey = thisGeneration;

            function onSpaNavigatedSettings() {
                if (document.getElementById('general-settings-form')) {
                    initSettingsApi();
                }
            }

            // Remove previously registered listener (if any) to prevent accumulation
            if (window._settingsSpaNavHandler) {
                document.removeEventListener('spa:navigated', window._settingsSpaNavHandler);
            }
            window._settingsSpaNavHandler = onSpaNavigatedSettings;
            document.addEventListener('spa:navigated', window._settingsSpaNavHandler);
        })();

        function initGeneralSettingsForm() {
            const form = document.getElementById('general-settings-form');
            if (!form || form.dataset.apiWired === 'true') return;

            const generalPanel = form.querySelector('#panel-general');
            const saveBtn = document.getElementById('save-general-btn');
            const btnEdit = document.getElementById('general-edit-btn');
            const btnCancel = document.getElementById('general-cancel-btn');
            const logoInput = document.getElementById('company_logo');
            const logoImg = document.getElementById('company_logo_preview_img');
            const iconInput = document.getElementById('company_icon');
            const iconImg = document.getElementById('company_icon_preview_img');
            const csrfInput = form.querySelector('input[type="hidden"][name*="csrf"]');

            xsDebugLog('General Settings: Found elements:', {
                generalPanel: !!generalPanel,
                saveBtn: !!saveBtn,
                btnEdit: !!btnEdit,
                btnCancel: !!btnCancel,
                logoInput: !!logoInput,
                logoImg: !!logoImg,
                iconInput: !!iconInput,
                iconImg: !!iconImg
            });

            if (!generalPanel || !saveBtn || !btnEdit) {
                return;
            }

            const lockableFields = Array.from(generalPanel.querySelectorAll('input:not([type="hidden"]), textarea, select'))
                .filter(el => el.id !== 'company_logo' && el.id !== 'company_icon');

            const fieldMap = {
                company_name: 'general.company_name',
                company_email: 'general.company_email',
                company_link: 'general.company_link',
                telephone_number: 'general.telephone_number',
                mobile_number: 'general.mobile_number',
                business_address: 'general.business_address'
            };

            const apiEndpoint = "<?= base_url('api/v1/settings') ?>";
            const logoEndpoint = "<?= base_url('api/v1/settings/logo') ?>";
            const iconEndpoint = "<?= base_url('api/v1/settings/icon') ?>";

            let editing = false;
            let hasChanges = false;
            let initialValues = collectCurrentValues();
            let initialLogoSrc = logoImg ? (logoImg.dataset.src || logoImg.src || '') : '';
            let initialIconSrc = iconImg ? (iconImg.dataset.src || iconImg.src || '') : '';

            function collectCurrentValues() {
                const values = {};
                Object.keys(fieldMap).forEach(name => {
                    const el = form.elements[name];
                    if (!el || el.type === 'file') return;
                    values[name] = el.value ?? '';
                });
                return values;
            }

            function applyValues(values) {
                Object.entries(values).forEach(([name, value]) => {
                    const el = form.elements[name];
                    if (!el || el.type === 'file') return;
                    el.value = value ?? '';
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            function checkForChanges() {
                if (!editing) return false;
                
                const currentValues = collectCurrentValues();
                const hasFieldChanges = Object.keys(fieldMap).some(name => {
                    return currentValues[name] !== initialValues[name];
                });
                
                const currentLogoSrc = logoImg ? (logoImg.src || '') : '';
                const hasLogoChange = logoInput?.files?.length > 0 || currentLogoSrc !== initialLogoSrc;
                
                return hasFieldChanges || hasLogoChange;
            }

            function updateSaveButtonState() {
                hasChanges = checkForChanges();
                if (saveBtn) {
                    saveBtn.disabled = !editing;
                    saveBtn.classList.toggle('opacity-60', !editing);
                    saveBtn.classList.toggle('cursor-not-allowed', !editing);
                }
            }

            function setLockedState(locked) {
                lockableFields.forEach(el => {
                    el.disabled = locked;
                    el.classList.toggle('cursor-not-allowed', locked);
                    el.classList.toggle('bg-gray-100', locked);
                    el.classList.toggle('dark:bg-gray-800/70', locked);
                    el.setAttribute('aria-readonly', locked ? 'true' : 'false');
                });
                if (logoInput) {
                    logoInput.disabled = locked;
                    logoInput.classList.toggle('cursor-not-allowed', locked);
                    logoInput.classList.toggle('bg-gray-100', locked);
                    logoInput.classList.toggle('dark:bg-gray-800/70', locked);
                }
                if (iconInput) {
                    iconInput.disabled = locked;
                    iconInput.classList.toggle('cursor-not-allowed', locked);
                    iconInput.classList.toggle('bg-gray-100', locked);
                    iconInput.classList.toggle('dark:bg-gray-800/70', locked);
                }
                if (btnEdit) btnEdit.classList.toggle('hidden', !locked);
                if (btnCancel) btnCancel.classList.toggle('hidden', locked);
                
                updateSaveButtonState();
            }

            function getCsrf() {
                const base = xsGetCsrf();
                // Also check appConfig and form-level csrfInput as fallbacks
                const header = base.header || window.appConfig?.csrfHeaderName || 'X-CSRF-TOKEN';
                const token  = base.token || window.appConfig?.csrfToken || csrfInput?.value || '';
                return { header, token };
            }

            function updateCsrfFromResponse(response) {
                if (!response) return;
                const newToken = response.headers.get('X-CSRF-TOKEN') || response.headers.get('x-csrf-token');
                if (newToken && csrfInput) {
                    csrfInput.value = newToken;
                }
            }

            function setSavingState(isSaving) {
                if (!saveBtn) return;
                if (isSaving) {
                    if (!saveBtn.dataset.originalLabel) {
                        saveBtn.dataset.originalLabel = saveBtn.innerHTML;
                    }
                    saveBtn.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Saving…</span>';
                    saveBtn.disabled = true;
                    saveBtn.classList.add('opacity-60', 'cursor-not-allowed');
                } else {
                    if (saveBtn.dataset.originalLabel) {
                        saveBtn.innerHTML = saveBtn.dataset.originalLabel;
                        delete saveBtn.dataset.originalLabel;
                    }
                    updateSaveButtonState();
                }
            }

            function previewLogo(file) {
                if (!logoImg || !file || !file.type?.startsWith('image/')) return;
                const reader = new FileReader();
                reader.onload = (event) => {
                    logoImg.src = event.target?.result || '';
                    logoImg.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }

            function previewIcon(file) {
                if (!iconImg || !file || !file.type?.startsWith('image/')) return;
                const reader = new FileReader();
                reader.onload = (event) => {
                    iconImg.src = event.target?.result || '';
                    iconImg.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }

            setLockedState(true);

            // Track changes on all lockable fields
            lockableFields.forEach(field => {
                field.addEventListener('input', updateSaveButtonState);
                field.addEventListener('change', updateSaveButtonState);
            });

            if (btnEdit) {
                xsDebugLog('General Settings: Attaching Edit button listener');
                btnEdit.addEventListener('click', () => {
                    xsDebugLog('General Edit clicked');
                    editing = true;
                    setLockedState(false);
                    // Focus first editable field for keyboard accessibility
                    // (avoid saveBtn.focus() which unexpectedly scrolls the page down)
                    lockableFields[0]?.focus();
                });
            }

            if (btnCancel) {
                btnCancel.addEventListener('click', () => {
                    xsDebugLog('General Cancel clicked');
                    editing = false;
                    hasChanges = false;
                    applyValues(initialValues);
                    if (logoImg && initialLogoSrc) {
                        logoImg.src = initialLogoSrc;
                        logoImg.dataset.src = initialLogoSrc;
                        logoImg.classList.remove('hidden');
                    }
                    if (logoImg && !initialLogoSrc) {
                        logoImg.classList.add('hidden');
                    }
                    if (logoInput) {
                        logoInput.value = '';
                    }
                    setLockedState(true);
                });
            }

            if (logoInput) {
                logoInput.addEventListener('change', (event) => {
                    const file = event.target?.files?.[0];
                    if (file) {
                        previewLogo(file);
                    } else if (logoImg && initialLogoSrc) {
                        logoImg.src = initialLogoSrc;
                    }
                    updateSaveButtonState();
                });
            }

            if (iconInput) {
                iconInput.addEventListener('change', (event) => {
                    const file = event.target?.files?.[0];
                    if (file) {
                        previewIcon(file);
                    } else if (iconImg && initialIconSrc) {
                        iconImg.src = initialIconSrc;
                    }
                    updateSaveButtonState();
                });
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                
                xsDebugLog('General form submitted - editing:', editing);

                if (!editing) {
                    window.XSNotify?.toast?.({
                        type: 'info',
                        title: 'Locked',
                        message: 'Click Edit before saving your changes.',
                        autoClose: true
                    });
                    return;
                }

                if (!form.reportValidity()) {
                    form.reportValidity();
                    return;
                }

                const { header, token } = getCsrf();
                const payload = {};
                Object.entries(fieldMap).forEach(([name, key]) => {
                    const el = form.elements[name];
                    if (!el || el.type === 'file') return;
                    payload[key] = el.value ?? '';
                });

                xsDebugLog('Saving general settings:', payload);

                const previousEditingState = editing;
                setSavingState(true);
                setLockedState(true);

                try {
                    const response = await fetch(apiEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(token ? { [header]: token } : {})
                        },
                        body: JSON.stringify(payload)
                    });

                    updateCsrfFromResponse(response);

                    xsDebugLog('API Response status:', response.status);

                    if (!response.ok) {
                        let errorId = '';
                        try {
                            const errJson = await response.json();
                            errorId = errJson?.error?.error_id || errJson?.error_id || '';
                        } catch (e) {
                            // ignore non-JSON errors
                        }
                        throw new Error(`Save failed (HTTP ${response.status})${errorId ? ` [Error ID: ${errorId}]` : ''}`);
                    }

                    const result = await response.json();
                    xsDebugLog('API Response data:', result);
                    
                    if (!result?.ok) {
                        throw new Error(result?.message || 'Unable to save settings.');
                    }

                    let uploadedLogoUrl = null;
                    if (logoInput?.files?.length) {
                        const fileData = new FormData();
                        fileData.append('company_logo', logoInput.files[0]);
                        if (csrfInput?.name && csrfInput.value) {
                            fileData.append(csrfInput.name, csrfInput.value);
                        }

                        const uploadToken = csrfInput?.value || token;

                        const logoResponse = await fetch(logoEndpoint, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                ...(uploadToken ? { [header]: uploadToken } : {})
                            },
                            body: fileData
                        });

                        updateCsrfFromResponse(logoResponse);

                        if (!logoResponse.ok) {
                            throw new Error(`Logo upload failed (HTTP ${logoResponse.status})`);
                        }

                        const logoResult = await logoResponse.json();
                        if (!logoResult?.ok) {
                            throw new Error(logoResult?.message || 'Logo upload failed.');
                        }
                        uploadedLogoUrl = logoResult.url || null;
                    }

                    let uploadedIconUrl = null;
                    if (iconInput?.files?.length) {
                        const fileData = new FormData();
                        fileData.append('company_icon', iconInput.files[0]);
                        if (csrfInput?.name && csrfInput.value) {
                            fileData.append(csrfInput.name, csrfInput.value);
                        }

                        const uploadToken = csrfInput?.value || token;

                        const iconResponse = await fetch(iconEndpoint, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                ...(uploadToken ? { [header]: uploadToken } : {})
                            },
                            body: fileData
                        });

                        updateCsrfFromResponse(iconResponse);

                        if (!iconResponse.ok) {
                            throw new Error(`Icon upload failed (HTTP ${iconResponse.status})`);
                        }

                        const iconResult = await iconResponse.json();
                        if (!iconResult?.ok) {
                            throw new Error(iconResult?.message || 'Icon upload failed.');
                        }
                        uploadedIconUrl = iconResult.url || null;
                    }

                    initialValues = collectCurrentValues();
                    editing = false;
                    hasChanges = false;
                    setSavingState(false);
                    setLockedState(true);

                    if (uploadedLogoUrl && logoImg) {
                        logoImg.src = uploadedLogoUrl;
                        logoImg.dataset.src = uploadedLogoUrl;
                        logoImg.classList.remove('hidden');
                    }
                    if (logoInput) {
                        logoInput.value = '';
                    }
                    if (logoImg && !uploadedLogoUrl) {
                        initialLogoSrc = logoImg.dataset.src || logoImg.src || initialLogoSrc;
                    } else if (uploadedLogoUrl) {
                        initialLogoSrc = uploadedLogoUrl;
                    }

                    if (uploadedIconUrl && iconImg) {
                        iconImg.src = uploadedIconUrl;
                        iconImg.dataset.src = uploadedIconUrl;
                        iconImg.classList.remove('hidden');
                    }
                    if (iconInput) {
                        iconInput.value = '';
                    }
                    if (iconImg && !uploadedIconUrl) {
                        initialIconSrc = iconImg.dataset.src || iconImg.src || initialIconSrc;
                    } else if (uploadedIconUrl) {
                        initialIconSrc = uploadedIconUrl;
                    }

                    xsDebugLog('General settings saved successfully!');
                    
                    window.XSNotify?.toast?.({
                        type: 'success',
                        title: '✓ Settings Updated',
                        message: 'Your general settings were saved successfully.',
                        autoClose: true,
                        duration: 4000
                    });
                    
                    if (!window.XSNotify?.toast) {
                        alert('✓ General settings updated successfully!');
                    }
                } catch (error) {
                    console.error('❌ General settings save failed:', error);
                    editing = previousEditingState;
                    setSavingState(false);
                    setLockedState(!editing);
                    
                    window.XSNotify?.toast?.({
                        type: 'error',
                        title: '✗ Save Failed',
                        message: error?.message || 'Failed to save general settings. Please try again.',
                        autoClose: false
                    });
                    
                    if (!window.XSNotify?.toast) {
                        alert('✗ Failed to save: ' + (error?.message || 'Unknown error'));
                    }
                }
            });

            form.dataset.apiWired = 'true';
        }

        // Generic tab form initialization for non-General tabs
        function initTabForm(tabName) {
            const form = document.getElementById(`${tabName}-settings-form`);
            if (!form || form.dataset.apiWired === 'true') return;

            const saveBtn = document.getElementById(`save-${tabName}-btn`);
            const panel = document.getElementById(`panel-${tabName}`);
            
            if (!saveBtn || !panel) {
                console.warn(`${tabName} Settings: Required elements not found`);
                return;
            }

            xsDebugLog(`${tabName} Settings: Initializing form`);

            // Track changes
            const formInputs = Array.from(panel.querySelectorAll('input, textarea, select'));
            let hasChanges = false;

            function updateSaveButtonState() {
                if (saveBtn) {
                    saveBtn.disabled = !hasChanges;
                    saveBtn.classList.toggle('opacity-60', !hasChanges);
                    saveBtn.classList.toggle('cursor-not-allowed', !hasChanges);
                }
            }

            formInputs.forEach(input => {
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

                xsDebugLog(`${tabName} form submitted`);

                if (!form.reportValidity()) {
                    form.reportValidity();
                    return;
                }

                // Get CSRF token
                const csrfInput = form.querySelector('input[type="hidden"][name*="csrf"]');
                const { header, token: metaToken } = xsGetCsrf();
                const token = metaToken || csrfInput?.value || '';

                // Collect form data, ensuring unchecked checkboxes are recorded as "0"
                const payload = {};

                formInputs.forEach(input => {
                    const name = input.name;
                    if (!name || name.includes('csrf') || name === 'form_source') {
                        return;
                    }

                    let value;
                    if (input.type === 'checkbox') {
                        value = input.checked ? '1' : '0';
                    } else if (input.type === 'radio') {
                        if (!input.checked) {
                            return; // only capture selected radio values
                        }
                        value = input.value;
                    } else if (input.type === 'file') {
                        return; // file uploads handled separately if needed
                    } else {
                        value = input.value ?? '';
                    }

                    let key = name;

                    // Convert snake_case names prefixed with the tab into dot notation (e.g., booking_address_display -> booking.address_display)
                    if (name.startsWith(`${tabName}_`)) {
                        key = `${tabName}.${name.substring(tabName.length + 1)}`;
                    } else if (!name.startsWith(`${tabName}.`)) {
                        key = `${tabName}.${name}`;
                    }

                    payload[key] = value;
                });

                xsDebugLog(`Saving ${tabName} settings:`, payload);

                // Show saving state
                const originalLabel = saveBtn.innerHTML;
                saveBtn.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Saving…</span>';
                saveBtn.disabled = true;

                try {
                    const response = await fetch("<?= base_url('api/v1/settings') ?>", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(token ? { [header]: token } : {})
                        },
                        body: JSON.stringify(payload)
                    });

                    xsDebugLog(`${tabName} API Response status:`, response.status);

                    if (!response.ok) {
                        let errorId = '';
                        try {
                            const errJson = await response.json();
                            errorId = errJson?.error?.error_id || errJson?.error_id || '';
                        } catch (e) {
                            // ignore non-JSON errors
                        }
                        throw new Error(`Save failed (HTTP ${response.status})${errorId ? ` [Error ID: ${errorId}]` : ''}`);
                    }

                    const result = await response.json();
                    xsDebugLog(`${tabName} API Response data:`, result);

                    if (!result?.ok) {
                        throw new Error(result?.message || 'Unable to save settings.');
                    }

                    // Reset change tracking
                    hasChanges = false;
                    saveBtn.innerHTML = originalLabel;
                    updateSaveButtonState();

                    xsDebugLog(`✅ ${tabName} settings saved successfully!`);

                    // Dispatch custom event for settings save (useful for time format updates)
                    const changedKeys = Object.keys(payload);
                    document.dispatchEvent(new CustomEvent('settingsSaved', {
                        detail: changedKeys
                    }));

                    window.XSNotify?.toast?.({
                        type: 'success',
                        title: '✓ Settings Updated',
                        message: `Your ${tabName} settings were saved successfully.`,
                        autoClose: true,
                        duration: 4000
                    });

                    if (!window.XSNotify?.toast) {
                        alert(`✓ ${tabName} settings updated successfully!`);
                    }
                } catch (error) {
                    console.error(`❌ ${tabName} settings save failed:`, error);
                    
                    saveBtn.innerHTML = originalLabel;
                    hasChanges = true;
                    updateSaveButtonState();

                    window.XSNotify?.toast?.({
                        type: 'error',
                        title: '✗ Save Failed',
                        message: error?.message || `Failed to save ${tabName} settings. Please try again.`,
                        autoClose: false
                    });

                    if (!window.XSNotify?.toast) {
                        alert(`✗ Failed to save: ` + (error?.message || 'Unknown error'));
                    }
                }
            });

            form.dataset.apiWired = 'true';
        }

        function initCustomFieldToggles() {
            // Handle custom field enable/disable toggles
            // Guard: skip toggles already initialized
            document.querySelectorAll('.custom-field-toggle').forEach(toggle => {
                if (toggle.dataset.toggleWired === 'true') return;
                toggle.dataset.toggleWired = 'true';
                
                toggle.addEventListener('change', function() {
                    const container = this.closest('.custom-field-container');
                    const settings = container.querySelector('.custom-field-settings');
                    const inputs = settings.querySelectorAll('.custom-field-input');
                    const isEnabled = this.checked;

                    settings.classList.toggle('opacity-50', !isEnabled);
                    settings.classList.toggle('pointer-events-none', !isEnabled);
                    inputs.forEach(input => {
                        input.disabled = !isEnabled;
                    });
                });
                
                // Trigger initial state
                toggle.dispatchEvent(new Event('change'));
            });
        }

        // NOTE: initCustomFieldToggles is called from initSettingsApi(), no separate listeners needed

        // ─── Time Format Handler ────────────────────────────────
            // Dynamic import works in both regular and SPA-injected scripts
            (async function initTimeFormatting() {
                try {
                    const module = await import('<?= base_url('build/assets/time-format-handler.js') ?>');
                    const timeFormatHandler = module.default || module;
                    await timeFormatHandler.init();
                    timeFormatHandler.addFormattedDisplays();
                    xsDebugLog('[Settings] Time format handler initialized with format:', timeFormatHandler.getFormat());
                } catch (err) {
                    console.warn('[Settings] Time format handler unavailable:', err.message);
                }
            })();

        // ─── Database Settings Tab ──────────────────────────────
        (function initDatabaseSettingsTab() {
            const backupToggle = document.getElementById('backup-enabled-toggle');
            const createBackupBtn = document.getElementById('create-backup-btn');
            const viewBackupsBtn = document.getElementById('view-backups-btn');
            const backupProgress = document.getElementById('backup-progress');
            const backupError = document.getElementById('backup-error');
            const backupSuccess = document.getElementById('backup-success');
            const lastBackupDetails = document.getElementById('last-backup-details');
            const backupListModal = document.getElementById('backup-list-modal');
            const closeBackupModal = document.getElementById('close-backup-modal');
            const backupListContent = document.getElementById('backup-list-content');

            // Database info elements
            const dbInfoType = document.getElementById('db-info-type');
            const dbInfoName = document.getElementById('db-info-name');
            const dbInfoHost = document.getElementById('db-info-host');

            if (!backupToggle || !createBackupBtn) return;

            const API_BASE = '<?= base_url('api/database-backup') ?>';

            // Load initial status
            async function loadBackupStatus() {
                try {
                    const response = await fetch(`${API_BASE}/status`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });
                    
                    if (!response.ok) {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.messages?.error || errorData.error?.message || 'Failed to load backup status');
                    }

                    const data = await response.json();

                    // Update toggle state
                    backupToggle.checked = data.backup_enabled;
                    createBackupBtn.disabled = !data.backup_enabled;

                    // Update database info
                    if (dbInfoType) dbInfoType.textContent = data.database?.type || 'Unknown';
                    if (dbInfoName) dbInfoName.textContent = data.database?.name || 'Unknown';
                    if (dbInfoHost) dbInfoHost.textContent = data.database?.host || 'Unknown';

                    // Update last backup info
                    // If status API has no last_backup record, try fetching from /list
                    if (data.last_backup?.time && data.last_backup?.filename) {
                        updateLastBackupDisplay(data.last_backup);
                    } else {
                        await fetchLatestBackupFromList();
                    }

                } catch (error) {
                    console.error('Failed to load backup status:', error);
                    if (dbInfoType) dbInfoType.textContent = 'Error loading';
                    if (dbInfoName) dbInfoName.textContent = 'Error loading';
                    if (dbInfoHost) dbInfoHost.textContent = 'Error loading';
                }
            }

            // Fetch the most recent backup from the /list endpoint as fallback
            async function fetchLatestBackupFromList() {
                try {
                    const response = await fetch(`${API_BASE}/list`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });
                    if (!response.ok) return;
                    const data = await response.json();
                    if (data.backups?.length > 0) {
                        const latest = data.backups[0]; // sorted descending by date
                        updateLastBackupDisplay({
                            time: latest.created,
                            filename: latest.filename,
                            size_formatted: latest.size_formatted
                        });
                    } else {
                        updateLastBackupDisplay(null);
                    }
                } catch {
                    updateLastBackupDisplay(null);
                }
            }

            // Update last backup display
            function updateLastBackupDisplay(lastBackup) {
                if (!lastBackupDetails) return;

                if (lastBackup?.time && lastBackup?.filename) {
                    lastBackupDetails.innerHTML = `
                        <div class="flex-1">
                            <p class="text-sm text-gray-900 dark:text-gray-100">${xsEscapeHtml(lastBackup.time)}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                ${xsEscapeHtml(lastBackup.filename)} 
                                <span class="text-gray-400">(${xsEscapeHtml(lastBackup.size_formatted || 'Unknown size')})</span>
                            </p>
                        </div>
                        <a href="${API_BASE}/download/${encodeURIComponent(lastBackup.filename)}" 
                           data-no-spa="true"
                           download="${xsEscapeHtml(lastBackup.filename)}"
                           class="text-blue-600 hover:text-blue-700 dark:text-blue-400 text-sm inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-base">download</span>
                            Download
                        </a>
                    `;
                } else {
                    lastBackupDetails.innerHTML = '<span class="text-sm text-gray-500 dark:text-gray-400">No backups yet</span>';
                }
            }

            // Toggle backup enabled
            backupToggle.addEventListener('change', async () => {
                const enabled = backupToggle.checked;
                
                try {
                    const response = await fetch(`${API_BASE}/toggle`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ enabled })
                    });

                    if (!response.ok) {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.messages?.error || errorData.error?.message || 'Failed to update setting');
                    }

                    createBackupBtn.disabled = !enabled;
                    
                } catch (error) {
                    console.error('Failed to toggle backup:', error);
                    // Revert toggle
                    backupToggle.checked = !enabled;
                    showError(error.message || 'Failed to update backup setting');
                }
            });

            // Create backup
            createBackupBtn.addEventListener('click', async () => {
                if (createBackupBtn.disabled) return;

                hideMessages();
                backupProgress.classList.remove('hidden');
                createBackupBtn.disabled = true;

                try {
                    const response = await fetch(`${API_BASE}/create`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.messages?.error || data.message || 'Backup failed');
                    }

                    // Update last backup display
                    updateLastBackupDisplay({
                        time: data.timestamp,
                        filename: data.filename,
                        size_formatted: data.size_formatted
                    });

                    showSuccess(`Backup created successfully: ${data.filename}`);

                } catch (error) {
                    console.error('Backup failed:', error);
                    showError(error.message || 'Failed to create backup');
                } finally {
                    backupProgress.classList.add('hidden');
                    createBackupBtn.disabled = !backupToggle.checked;
                }
            });

            // View all backups
            viewBackupsBtn.addEventListener('click', async () => {
                backupListModal.classList.remove('hidden');
                backupListModal.classList.add('flex');
                
                backupListContent.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <div class="loading-spinner mx-auto mb-2"></div>
                        Loading backups...
                    </div>
                `;

                try {
                    const response = await fetch(`${API_BASE}/list`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.messages?.error || errorData.error?.message || 'Failed to load backups');
                    }

                    const data = await response.json();

                    if (data.backups?.length === 0) {
                        backupListContent.innerHTML = `
                            <div class="text-center py-8 text-gray-500">
                                <span class="material-symbols-outlined text-4xl mb-2">folder_off</span>
                                <p>No backups found</p>
                            </div>
                        `;
                    } else {
                        backupListContent.innerHTML = `
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                ${data.backups.map(backup => `
                                    <div class="py-3 flex items-center justify-between gap-4">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">${xsEscapeHtml(backup.filename)}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                ${xsEscapeHtml(backup.created)} • ${xsEscapeHtml(backup.size_formatted)}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a href="${API_BASE}/download/${encodeURIComponent(backup.filename)}" 
                                               data-no-spa="true"
                                               download="${xsEscapeHtml(backup.filename)}"
                                               class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition"
                                               title="Download">
                                                <span class="material-symbols-outlined text-xl">download</span>
                                            </a>
                                            <button type="button" 
                                                    class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition delete-backup-btn"
                                                    data-filename="${xsEscapeHtml(backup.filename)}"
                                                    title="Delete">
                                                <span class="material-symbols-outlined text-xl">delete</span>
                                            </button>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        `;

                        // Add delete handlers
                        backupListContent.querySelectorAll('.delete-backup-btn').forEach(btn => {
                            btn.addEventListener('click', async () => {
                                const filename = btn.dataset.filename;
                                if (!confirm(`Delete backup "${filename}"?`)) return;

                                try {
                                    const delResponse = await fetch(`${API_BASE}/delete/${encodeURIComponent(filename)}`, {
                                        method: 'DELETE',
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },
                                        credentials: 'same-origin'
                                    });

                                    if (!delResponse.ok) {
                                        const errorData = await delResponse.json().catch(() => ({}));
                                        throw new Error(errorData.messages?.error || 'Failed to delete backup');
                                    }

                                    // Refresh list
                                    viewBackupsBtn.click();
                                    loadBackupStatus();

                                } catch (error) {
                                    console.error('Delete failed:', error);
                                    alert(error.message || 'Failed to delete backup');
                                }
                            });
                        });
                    }

                } catch (error) {
                    console.error('Failed to load backups:', error);
                    backupListContent.innerHTML = `
                        <div class="text-center py-8 text-red-500">
                            <span class="material-symbols-outlined text-4xl mb-2">error</span>
                            <p>Failed to load backups</p>
                        </div>
                    `;
                }
            });

            // Close modal
            closeBackupModal?.addEventListener('click', () => {
                backupListModal.classList.add('hidden');
                backupListModal.classList.remove('flex');
            });

            backupListModal?.addEventListener('click', (e) => {
                if (e.target === backupListModal) {
                    backupListModal.classList.add('hidden');
                    backupListModal.classList.remove('flex');
                }
            });

            // Helper functions
            function hideMessages() {
                backupError.classList.add('hidden');
                backupSuccess.classList.add('hidden');
            }

            function showError(message) {
                backupError.textContent = message;
                backupError.classList.remove('hidden');
            }

            function showSuccess(message) {
                backupSuccess.textContent = message;
                backupSuccess.classList.remove('hidden');
            }

            // Initialize when database tab is shown
            document.querySelectorAll('[data-tab="database"]').forEach(btn => {
                if (btn.dataset.dbTabWired === 'true') return;
                btn.dataset.dbTabWired = 'true';
                btn.addEventListener('click', () => {
                    loadBackupStatus();
                });
            });

            // Load immediately — data will populate when the tab becomes visible
            loadBackupStatus();
        })();
        </script>
<?= $this->endSection() ?>
