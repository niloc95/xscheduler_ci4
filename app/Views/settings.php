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
                <button class="tab-btn" data-tab="localization">Localization</button>
                <button class="tab-btn" data-tab="booking">Booking</button>
                <button class="tab-btn" data-tab="business">Business hours</button>
                <button class="tab-btn" data-tab="legal">Legal Contents</button>
                <button class="tab-btn" data-tab="integrations">Integrations</button>
                
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
                    
                    console.log('🔄 Saving block period to database:', period);
                    console.log('📋 Full block periods array:', blockPeriods);
                    
                    try {
                        // Get CSRF token
                        const header = document.querySelector('meta[name="csrf-header"]')?.getAttribute('content') || 'X-CSRF-TOKEN';
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        
                        // Save to database via API
                        const response = await fetch("<?= base_url('api/v1/settings') ?>", {
                            method: 'PUT',
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
                        
                        console.log('📡 Block period API Response status:', response.status);
                        
                        if (!response.ok) {
                            throw new Error(`Save failed (HTTP ${response.status})`);
                        }
                        
                        const result = await response.json();
                        console.log('📦 Block period API Response data:', result);
                        
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
                        
                        console.log('✅ Block period saved successfully!');
                        
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
                        
                        console.log('🗑️ Deleting block period at index:', idx);
                        
                        // Remove from array
                        const deletedPeriod = blockPeriods[idx];
                        blockPeriods.splice(idx, 1);
                        
                        console.log('📋 Remaining block periods:', blockPeriods);
                        
                        try {
                            // Get CSRF token
                            const header = document.querySelector('meta[name="csrf-header"]')?.getAttribute('content') || 'X-CSRF-TOKEN';
                            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                            
                            // Save to database via API
                            const response = await fetch("<?= base_url('api/v1/settings') ?>", {
                                method: 'PUT',
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
                            
                            console.log('📡 Delete block period API Response status:', response.status);
                            
                            if (!response.ok) {
                                throw new Error(`Delete failed (HTTP ${response.status})`);
                            }
                            
                            const result = await response.json();
                            console.log('📦 Delete block period API Response data:', result);
                            
                            if (!result?.ok) {
                                throw new Error(result?.message || 'Unable to delete block period.');
                            }
                            
                            // Update hidden input and UI
                            saveBlockPeriods();
                            
                            console.log('✅ Block period deleted successfully!');
                            
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
        // General tab API integration: load on view and save via PUT to /api/v1/settings
        document.addEventListener('DOMContentLoaded', () => {
            console.log('Settings: DOMContentLoaded fired, calling initSettingsApi');
            initSettingsApi();
        });
        document.addEventListener('spa:navigated', () => {
            console.log('Settings: spa:navigated fired, calling initSettingsApi');
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
            console.log('Settings: initSettingsApi called, root found:', !!root);
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
            console.log('General Form found:', !!form, 'apiWired:', form?.dataset.apiWired);
            if (!form || form.dataset.apiWired === 'true') return;

            const generalPanel = form.querySelector('#panel-general');
            const saveBtn = document.getElementById('save-general-btn');
            const btnEdit = document.getElementById('general-edit-btn');
            const btnCancel = document.getElementById('general-cancel-btn');
            const logoInput = document.getElementById('company_logo');
            const logoImg = document.getElementById('company_logo_preview_img');
            const csrfInput = form.querySelector('input[type="hidden"][name*="csrf"]');

            console.log('General Settings: Found elements:', {
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
                console.log('General Settings: Attaching Edit button listener');
                btnEdit.addEventListener('click', () => {
                    console.log('📝 General Edit clicked');
                    editing = true;
                    setLockedState(false);
                    saveBtn?.focus();
                });
            }

            if (btnCancel) {
                btnCancel.addEventListener('click', () => {
                    console.log('↩️ General Cancel clicked');
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
                
                console.log('General form submitted - editing:', editing);

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

                console.log('Saving general settings:', payload);

                const previousEditingState = editing;
                setSavingState(true);
                setLockedState(true);

                try {
                    const response = await fetch(apiEndpoint, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(token ? { [header]: token } : {})
                        },
                        body: JSON.stringify(payload)
                    });

                    updateCsrfFromResponse(response);

                    console.log('API Response status:', response.status);

                    if (!response.ok) {
                        throw new Error(`Save failed (HTTP ${response.status})`);
                    }

                    const result = await response.json();
                    console.log('API Response data:', result);
                    
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

                    console.log('✅ General settings saved successfully!');
                    
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

            console.log(`${tabName} Settings: Initializing form`);

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

                console.log(`${tabName} form submitted`);

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

                console.log(`Saving ${tabName} settings:`, payload);

                // Show saving state
                const originalLabel = saveBtn.innerHTML;
                saveBtn.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Saving…</span>';
                saveBtn.disabled = true;

                try {
                    const response = await fetch("<?= base_url('api/v1/settings') ?>", {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(token ? { [header]: token } : {})
                        },
                        body: JSON.stringify(payload)
                    });

                    console.log(`${tabName} API Response status:`, response.status);

                    if (!response.ok) {
                        throw new Error(`Save failed (HTTP ${response.status})`);
                    }

                    const result = await response.json();
                    console.log(`${tabName} API Response data:`, result);

                    if (!result?.ok) {
                        throw new Error(result?.message || 'Unable to save settings.');
                    }

                    // Reset change tracking
                    hasChanges = false;
                    saveBtn.innerHTML = originalLabel;
                    updateSaveButtonState();

                    console.log(`✅ ${tabName} settings saved successfully!`);

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
                console.log('[Settings] Time format handler initialized with format:', timeFormatHandler.getFormat());
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
<?= $this->endSection() ?>
