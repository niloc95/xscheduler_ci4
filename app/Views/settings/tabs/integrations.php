<?php
/**
 * Settings Tab: Integrations
 *
 * Webhooks, analytics, API integrations, LDAP settings.
 * Included by settings/index.php — all view variables ($settings, etc.) are
 * available via CI4's $this->include() data sharing.
 */
?>
            <!-- Integrations Form -->
            <form id="integrations-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" class="mt-4 space-y-6" data-tab-form="integrations" data-no-spa="true">
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
                                <input type="checkbox" name="ldap_enabled" class="form-checkbox h-4 w-4 text-blue-600" value="1" <?= ($settings['integrations.ldap_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> Enable LDAP
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
                    <button id="save-integrations-btn" type="submit" class="btn-submit">
                        Save Integrations Settings
                    </button>
                </div>
            </section>
            </form>
