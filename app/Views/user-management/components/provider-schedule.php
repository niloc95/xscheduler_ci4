<?php
/**
 * Provider schedule form component.
 *
 * Uses native <input type="time"> for consistent cross-browser time picking.
 * Values are always HH:MM (24h) — the browser renders per the user's locale.
 *
 * Expected data:
 * - $scheduleDays: array of day keys (monday ... sunday)
 * - $providerSchedule: associative array keyed by day with is_active/start/end/breaks (HH:MM strings)
 * - $scheduleErrors: associative array of validation messages keyed by day
 * - $localizationContext: ['time_format' => '24h'|'12h', 'timezone' => string]
 * - $providerLocations: (optional) array of location rows with 'days' sub-arrays from LocationModel
 */
$localizationContext = $localizationContext ?? ['time_format' => '24h', 'timezone' => 'UTC'];
$timezone = $localizationContext['timezone'] ?? 'UTC';
$firstDay = $scheduleDays[0] ?? 'monday';

// Build location → days lookup for tick-box rendering.
// Each entry: ['id' => int, 'name' => string, 'days' => [0,1,...6]]
$providerLocations = $providerLocations ?? [];
$dayNameToInt = \App\Models\LocationModel::DAY_NAME_TO_INT;
$hasLocations = !empty($providerLocations);
?>
<div id="provider-schedule-section"
     class="mt-8"
     data-provider-schedule-section
     data-source-day="<?= esc($firstDay) ?>"
     data-timezone="<?= esc($timezone) ?>">
    <div class="card card-spacious">
        <div class="card-header flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h3 class="card-title text-lg">Provider Work Schedule</h3>
                <p class="card-subtitle text-sm">Define weekly availability, including optional mid-day breaks.</p>
            </div>
        </div>

        <div class="card-body space-y-6">
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-100">
                <p class="font-medium flex items-center justify-between gap-2">
                    <span>How it works</span>
                    <span class="text-xs font-normal text-blue-600 dark:text-blue-200">Timezone: <?= esc($timezone) ?></span>
                </p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Active days override the global Business Hours.</li>
                    <li>Leave a day inactive to fall back to the Business Hours configuration.</li>
                    <li>Breaks are optional and reduce availability within the active range.</li>
                </ul>
            </div>

            <div class="space-y-4">
                <!-- Copy to All Days — sticky bar visible while scrolling through days -->
                <div class="sticky top-0 z-10 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-3 flex items-center justify-between shadow-sm">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Set hours for each day, or copy from the first active day:</span>
                    <button type="button" class="btn btn-secondary inline-flex items-center gap-2 px-3 py-2 text-sm whitespace-nowrap" data-copy-schedule disabled aria-disabled="true" title="Copy the first day's schedule to all other days">
                        <span class="material-symbols-outlined text-base">content_copy</span>
                        Copy to All Days
                    </button>
                </div>
                <?php foreach ($scheduleDays as $index => $day):
                    $dayData = $providerSchedule[$day] ?? ['is_active' => false, 'start_time' => '', 'end_time' => '', 'break_start' => '', 'break_end' => ''];
                    $dayLabel = ucfirst($day);
                    $isActive = !empty($dayData['is_active']);
                    $error = $scheduleErrors[$day] ?? null;
                    $dayId = 'schedule-' . $day;
                    $isSourceDay = $index === 0;
                ?>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4" data-schedule-day="<?= esc($day) ?>" data-day-label="<?= esc($dayLabel) ?>" <?= $isSourceDay ? 'data-copy-source="true"' : '' ?>>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h4 class="text-base font-medium text-gray-800 dark:text-gray-200"><?= esc($dayLabel) ?></h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Set working hours and optional break.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="schedule[<?= esc($day) ?>][is_active]" value="0">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="toggle toggle-primary js-day-active"
                                    id="<?= esc($dayId) ?>-active"
                                    name="schedule[<?= esc($day) ?>][is_active]"
                                    value="1"
                                    <?= $isActive ? 'checked' : '' ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                        </div>
                    </div>

                    <?php if ($hasLocations): ?>
                    <!-- Location assignment tick boxes -->
                    <div class="mt-3 flex flex-wrap items-center gap-4 schedule-location-checkboxes <?= $isActive ? '' : 'opacity-40 pointer-events-none' ?>">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Locations:</span>
                        <?php foreach ($providerLocations as $loc):
                            $locDays = array_map('intval', $loc['days'] ?? []);
                            $dayInt  = $dayNameToInt[$day] ?? -1;
                            $isChecked = in_array($dayInt, $locDays, true);
                        ?>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-sm">
                            <input type="checkbox"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 js-location-day"
                                   name="schedule[<?= esc($day) ?>][locations][]"
                                   value="<?= esc($loc['id']) ?>"
                                   data-location-id="<?= esc($loc['id']) ?>"
                                   data-day="<?= esc($day) ?>"
                                   <?= $isChecked ? 'checked' : '' ?>
                                   <?= $isActive ? '' : 'disabled' ?>>
                            <span class="text-gray-700 dark:text-gray-300"><?= esc($loc['name'] ?: 'Location ' . ($loc['id'])) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4 schedule-time-grid">
                        <div>
                            <label for="<?= esc($dayId) ?>-start" class="form-label">Start</label>
                            <input type="time" id="<?= esc($dayId) ?>-start"
                                   name="schedule[<?= esc($day) ?>][start_time]"
                                   value="<?= esc($dayData['start_time']) ?>"
                                   data-field="start"
                                   data-time-input
                                   class="form-input mt-1"
                                <?= $isActive ? 'required' : '' ?>
                                   <?= $isActive ? '' : 'disabled' ?>>
                        </div>
                        <div>
                            <label for="<?= esc($dayId) ?>-end" class="form-label">End</label>
                            <input type="time" id="<?= esc($dayId) ?>-end"
                                   name="schedule[<?= esc($day) ?>][end_time]"
                                   value="<?= esc($dayData['end_time']) ?>"
                                   data-field="end"
                                   data-time-input
                                   class="form-input mt-1"
                                <?= $isActive ? 'required' : '' ?>
                                   <?= $isActive ? '' : 'disabled' ?>>
                        </div>
                        <div>
                            <label for="<?= esc($dayId) ?>-break-start" class="form-label">Break Start</label>
                            <input type="time" id="<?= esc($dayId) ?>-break-start"
                                   name="schedule[<?= esc($day) ?>][break_start]"
                                   value="<?= esc($dayData['break_start']) ?>"
                                   data-field="break_start"
                                   data-time-input
                                   class="form-input mt-1"
                                   <?= $isActive ? '' : 'disabled' ?>>
                        </div>
                        <div>
                            <label for="<?= esc($dayId) ?>-break-end" class="form-label">Break End</label>
                            <input type="time" id="<?= esc($dayId) ?>-break-end"
                                   name="schedule[<?= esc($day) ?>][break_end]"
                                   value="<?= esc($dayData['break_end']) ?>"
                                   data-field="break_end"
                                   data-time-input
                                   class="form-input mt-1"
                                   <?= $isActive ? '' : 'disabled' ?>>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <p class="mt-3 rounded border border-red-400 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-200">
                            <?= esc($error) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
