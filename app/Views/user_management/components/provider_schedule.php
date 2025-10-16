<?php
/**
 * Provider schedule form component.
 *
 * Expected data:
 * - $scheduleDays: array of day keys (monday ... sunday)
 * - $providerSchedule: associative array keyed by day with is_active/start/end/breaks (strings)
 * - $scheduleErrors: associative array of validation messages keyed by day
 */
?>
<div id="provider-schedule-section" class="mt-8" data-provider-schedule-section style="display: none;">
    <div class="card card-spacious">
        <div class="card-header flex-col items-start gap-2">
            <h3 class="card-title text-lg">Provider Work Schedule</h3>
            <p class="card-subtitle text-sm">Define weekly availability, including optional mid-day breaks.</p>
        </div>

        <div class="card-body space-y-6">
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-100">
                <p class="font-medium">How it works</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Active days override the global Business Hours.</li>
                    <li>Leave a day inactive to fall back to the Business Hours configuration.</li>
                    <li>Breaks are optional and reduce availability within the active range.</li>
                </ul>
            </div>

            <div class="space-y-4">
                <?php foreach ($scheduleDays as $day):
                    $dayData = $providerSchedule[$day] ?? ['is_active' => false, 'start_time' => '', 'end_time' => '', 'break_start' => '', 'break_end' => ''];
                    $dayLabel = ucfirst($day);
                    $isActive = !empty($dayData['is_active']);
                    $error = $scheduleErrors[$day] ?? null;
                    $dayId = 'schedule-' . $day;
                ?>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4" data-schedule-day="<?= esc($day) ?>">
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

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4 schedule-time-grid">
                        <div>
                            <label for="<?= esc($dayId) ?>-start" class="block text-sm font-medium text-gray-600 dark:text-gray-300">Start</label>
                            <input type="time" id="<?= esc($dayId) ?>-start"
                                   name="schedule[<?= esc($day) ?>][start_time]"
                                   value="<?= esc($dayData['start_time']) ?>"
                                   class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                   <?= $isActive ? '' : 'disabled' ?>>
                        </div>
                        <div>
                            <label for="<?= esc($dayId) ?>-end" class="block text-sm font-medium text-gray-600 dark:text-gray-300">End</label>
                            <input type="time" id="<?= esc($dayId) ?>-end"
                                   name="schedule[<?= esc($day) ?>][end_time]"
                                   value="<?= esc($dayData['end_time']) ?>"
                                   class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                   <?= $isActive ? '' : 'disabled' ?>>
                        </div>
                        <div>
                            <label for="<?= esc($dayId) ?>-break-start" class="block text-sm font-medium text-gray-600 dark:text-gray-300">Break Start</label>
                            <input type="time" id="<?= esc($dayId) ?>-break-start"
                                   name="schedule[<?= esc($day) ?>][break_start]"
                                   value="<?= esc($dayData['break_start']) ?>"
                                   class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                   <?= $isActive ? '' : 'disabled' ?>>
                        </div>
                        <div>
                            <label for="<?= esc($dayId) ?>-break-end" class="block text-sm font-medium text-gray-600 dark:text-gray-300">Break End</label>
                            <input type="time" id="<?= esc($dayId) ?>-break-end"
                                   name="schedule[<?= esc($day) ?>][break_end]"
                                   value="<?= esc($dayData['break_end']) ?>"
                                   class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
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

<script>
(function() {
    function toggleScheduleSection(roleValue) {
        const section = document.getElementById('provider-schedule-section');
        if (!section) return;
        section.style.display = roleValue === 'provider' ? 'block' : 'none';
    }

    function toggleDayInputs(dayRow, isActive) {
        if (!dayRow) return;
        const inputs = dayRow.querySelectorAll('input[type="time"]');
        inputs.forEach((input) => {
            input.disabled = !isActive;
            if (!isActive) {
                input.classList.add('opacity-70');
            } else {
                input.classList.remove('opacity-70');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('role');
        if (!roleSelect) return;

        toggleScheduleSection(roleSelect.value);

        document.querySelectorAll('[data-schedule-day]').forEach(function(row) {
            const checkbox = row.querySelector('.js-day-active');
            toggleDayInputs(row, checkbox && checkbox.checked);
            if (checkbox) {
                checkbox.addEventListener('change', function() {
                    toggleDayInputs(row, checkbox.checked);
                });
            }
        });

        roleSelect.addEventListener('change', function() {
            toggleScheduleSection(roleSelect.value);
        });
    });
})();
</script>
