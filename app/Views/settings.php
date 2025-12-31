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
                <?php if (session()->getFlashdata('success_html')): ?>
                    <?= session()->getFlashdata('success') ?>
                <?php else: ?>
                    <?= esc(session()->getFlashdata('success')) ?>
                <?php endif; ?>
            </div>
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
    
    <!-- General Settings Form -->
    <form id="general-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" enctype="multipart/form-data" class="mt-4 space-y-6" data-tab-form="general">
        <?= csrf_field() ?>
        <input type="hidden" name="form_source" value="general_settings">
            <!-- General Settings -->
            <section id="panel-general" class="tab-panel">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">General</h3>
                    <div class="flex items-center gap-2" id="general-actions">
                        <button type="button" id="general-edit-btn" class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Edit</button>
                        <button type="button" id="general-cancel-btn" class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition hidden">Cancel</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-field">
                        <label class="form-label">Company Name <span class="req">*</span></label>
                        <input name="company_name" required class="form-input" placeholder="Acme Inc." value="<?= esc($settings['general.company_name'] ?? '') ?>" disabled />
                        <p class="form-help">Displayed throughout the system</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Company Email <span class="req">*</span></label>
                        <input type="email" name="company_email" required class="form-input" placeholder="info@acme.com" value="<?= esc($settings['general.company_email'] ?? '') ?>" disabled />
                        <p class="form-help">Used as sender/reply address for system emails</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Telephone Number</label>
                        <input type="tel" name="telephone_number" class="form-input" placeholder="(555) 123-4567" value="<?= esc($settings['general.telephone_number'] ?? '') ?>" disabled />
                        <p class="form-help">Main business phone number</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Mobile Number</label>
                        <input type="tel" name="mobile_number" class="form-input" placeholder="(555) 987-6543" value="<?= esc($settings['general.mobile_number'] ?? '') ?>" disabled />
                        <p class="form-help">Mobile contact number</p>
                    </div>
                    <div class="form-field md:col-span-2">
                        <label class="form-label">Company Link <span class="req">*</span></label>
                        <input type="url" name="company_link" required class="form-input" placeholder="https://acme.com" value="<?= esc($settings['general.company_link'] ?? '') ?>" disabled />
                    </div>
                    <div class="form-field md:col-span-2">
                        <label class="form-label">Business Address</label>
                        <textarea name="business_address" rows="3" class="form-input" placeholder="123 Business St., Suite 100&#10;City, State 12345&#10;Country" disabled><?= esc($settings['general.business_address'] ?? '') ?></textarea>
                        <p class="form-help">Complete business address</p>
                    </div>
                                        <div class="form-field">
                        <label class="form-label">Company Logo</label>
                        <div class="md:flex md:items-center md:gap-4">
                            <div class="md:flex-1">
                                <input id="company_logo" type="file" name="company_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-input file-input" disabled />
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
                
                <!-- Save Button for General Settings -->
                <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button id="save-general-btn" type="submit" class="px-5 py-2.5 rounded-lg text-white opacity-60 cursor-not-allowed" style="background-color: var(--md-sys-color-primary)" disabled>
                        Save General Settings
                    </button>
                </div>
            </section>
        </form>

            <!-- Localization Form -->
            <form id="localization-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" class="mt-4 space-y-6" data-tab-form="localization">
                <?= csrf_field() ?>
                <input type="hidden" name="form_source" value="localization_settings">
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
                
                <!-- Save Button for Localization Settings -->
                <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button id="save-localization-btn" type="submit" class="px-5 py-2.5 rounded-lg text-white" style="background-color: var(--md-sys-color-primary)">
                        Save Localization Settings
                    </button>
                </div>
            </section>
            </form>

            <!-- Booking Settings Form -->
            <form id="booking-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" class="mt-4 space-y-6" data-tab-form="booking">
                <?= csrf_field() ?>
                <input type="hidden" name="form_source" value="booking_settings">
            <section id="panel-booking" class="tab-panel hidden">
                <div class="space-y-6">
                    <!-- Standard Booking Fields -->
                    <div class="form-field">
                        <label class="form-label">Standard Booking Fields</label>
                        <div class="space-y-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                            <!-- Name Fields -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">First Names</label>
                                    <div class="space-y-2">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_first_names_display" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.first_names_display'] ?? '1') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Display in booking form</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_first_names_required" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.first_names_required'] ?? '1') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Required field</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Surname</label>
                                    <div class="space-y-2">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_surname_display" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.surname_display'] ?? '1') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Display in booking form</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_surname_required" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.surname_required'] ?? '1') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Required field</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Fields -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                                    <div class="space-y-2">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_email_display" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.email_display'] ?? '1') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Display in booking form</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_email_required" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.email_required'] ?? '1') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Required field</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone Number</label>
                                    <div class="space-y-2">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_phone_display" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.phone_display'] ?? '1') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Display in booking form</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_phone_required" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.phone_required'] ?? '0') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Required field</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Standard Fields -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Address</label>
                                    <div class="space-y-2">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_address_display" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.address_display'] ?? '0') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Display in booking form</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_address_required" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.address_required'] ?? '0') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Required field</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes/Comments</label>
                                    <div class="space-y-2">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_notes_display" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.notes_display'] ?? '1') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Display in booking form</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_notes_required" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= ($settings['booking.notes_required'] ?? '0') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Required field</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Custom Fields -->
                    <div class="form-field">
                        <label class="form-label">Additional Custom Fields</label>
                        <div class="space-y-4">
                            <?php for($i = 1; $i <= 6; $i++): ?>
                                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg custom-field-container" data-field-index="<?= $i ?>">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Custom Field <?= $i ?></h4>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="booking_custom_field_<?= $i ?>_enabled" value="1" class="form-checkbox h-4 w-4 text-blue-600 custom-field-toggle" <?= ($settings["booking.custom_field_{$i}_enabled"] ?? '0') === '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Enable Field</span>
                                        </label>
                                    </div>
                                    
                                    <div class="custom-field-settings" <?= ($settings["booking.custom_field_{$i}_enabled"] ?? '0') !== '1' ? 'style="opacity: 0.5; pointer-events: none;"' : '' ?>>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Field Title</label>
                                                <input type="text" name="booking_custom_field_<?= $i ?>_title" value="<?= esc($settings["booking.custom_field_{$i}_title"] ?? '') ?>" class="form-input custom-field-input" placeholder="Enter field label">
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Input Type</label>
                                                <select name="booking_custom_field_<?= $i ?>_type" class="form-input custom-field-input">
                                                    <option value="text" <?= ($settings["booking.custom_field_{$i}_type"] ?? 'text') === 'text' ? 'selected' : '' ?>>Text Input</option>
                                                    <option value="textarea" <?= ($settings["booking.custom_field_{$i}_type"] ?? '') === 'textarea' ? 'selected' : '' ?>>Text Area</option>
                                                    <option value="select" <?= ($settings["booking.custom_field_{$i}_type"] ?? '') === 'select' ? 'selected' : '' ?>>Dropdown (Future)</option>
                                                    <option value="checkbox" <?= ($settings["booking.custom_field_{$i}_type"] ?? '') === 'checkbox' ? 'selected' : '' ?>>Checkbox (Future)</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <label class="inline-flex items-center">
                                                <input type="checkbox" name="booking_custom_field_<?= $i ?>_required" value="1" class="form-checkbox h-4 w-4 text-blue-600 custom-field-input" <?= ($settings["booking.custom_field_{$i}_required"] ?? '0') === '1' ? 'checked' : '' ?>>
                                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Required field</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>


                </div>
                
                <!-- Save Button for Booking Settings -->
                <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button id="save-booking-btn" type="submit" class="px-5 py-2.5 rounded-lg text-white" style="background-color: var(--md-sys-color-primary)">
                        Save Booking Settings
                    </button>
                </div>
            </section>
            </form>

            <!-- Business hours Form -->
            <form id="business-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" class="mt-4 space-y-6" data-tab-form="business">
                <?= csrf_field() ?>
                <input type="hidden" name="form_source" value="business_settings">
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
                        <div class="space-y-4">
                            <div class="flex justify-between items-start">
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    Configure periods when bookings are not allowed (e.g., holidays, maintenance closures).
                                </p>
                                <button type="button" id="add-block-period-btn" class="btn btn-primary btn-sm flex items-center gap-2 whitespace-nowrap">
                                    <span class="material-symbols-outlined text-base">add</span>
                                    Add Period
                                </button>
                            </div>
                            
                            <!-- Block Periods List -->
                            <div class="card card-flat">
                                <div class="card-body p-0">
                                    <div id="block-periods-list" class="divide-y divide-gray-200 dark:divide-gray-700">
                                        <!-- Block periods will be rendered here by JS -->
                                    </div>
                                    <div id="block-periods-empty" class="text-center py-8 text-gray-500 dark:text-gray-400 hidden">
                                        <span class="material-symbols-outlined text-4xl mb-2 opacity-50">event_busy</span>
                                        <p class="text-sm">No blocked periods configured</p>
                                        <p class="text-xs text-gray-400">Click "Add Period" to get started</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Hidden input to store block periods as JSON -->
                        <input type="hidden" name="blocked_periods" id="blocked_periods_json" value="<?= esc(is_array($settings['business.blocked_periods'] ?? '') ? json_encode($settings['business.blocked_periods']) : ($settings['business.blocked_periods'] ?? '[]')) ?>">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-field">
                            <label class="form-label">Rescheduling Rules</label>
                            <select name="reschedule" class="form-input">
                                <option value="24h" <?= ($settings['business.reschedule'] ?? '24h') === '24h' ? 'selected' : '' ?>>Up to 24h before</option>
                                <option value="12h" <?= ($settings['business.reschedule'] ?? '') === '12h' ? 'selected' : '' ?>>Up to 12h before</option>
                                <option value="none" <?= ($settings['business.reschedule'] ?? '') === 'none' ? 'selected' : '' ?>>Not allowed</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Cancellation Rules</label>
                            <select name="cancel" class="form-input">
                                <option value="24h" <?= ($settings['business.cancel'] ?? '24h') === '24h' ? 'selected' : '' ?>>Up to 24h before</option>
                                <option value="12h" <?= ($settings['business.cancel'] ?? '') === '12h' ? 'selected' : '' ?>>Up to 12h before</option>
                                <option value="none" <?= ($settings['business.cancel'] ?? '') === 'none' ? 'selected' : '' ?>>Not allowed</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-field">
                            <label class="form-label">Future Booking Limit</label>
                            <select name="future_limit" class="form-input">
                                <option value="30" <?= ($settings['business.future_limit'] ?? '30') === '30' ? 'selected' : '' ?>>30 days</option>
                                <option value="60" <?= ($settings['business.future_limit'] ?? '') === '60' ? 'selected' : '' ?>>60 days</option>
                                <option value="90" <?= ($settings['business.future_limit'] ?? '') === '90' ? 'selected' : '' ?>>90 days</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Appointment Status Options</label>
                            <input name="statuses" class="form-input" placeholder="booked,confirmed,completed,cancelled" value="<?= esc($settings['booking.statuses'] ?? 'booked,confirmed,completed,cancelled') ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Save Button for Business Settings -->
                <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button id="save-business-btn" type="submit" class="px-5 py-2.5 rounded-lg text-white" style="background-color: var(--md-sys-color-primary)">
                        Save Business Settings
                    </button>
                </div>
            </section>
            </form>

            <!-- Legal Contents Form -->
            <form id="legal-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" class="mt-4 space-y-6" data-tab-form="legal">
                <?= csrf_field() ?>
                <input type="hidden" name="form_source" value="legal_settings">
            <section id="panel-legal" class="tab-panel hidden">
                <div class="space-y-4">
                    <div class="form-field">
                        <label class="form-label">Cookie Notice</label>
                        <textarea name="cookie_notice" rows="3" class="form-input" placeholder="Enter your cookie notice text here..."><?= esc($settings['legal.cookie_notice'] ?? '') ?></textarea>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Terms & Conditions</label>
                        <textarea name="terms" rows="6" class="form-input" placeholder="Enter your terms and conditions here..."><?= esc($settings['legal.terms'] ?? '') ?></textarea>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Privacy Policy</label>
                        <textarea name="privacy" rows="6" class="form-input" placeholder="Enter your privacy policy here..."><?= esc($settings['legal.privacy'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <!-- Save Button for Legal Settings -->
                <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button id="save-legal-btn" type="submit" class="px-5 py-2.5 rounded-lg text-white" style="background-color: var(--md-sys-color-primary)">
                        Save Legal Settings
                    </button>
                </div>
            </section>
            </form>

            <!-- Integrations Form -->
            <form id="integrations-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" class="mt-4 space-y-6" data-tab-form="integrations">
                <?= csrf_field() ?>
                <input type="hidden" name="form_source" value="integrations_settings">
            <section id="panel-integrations" class="tab-panel hidden">
                <div class="space-y-6">
                    <div class="form-field">
                        <label class="form-label">Webhooks</label>
                        <input name="webhook_url" type="url" class="form-input" placeholder="https://example.com/webhook" value="<?= esc($settings['integrations.webhook_url'] ?? '') ?>" />
                        <p class="form-help">External notifications endpoint</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-field">
                            <label class="form-label">Analytics</label>
                            <select name="analytics" class="form-input">
                                <option value="none" <?= ($settings['integrations.analytics'] ?? 'none') === 'none' ? 'selected' : '' ?>>None</option>
                                <option value="google" <?= ($settings['integrations.analytics'] ?? '') === 'google' ? 'selected' : '' ?>>Google Analytics</option>
                                <option value="matomo" <?= ($settings['integrations.analytics'] ?? '') === 'matomo' ? 'selected' : '' ?>>Matomo</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">API Integrations</label>
                            <input name="api_integrations" class="form-input" placeholder="comma,separated,keys" value="<?= esc($settings['integrations.api_integrations'] ?? '') ?>" />
                        </div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">LDAP Authentication</label>
                        <div class="space-y-2">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="ldap_enabled" class="checkbox" value="1" <?= ($settings['integrations.ldap_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> Enable LDAP
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <input name="ldap_host" class="form-input" placeholder="ldap://host" value="<?= esc($settings['integrations.ldap_host'] ?? '') ?>" />
                                <input name="ldap_dn" class="form-input" placeholder="cn=admin,dc=example,dc=com" value="<?= esc($settings['integrations.ldap_dn'] ?? '') ?>" />
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button for Integrations Settings -->
                <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button id="save-integrations-btn" type="submit" class="px-5 py-2.5 rounded-lg text-white" style="background-color: var(--md-sys-color-primary)">
                        Save Integrations Settings
                    </button>
                </div>
            </section>
            </form>

            <!-- Notifications Settings Form (Phase 1: Rules only, no sending) -->
            <form id="notifications-settings-form" method="POST" action="<?= base_url('settings/notifications') ?>" class="mt-4 space-y-6" data-tab-form="notifications">
                <?= csrf_field() ?>
                <input type="hidden" name="form_source" value="notification_rules_phase1">
                <section id="panel-notifications" class="tab-panel hidden">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">Notifications (Phase 1)</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Configure which events should trigger notifications. Sending will be enabled in later phases.</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">Rules only</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-field">
                            <label class="form-label">Default Notification Language</label>
                            <?php $notifLang = $settings['notifications.default_language'] ?? ($settings['localization.language'] ?? 'English'); ?>
                            <select name="notification_default_language" class="form-input">
                                <option value="English" <?= $notifLang === 'English' ? 'selected' : '' ?>>English</option>
                                <option value="Portuguese-BR" <?= $notifLang === 'Portuguese-BR' ? 'selected' : '' ?>>Portuguese-BR</option>
                                <option value="Spanish" <?= $notifLang === 'Spanish' ? 'selected' : '' ?>>Spanish</option>
                            </select>
                            <p class="form-help">Used as the default language when templates are introduced.</p>
                        </div>

                        <div class="form-field">
                            <label class="form-label">Reminder Offset (minutes)</label>
                            <?php
                                $defaultOffset = null;
                                if (!empty($notificationRules['appointment_reminder']['email']['reminder_offset_minutes'])) {
                                    $defaultOffset = (int) $notificationRules['appointment_reminder']['email']['reminder_offset_minutes'];
                                } elseif (!empty($notificationRules['appointment_reminder']['sms']['reminder_offset_minutes'])) {
                                    $defaultOffset = (int) $notificationRules['appointment_reminder']['sms']['reminder_offset_minutes'];
                                }
                            ?>
                            <input type="number" min="0" max="43200" name="reminder_offset_minutes" class="form-input" value="<?= esc($defaultOffset ?? 60) ?>" />
                            <p class="form-help">Applies to “Appointment Reminder” across channels (Phase 1).</p>
                        </div>
                    </div>

                    <?php
                        $emailIntegration = $notificationEmailIntegration ?? [
                            'provider_name' => '',
                            'is_active' => false,
                            'config' => [
                                'host' => '',
                                'port' => 587,
                                'crypto' => 'tls',
                                'username' => '',
                                'from_email' => '',
                                'from_name' => '',
                            ],
                            'decrypt_error' => null,
                        ];
                        $emailCfg = $emailIntegration['config'] ?? [];
                        $emailDecryptError = $emailIntegration['decrypt_error'] ?? null;
                        $allIntegrationStatus = $notificationIntegrationStatus ?? [];
                        $emailStatus = $allIntegrationStatus['email'] ?? [];
                        $emailHealth = (string) ($emailStatus['health_status'] ?? 'unknown');
                        $emailLastTested = $emailStatus['last_tested_at'] ?? null;

                        $smsIntegration = $notificationSmsIntegration ?? [
                            'provider' => 'clickatell',
                            'is_active' => false,
                            'config' => [
                                'clickatell_api_key' => '',
                                'clickatell_from' => '',
                                'twilio_account_sid' => '',
                                'twilio_from_number' => '',
                            ],
                            'decrypt_error' => null,
                        ];
                        $smsCfg = $smsIntegration['config'] ?? [];
                        $smsDecryptError = $smsIntegration['decrypt_error'] ?? null;
                        $smsStatus = $allIntegrationStatus['sms'] ?? [];
                        $smsHealth = (string) ($smsStatus['health_status'] ?? 'unknown');
                        $smsLastTested = $smsStatus['last_tested_at'] ?? null;

                        $waIntegration = $notificationWhatsAppIntegration ?? [
                            'provider' => 'link_generator',
                            'provider_name' => 'link_generator',
                            'is_active' => false,
                            'config' => [
                                'phone_number_id' => '',
                                'waba_id' => '',
                                'twilio_whatsapp_from' => '',
                            ],
                            'decrypt_error' => null,
                        ];
                        $waCfg = $waIntegration['config'] ?? [];
                        $waDecryptError = $waIntegration['decrypt_error'] ?? null;
                        $waStatus = $allIntegrationStatus['whatsapp'] ?? [];
                        $waHealth = (string) ($waStatus['health_status'] ?? 'unknown');
                        $waLastTested = $waStatus['last_tested_at'] ?? null;

                        $waTplMap = $notificationWhatsAppTemplates ?? [];
                    ?>

                    <div class="mt-6 border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200">Email (SMTP) Integration</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Credentials are stored encrypted. Use “Send Test Email” to verify connectivity.</p>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Health: <span class="font-semibold"><?= esc(ucfirst($emailHealth)) ?></span></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Last tested: <span class="font-semibold"><?= esc($emailLastTested ?: 'Never') ?></span></div>
                            </div>
                        </div>

                        <?php if ($emailDecryptError === 'encryption_key_mismatch'): ?>
                        <div class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-xl">warning</span>
                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Encryption Key Mismatch</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                        Previously saved credentials cannot be decrypted because the server's encryption key has changed.
                                        Please re-enter your SMTP credentials and save to restore email functionality.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div class="form-field">
                                <label class="form-label">Provider Name (optional)</label>
                                <input name="email_provider_name" class="form-input" placeholder="e.g. Gmail, SendGrid SMTP" value="<?= esc((string) ($emailIntegration['provider_name'] ?? '')) ?>" />
                            </div>

                            <div class="form-field">
                                <label class="form-label">Enable Email Sending (Phase 2 readiness)</label>
                                <label class="inline-flex items-center gap-2 mt-2">
                                    <input type="checkbox" name="email_is_active" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= !empty($emailIntegration['is_active']) ? 'checked' : '' ?> />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                                </label>
                                <p class="form-help">Rule toggles are separate; actual sending will be enabled after Phase 2 dispatch wiring.</p>
                            </div>

                            <div class="form-field">
                                <label class="form-label">SMTP Host</label>
                                <input name="smtp_host" class="form-input" placeholder="smtp.example.com" value="<?= esc((string) ($emailCfg['host'] ?? '')) ?>" />
                            </div>

                            <div class="form-field">
                                <label class="form-label">SMTP Port</label>
                                <input type="number" min="1" max="65535" name="smtp_port" class="form-input" value="<?= esc((string) ((int) ($emailCfg['port'] ?? 587))) ?>" />
                            </div>

                            <div class="form-field">
                                <label class="form-label">SMTP Encryption</label>
                                <?php $crypto = (string) ($emailCfg['crypto'] ?? 'tls'); ?>
                                <select name="smtp_crypto" class="form-input">
                                    <option value="tls" <?= $crypto === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                                    <option value="ssl" <?= $crypto === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="none" <?= $crypto === '' ? 'selected' : '' ?>>None</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label class="form-label">SMTP Username</label>
                                <input name="smtp_user" class="form-input" placeholder="user@example.com" value="<?= esc((string) ($emailCfg['username'] ?? '')) ?>" />
                            </div>

                            <div class="form-field">
                                <label class="form-label">SMTP Password</label>
                                <input type="password" name="smtp_pass" class="form-input" placeholder="(leave blank to keep existing)" value="" autocomplete="new-password" />
                                <p class="form-help">Password is never shown. Leave blank to keep the existing stored password.</p>
                            </div>

                            <div class="form-field">
                                <label class="form-label">From Email</label>
                                <input name="smtp_from_email" class="form-input" placeholder="no-reply@example.com" value="<?= esc((string) ($emailCfg['from_email'] ?? '')) ?>" />
                            </div>

                            <div class="form-field">
                                <label class="form-label">From Name</label>
                                <input name="smtp_from_name" class="form-input" placeholder="WebSchedulr" value="<?= esc((string) ($emailCfg['from_name'] ?? '')) ?>" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div class="form-field">
                                <label class="form-label">Test Recipient Email</label>
                                <input name="test_email_to" class="form-input" placeholder="you@yourdomain.com" value="" />
                                <p class="form-help">Sends a single test email using the saved SMTP settings.</p>
                            </div>

                            <div class="flex items-end justify-end gap-3">
                                <button type="submit" name="intent" value="test_email" class="px-4 py-2 rounded-lg text-white" style="background-color: var(--md-sys-color-secondary)">
                                    Send Test Email
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <button type="submit" name="intent" value="save_email" class="px-4 py-2 rounded-lg text-white inline-flex items-center gap-2" style="background-color: var(--md-sys-color-primary)">
                                <span class="material-symbols-outlined text-base">save</span>
                                Save Email Settings
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800">
                        <?php $waProvider = (string) ($waIntegration['provider'] ?? 'link_generator'); ?>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200">WhatsApp Integration</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Send appointment notifications via WhatsApp. Choose your preferred method below.</p>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Health: <span class="font-semibold"><?= esc(ucfirst($waHealth)) ?></span></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Last tested: <span class="font-semibold"><?= esc($waLastTested ?: 'Never') ?></span></div>
                            </div>
                        </div>

                        <?php if ($waDecryptError === 'encryption_key_mismatch'): ?>
                        <div class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-xl">warning</span>
                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Encryption Key Mismatch</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                        Previously saved WhatsApp credentials cannot be decrypted. Please re-enter your credentials.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Provider Selection -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div class="form-field">
                                <label class="form-label">WhatsApp Provider</label>
                                <select name="whatsapp_provider" id="whatsapp_provider" class="form-input">
                                    <option value="link_generator" <?= $waProvider === 'link_generator' ? 'selected' : '' ?>>📱 Link Generator (Simplest - No Setup)</option>
                                    <option value="twilio" <?= $waProvider === 'twilio' ? 'selected' : '' ?>>⚡ Twilio WhatsApp (Automated)</option>
                                    <option value="meta_cloud" <?= $waProvider === 'meta_cloud' ? 'selected' : '' ?>>🏢 Meta Cloud API (Enterprise)</option>
                                </select>
                                <p class="form-help">Link Generator = manual send via click. Twilio/Meta = fully automated.</p>
                            </div>

                            <div class="form-field">
                                <label class="form-label">Enable WhatsApp</label>
                                <label class="inline-flex items-center gap-2 mt-2">
                                    <input type="checkbox" name="whatsapp_is_active" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= !empty($waIntegration['is_active']) ? 'checked' : '' ?> />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                                </label>
                                <p class="form-help">Enable to show WhatsApp buttons and send notifications.</p>
                            </div>
                        </div>

                        <!-- Link Generator Info (shown when link_generator selected) -->
                        <div id="wa_link_generator_section" class="mt-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700" style="<?= $waProvider !== 'link_generator' ? 'display:none;' : '' ?>">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-2xl">check_circle</span>
                                <div>
                                    <p class="text-sm font-medium text-green-800 dark:text-green-200">Zero Configuration Required!</p>
                                    <p class="text-xs text-green-700 dark:text-green-300 mt-1">
                                        Link Generator creates clickable WhatsApp links with pre-filled messages. When you click "Send WhatsApp" on an appointment, 
                                        it opens WhatsApp on your phone/desktop with the message ready to send. Perfect for small businesses!
                                    </p>
                                    <ul class="text-xs text-green-700 dark:text-green-300 mt-2 list-disc list-inside">
                                        <li>No API keys or accounts needed</li>
                                        <li>Works with your personal WhatsApp</li>
                                        <li>Pre-filled professional messages</li>
                                        <li>One click to send confirmations/reminders</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Twilio WhatsApp Settings (shown when twilio selected) -->
                        <div id="wa_twilio_section" class="mt-4" style="<?= $waProvider !== 'twilio' ? 'display:none;' : '' ?>">
                            <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 mb-4">
                                <div class="flex items-start gap-3">
                                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-xl">info</span>
                                    <div>
                                        <p class="text-sm font-medium text-blue-800 dark:text-blue-200">Twilio WhatsApp</p>
                                        <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                            Uses your Twilio SMS credentials (configured above). You need a Twilio WhatsApp-enabled number.
                                            <a href="https://www.twilio.com/docs/whatsapp" target="_blank" class="underline">Learn more →</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-field">
                                    <label class="form-label">Twilio WhatsApp From Number</label>
                                    <input name="twilio_whatsapp_from" class="form-input" placeholder="+14155238886 or whatsapp:+14155238886" value="<?= esc((string) ($waCfg['twilio_whatsapp_from'] ?? '')) ?>" />
                                    <p class="form-help">Your Twilio WhatsApp-enabled number. The whatsapp: prefix is added automatically.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Meta Cloud API Settings (shown when meta_cloud selected) -->
                        <div id="wa_meta_section" class="mt-4" style="<?= $waProvider !== 'meta_cloud' ? 'display:none;' : '' ?>">
                            <div class="p-4 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 mb-4">
                                <div class="flex items-start gap-3">
                                    <span class="material-symbols-outlined text-purple-600 dark:text-purple-400 text-xl">business</span>
                                    <div>
                                        <p class="text-sm font-medium text-purple-800 dark:text-purple-200">Meta Cloud API (Enterprise)</p>
                                        <p class="text-xs text-purple-700 dark:text-purple-300 mt-1">
                                            Requires Meta Business verification and pre-approved message templates.
                                            <a href="https://developers.facebook.com/docs/whatsapp/cloud-api" target="_blank" class="underline">Setup guide →</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-field">
                                    <label class="form-label">Phone Number ID</label>
                                    <input name="whatsapp_phone_number_id" class="form-input" placeholder="(numeric)" value="<?= esc((string) ($waCfg['phone_number_id'] ?? '')) ?>" />
                                </div>
                                <div class="form-field">
                                    <label class="form-label">WABA ID (optional)</label>
                                    <input name="whatsapp_waba_id" class="form-input" placeholder="(optional)" value="<?= esc((string) ($waCfg['waba_id'] ?? '')) ?>" />
                                </div>
                                <div class="form-field md:col-span-2">
                                    <label class="form-label">Access Token</label>
                                    <input type="password" name="whatsapp_access_token" class="form-input" placeholder="(leave blank to keep existing)" value="" autocomplete="new-password" />
                                    <p class="form-help">Token is never shown. Leave blank to keep existing stored token.</p>
                                </div>
                            </div>

                            <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                                <h5 class="text-sm font-medium text-gray-800 dark:text-gray-200">Template References</h5>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Provide approved Meta template name + locale per event.</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                                    <?php foreach (($notificationEvents ?? []) as $eventType => $eventLabel): ?>
                                        <?php $tpl = ($waTplMap[$eventType] ?? ['template_name' => '', 'locale' => 'en_US']); ?>
                                        <div class="form-field">
                                            <label class="form-label"><?= esc($eventLabel) ?> Template Name</label>
                                            <input name="whatsapp_template_<?= esc($eventType) ?>" class="form-input" placeholder="meta_template_name" value="<?= esc((string) ($tpl['template_name'] ?? '')) ?>" />
                                        </div>
                                        <div class="form-field">
                                            <label class="form-label"><?= esc($eventLabel) ?> Locale</label>
                                            <input name="whatsapp_locale_<?= esc($eventType) ?>" class="form-input" placeholder="en_US" value="<?= esc((string) ($tpl['locale'] ?? 'en_US')) ?>" />
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Test Section -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="form-field">
                                <label class="form-label">Test Recipient Phone (+E.164)</label>
                                <input name="test_whatsapp_to" class="form-input" placeholder="+15551234567" value="" />
                                <p class="form-help">Test the WhatsApp integration with this phone number.</p>
                            </div>

                            <div class="flex items-end justify-end gap-3">
                                <button type="submit" name="intent" value="test_whatsapp" class="px-4 py-2 rounded-lg text-white" style="background-color: var(--md-sys-color-secondary)">
                                    Test WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <button type="submit" name="intent" value="save_whatsapp" class="px-4 py-2 rounded-lg text-white inline-flex items-center gap-2" style="background-color: var(--md-sys-color-primary)">
                                <span class="material-symbols-outlined text-base">save</span>
                                Save WhatsApp Settings
                            </button>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const providerSelect = document.getElementById('whatsapp_provider');
                            const sections = {
                                link_generator: document.getElementById('wa_link_generator_section'),
                                twilio: document.getElementById('wa_twilio_section'),
                                meta_cloud: document.getElementById('wa_meta_section')
                            };
                            
                            function updateSections() {
                                const selected = providerSelect.value;
                                Object.keys(sections).forEach(key => {
                                    if (sections[key]) {
                                        sections[key].style.display = key === selected ? '' : 'none';
                                    }
                                });
                            }
                            
                            providerSelect.addEventListener('change', updateSections);
                        });
                        </script>

                        <?php if ($smsDecryptError === 'encryption_key_mismatch'): ?>
                        <div class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-xl">warning</span>
                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Encryption Key Mismatch</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                        Previously saved SMS credentials cannot be decrypted because the server's encryption key has changed.
                                        Please re-enter your credentials and save to restore SMS functionality.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div class="form-field">
                                <label class="form-label">SMS Provider</label>
                                <?php $smsProvider = (string) ($smsIntegration['provider'] ?? 'clickatell'); ?>
                                <select name="sms_provider" class="form-input">
                                    <option value="clickatell" <?= $smsProvider === 'clickatell' ? 'selected' : '' ?>>Clickatell (primary)</option>
                                    <option value="twilio" <?= $smsProvider === 'twilio' ? 'selected' : '' ?>>Twilio (optional)</option>
                                </select>
                                <p class="form-help">Select your SMS provider (Phase 3).</p>
                            </div>

                            <div class="form-field">
                                <label class="form-label">Enable SMS Sending (Phase 3 readiness)</label>
                                <label class="inline-flex items-center gap-2 mt-2">
                                    <input type="checkbox" name="sms_is_active" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= !empty($smsIntegration['is_active']) ? 'checked' : '' ?> />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                                </label>
                                <p class="form-help">Rule toggles are separate; reminders will be sent only when both the rule and integration are enabled.</p>
                            </div>

                            <div class="form-field">
                                <label class="form-label">Clickatell API Key</label>
                                <input name="clickatell_api_key" class="form-input" placeholder="(Clickatell API Key)" value="<?= esc((string) ($smsCfg['clickatell_api_key'] ?? '')) ?>" autocomplete="off" />
                            </div>

                            <div class="form-field">
                                <label class="form-label">Clickatell Sender ID / From (optional)</label>
                                <input name="clickatell_from" class="form-input" placeholder="+27821234567 or WebSchedulr" value="<?= esc((string) ($smsCfg['clickatell_from'] ?? '')) ?>" />
                                <p class="form-help">Either +E.164 phone or 3–11 alphanumeric.</p>
                            </div>

                            <div class="form-field">
                                <label class="form-label">Twilio Account SID</label>
                                <input name="twilio_account_sid" class="form-input" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" value="<?= esc((string) ($smsCfg['twilio_account_sid'] ?? '')) ?>" autocomplete="off" />
                            </div>

                            <div class="form-field">
                                <label class="form-label">Twilio Auth Token</label>
                                <input type="password" name="twilio_auth_token" class="form-input" placeholder="(leave blank to keep existing)" value="" autocomplete="new-password" />
                                <p class="form-help">Token is never shown. Leave blank to keep existing stored token.</p>
                            </div>

                            <div class="form-field">
                                <label class="form-label">Twilio From Number (+E.164)</label>
                                <input name="twilio_from_number" class="form-input" placeholder="+15551234567" value="<?= esc((string) ($smsCfg['twilio_from_number'] ?? '')) ?>" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div class="form-field">
                                <label class="form-label">Test Recipient Phone (+E.164)</label>
                                <input name="test_sms_to" class="form-input" placeholder="+27821234567" value="" />
                                <p class="form-help">Sends a single test SMS using the saved provider settings.</p>
                            </div>

                            <div class="flex items-end justify-end gap-3">
                                <button type="submit" name="intent" value="test_sms" class="px-4 py-2 rounded-lg text-white" style="background-color: var(--md-sys-color-secondary)">
                                    Send Test SMS
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <button type="submit" name="intent" value="save_sms" class="px-4 py-2 rounded-lg text-white inline-flex items-center gap-2" style="background-color: var(--md-sys-color-primary)">
                                <span class="material-symbols-outlined text-base">save</span>
                                Save SMS Settings
                            </button>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-2">Event → Channel Matrix</h4>
                        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">Event</th>
                                        <th class="px-4 py-3 text-center font-semibold">Email</th>
                                        <th class="px-4 py-3 text-center font-semibold">SMS</th>
                                        <th class="px-4 py-3 text-center font-semibold">WhatsApp</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php
                                        $events = $notificationEvents ?? [
                                            'appointment_confirmed' => 'Appointment Confirmed',
                                            'appointment_reminder'  => 'Appointment Reminder',
                                            'appointment_cancelled' => 'Appointment Cancelled',
                                        ];
                                        $integrationStatus = $notificationIntegrationStatus ?? [];

                                        $labelFor = function(string $eventType, string $channel): string {
                                            return 'rule_' . preg_replace('/[^a-z0-9_]+/i', '_', $eventType . '_' . $channel);
                                        };

                                        $preview = function(string $eventType, string $channel) {
                                            try {
                                                $svc = new \App\Services\NotificationPhase1();
                                                return $svc->buildPreview($eventType, $channel);
                                            } catch (\Throwable $e) {
                                                return '';
                                            }
                                        };
                                    ?>

                                    <?php foreach ($events as $eventType => $eventLabel): ?>
                                        <?php
                                            $emailEnabled = (int) ($notificationRules[$eventType]['email']['is_enabled'] ?? 0) === 1;
                                            $smsEnabled = (int) ($notificationRules[$eventType]['sms']['is_enabled'] ?? 0) === 1;
                                            $waEnabled = (int) ($notificationRules[$eventType]['whatsapp']['is_enabled'] ?? 0) === 1;

                                            $emailConfigured = (bool) ($integrationStatus['email']['configured'] ?? false);
                                            $smsConfigured = (bool) ($integrationStatus['sms']['configured'] ?? false);
                                            $waConfigured = (bool) ($integrationStatus['whatsapp']['configured'] ?? false);
                                        ?>
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                                <div class="font-medium"><?= esc($eventLabel) ?></div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    <div><span class="font-semibold">Email preview:</span> <?= esc($preview($eventType, 'email')) ?></div>
                                                    <div class="mt-1"><span class="font-semibold">SMS preview:</span> <?= esc($preview($eventType, 'sms')) ?></div>
                                                    <div class="mt-1"><span class="font-semibold">WhatsApp preview:</span> <?= esc($preview($eventType, 'whatsapp')) ?></div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <label class="inline-flex items-center justify-center">
                                                    <input id="<?= esc($labelFor($eventType, 'email')) ?>" type="checkbox" name="rules[<?= esc($eventType) ?>][email]" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= $emailEnabled ? 'checked' : '' ?> />
                                                </label>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    <?= $emailConfigured ? 'Configured' : 'Not configured' ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <label class="inline-flex items-center justify-center">
                                                    <input id="<?= esc($labelFor($eventType, 'sms')) ?>" type="checkbox" name="rules[<?= esc($eventType) ?>][sms]" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= $smsEnabled ? 'checked' : '' ?> />
                                                </label>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    <?= $smsConfigured ? 'Configured' : 'Not configured' ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <label class="inline-flex items-center justify-center">
                                                    <input id="<?= esc($labelFor($eventType, 'whatsapp')) ?>" type="checkbox" name="rules[<?= esc($eventType) ?>][whatsapp]" value="1" class="form-checkbox h-4 w-4 text-blue-600" <?= $waEnabled ? 'checked' : '' ?> />
                                                </label>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    <?= $waConfigured ? 'Configured' : 'Not configured' ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-2">Delivery Logs (Phase 6)</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Recent notification delivery attempts from the queue dispatcher.</p>

                        <?php
                            $deliveryLogs = $notificationDeliveryLogs ?? [];
                            $maskRecipient = function (?string $recipient): string {
                                $recipient = trim((string) $recipient);
                                if ($recipient === '') {
                                    return '';
                                }
                                if (strpos($recipient, '@') !== false) {
                                    [$local, $domain] = array_pad(explode('@', $recipient, 2), 2, '');
                                    $local = (string) $local;
                                    $domain = (string) $domain;
                                    $head = $local !== '' ? substr($local, 0, 1) : '';
                                    return $head . '***@' . $domain;
                                }
                                // phone: show last 4
                                $digits = preg_replace('/\D+/', '', $recipient);
                                $last4 = $digits !== '' ? substr($digits, -4) : '';
                                return '+***' . $last4;
                            };
                        ?>

                        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">When</th>
                                        <th class="px-4 py-3 text-left font-semibold">Channel</th>
                                        <th class="px-4 py-3 text-left font-semibold">Event</th>
                                        <th class="px-4 py-3 text-left font-semibold">Recipient</th>
                                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                                        <th class="px-4 py-3 text-left font-semibold">Correlation</th>
                                        <th class="px-4 py-3 text-left font-semibold">Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php if (empty($deliveryLogs)): ?>
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td colspan="7" class="px-4 py-3 text-gray-600 dark:text-gray-400">No delivery logs yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($deliveryLogs as $log): ?>
                                            <?php
                                                $status = (string) ($log['status'] ?? '');
                                                $statusClass = 'text-gray-700 dark:text-gray-300';
                                                if ($status === 'success') {
                                                    $statusClass = 'text-green-700 dark:text-green-300';
                                                } elseif ($status === 'failed') {
                                                    $statusClass = 'text-red-700 dark:text-red-300';
                                                } elseif ($status === 'cancelled') {
                                                    $statusClass = 'text-yellow-700 dark:text-yellow-300';
                                                }
                                            ?>
                                            <tr class="bg-white dark:bg-gray-800">
                                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?= esc((string) ($log['created_at'] ?? '')) ?></td>
                                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?= esc((string) ($log['channel'] ?? '')) ?></td>
                                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?= esc((string) ($log['event_type'] ?? '')) ?></td>
                                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?= esc($maskRecipient($log['recipient'] ?? null)) ?></td>
                                                <td class="px-4 py-3 font-semibold <?= $statusClass ?>"><?= esc($status) ?></td>
                                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?= esc((string) ($log['correlation_id'] ?? '')) ?></td>
                                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400"><?= esc((string) ($log['error_message'] ?? '')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button id="save-notifications-btn" type="submit" name="intent" value="save" class="px-5 py-2.5 rounded-lg text-white" style="background-color: var(--md-sys-color-primary)">
                            Save Notification Settings
                        </button>
                    </div>
                </section>
            </form>

            <!-- Database Settings Panel -->
            <section id="panel-database" class="tab-panel hidden">
                <div class="space-y-6">
                    <!-- Database Information -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-base">database</span>
                            Database Information
                        </h4>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Database Type</label>
                                    <p id="db-info-type" class="text-sm text-gray-900 dark:text-gray-100 font-medium">Loading...</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Database Name</label>
                                    <p id="db-info-name" class="text-sm text-gray-900 dark:text-gray-100 font-medium">Loading...</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Host</label>
                                    <p id="db-info-host" class="text-sm text-gray-900 dark:text-gray-100 font-medium">Loading...</p>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                <span class="material-symbols-outlined text-xs align-middle">info</span>
                                Database connection is configured via the <code class="bg-gray-200 dark:bg-gray-600 px-1 rounded">.env</code> file. Contact your system administrator to change database settings.
                            </p>
                        </div>
                    </div>

                    <!-- Database Backup Section -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-base">backup</span>
                            Database Backup
                        </h4>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                            <!-- Backup Enable Toggle -->
                            <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200 dark:border-gray-600">
                                <div>
                                    <label class="text-sm font-medium text-gray-900 dark:text-gray-100">Enable Database Backups</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Master switch for backup functionality</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="backup-enabled-toggle" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            <!-- Last Backup Info -->
                            <div id="last-backup-info" class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-600">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Last Backup</label>
                                <div id="last-backup-details" class="flex items-center gap-3">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">No backups yet</span>
                                </div>
                            </div>

                            <!-- Backup Actions -->
                            <div class="flex items-center gap-3">
                                <button type="button" id="create-backup-btn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium inline-flex items-center gap-2 transition-colors" disabled>
                                    <span class="material-symbols-outlined text-base">backup</span>
                                    Create Backup Now
                                </button>
                                <button type="button" id="view-backups-btn" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-800 dark:text-gray-100 rounded-lg text-sm font-medium inline-flex items-center gap-2 transition-colors">
                                    <span class="material-symbols-outlined text-base">folder_open</span>
                                    View All Backups
                                </button>
                            </div>

                            <!-- Backup Progress -->
                            <div id="backup-progress" class="mt-4 hidden">
                                <div class="flex items-center gap-3">
                                    <div class="loading-spinner"></div>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Creating backup...</span>
                                </div>
                            </div>

                            <!-- Backup Error -->
                            <div id="backup-error" class="mt-4 hidden p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300 text-sm"></div>

                            <!-- Backup Success -->
                            <div id="backup-success" class="mt-4 hidden p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300 text-sm"></div>
                        </div>
                    </div>

                    <!-- Security Notice -->
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-amber-600 dark:text-amber-400">security</span>
                            <div>
                                <h5 class="text-sm font-medium text-amber-800 dark:text-amber-200">Security Notice</h5>
                                <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                    Database backups are stored securely outside the web root. Only administrators can create and download backups. 
                                    All backup operations are logged for audit purposes.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

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
                        <button type="button" id="block-period-cancel" class="btn btn-outline">Cancel</button>
                        <button type="submit" id="block-period-save-btn" class="btn btn-primary">Save Period</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
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
        })();
        </script>

        <script>
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
                            ${period.notes ? `<p class="text-sm text-gray-600 dark:text-gray-400 ml-6">${period.notes.replace(/</g, '&lt;')}</p>` : ''}
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
                        // Get CSRF token
                        const header = document.querySelector('meta[name="csrf-header"]')?.getAttribute('content') || 'X-CSRF-TOKEN';
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        
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
                        
                    } catch (error) {
                        console.error('❌ Block period save failed:', error);
                        
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
                        error.textContent = error?.message || 'Failed to save block period. Please try again.';
                        error.classList.remove('hidden');
                        
                        // Show error toast
                        window.XSNotify?.toast?.({
                            type: 'error',
                            title: '✗ Save Failed',
                            message: error?.message || 'Failed to save block period. Please try again.',
                            autoClose: false
                        });
                        
                        if (!window.XSNotify?.toast) {
                            alert('✗ Failed to save block period: ' + (error?.message || 'Unknown error'));
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
                            // Get CSRF token
                            const header = document.querySelector('meta[name="csrf-header"]')?.getAttribute('content') || 'X-CSRF-TOKEN';
                            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                            
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
        </script>
    </div>
</div>

        <script>
        // General tab API integration: load on view and save via POST to /api/v1/settings
        document.addEventListener('DOMContentLoaded', () => {
            xsDebugLog('Settings: DOMContentLoaded fired, calling initSettingsApi');
            initSettingsApi();
        });
        document.addEventListener('spa:navigated', () => {
            xsDebugLog('Settings: spa:navigated fired, calling initSettingsApi');
            initSettingsApi();
        });

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
            // Initial bind + on SPA navigations
            document.addEventListener('DOMContentLoaded', bindInput);
            document.addEventListener('spa:navigated', () => {
                bindInput();
                // After navigation (e.g., post-save redirect), ensure value is reflected
                const nameVal = document.getElementById('spa-content')?.querySelector('input[name="company_name"]')?.value;
                if (typeof nameVal !== 'undefined') setBrandName(nameVal);
            });
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

        function initGeneralSettingsForm() {
            const form = document.getElementById('general-settings-form');
            xsDebugLog('General Form found:', !!form, 'apiWired:', form?.dataset.apiWired);
            if (!form || form.dataset.apiWired === 'true') return;

            const generalPanel = form.querySelector('#panel-general');
            const saveBtn = document.getElementById('save-general-btn');
            const btnEdit = document.getElementById('general-edit-btn');
            const btnCancel = document.getElementById('general-cancel-btn');
            const logoInput = document.getElementById('company_logo');
            const logoImg = document.getElementById('company_logo_preview_img');
            const csrfInput = form.querySelector('input[type="hidden"][name*="csrf"]');

            xsDebugLog('General Settings: Found elements:', {
                generalPanel: !!generalPanel,
                saveBtn: !!saveBtn,
                btnEdit: !!btnEdit,
                btnCancel: !!btnCancel,
                logoInput: !!logoInput,
                logoImg: !!logoImg
            });

            if (!generalPanel || !saveBtn || !btnEdit) {
                console.warn('General Settings: Required elements not found');
                return;
            }

            const lockableFields = Array.from(generalPanel.querySelectorAll('input:not([type="hidden"]), textarea, select'))
                .filter(el => el.id !== 'company_logo');

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

            let editing = false;
            let hasChanges = false;
            let initialValues = collectCurrentValues();
            let initialLogoSrc = logoImg ? (logoImg.dataset.src || logoImg.src || '') : '';

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
                if (btnEdit) btnEdit.classList.toggle('hidden', !locked);
                if (btnCancel) btnCancel.classList.toggle('hidden', locked);
                
                updateSaveButtonState();
            }

            function getCsrf() {
                const header = document.querySelector('meta[name="csrf-header"]')?.getAttribute('content')
                    || window.appConfig?.csrfHeaderName
                    || 'X-CSRF-TOKEN';
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || window.appConfig?.csrfToken
                    || csrfInput?.value
                    || '';
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
                    saveBtn?.focus();
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
                const header = document.querySelector('meta[name="csrf-header"]')?.getAttribute('content') || 'X-CSRF-TOKEN';
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || csrfInput?.value || '';

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
            document.querySelectorAll('.custom-field-toggle').forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const container = this.closest('.custom-field-container');
                    const settings = container.querySelector('.custom-field-settings');
                    const inputs = settings.querySelectorAll('.custom-field-input');
                    
                    if (this.checked) {
                        // Enable field - make it editable and visible
                        settings.style.opacity = '1';
                        settings.style.pointerEvents = 'auto';
                        inputs.forEach(input => {
                            input.disabled = false;
                        });
                    } else {
                        // Disable field - grey out and make non-editable
                        settings.style.opacity = '0.5';
                        settings.style.pointerEvents = 'none';
                        inputs.forEach(input => {
                            input.disabled = true;
                        });
                    }
                });
                
                // Trigger initial state
                toggle.dispatchEvent(new Event('change'));
            });
        }

        // Initialize on page load and SPA navigation
        document.addEventListener('DOMContentLoaded', initCustomFieldToggles);
        document.addEventListener('spa:navigated', initCustomFieldToggles);
        </script>

        <!-- Time Format Handler Script -->
        <script type="module">
            import timeFormatHandler from '<?= base_url('build/assets/time-format-handler.js') ?>';
            
            // Initialize on settings page load
            async function initTimeFormatting() {
                await timeFormatHandler.init();
                timeFormatHandler.addFormattedDisplays();
                xsDebugLog('[Settings] Time format handler initialized with format:', timeFormatHandler.getFormat());
            }
            
            // Run on page load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTimeFormatting);
            } else {
                initTimeFormatting();
            }
            
            // Re-run on SPA navigation
            document.addEventListener('spa:navigated', initTimeFormatting);
        </script>

        <!-- Database Settings Tab Script -->
        <script>
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
                    updateLastBackupDisplay(data.last_backup);

                } catch (error) {
                    console.error('Failed to load backup status:', error);
                    if (dbInfoType) dbInfoType.textContent = 'Error loading';
                }
            }

            // Update last backup display
            function updateLastBackupDisplay(lastBackup) {
                if (!lastBackupDetails) return;

                if (lastBackup?.time && lastBackup?.filename) {
                    lastBackupDetails.innerHTML = `
                        <div class="flex-1">
                            <p class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(lastBackup.time)}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                ${escapeHtml(lastBackup.filename)} 
                                <span class="text-gray-400">(${escapeHtml(lastBackup.size_formatted || 'Unknown size')})</span>
                            </p>
                        </div>
                        <a href="${API_BASE}/download/${encodeURIComponent(lastBackup.filename)}" 
                           data-no-spa="true"
                           download="${escapeHtml(lastBackup.filename)}"
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
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">${escapeHtml(backup.filename)}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                ${escapeHtml(backup.created)} • ${escapeHtml(backup.size_formatted)}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a href="${API_BASE}/download/${encodeURIComponent(backup.filename)}" 
                                                              data-no-spa="true"
                                                              download="${escapeHtml(backup.filename)}"
                                               class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition"
                                               title="Download">
                                                <span class="material-symbols-outlined text-xl">download</span>
                                            </a>
                                            <button type="button" 
                                                    class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition delete-backup-btn"
                                                    data-filename="${escapeHtml(backup.filename)}"
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

            function escapeHtml(str) {
                if (!str) return '';
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

            // Initialize when database tab is shown
            document.querySelectorAll('[data-tab="database"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    loadBackupStatus();
                });
            });

            // Also load on initial page load if database tab is active
            if (document.querySelector('[data-tab="database"].active')) {
                loadBackupStatus();
            }
        })();
        </script>
<?= $this->endSection() ?>
