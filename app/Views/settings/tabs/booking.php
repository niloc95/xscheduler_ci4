<?php
/**
 * Settings Tab: Booking
 *
 * Standard booking fields (display/required toggles) and custom fields.
 * Included by settings/index.php — all view variables ($settings, etc.) are
 * available via CI4's $this->include() data sharing.
 */
?>
            <!-- Booking Settings Form -->
            <form id="booking-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" class="mt-4 space-y-6" data-tab-form="booking" data-no-spa="true">
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
                                    
                                    <div class="custom-field-settings <?= ($settings["booking.custom_field_{$i}_enabled"] ?? '0') !== '1' ? 'opacity-50 pointer-events-none' : '' ?>">
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
                    <button id="save-booking-btn" type="submit" class="btn-submit">
                        Save Booking Settings
                    </button>
                </div>
            </section>
            </form>
