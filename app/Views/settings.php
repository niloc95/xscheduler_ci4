<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/admin-sidebar', ['current_page' => 'settings']) ?>
<?= $this->endSection() ?>

<?= $this->section('header_title') ?>Settings<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content" data-page-title="Settings" data-page-subtitle="Manage your application configuration">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-brand p-4 md:p-6 mb-6">

        <!-- Tabs -->
        <div class="overflow-x-auto">
            <nav class="flex gap-2 border-b border-gray-200 dark:border-gray-700" role="tablist" aria-label="Settings Sections">
                <button class="tab-btn active" data-tab="general">General</button>
                <button class="tab-btn" data-tab="localization">Localization</button>
                <button class="tab-btn" data-tab="booking">Booking</button>
                <button class="tab-btn" data-tab="business">Business Logic</button>
                <button class="tab-btn" data-tab="legal">Legal Contents</button>
                <button class="tab-btn" data-tab="integrations">Integrations</button>
                <button class="tab-btn" data-tab="users">Users</button>
            </nav>
        </div>

        <!-- Tab Panels -->
    <form id="settingsForm" class="mt-4 space-y-6">
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
                <button type="submit" class="px-5 py-2.5 rounded-lg text-white" style="background-color: var(--md-sys-color-primary)">Save Settings</button>
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
                // Load
                fetch(`${apiBase}?prefix=general.`)
                    .then(r => r.json())
                    .then(({ ok, data }) => {
                        if (!ok || !data) return;
                        const map = {
                            company_name: 'general.company_name',
                            company_email: 'general.company_email',
                            company_link: 'general.company_link',
                        };
                        Object.entries(map).forEach(([field, key]) => {
                            const input = generalPanel.querySelector(`[name="${field}"]`);
                            if (input && key in data) input.value = data[key] ?? '';
                        });
                    }).catch(() => {});

                // Save
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const fd = new FormData(form);
                    // Only general fields for now
                    const payload = {
                        'general.company_name': fd.get('company_name') || '',
                        'general.company_email': fd.get('company_email') || '',
                        'general.company_link': fd.get('company_link') || '',
                    };
                    try {
                        const res = await fetch(apiBase, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const json = await res.json();
                        if (json.ok) {
                            // Simple toast replacement
                            alert('Settings saved');
                        } else {
                            alert('Failed to save settings');
                        }
                    } catch (err) {
                        alert('Failed to save settings');
                    }
                }, { once: true });

                form.dataset.apiWired = 'true';
        }
        </script>
<?= $this->endSection() ?>
