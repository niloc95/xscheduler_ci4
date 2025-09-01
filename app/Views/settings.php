<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/admin-sidebar', ['current_page' => 'settings']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Settings<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Settings" data-page-subtitle="Manage your application configuration">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand p-4 md:p-6 mb-6">

        <?php if (session()->getFlashdata('error')): ?>
            <div class="mb-4 p-3 rounded-lg border border-red-300/60 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200">
                <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('success')): ?>
            <div class="mb-4 p-3 rounded-lg border border-green-300/60 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200">
                <?= esc(session()->getFlashdata('success')) ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="overflow-x-auto">
            <nav class="flex gap-2 border-b border-gray-200 dark:border-gray-700" role="tablist" aria-label="Settings Sections">
                <button class="tab-btn active" data-tab="general">General</button>
                <button class="tab-btn" data-tab="users">Users</button>
                <button class="tab-btn" data-tab="localization">Localization</button>
                <button class="tab-btn" data-tab="booking">Booking</button>
                <button class="tab-btn" data-tab="business">Business Logic</button>
                <button class="tab-btn" data-tab="legal">Legal Contents</button>
                <button class="tab-btn" data-tab="integrations">Integrations</button>
                
            </nav>
        </div>

    <!-- Tab Panels -->
    <div id="settings-content">
    
    <form id="settingsForm" method="POST" action="<?= base_url('settings') ?>" enctype="multipart/form-data" class="mt-4 space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="form_source" value="main_settings_form">
            <!-- General Settings -->
            <section id="panel-general" class="tab-panel">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">General</h3>
                    <div class="flex items-center gap-2" id="general-actions">
                        <button type="button" id="general-edit-btn" class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Edit</button>
                        <button type="button" id="general-cancel-btn" class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hidden">Cancel</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-field">
                        <label class="form-label">Company Name <span class="req">*</span></label>
                        <input name="company_name" required class="form-input" placeholder="Acme Inc." />
                        <p class="form-help">Displayed throughout the system</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Company Email <span class="req">*</span></label>
                        <input type="email" name="company_email" required class="form-input" placeholder="info@acme.com" />
                        <p class="form-help">Used as sender/reply address for system emails</p>
                    </div>
                    <div class="form-field md:col-span-2">
                        <label class="form-label">Company Link <span class="req">*</span></label>
                        <input type="url" name="company_link" required class="form-input" placeholder="https://acme.com" />
                    </div>
                                        <div class="form-field">
                        <label class="form-label">Company Logo</label>
                        <div class="md:flex md:items-center md:gap-4">
                            <div class="md:flex-1">
                                <input id="company_logo" type="file" name="company_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-input file-input" />
                            </div>
                            <div id="company_logo_preview_container" class="mt-2 md:mt-0">
                                <?php $logoPreview = setting_url('general.company_logo'); if ($logoPreview): ?>
                                    <img id="company_logo_preview_img" src="<?= esc($logoPreview) ?>" data-src="<?= esc($logoPreview) ?>" alt="Current logo" class="h-16 w-auto rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1" style="object-fit: contain;" />
                                <?php else: ?>
                                    <img id="company_logo_preview_img" src="" alt="Logo preview" class="h-16 w-auto rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1 hidden" style="object-fit: contain;" />
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    
                </div>
            </section>

            <!-- Localization -->
            <section id="panel-localization" class="tab-panel hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-field">
                        <label class="form-label">Date Format</label>
                        <select name="date_format" class="form-input">
                            <option value="DMY">DMY</option>
                            <option value="MDY">MDY</option>
                            <option value="YMD">YMD</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Time Format</label>
                        <select name="time_format" class="form-input">
                            <option value="24h">HH:MM (24h)</option>
                            <option value="12h">hh:mm AM/PM (12h)</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">First Day of Week</label>
                        <select name="first_day" class="form-input">
                            <option>Sunday</option>
                            <option>Monday</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Default Language</label>
                        <select name="language" class="form-input">
                            <option>English</option>
                            <option>Portuguese-BR</option>
                            <option>Spanish</option>
                        </select>
                    </div>
                    <div class="form-field md:col-span-2">
                        <label class="form-label">Default Timezone</label>
                        <select name="timezone" class="form-input">
                            <option>UTC</option>
                            <option>Sao_Paulo -3:00</option>
                            <option>New_York -5:00</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Booking Settings -->
            <section id="panel-booking" class="tab-panel hidden">
                <div class="space-y-4">
                    <div class="form-field">
                        <label class="form-label">Booking Form Fields</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <?php foreach ([["name","Name"],["email","Email"],["phone","Phone"],["notes","Notes"]] as $f): ?>
                                <label class="inline-flex items-center gap-2 p-2 rounded-md border border-gray-200 dark:border-gray-700">
                                    <input type="checkbox" name="fields[]" value="<?= $f[0] ?>" class="checkbox">
                                    <span><?= $f[1] ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Custom Fields</label>
                        <textarea name="custom_fields" rows="3" class="form-input" placeholder='[{"key":"company","label":"Company"}]'></textarea>
                        <p class="form-help">JSON array of custom fields</p>
                    </div>
                </div>
            </section>

            <!-- Business Logic -->
            <section id="panel-business" class="tab-panel hidden">
                <div class="space-y-6">
                    <div class="form-field">
                        <label class="form-label">Default Working Hours</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <input type="time" name="work_start" class="form-input" value="09:00">
                            <input type="time" name="work_end" class="form-input" value="17:00">
                        </div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Breaks</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <input type="time" name="break_start" class="form-input" value="12:00">
                            <input type="time" name="break_end" class="form-input" value="13:00">
                        </div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Blocked Periods</label>
                        <textarea name="blocked_periods" rows="3" class="form-input" placeholder='["2025-12-25","2025-12-31"]'></textarea>
                        <p class="form-help">Holidays, closures, etc. (JSON array of dates)</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-field">
                            <label class="form-label">Rescheduling Rules</label>
                            <select name="reschedule" class="form-input">
                                <option>Up to 24h before</option>
                                <option>Up to 12h before</option>
                                <option>Not allowed</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Cancellation Rules</label>
                            <select name="cancel" class="form-input">
                                <option>Up to 24h before</option>
                                <option>Up to 12h before</option>
                                <option>Not allowed</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-field">
                            <label class="form-label">Future Booking Limit</label>
                            <select name="future_limit" class="form-input">
                                <option>30 days</option>
                                <option>60 days</option>
                                <option>90 days</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Appointment Status Options</label>
                            <input name="statuses" class="form-input" placeholder="booked,confirmed,completed,cancelled">
                        </div>
                    </div>
                </div>
            </section>

            <!-- Legal Contents -->
            <section id="panel-legal" class="tab-panel hidden">
                <div class="space-y-4">
                    <div class="form-field">
                        <label class="form-label">Cookie Notice</label>
                        <textarea name="cookie_notice" rows="3" class="form-input"></textarea>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Terms & Conditions</label>
                        <textarea name="terms" rows="6" class="form-input"></textarea>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Privacy Policy</label>
                        <textarea name="privacy" rows="6" class="form-input"></textarea>
                    </div>
                </div>
            </section>

            <!-- Integrations -->
            <section id="panel-integrations" class="tab-panel hidden">
                <div class="space-y-6">
                    <div class="form-field">
                        <label class="form-label">Webhooks</label>
                        <input name="webhook_url" type="url" class="form-input" placeholder="https://example.com/webhook" />
                        <p class="form-help">External notifications endpoint</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-field">
                            <label class="form-label">Analytics</label>
                            <select name="analytics" class="form-input">
                                <option>None</option>
                                <option>Google Analytics</option>
                                <option>Matomo</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">API Integrations</label>
                            <input name="api_integrations" class="form-input" placeholder="comma,separated,keys" />
                        </div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">LDAP Authentication</label>
                        <div class="space-y-2">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="ldap_enabled" class="checkbox"> Enable LDAP
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <input name="ldap_host" class="form-input" placeholder="ldap://host" />
                                <input name="ldap_dn" class="form-input" placeholder="cn=admin,dc=example,dc=com" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Users management within Settings -->
            <section id="panel-users" class="tab-panel hidden">
                <div class="space-y-6">
                    <div class="form-field">
                        <label class="form-label">Invite New User</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                            <input type="text" name="new_user_name" class="form-input" placeholder="Full name" />
                            <input type="email" name="new_user_email" class="form-input" placeholder="email@example.com" />
                            <select name="new_user_role" class="form-input">
                                <option value="admin">Admin</option>
                                <option value="provider">Provider</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <p class="form-help">Sends an invitation email with a temporary password.</p>
                    </div>

                    <div class="form-field">
                        <label class="form-label">User Defaults</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <select name="default_user_role" class="form-input">
                                <option value="provider">Provider</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="users_require_2fa" class="checkbox" />
                                <span>Require 2FA for all users</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-field">
                        <label class="form-label">Access Policies</label>
                        <textarea name="access_policies" rows="4" class="form-input" placeholder='{"providers":["schedule.read","appointments.manage"],"staff":["schedule.read" ]}'></textarea>
                        <p class="form-help">JSON map of role to allowed permissions.</p>
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button id="global-save-btn" type="submit" class="px-5 py-2.5 rounded-lg text-white" style="background-color: var(--md-sys-color-primary)">Save All Settings</button>
            </div>
        </form>
    </div>
