<?php
/**
 * Settings Tab: Notifications
 *
 * Email/SMS/WhatsApp integration config, event-channel rules matrix,
 * delivery logs, message templates. POSTs to /settings/notifications.
 * Included by settings/index.php — all view variables are available
 * via CI4's $this->include() data sharing.
 */
?>
            <!-- Notifications Settings Form (Phase 1: Rules only, no sending) -->
            <form id="notifications-settings-form" method="POST" action="<?= base_url('settings/notifications') ?>" class="mt-4 space-y-6" data-tab-form="notifications" data-no-spa="true">
                <?= csrf_field() ?>
                <input type="hidden" name="form_source" value="notification_rules_phase1">
                <section id="panel-notifications" class="tab-panel hidden">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">Notifications</h3>
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
                            <p class="form-help">Applies to "Appointment Reminder" across channels (Phase 1).</p>
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
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Credentials are stored encrypted. Use "Send Test Email" to verify connectivity.</p>
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
                                <button type="submit" name="intent" value="test_email" class="btn-test">
                                    Send Test Email
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <button type="submit" name="intent" value="save_email" class="btn-submit inline-flex items-center gap-2">
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
                        <div id="wa_link_generator_section" class="mt-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 <?= $waProvider !== 'link_generator' ? 'hidden' : '' ?>">
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
                        <div id="wa_twilio_section" class="mt-4 <?= $waProvider !== 'twilio' ? 'hidden' : '' ?>">
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
                        <div id="wa_meta_section" class="mt-4 <?= $waProvider !== 'meta_cloud' ? 'hidden' : '' ?>">
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
                                <button type="submit" name="intent" value="test_whatsapp" class="btn-test">
                                    Test WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <button type="submit" name="intent" value="save_whatsapp" class="btn-submit inline-flex items-center gap-2">
                                <span class="material-symbols-outlined text-base">save</span>
                                Save WhatsApp Settings
                            </button>
                        </div>


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
                                <button type="submit" name="intent" value="test_sms" class="btn-test">
                                    Send Test SMS
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <button type="submit" name="intent" value="save_sms" class="btn-submit inline-flex items-center gap-2">
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

                    <!-- Message Templates Section -->
                    <div class="mt-6 border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200">Message Templates</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Customize notification messages for each event type and channel. Use placeholders like <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{customer_name}</code>, <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{service_name}</code>, <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{appointment_date}</code>, <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{appointment_time}</code>, <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{provider_name}</code>, <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{business_name}</code>.
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Legal placeholders: <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{cancellation_policy}</code>, <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{rescheduling_policy}</code>, <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{terms_link}</code>, <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{privacy_link}</code>.
                                </p>
                            </div>
                        </div>

                        <?php
                            // Get existing templates from database or use defaults
                            $messageTemplates = $notificationMessageTemplates ?? [];
                            
                            // Default templates for each event/channel combination
                            $defaultTemplates = [
                                'appointment_confirmed' => [
                                    'email' => [
                                        'subject' => 'Appointment Confirmed - {service_name}',
                                        'body' => "Hi {customer_name},\n\nYour appointment has been confirmed!\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\nImportant Information:\n{cancellation_policy}\n{rescheduling_policy}\n\nThank you for booking with {business_name}!\n\nView our Terms & Conditions: {terms_link}\nPrivacy Policy: {privacy_link}"
                                    ],
                                    'sms' => [
                                        'body' => "✅ Appt confirmed: {service_name} on {appointment_date} at {appointment_time} with {provider_name}. {business_name}"
                                    ],
                                    'whatsapp' => [
                                        'body' => "✅ *Appointment Confirmed*\n\nHi {customer_name}!\n\nYour appointment has been confirmed:\n\n📅 *Date:* {appointment_date}\n🕐 *Time:* {appointment_time}\n💼 *Service:* {service_name}\n👤 *With:* {provider_name}\n\n{cancellation_policy}\n\nThank you for booking with {business_name}!\n\n_Reply to this message if you need to make any changes._"
                                    ]
                                ],
                                'appointment_reminder' => [
                                    'email' => [
                                        'subject' => 'Reminder: Your Appointment Tomorrow - {service_name}',
                                        'body' => "Hi {customer_name},\n\nThis is a friendly reminder about your upcoming appointment:\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\n{rescheduling_policy}\n\nWe look forward to seeing you!\n\n{business_name}"
                                    ],
                                    'sms' => [
                                        'body' => "⏰ Reminder: {service_name} on {appointment_date} at {appointment_time}. {business_name}"
                                    ],
                                    'whatsapp' => [
                                        'body' => "⏰ *Appointment Reminder*\n\nHi {customer_name}!\n\nThis is a friendly reminder about your upcoming appointment:\n\n📅 *Date:* {appointment_date}\n🕐 *Time:* {appointment_time}\n💼 *Service:* {service_name}\n👤 *With:* {provider_name}\n\nWe look forward to seeing you!\n\n_{business_name}_"
                                    ]
                                ],
                                'appointment_cancelled' => [
                                    'email' => [
                                        'subject' => 'Appointment Cancelled - {service_name}',
                                        'body' => "Hi {customer_name},\n\nYour appointment has been cancelled:\n\n📅 Date: {appointment_date}\n🕐 Time: {appointment_time}\n💼 Service: {service_name}\n\nWe hope to see you again soon! To reschedule, please contact us or book online.\n\n{business_name}"
                                    ],
                                    'sms' => [
                                        'body' => "❌ Appt cancelled: {service_name} on {appointment_date}. Contact us to reschedule. {business_name}"
                                    ],
                                    'whatsapp' => [
                                        'body' => "❌ *Appointment Cancelled*\n\nHi {customer_name},\n\nYour appointment has been cancelled:\n\n📅 *Date:* {appointment_date}\n🕐 *Time:* {appointment_time}\n💼 *Service:* {service_name}\n\nWe hope to see you again soon!\n\nTo reschedule, please contact us or book online.\n\n_{business_name}_"
                                    ]
                                ],
                                'appointment_rescheduled' => [
                                    'email' => [
                                        'subject' => 'Appointment Rescheduled - {service_name}',
                                        'body' => "Hi {customer_name},\n\nYour appointment has been rescheduled to:\n\n📅 New Date: {appointment_date}\n🕐 New Time: {appointment_time}\n💼 Service: {service_name}\n👤 With: {provider_name}\n\n{rescheduling_policy}\n\nPlease let us know if this doesn't work for you.\n\n{business_name}"
                                    ],
                                    'sms' => [
                                        'body' => "📅 Appt rescheduled: {service_name} now {appointment_date} at {appointment_time}. {business_name}"
                                    ],
                                    'whatsapp' => [
                                        'body' => "📅 *Appointment Rescheduled*\n\nHi {customer_name}!\n\nYour appointment has been rescheduled to:\n\n📅 *New Date:* {appointment_date}\n🕐 *New Time:* {appointment_time}\n💼 *Service:* {service_name}\n👤 *With:* {provider_name}\n\nPlease let us know if this doesn't work for you.\n\n_{business_name}_"
                                    ]
                                ],
                            ];
                            
                            // Merge stored templates with defaults
                            foreach ($defaultTemplates as $eventType => $channels) {
                                foreach ($channels as $channel => $defaults) {
                                    $storedSubject = $messageTemplates[$eventType][$channel]['subject'] ?? null;
                                    $storedBody = $messageTemplates[$eventType][$channel]['body'] ?? null;
                                    $defaultTemplates[$eventType][$channel]['subject'] = $storedSubject ?? ($defaults['subject'] ?? '');
                                    $defaultTemplates[$eventType][$channel]['body'] = $storedBody ?? ($defaults['body'] ?? '');
                                }
                            }
                            
                            $channelLabels = [
                                'email' => ['icon' => 'email', 'label' => 'Email'],
                                'sms' => ['icon' => 'sms', 'label' => 'SMS (248 chars max)'],
                                'whatsapp' => ['icon' => 'chat', 'label' => 'WhatsApp']
                            ];
                        ?>

                        <!-- Template Tabs by Event Type -->
                        <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
                            <nav class="-mb-px flex space-x-4 overflow-x-auto" aria-label="Template tabs">
                                <?php $first = true; foreach (array_keys($defaultTemplates) as $eventType): ?>
                                    <button type="button" 
                                            class="template-tab-btn whitespace-nowrap py-2 px-3 border-b-2 font-medium text-sm <?= $first ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' ?>"
                                            data-template-tab="<?= esc($eventType) ?>">
                                        <?= esc(ucwords(str_replace('_', ' ', $eventType))) ?>
                                    </button>
                                <?php $first = false; endforeach; ?>
                            </nav>
                        </div>

                        <!-- Template Content Panels -->
                        <?php $first = true; foreach ($defaultTemplates as $eventType => $channels): ?>
                            <div class="template-panel <?= $first ? '' : 'hidden' ?>" data-template-panel="<?= esc($eventType) ?>">
                                <div class="space-y-4">
                                    <?php foreach ($channels as $channel => $template): ?>
                                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                            <div class="flex items-center gap-2 mb-3">
                                                <span class="material-symbols-outlined text-gray-500 dark:text-gray-400"><?= esc($channelLabels[$channel]['icon']) ?></span>
                                                <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= esc($channelLabels[$channel]['label']) ?></h5>
                                            </div>
                                            
                                            <?php if ($channel === 'email'): ?>
                                                <div class="form-field mb-3">
                                                    <label class="form-label text-xs">Subject</label>
                                                    <input type="text" 
                                                           name="templates[<?= esc($eventType) ?>][<?= esc($channel) ?>][subject]" 
                                                           class="form-input text-sm"
                                                           value="<?= esc($template['subject'] ?? '') ?>"
                                                           placeholder="Email subject line..." />
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="form-field">
                                                <label class="form-label text-xs">Message Body</label>
                                                <textarea name="templates[<?= esc($eventType) ?>][<?= esc($channel) ?>][body]" 
                                                          rows="<?= $channel === 'sms' ? '2' : '6' ?>" 
                                                          class="form-input text-sm font-mono <?= $channel === 'sms' ? 'sms-template-textarea' : '' ?>"
                                                          data-channel="<?= esc($channel) ?>"
                                                          <?php if ($channel === 'sms'): ?>data-maxlength="248"<?php endif; ?>
                                                          placeholder="<?= $channel === 'sms' ? 'SMS message (keep under 248 characters)...' : 'Message body...' ?>"><?= esc($template['body'] ?? '') ?></textarea>
                                                <?php if ($channel === 'sms'): ?>
                                                    <div class="flex justify-between items-center mt-1">
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Keep SMS messages concise. Max 248 characters recommended.</p>
                                                        <span class="sms-char-counter text-xs font-medium" data-for="templates[<?= esc($eventType) ?>][sms][body]">
                                                            <span class="char-count">0</span>/248
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php $first = false; endforeach; ?>

                        <div class="flex justify-end mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <button type="button" id="reset-templates-btn" class="btn btn-ghost mr-3">
                                Reset to Defaults
                            </button>
                            <button type="submit" name="intent" value="save_templates" class="btn-submit inline-flex items-center gap-2">
                                <span class="material-symbols-outlined text-base">save</span>
                                Save Templates
                            </button>
                        </div>
                    </div>

                    <!-- Template Tabs JavaScript -->

                    <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button id="save-notifications-btn" type="submit" name="intent" value="save" class="btn-submit">
                            Save Notification Settings
                        </button>
                    </div>
                </section>
            </form>
