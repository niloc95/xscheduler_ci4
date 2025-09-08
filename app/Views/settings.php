<?= $this->extend('components/layout') ?>

<?= $this->section('sidebar') ?>
    <?= $this->include('components/unified-sidebar', ['current_page' => 'settings']) ?>
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
                <button class="tab-btn" data-tab="business">Business hours</button>
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
                        <input name="company_name" required class="form-input" placeholder="Acme Inc." value="<?= esc($settings['general.company_name'] ?? '') ?>" />
                        <p class="form-help">Displayed throughout the system</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Company Email <span class="req">*</span></label>
                        <input type="email" name="company_email" required class="form-input" placeholder="info@acme.com" value="<?= esc($settings['general.company_email'] ?? '') ?>" />
                        <p class="form-help">Used as sender/reply address for system emails</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Telephone Number</label>
                        <input type="tel" name="telephone_number" class="form-input" placeholder="(555) 123-4567" value="<?= esc($settings['general.telephone_number'] ?? '') ?>" />
                        <p class="form-help">Main business phone number</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Mobile Number</label>
                        <input type="tel" name="mobile_number" class="form-input" placeholder="(555) 987-6543" value="<?= esc($settings['general.mobile_number'] ?? '') ?>" />
                        <p class="form-help">Mobile contact number</p>
                    </div>
                    <div class="form-field md:col-span-2">
                        <label class="form-label">Company Link <span class="req">*</span></label>
                        <input type="url" name="company_link" required class="form-input" placeholder="https://acme.com" value="<?= esc($settings['general.company_link'] ?? '') ?>" />
                    </div>
                    <div class="form-field md:col-span-2">
                        <label class="form-label">Business Address</label>
                        <textarea name="business_address" rows="3" class="form-input" placeholder="123 Business St., Suite 100&#10;City, State 12345&#10;Country"><?= esc($settings['general.business_address'] ?? '') ?></textarea>
                        <p class="form-help">Complete business address</p>
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
                        <label class="form-label">Time Format</label>
                        <select name="time_format" class="form-input">
                            <option value="24h" <?= ($settings['localization.time_format'] ?? '') === '24h' ? 'selected' : '' ?>>HH:MM (24h)</option>
                            <option value="12h" <?= ($settings['localization.time_format'] ?? '') === '12h' ? 'selected' : '' ?>>hh:mm AM/PM (12h)</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">First Day of Week</label>
                        <select name="first_day" class="form-input">
                            <option value="Sunday" <?= ($settings['localization.first_day'] ?? '') === 'Sunday' ? 'selected' : '' ?>>Sunday</option>
                            <option value="Monday" <?= ($settings['localization.first_day'] ?? '') === 'Monday' ? 'selected' : '' ?>>Monday</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Default Language</label>
                        <select name="language" class="form-input">
                            <option value="English" <?= ($settings['localization.language'] ?? '') === 'English' ? 'selected' : '' ?>>English</option>
                            <option value="Portuguese-BR" <?= ($settings['localization.language'] ?? '') === 'Portuguese-BR' ? 'selected' : '' ?>>Portuguese-BR</option>
                            <option value="Spanish" <?= ($settings['localization.language'] ?? '') === 'Spanish' ? 'selected' : '' ?>>Spanish</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Currency</label>
                        <select name="currency" class="form-input">
                            <option value="ZAR" <?= ($settings['localization.currency'] ?? 'ZAR') === 'ZAR' ? 'selected' : '' ?>>South African Rand (ZAR)</option>
                            <option value="USD" <?= ($settings['localization.currency'] ?? '') === 'USD' ? 'selected' : '' ?>>US Dollar (USD)</option>
                            <option value="EUR" <?= ($settings['localization.currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
                            <option value="GBP" <?= ($settings['localization.currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>British Pound (GBP)</option>
                            <option value="AUD" <?= ($settings['localization.currency'] ?? '') === 'AUD' ? 'selected' : '' ?>>Australian Dollar (AUD)</option>
                            <option value="CAD" <?= ($settings['localization.currency'] ?? '') === 'CAD' ? 'selected' : '' ?>>Canadian Dollar (CAD)</option>
                            <option value="JPY" <?= ($settings['localization.currency'] ?? '') === 'JPY' ? 'selected' : '' ?>>Japanese Yen (JPY)</option>
                            <option value="CHF" <?= ($settings['localization.currency'] ?? '') === 'CHF' ? 'selected' : '' ?>>Swiss Franc (CHF)</option>
                            <option value="CNY" <?= ($settings['localization.currency'] ?? '') === 'CNY' ? 'selected' : '' ?>>Chinese Yuan (CNY)</option>
                            <option value="INR" <?= ($settings['localization.currency'] ?? '') === 'INR' ? 'selected' : '' ?>>Indian Rupee (INR)</option>
                            <option value="BRL" <?= ($settings['localization.currency'] ?? '') === 'BRL' ? 'selected' : '' ?>>Brazilian Real (BRL)</option>
                        </select>
                    </div>
                    <div class="form-field md:col-span-2">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-input">
                            <!-- Africa -->
                            <optgroup label="Africa">
                                <option value="Africa/Johannesburg" <?= ($settings['localization.timezone'] ?? 'Africa/Johannesburg') === 'Africa/Johannesburg' ? 'selected' : '' ?>>Johannesburg (SAST +2:00)</option>
                                <option value="Africa/Cairo" <?= ($settings['localization.timezone'] ?? '') === 'Africa/Cairo' ? 'selected' : '' ?>>Cairo (EET +2:00)</option>
                                <option value="Africa/Lagos" <?= ($settings['localization.timezone'] ?? '') === 'Africa/Lagos' ? 'selected' : '' ?>>Lagos (WAT +1:00)</option>
                                <option value="Africa/Nairobi" <?= ($settings['localization.timezone'] ?? '') === 'Africa/Nairobi' ? 'selected' : '' ?>>Nairobi (EAT +3:00)</option>
                                <option value="Africa/Casablanca" <?= ($settings['localization.timezone'] ?? '') === 'Africa/Casablanca' ? 'selected' : '' ?>>Casablanca (WET +0:00)</option>
                            </optgroup>
                            <!-- Americas -->
                            <optgroup label="Americas">
                                <option value="America/New_York" <?= ($settings['localization.timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>New York (EST -5:00)</option>
                                <option value="America/Chicago" <?= ($settings['localization.timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Chicago (CST -6:00)</option>
                                <option value="America/Denver" <?= ($settings['localization.timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Denver (MST -7:00)</option>
                                <option value="America/Los_Angeles" <?= ($settings['localization.timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Los Angeles (PST -8:00)</option>
                                <option value="America/Sao_Paulo" <?= ($settings['localization.timezone'] ?? '') === 'America/Sao_Paulo' ? 'selected' : '' ?>>São Paulo (BRT -3:00)</option>
                                <option value="America/Toronto" <?= ($settings['localization.timezone'] ?? '') === 'America/Toronto' ? 'selected' : '' ?>>Toronto (EST -5:00)</option>
                                <option value="America/Mexico_City" <?= ($settings['localization.timezone'] ?? '') === 'America/Mexico_City' ? 'selected' : '' ?>>Mexico City (CST -6:00)</option>
                            </optgroup>
                            <!-- Asia -->
                            <optgroup label="Asia">
                                <option value="Asia/Tokyo" <?= ($settings['localization.timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo (JST +9:00)</option>
                                <option value="Asia/Shanghai" <?= ($settings['localization.timezone'] ?? '') === 'Asia/Shanghai' ? 'selected' : '' ?>>Shanghai (CST +8:00)</option>
                                <option value="Asia/Kolkata" <?= ($settings['localization.timezone'] ?? '') === 'Asia/Kolkata' ? 'selected' : '' ?>>Kolkata (IST +5:30)</option>
                                <option value="Asia/Dubai" <?= ($settings['localization.timezone'] ?? '') === 'Asia/Dubai' ? 'selected' : '' ?>>Dubai (GST +4:00)</option>
                                <option value="Asia/Singapore" <?= ($settings['localization.timezone'] ?? '') === 'Asia/Singapore' ? 'selected' : '' ?>>Singapore (SGT +8:00)</option>
                                <option value="Asia/Hong_Kong" <?= ($settings['localization.timezone'] ?? '') === 'Asia/Hong_Kong' ? 'selected' : '' ?>>Hong Kong (HKT +8:00)</option>
                                <option value="Asia/Seoul" <?= ($settings['localization.timezone'] ?? '') === 'Asia/Seoul' ? 'selected' : '' ?>>Seoul (KST +9:00)</option>
                            </optgroup>
                            <!-- Europe -->
                            <optgroup label="Europe">
                                <option value="Europe/London" <?= ($settings['localization.timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London (GMT +0:00)</option>
                                <option value="Europe/Paris" <?= ($settings['localization.timezone'] ?? '') === 'Europe/Paris' ? 'selected' : '' ?>>Paris (CET +1:00)</option>
                                <option value="Europe/Berlin" <?= ($settings['localization.timezone'] ?? '') === 'Europe/Berlin' ? 'selected' : '' ?>>Berlin (CET +1:00)</option>
                                <option value="Europe/Rome" <?= ($settings['localization.timezone'] ?? '') === 'Europe/Rome' ? 'selected' : '' ?>>Rome (CET +1:00)</option>
                                <option value="Europe/Madrid" <?= ($settings['localization.timezone'] ?? '') === 'Europe/Madrid' ? 'selected' : '' ?>>Madrid (CET +1:00)</option>
                                <option value="Europe/Amsterdam" <?= ($settings['localization.timezone'] ?? '') === 'Europe/Amsterdam' ? 'selected' : '' ?>>Amsterdam (CET +1:00)</option>
                                <option value="Europe/Moscow" <?= ($settings['localization.timezone'] ?? '') === 'Europe/Moscow' ? 'selected' : '' ?>>Moscow (MSK +3:00)</option>
                            </optgroup>
                            <!-- Oceania -->
                            <optgroup label="Oceania">
                                <option value="Australia/Sydney" <?= ($settings['localization.timezone'] ?? '') === 'Australia/Sydney' ? 'selected' : '' ?>>Sydney (AEDT +11:00)</option>
                                <option value="Australia/Melbourne" <?= ($settings['localization.timezone'] ?? '') === 'Australia/Melbourne' ? 'selected' : '' ?>>Melbourne (AEDT +11:00)</option>
                                <option value="Australia/Perth" <?= ($settings['localization.timezone'] ?? '') === 'Australia/Perth' ? 'selected' : '' ?>>Perth (AWST +8:00)</option>
                                <option value="Pacific/Auckland" <?= ($settings['localization.timezone'] ?? '') === 'Pacific/Auckland' ? 'selected' : '' ?>>Auckland (NZDT +13:00)</option>
                            </optgroup>
                            <!-- UTC -->
                            <optgroup label="Universal">
                                <option value="UTC" <?= ($settings['localization.timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC (Coordinated Universal Time)</option>
                            </optgroup>
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

            <!-- Business hours -->
            <section id="panel-business" class="tab-panel hidden">
                <div class="space-y-6">
                    <div class="form-field">
                        <label class="form-label">Default Working Hours</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <input type="time" name="work_start" class="form-input" value="<?= esc($settings['business.work_start'] ?? '09:00') ?>">
                            <input type="time" name="work_end" class="form-input" value="<?= esc($settings['business.work_end'] ?? '17:00') ?>">
                        </div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Breaks</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <input type="time" name="break_start" class="form-input" value="<?= esc($settings['business.break_start'] ?? '12:00') ?>">
                            <input type="time" name="break_end" class="form-input" value="<?= esc($settings['business.break_end'] ?? '13:00') ?>">
                        </div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Blocked Periods</label>
                        <textarea name="blocked_periods" rows="3" class="form-input" placeholder='["2025-12-25","2025-12-31"]'><?= esc(is_array($settings['business.blocked_periods'] ?? '') ? json_encode($settings['business.blocked_periods']) : ($settings['business.blocked_periods'] ?? '')) ?></textarea>
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

                const btnEdit = generalPanel.querySelector('#general-edit-btn');
                const btnCancel = generalPanel.querySelector('#general-cancel-btn');
                const globalSaveBtn = form.querySelector('#global-save-btn');
                const inputs = Array.from(generalPanel.querySelectorAll('input, select, textarea'));
                let editing = false;
                // Store initial values for cancel functionality
                let initialValues = {
                    company_name: (generalPanel.querySelector('[name="company_name"]')?.value || ''),
                    company_email: (generalPanel.querySelector('[name="company_email"]')?.value || ''),
                    company_link: (generalPanel.querySelector('[name="company_link"]')?.value || ''),
                    telephone_number: (generalPanel.querySelector('[name="telephone_number"]')?.value || ''),
                    mobile_number: (generalPanel.querySelector('[name="mobile_number"]')?.value || ''),
                    business_address: (generalPanel.querySelector('[name="business_address"]')?.value || '')
                };
                
                // Live preview refs for logo
                const logoInput = generalPanel.querySelector('#company_logo');
                const logoImg = generalPanel.querySelector('#company_logo_preview_img');
                const originalLogoSrc = logoImg ? logoImg.getAttribute('data-src') || logoImg.src || '' : '';

                function applyValues(values) {
                    const { company_name, company_email, company_link, telephone_number, mobile_number, business_address } = values;
                    const nameEl = generalPanel.querySelector('[name="company_name"]');
                    const emailEl = generalPanel.querySelector('[name="company_email"]');
                    const linkEl = generalPanel.querySelector('[name="company_link"]');
                    const telephoneEl = generalPanel.querySelector('[name="telephone_number"]');
                    const mobileEl = generalPanel.querySelector('[name="mobile_number"]');
                    const addressEl = generalPanel.querySelector('[name="business_address"]');
                    if (nameEl) nameEl.value = company_name ?? '';
                    if (emailEl) emailEl.value = company_email ?? '';
                    if (linkEl) linkEl.value = company_link ?? '';
                    if (telephoneEl) telephoneEl.value = telephone_number ?? '';
                    if (mobileEl) mobileEl.value = mobile_number ?? '';
                    if (addressEl) addressEl.value = business_address ?? '';
                }

                function collectValues() {
                    return {
                        company_name: (generalPanel.querySelector('[name="company_name"]').value || ''),
                        company_email: (generalPanel.querySelector('[name="company_email"]').value || ''),
                        company_link: (generalPanel.querySelector('[name="company_link"]').value || ''),
                        telephone_number: (generalPanel.querySelector('[name="telephone_number"]').value || ''),
                        mobile_number: (generalPanel.querySelector('[name="mobile_number"]').value || ''),
                        business_address: (generalPanel.querySelector('[name="business_address"]').value || ''),
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

                // Start locked by default - values are already populated from server
                setLockedState(true);

                // Edit -> unlock
                btnEdit?.addEventListener('click', () => {
                    console.log('Edit button clicked, setting editing to true');
                    setLockedState(false);
                });

                // Cancel -> revert and lock
                btnCancel?.addEventListener('click', () => {
                    applyValues(initialValues);
                    // Reset logo preview to original on cancel
                    if (logoImg && originalLogoSrc) {
                        logoImg.src = originalLogoSrc;
                        logoImg.classList.remove('hidden');
                    }
                    // Clear file input
                    const logoInputEl = document.getElementById('company_logo');
                    if (logoInputEl) logoInputEl.value = '';
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
                        // Update initial values after successful submit (will be done on page reload)
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
