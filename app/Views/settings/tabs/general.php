<?php
/**
 * Settings Tab: General
 *
 * Responsible for company name, email, phone, address, logo & icon uploads.
 * Included by settings/index.php — all view variables ($settings, etc.) are
 * available via CI4's $this->include() data sharing.
 */
?>
    <!-- General Settings Form -->
    <form id="general-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" enctype="multipart/form-data" class="mt-4 space-y-6" data-tab-form="general" data-no-spa="true">
        <?= csrf_field() ?>
        <input type="hidden" name="form_source" value="general_settings">
            <!-- General Settings -->
            <section id="panel-general" class="tab-panel">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">General</h3>
                    <div class="flex items-center gap-2" id="general-actions">
                        <button type="button" id="general-edit-btn" class="btn btn-secondary">Edit</button>
                        <button type="button" id="general-cancel-btn" class="btn btn-ghost hidden">Cancel</button>
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
                                <?php $logoPreview = setting_url('general.company_logo', 'assets/settings/default-logo.svg'); if ($logoPreview): ?>
                                    <img id="company_logo_preview_img" src="<?= esc($logoPreview) ?>" data-src="<?= esc($logoPreview) ?>" alt="Current logo" class="h-16 w-auto rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1 object-contain" />
                                <?php else: ?>
                                    <img id="company_logo_preview_img" src="" alt="Logo preview" class="h-16 w-auto rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1 hidden object-contain" />
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-field">
                        <label class="form-label">Company Icon (Favicon)</label>
                        <div class="md:flex md:items-center md:gap-4">
                            <div class="md:flex-1">
                                <input id="company_icon" type="file" name="company_icon" accept="image/x-icon,image/png,image/svg+xml,.ico" class="form-input file-input" disabled />
                                <p class="form-help mt-1">Browser tab icon (16x16 or 32x32 recommended). Supports ICO, PNG, SVG.</p>
                            </div>
                            <div id="company_icon_preview_container" class="mt-2 md:mt-0">
                                <?php $iconPreview = setting_url('general.company_icon', 'assets/settings/default-icon.svg'); if ($iconPreview): ?>
                                    <img id="company_icon_preview_img" src="<?= esc($iconPreview) ?>" data-src="<?= esc($iconPreview) ?>" alt="Current icon" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1 object-contain" />
                                <?php else: ?>
                                    <img id="company_icon_preview_img" src="" alt="Icon preview" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1 hidden object-contain" />
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Save Button for General Settings -->
                <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button id="save-general-btn" type="submit" class="btn-submit" disabled>
                        Save General Settings
                    </button>
                </div>
            </section>
        </form>
