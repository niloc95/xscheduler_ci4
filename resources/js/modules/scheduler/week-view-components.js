import { escapeHtml } from '../../utils/html.js';

export function renderWeekShell({ dayHeadersHtml, weekGridHtml, slotPanelHtml }) {
    return `
        <div class="scheduler-month-view rounded-xl bg-surface-0 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-7 px-1 pt-2 pb-1">
                ${dayHeadersHtml}
            </div>

            <div class="grid grid-cols-7 grid-rows-1 gap-px px-1 pb-1">
                ${weekGridHtml}
            </div>

            <div class="p-3 md:p-4 border-t-2 border-blue-200/70 dark:border-blue-500/40 bg-gradient-to-b from-blue-50/70 to-surface-1 dark:from-blue-900/20 dark:to-gray-800/40" id="week-slot-panel">
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
    overflowButtonHtml = '',
}) {
    return `
        <div class="${cellClasses}" data-week-date="${dateIso}">
            <button type="button" class="inline-flex items-center justify-center w-7 h-7 rounded-full text-sm leading-none ${dayNumberClass}" data-week-select-day="${dateIso}">
                ${dayNumber}
            </button>
            <div class="day-appointments flex-1 space-y-1 mt-1" data-week-date="${dateIso}">
                ${appointmentChipsHtml}
                ${overflowButtonHtml}
            </div>
        </div>
    `;
}

export function renderWeekSlotPanel({ selectedDateLabel, appointmentCount, appointmentListHtml }) {
    return `
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white" data-week-selected-label>${escapeHtml(selectedDateLabel)}</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400" data-week-appointment-count>${appointmentCount} appointment${appointmentCount === 1 ? '' : 's'}</span>
            </div>

            <div class="rounded-lg border border-blue-200/70 dark:border-blue-600/40 bg-white/70 dark:bg-gray-800/50 p-2 md:p-3">
                <section>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Appointments</h4>
                    <div class="space-y-1.5" data-week-appointments-content>${appointmentListHtml}</div>
                </section>
            </div>
        </div>
    `;
}

export function renderWeekAppointmentRow({
    appointmentId,
    timeLabel,
    providerName,
    serviceName,
    providerInitials,
    providerColor,
    statusLabel,
    statusDotColor,
}) {
    return `
        <button type="button" class="w-full text-left rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:shadow-sm hover:border-blue-200 dark:hover:border-blue-700 transition-all overflow-hidden" data-appointment-id="${appointmentId}">
            <div class="px-2.5 py-1.5 flex items-center justify-between" data-bg-color="${escapeHtml(`${providerColor}1f`)}">
                <div class="text-[11px] font-semibold text-gray-800 dark:text-gray-100 tabular-nums">${escapeHtml(timeLabel)}</div>
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                    <span class="w-1.5 h-1.5 rounded-full" data-bg-color="${escapeHtml(statusDotColor)}"></span>
                    <span>${escapeHtml(statusLabel)}</span>
                </span>
            </div>

            <div class="px-2.5 py-2">
            <div class="flex items-start gap-2">
                <span class="w-7 h-7 rounded-full text-[10px] font-semibold text-white inline-flex items-center justify-center" data-bg-color="${escapeHtml(providerColor)}">${escapeHtml(providerInitials)}</span>
                <div class="flex-1 min-w-0">
                    <div class="text-[11px] text-gray-600 dark:text-gray-300 truncate">${escapeHtml(providerName)}${serviceName ? ` • ${escapeHtml(serviceName)}` : ''}</div>
                </div>
            </div>
            </div>
        </button>
    `;
}

export function renderEmptyState(message) {
    return `
        <div class="text-xs text-gray-500 dark:text-gray-400 px-2 py-2 rounded-md bg-gray-50 dark:bg-gray-800/60 border border-dashed border-gray-200 dark:border-gray-700">
            ${escapeHtml(message)}
        </div>
    `;
}

