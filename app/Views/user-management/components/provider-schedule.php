<?php
/**
 * Provider schedule form component.
 *
 * Expected data:
 * - $scheduleDays: array of day keys (monday ... sunday)
 * - $providerSchedule: associative array keyed by day with is_active/start/end/breaks (strings)
 * - $scheduleErrors: associative array of validation messages keyed by day
 */
$localizationContext = $localizationContext ?? ['time_format' => '24h', 'timezone' => 'UTC'];
$timeFormat = $localizationContext['time_format'] ?? '24h';
$timezone = $localizationContext['timezone'] ?? 'UTC';
$timeExample = $timeFormatExample ?? ($timeFormat === '12h' ? '09:00 AM' : '09:00');
$timePattern = $timeFormat === '12h'
    ? '^(0?[1-9]|1[0-2]):[0-5]\\d\\s?(AM|PM|am|pm)$'
    : '^([01]?\\d|2[0-3]):[0-5]\\d$';
$inputMode = $timeFormat === '12h' ? 'text' : 'numeric';
$formatDescription = $localizationContext['format_description'] ?? (
    $timeFormat === '12h'
        ? 'Use 12-hour time like ' . $timeExample . ' (include AM/PM).'
        : 'Use 24-hour time like ' . $timeExample . '.'
);
$firstDay = $scheduleDays[0] ?? 'monday';
?>
<?php
// Determine initial visibility based on current role selection
$currentRole = old('role', $user['role'] ?? '');
$isProviderRole = ($currentRole === 'provider');
?>
<div id="provider-schedule-section"
     class="mt-8 <?= $isProviderRole ? '' : 'hidden' ?>"
     data-provider-schedule-section
     data-source-day="<?= esc($firstDay) ?>"
     data-time-format="<?= esc($timeFormat) ?>"
     data-time-example="<?= esc($timeExample) ?>"
     data-timezone="<?= esc($timezone) ?>"
     data-time-pattern="<?= esc($timePattern) ?>"
     data-format-description="<?= esc($formatDescription) ?>">
    <div class="card card-spacious">
        <div class="card-header flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h3 class="card-title text-lg">Provider Work Schedule</h3>
                <p class="card-subtitle text-sm">Define weekly availability, including optional mid-day breaks.</p>
            </div>
            <button type="button" class="btn btn-secondary inline-flex items-center gap-2 px-3 py-2 text-sm" data-copy-schedule disabled aria-disabled="true" title="Copy the first day's schedule to all other days">
                <span class="material-symbols-outlined text-base">content_copy</span>
                Copy to All Days
            </button>
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
                    <li><?= esc($formatDescription) ?></li>
                </ul>
            </div>

            <div class="space-y-4">
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

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4 schedule-time-grid">
                        <div>
                <label for="<?= esc($dayId) ?>-start" class="block text-sm font-medium text-gray-600 dark:text-gray-300">Start</label>
                <input type="text" id="<?= esc($dayId) ?>-start"
                    name="schedule[<?= esc($day) ?>][start_time]"
                    value="<?= esc($dayData['start_time']) ?>"
                    data-field="start"
                    data-time-input
                    pattern="<?= esc($timePattern) ?>"
                                   inputmode="<?= esc($inputMode) ?>"
                    placeholder="<?= esc($timeExample) ?>"
                    title="<?= esc($formatDescription) ?>"
                    class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    <?= $isActive ? '' : 'disabled' ?>>
                        </div>
                        <div>
                <label for="<?= esc($dayId) ?>-end" class="block text-sm font-medium text-gray-600 dark:text-gray-300">End</label>
                <input type="text" id="<?= esc($dayId) ?>-end"
                                   name="schedule[<?= esc($day) ?>][end_time]"
                                   value="<?= esc($dayData['end_time']) ?>"
                                   data-field="end"
                    data-time-input
                    pattern="<?= esc($timePattern) ?>"
                                   inputmode="<?= esc($inputMode) ?>"
                    placeholder="<?= esc($timeExample) ?>"
                    title="<?= esc($formatDescription) ?>"
                                   class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                   <?= $isActive ? '' : 'disabled' ?>>
                        </div>
                        <div>
                <label for="<?= esc($dayId) ?>-break-start" class="block text-sm font-medium text-gray-600 dark:text-gray-300">Break Start</label>
                <input type="text" id="<?= esc($dayId) ?>-break-start"
                                   name="schedule[<?= esc($day) ?>][break_start]"
                                   value="<?= esc($dayData['break_start']) ?>"
                                   data-field="break_start"
                    data-time-input
                    pattern="<?= esc($timePattern) ?>"
                                   inputmode="<?= esc($inputMode) ?>"
                    placeholder="<?= esc($timeExample) ?>"
                    title="<?= esc($formatDescription) ?>"
                                   class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                   <?= $isActive ? '' : 'disabled' ?>>
                        </div>
                        <div>
                <label for="<?= esc($dayId) ?>-break-end" class="block text-sm font-medium text-gray-600 dark:text-gray-300">Break End</label>
                <input type="text" id="<?= esc($dayId) ?>-break-end"
                                   name="schedule[<?= esc($day) ?>][break_end]"
                                   value="<?= esc($dayData['break_end']) ?>"
                                   data-field="break_end"
                    data-time-input
                    pattern="<?= esc($timePattern) ?>"
                                   inputmode="<?= esc($inputMode) ?>"
                    placeholder="<?= esc($timeExample) ?>"
                    title="<?= esc($formatDescription) ?>"
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
    let scheduleSection = null;
    let copyBtn = null;
    let sourceDayKey = 'monday';

    function setCopyButtonDisabled(disabled) {
        if (!copyBtn) return;
        copyBtn.disabled = disabled;
        copyBtn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    }

    function toggleScheduleSection(roleValue) {
        if (!scheduleSection) return;
        const isProvider = roleValue === 'provider';
        scheduleSection.classList.toggle('hidden', !isProvider);
        if (!isProvider) {
            setCopyButtonDisabled(true);
        } else {
            updateCopyButtonState();
        }
    }

    function toggleDayInputs(dayRow, isActive) {
        if (!dayRow) return;
    const inputs = dayRow.querySelectorAll('[data-time-input]');
        inputs.forEach((input) => {
            input.disabled = !isActive;
            if (!isActive) {
                input.classList.add('opacity-70');
            } else {
                input.classList.remove('opacity-70');
            }
        });
    }

    function getSourceRow() {
        if (!scheduleSection) return null;
        return scheduleSection.querySelector(`[data-schedule-day="${sourceDayKey}"]`);
    }

    function getField(row, field) {
        return row ? row.querySelector(`[data-field="${field}"]`) : null;
    }

    function parseToMinutes(value) {
        if (!value) return null;
        const trimmed = value.trim();
        if (!trimmed) return null;

        const format = scheduleSection?.dataset.timeFormat || '24h';

        if (format === '12h') {
            const match = trimmed.match(/^(0?[1-9]|1[0-2]):([0-5]\d)\s?(AM|PM)$/i);
            if (!match) return null;
            let hour = parseInt(match[1], 10);
            const minute = parseInt(match[2], 10);
            const period = match[3].toUpperCase();
            if (period === 'PM' && hour !== 12) {
                hour += 12;
            } else if (period === 'AM' && hour === 12) {
                hour = 0;
            }
            return hour * 60 + minute;
        }

        const match = trimmed.match(/^([01]?\d|2[0-3]):([0-5]\d)$/);
        if (!match) return null;
        const hour = parseInt(match[1], 10);
        const minute = parseInt(match[2], 10);
        return hour * 60 + minute;
    }

    function isValidTimeRange(start, end) {
        const startMinutes = parseToMinutes(start);
        const endMinutes = parseToMinutes(end);
        if (startMinutes === null || endMinutes === null) return false;
        return startMinutes < endMinutes;
    }

    function normaliseInputValue(value) {
        if (!value) return value;
        const format = scheduleSection?.dataset.timeFormat || '24h';
        const trimmed = value.trim();
        if (!trimmed) return trimmed;
        if (format === '12h') {
            const match = trimmed.match(/^(0?[1-9]|1[0-2]):([0-5]\d)\s?(AM|PM)$/i);
            if (!match) return trimmed;
            const hour = match[1].padStart(2, '0');
            const minute = match[2];
            const period = match[3].toUpperCase();
            return `${hour}:${minute} ${period}`;
        }
        const match = trimmed.match(/^([01]?\d|2[0-3]):([0-5]\d)$/);
        if (!match) return trimmed;
        const hour = match[1].padStart(2, '0');
        const minute = match[2];
        return `${hour}:${minute}`;
    }

    function updateCopyButtonState() {
        if (!scheduleSection || !copyBtn) return;
        if (scheduleSection.classList.contains('hidden')) {
            setCopyButtonDisabled(true);
            return;
        }
        const sourceRow = getSourceRow();
        const checkbox = sourceRow ? sourceRow.querySelector('.js-day-active') : null;
        const startInput = getField(sourceRow, 'start');
        const endInput = getField(sourceRow, 'end');
        const enabled = !!(checkbox && checkbox.checked && startInput && endInput && isValidTimeRange(startInput.value, endInput.value));
        setCopyButtonDisabled(!enabled);
    }

    function applyValues(row, checkbox, values, activateIfNeeded) {
        if (!row) return;
        const shouldActivate = !!activateIfNeeded && checkbox && !checkbox.checked;
        if (shouldActivate) {
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const mapping = ['start', 'end', 'break_start', 'break_end'];
        mapping.forEach((key) => {
            const value = values[key];
            if (!value && (key === 'break_start' || key === 'break_end')) {
                return; // skip empty optional fields
            }
            const input = getField(row, key);
            if (!input || input.disabled) return;
            if (value) {
                input.value = normaliseInputValue(value);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function handleCopyClick() {
        const sourceRow = getSourceRow();
        if (!sourceRow) return;

        const checkbox = sourceRow.querySelector('.js-day-active');
        const startInput = getField(sourceRow, 'start');
        const endInput = getField(sourceRow, 'end');
        const breakStartInput = getField(sourceRow, 'break_start');
        const breakEndInput = getField(sourceRow, 'break_end');

        const start = startInput?.value || '';
        const end = endInput?.value || '';

        if (!checkbox || !checkbox.checked) {
            setCopyButtonDisabled(true);
            return;
        }

        if (!start || !end) {
            window.XSNotify?.toast?.({ type: 'warning', title: 'Incomplete schedule', message: 'Set start and end times before copying.' })
                ?? window.alert?.('Set start and end times before copying.');
            return;
        }

        if (!isValidTimeRange(start, end)) {
            window.XSNotify?.toast?.({ type: 'warning', title: 'Invalid time range', message: 'Start time must be earlier than end time.' })
                ?? window.alert?.('Start time must be earlier than end time.');
            return;
        }

        const values = {
            start,
            end,
            break_start: breakStartInput?.value || '',
            break_end: breakEndInput?.value || '',
        };

        const rows = Array.from(scheduleSection.querySelectorAll('[data-schedule-day]'));
        const inactive = [];
        const active = [];

        rows.forEach((row) => {
            if (row === sourceRow) return;
            const rowCheckbox = row.querySelector('.js-day-active');
            if (rowCheckbox && rowCheckbox.checked) {
                active.push({ row, checkbox: rowCheckbox });
            } else {
                inactive.push({ row, checkbox: rowCheckbox });
            }
        });

        active.forEach(({ row, checkbox: rowCheckbox }) => {
            applyValues(row, rowCheckbox, values, false);
        });

        if (inactive.length) {
            const sourceLabel = sourceRow.dataset.dayLabel || 'Source day';
            const shouldActivate = window.confirm(`Some days are inactive. Activate them and apply ${sourceLabel}'s schedule?`);
            if (shouldActivate) {
                inactive.forEach(({ row, checkbox: rowCheckbox }) => {
                    applyValues(row, rowCheckbox, values, true);
                });
            }
        }

        const sourceLabel = sourceRow.dataset.dayLabel || 'Source day';
        window.XSNotify?.toast?.({
            type: 'success',
            title: 'Schedule copied',
            message: `${sourceLabel}'s hours copied to other days.`,
        }) ?? console.log(`${sourceLabel}'s hours copied to other days.`);

        updateCopyButtonState();
    }

    document.addEventListener('DOMContentLoaded', function() {
        scheduleSection = document.querySelector('[data-provider-schedule-section]');
        if (scheduleSection) {
            sourceDayKey = scheduleSection.dataset.sourceDay || sourceDayKey;
            copyBtn = scheduleSection.querySelector('[data-copy-schedule]');
        }

        const roleSelect = document.getElementById('role');
        if (!roleSelect) return;

        const dayRows = document.querySelectorAll('[data-schedule-day]');
        dayRows.forEach(function(row) {
            const checkbox = row.querySelector('.js-day-active');
            toggleDayInputs(row, checkbox && checkbox.checked);
            if (checkbox) {
                checkbox.addEventListener('change', function() {
                    toggleDayInputs(row, checkbox.checked);
                    if (row === getSourceRow()) {
                        updateCopyButtonState();
                    }
                });
            }
        });

        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                if (copyBtn.disabled) return;
                handleCopyClick();
            });

            const sourceRow = getSourceRow();
            if (sourceRow) {
                ['start', 'end'].forEach((field) => {
                    const input = getField(sourceRow, field);
                    if (input) {
                        input.addEventListener('input', updateCopyButtonState);
                        input.addEventListener('change', updateCopyButtonState);
                    }
                });
                const checkbox = sourceRow.querySelector('.js-day-active');
                checkbox?.addEventListener('change', updateCopyButtonState);
            }
        }

        toggleScheduleSection(roleSelect.value);
        updateCopyButtonState();

        roleSelect.addEventListener('change', function() {
            toggleScheduleSection(roleSelect.value);
        });

        const inputs = scheduleSection?.querySelectorAll('[data-time-input]') || [];
        inputs.forEach((input) => {
            input.addEventListener('blur', function() {
                input.value = normaliseInputValue(input.value);
            });
        });
    });
})();
</script>
