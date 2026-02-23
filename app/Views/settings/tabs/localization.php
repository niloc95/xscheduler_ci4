<?php
/**
 * Settings Tab: Localization
 *
 * Time format, first day of week, language, currency, timezone.
 * Included by settings/index.php — all view variables ($settings, etc.) are
 * available via CI4's $this->include() data sharing.
 */
?>
            <!-- Localization Form -->
            <form id="localization-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" class="mt-4 space-y-6" data-tab-form="localization" data-no-spa="true">
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
                    <button id="save-localization-btn" type="submit" class="btn-submit">
                        Save Localization Settings
                    </button>
                </div>
            </section>
            </form>
