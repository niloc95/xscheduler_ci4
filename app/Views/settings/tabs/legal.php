<?php
/**
 * Settings Tab: Legal Contents
 *
 * Cookie notice, terms, privacy, cancellation/rescheduling policies, URLs.
 * Included by settings/index.php — all view variables ($settings, etc.) are
 * available via CI4's $this->include() data sharing.
 */
?>
            <!-- Legal Contents Form -->
            <form id="legal-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" class="mt-4 space-y-6" data-tab-form="legal" data-no-spa="true">
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
                        <p class="form-help">Use placeholder <code>{terms_link}</code> in notification templates to include this.</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Privacy Policy</label>
                        <textarea name="privacy" rows="6" class="form-input" placeholder="Enter your privacy policy here..."><?= esc($settings['legal.privacy'] ?? '') ?></textarea>
                        <p class="form-help">Use placeholder <code>{privacy_link}</code> in notification templates to include this.</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Cancellation Policy</label>
                        <textarea name="cancellation_policy" rows="4" class="form-input" placeholder="E.g., Cancellations must be made at least 24 hours in advance..."><?= esc($settings['legal.cancellation_policy'] ?? '') ?></textarea>
                        <p class="form-help">Use placeholder <code>{cancellation_policy}</code> in notification templates to include this.</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Rescheduling Policy</label>
                        <textarea name="rescheduling_policy" rows="4" class="form-input" placeholder="E.g., You may reschedule your appointment up to 12 hours before..."><?= esc($settings['legal.rescheduling_policy'] ?? '') ?></textarea>
                        <p class="form-help">Use placeholder <code>{rescheduling_policy}</code> in notification templates to include this.</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Terms & Conditions URL (Optional)</label>
                        <input type="url" name="terms_url" class="form-input" placeholder="https://yourdomain.com/terms" value="<?= esc($settings['legal.terms_url'] ?? '') ?>" />
                        <p class="form-help">If set, <code>{terms_link}</code> will use this URL instead of showing full text.</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Privacy Policy URL (Optional)</label>
                        <input type="url" name="privacy_url" class="form-input" placeholder="https://yourdomain.com/privacy" value="<?= esc($settings['legal.privacy_url'] ?? '') ?>" />
                        <p class="form-help">If set, <code>{privacy_link}</code> will use this URL instead of showing full text.</p>
                    </div>
                </div>
                
                <!-- Save Button for Legal Settings -->
                <div class="flex justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button id="save-legal-btn" type="submit" class="btn-submit">
                        Save Legal Settings
                    </button>
                </div>
            </section>
            </form>
