<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/admin-sidebar', ['current_page' => 'settings']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Settings<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Settings">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand p-4 md:p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Settings</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Manage your application configuration</p>
            </div>
            
        </div>

        <!-- Tabs -->
        <div class="overflow-x-auto">
            <nav class="flex gap-2 border-b border-gray-200 dark:border-gray-700" role="tablist" aria-label="Settings Sections">
                <button class="tab-btn active" data-tab="general">General</button>
                <button class="tab-btn" data-tab="localization">Localization</button>
                <button class="tab-btn" data-tab="booking">Booking</button>
                <button class="tab-btn" data-tab="business">Business Logic</button>
                <button class="tab-btn" data-tab="legal">Legal Contents</button>
                <button class="tab-btn" data-tab="integrations">Integrations</button>
            </nav>
        </div>

        <!-- Tab Panels -->
        <form id="settingsForm" method="post" enctype="multipart/form-data" action="<?= base_url('settings') ?>" class="mt-4 space-y-6">
            <!-- General Settings -->
            <section id="panel-general" class="tab-panel">
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
                        <input type="file" name="company_logo" accept="image/*" class="form-input file-input" />
                        <p class="form-help">PNG/JPG, max 2MB</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Company Color</label>
                        <input type="color" name="company_color" class="form-input h-11 p-1" />
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
                            <?php foreach ([['name','Name'],['email','Email'],['phone','Phone'],['notes','Notes']] as $f): ?>
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

            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-lg text-white" style="background-color: var(--md-sys-color-primary)">Save Settings</button>
            </div>
        </form>
    </div>
</div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const tabs = document.querySelectorAll('.tab-btn');
        const panels = {
            general: document.getElementById('panel-general'),
            localization: document.getElementById('panel-localization'),
            booking: document.getElementById('panel-booking'),
            business: document.getElementById('panel-business'),
            legal: document.getElementById('panel-legal'),
            integrations: document.getElementById('panel-integrations'),
        };

        function showTab(name) {
            tabs.forEach(t => t.classList.remove('active'));
            Object.values(panels).forEach(p => p.classList.add('hidden'));
            document.querySelector(`[data-tab="${name}"]`)?.classList.add('active');
            panels[name]?.classList.remove('hidden');
        }

        tabs.forEach(btn => btn.addEventListener('click', () => showTab(btn.dataset.tab)));
        showTab('general');

    // Top Save button removed; bottom Save Settings button submits the form
    });
    </script>
<?= $this->endSection() ?>
