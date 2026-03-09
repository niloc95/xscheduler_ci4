import { escapeHtml } from '../../utils/html.js';

export function renderWeekShell({ dayHeadersHtml, weekGridHtml, slotPanelHtml }) {
    return `
        <div class="scheduler-month-view rounded-xl overflow-hidden bg-surface-0 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-7 px-1 pt-2 pb-1">
                ${dayHeadersHtml}
            </div>

            <div class="grid grid-cols-7 grid-rows-1 gap-px px-1 pb-1">
                ${weekGridHtml}
            </div>

            <div class="p-3 md:p-4 border-t border-gray-100 dark:border-gray-800 bg-surface-1 dark:bg-gray-800/40" id="week-slot-panel">
                ${slotPanelHtml}
            </div>
        </div>
    `;
}

export function renderWeekDayCell({
    dateIso,
    dayNumber,
    dayNumberClass,
    cellClasses,
    appointmentChipsHtml,
    overflowLabel = '',
}) {
    return `
        <div class="${cellClasses}" data-week-date="${dateIso}">
            <button type="button" class="inline-flex items-center justify-center w-7 h-7 rounded-full text-sm leading-none ${dayNumberClass}" data-week-select-day="${dateIso}">
                ${dayNumber}
            </button>
            <div class="day-appointments flex-1 space-y-1 mt-1" data-week-date="${dateIso}">
                ${appointmentChipsHtml}
                ${overflowLabel ? `<div class="text-[10px] text-blue-600 dark:text-blue-400 font-medium">${escapeHtml(overflowLabel)}</div>` : ''}
            </div>
        </div>
    `;
}

export function renderWeekSlotPanel({
    selectedDateLabel,
    appointmentCount,
    appointmentListHtml,
    slotsHtml,
}) {
    return `
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">${escapeHtml(selectedDateLabel)}</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">${appointmentCount} appointment${appointmentCount === 1 ? '' : 's'}</span>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Appointments</h4>
                <div class="space-y-1.5 max-h-40 overflow-y-auto">${appointmentListHtml}</div>
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Available Slots</h4>
                <div class="space-y-1.5 max-h-48 overflow-y-auto">${slotsHtml}</div>
            </div>
        </div>
    `;
}

export function renderWeekAppointmentRow({ appointmentId, timeLabel, providerName, serviceName }) {
    return `
        <button type="button" class="w-full text-left px-2.5 py-2 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-surface-2 dark:hover:bg-gray-700/60 transition-colors" data-appointment-id="${appointmentId}">
            <div class="text-xs font-semibold text-gray-900 dark:text-white">${escapeHtml(timeLabel)}</div>
            <div class="text-[11px] text-gray-600 dark:text-gray-300 truncate">${escapeHtml(providerName)}${serviceName ? ` • ${escapeHtml(serviceName)}` : ''}</div>
        </button>
    `;
}

export function renderWeekAppointmentEmptyState() {
    return `
        <div class="text-xs text-gray-500 dark:text-gray-400 px-2 py-2 rounded-md bg-gray-50 dark:bg-gray-800/60 border border-dashed border-gray-200 dark:border-gray-700">
            No appointments for this day.
        </div>
    `;
}

export function renderWeekSlotRow({ providerName, timeLabel }) {
    return `
        <div class="px-2.5 py-2 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-900 dark:text-white">${escapeHtml(timeLabel)}</div>
            <div class="text-[11px] text-gray-600 dark:text-gray-300 truncate">${escapeHtml(providerName)}</div>
        </div>
    `;
}

export function renderWeekSlotEmptyState(message = 'No slots available for this day.') {
    return `
        <div class="text-xs text-gray-500 dark:text-gray-400 px-2 py-2 rounded-md bg-gray-50 dark:bg-gray-800/60 border border-dashed border-gray-200 dark:border-gray-700">
            ${escapeHtml(message)}
        </div>
    `;
}