</div>

        <script>
        // General tab API integration: load on view and save via PUT to /api/v1/settings
        document.addEventListener('DOMContentLoaded', () => initSettingsApi());
        document.addEventListener('spa:navigated', () => initSettingsApi());

        function initSettingsApi() {
                const root = document.getElementById('spa-content');
                if (!root) return;
                const generalPanel = root.querySelector('#panel-general');
                const form = root.querySelector('#settingsForm');
                if (!generalPanel || !form || form.dataset.apiWired === 'true') return;

                const apiBase = '<?= base_url('api/v1/settings') ?>';
                const btnEdit = generalPanel.querySelector('#general-edit-btn');
                const btnCancel = generalPanel.querySelector('#general-cancel-btn');
                const globalSaveBtn = form.querySelector('#global-save-btn');
                const inputs = Array.from(generalPanel.querySelectorAll('input, select, textarea'));
                let lastSaved = { company_name: '', company_email: '', company_link: '' };
                let editing = false;
                // Live preview refs for logo
                const logoInput = generalPanel.querySelector('#company_logo');
                const logoImg = generalPanel.querySelector('#company_logo_preview_img');
                const originalLogoSrc = logoImg ? logoImg.getAttribute('data-src') || '' : '';

                function applyValues(values) {
                    const { company_name, company_email, company_link } = values;
                    const nameEl = generalPanel.querySelector('[name="company_name"]');
                    const emailEl = generalPanel.querySelector('[name="company_email"]');
                    const linkEl = generalPanel.querySelector('[name="company_link"]');
                    if (nameEl) nameEl.value = company_name ?? '';
                    if (emailEl) emailEl.value = company_email ?? '';
                    if (linkEl) linkEl.value = company_link ?? '';
                }

                function collectValues() {
                    return {
                        company_name: (generalPanel.querySelector('[name="company_name"]').value || ''),
                        company_email: (generalPanel.querySelector('[name="company_email"]').value || ''),
                        company_link: (generalPanel.querySelector('[name="company_link"]').value || ''),
                    };
                }

                function setLockedState(locked) {
                    editing = !locked;
                    inputs.forEach(el => { el.disabled = locked; });
                    // Logo file input should also follow the locked state
                    const logoInputEl = document.getElementById('company_logo');
                    if (logoInputEl) logoInputEl.disabled = locked;
                    // Button toggles
                    if (locked) {
                        btnEdit?.classList.remove('hidden');
                        btnCancel?.classList.add('hidden');
                    } else {
                        btnEdit?.classList.add('hidden');
                        btnCancel?.classList.remove('hidden');
                    }
                    // Keep global bottom save visible so form can always be submitted
                    if (globalSaveBtn) globalSaveBtn.classList.remove('hidden');
                }

                // Load existing values
                fetch(`${apiBase}?prefix=general.`)
                    .then(r => r.json())
                    .then(({ ok, data }) => {
                        if (!ok || !data) return;
                        lastSaved = {
                            company_name: data['general.company_name'] ?? '',
                            company_email: data['general.company_email'] ?? '',
                            company_link: data['general.company_link'] ?? ''
                        };
                        applyValues(lastSaved);
                    })
                    .catch(() => {})
                    .finally(() => {
                        setLockedState(true); // default to locked with Edit visible
                    });

                // Edit -> unlock
                btnEdit?.addEventListener('click', () => {
                    console.log('Edit button clicked, setting editing to true');
                    setLockedState(false);
                });

                // Save button is now type="submit" - no event listener needed

                // Cancel -> revert and lock
                btnCancel?.addEventListener('click', () => {
                    applyValues(lastSaved);
                    // Don't reset logo preview on cancel - keep current saved logo
                    setLockedState(true);
                });

                // Save handler: allow normal POST submit; disable buttons to avoid double submits
                form.addEventListener('submit', (e) => {
                    console.log('Form submit event triggered, editing state:', editing);
                    // If we're in editing mode, allow normal form submission to /settings POST
                    if (editing) {
                        console.log('Submitting settings form normally');
                        // Don't prevent default - let it submit normally
                        btnCancel?.setAttribute('disabled', 'disabled');
                        return; // Allow normal form submission
                    } else {
                        // If not in edit mode, prevent submission
                        e.preventDefault();
                        console.log('Form submission prevented - not in edit mode');
                    }
                });

                // LOGO PREVIEW HANDLER (no auto-submit)
                // Handle file selection for logo preview only
                document.addEventListener('change', function(event) {
                    const target = event.target;
                    if (target && target.id === 'company_logo') {
                        console.log('Logo file selected');
                        const file = target.files && target.files[0];
                        if (!file) return;
                        
                        // Preview the image immediately
                        const img = document.getElementById('company_logo_preview_img');
                        if (img && file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                img.src = e.target.result;
                                img.classList.remove('hidden');
                                console.log('Logo preview updated');
                            };
                            reader.readAsDataURL(file);
                        }
                        
                        // NO AUTO-SUBMIT - user must click Save All Settings
                    }
                }, false);

                form.dataset.apiWired = 'true';
        }
        </script>
<?= $this->endSection() ?>
