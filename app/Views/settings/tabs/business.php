<?php
/**
 * Settings Tab: Business Hours
 *
 * Work hours, breaks, blocked periods, reschedule/cancel rules, future limit, statuses.
 * Included by settings/index.php — all view variables ($settings, etc.) are
 * available via CI4's $this->include() data sharing.
 */

$localizationContext = is_array($localizationContext ?? null) ? $localizationContext : [];
$isTwelveHour = ($localizationContext['time_format'] ?? '24h') === '12h';
$timeFormatExample = (string) ($localizationContext['format_example'] ?? ($isTwelveHour ? '09:00 AM' : '09:00'));
$timeFormatHint = (string) ($localizationContext['format_description'] ?? ($isTwelveHour
    ? 'Use HH:MM AM/PM (e.g. 09:00 AM).'
    : 'Use 24-hour HH:MM (e.g. 09:00).'));

$localizationService = new \App\Services\LocalizationSettingsService();
$displayTime = static fn (?string $value): string => $localizationService->formatTimeForDisplay($value);
$timeInputPattern = $isTwelveHour
    ? '^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$'
    : '^([01][0-9]|2[0-3]):[0-5][0-9]$';
?>
            <!-- Business hours Form -->
            <form id="business-settings-form" method="POST" action="<?= base_url('api/v1/settings') ?>" class="mt-4 space-y-6" data-tab-form="business" data-no-spa="true">
                <?= csrf_field() ?>
                <input type="hidden" name="form_source" value="business_settings">
                <input type="hidden" name="time_format" value="<?= esc($isTwelveHour ? '12h' : '24h') ?>">
            <section id="panel-business" class="tab-panel hidden">
                <div class="space-y-6">
                    <div class="form-field">
                        <label class="form-label">Default Working Hours</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <input type="text" name="work_start" class="form-input" value="<?= esc($displayTime($settings['business.work_start'] ?? '09:00')) ?>" placeholder="<?= esc($timeFormatExample) ?>" pattern="<?= esc($timeInputPattern) ?>" data-time-format="<?= esc($isTwelveHour ? '12h' : '24h') ?>" inputmode="numeric" autocomplete="off">
                            <input type="text" name="work_end" class="form-input" value="<?= esc($displayTime($settings['business.work_end'] ?? '17:00')) ?>" placeholder="<?= esc($timeFormatExample) ?>" pattern="<?= esc($timeInputPattern) ?>" data-time-format="<?= esc($isTwelveHour ? '12h' : '24h') ?>" inputmode="numeric" autocomplete="off">
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" data-time-format-hint><?= esc($timeFormatHint) ?></p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Breaks</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <input type="text" name="break_start" class="form-input" value="<?= esc($displayTime($settings['business.break_start'] ?? '12:00')) ?>" placeholder="<?= esc($timeFormatExample) ?>" pattern="<?= esc($timeInputPattern) ?>" data-time-format="<?= esc($isTwelveHour ? '12h' : '24h') ?>" inputmode="numeric" autocomplete="off">
                            <input type="text" name="break_end" class="form-input" value="<?= esc($displayTime($settings['business.break_end'] ?? '13:00')) ?>" placeholder="<?= esc($timeFormatExample) ?>" pattern="<?= esc($timeInputPattern) ?>" data-time-format="<?= esc($isTwelveHour ? '12h' : '24h') ?>" inputmode="numeric" autocomplete="off">
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" data-time-format-hint><?= esc($timeFormatHint) ?></p>
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
                    <button id="save-business-btn" type="submit" class="btn-submit">
                        Save Business Settings
                    </button>
                </div>
            </section>
            </form>
