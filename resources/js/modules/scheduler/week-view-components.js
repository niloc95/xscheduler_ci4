import { escapeHtml } from '../../utils/html.js';

export function renderWeekHeader({ weekStart, weekEnd }) {
    return `
        <div class="mb-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Week of ${weekStart.toFormat('MMMM d')}
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                ${weekStart.toFormat('MMM d')} - ${weekEnd.toFormat('MMM d, yyyy')}
            </p>
        </div>
    `;
}

export function renderWeekLeftPanel({
    weekHeaderHtml,
    miniCalendarHtml,
    selectedDateLabel,
    appointmentCount,
    appointmentSummaryHtml,
    addAppointmentUrl,
}) {
    return `
        <div class="week-left-panel border-r-0 md:border-r border-gray-200 dark:border-gray-700 p-3 md:p-4 order-1 bg-surface-1 dark:bg-gray-800/50">
            ${weekHeaderHtml}
            ${miniCalendarHtml}
            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Appointments for ${selectedDateLabel}
                    </h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400" id="appointment-count">
                        ${appointmentCount} appointments
                    </span>
                </div>
                <div id="appointment-summary-list" class="space-y-2 max-h-[250px] overflow-y-auto">
                    ${appointmentSummaryHtml}
                </div>
            </div>
            <a href="${addAppointmentUrl}"
               id="week-view-add-btn"
               class="w-full mt-3 px-3 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm transition-colors flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-lg">add</span>
                Add Appointment
            </a>
        </div>
    `;
}

export function renderWeekSlotEngineHeader({ selectedDate, providerCount, slotDatePickerHtml }) {
    return `
        <div class="mb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">event_available</span>
                        Available Slots
                    </h3>
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 bg-surface-2 dark:bg-gray-700 px-2 py-1 rounded-full" id="provider-count-badge">
                    <span class="material-symbols-outlined text-sm">group</span>
                    <span id="provider-count-label">${providerCount} provider${providerCount !== 1 ? 's' : ''}</span>
                </div>
            </div>

            <div class="mt-2 flex items-center gap-2">
                <button type="button"
                        id="prev-slot-date"
                        class="p-1.5 rounded-lg hover:bg-surface-2 dark:hover:bg-gray-600 transition-colors"
                        title="Previous day">
                    <span class="material-symbols-outlined text-gray-600 dark:text-gray-300">chevron_left</span>
                </button>

                <div class="relative flex-1">
                    <button type="button"
                            id="slot-date-picker-toggle"
                            class="w-full flex items-center justify-between gap-2 px-4 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-sm hover:border-blue-400 dark:hover:border-blue-500 transition-all group">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-lg">calendar_today</span>
                            <div class="text-left">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white" id="slot-engine-weekday">
                                    ${selectedDate.toFormat('EEEE')}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400" id="slot-engine-date">
                                    ${selectedDate.toFormat('MMMM d, yyyy')}
                                </div>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-gray-400 group-hover:text-blue-500 transition-colors" id="date-picker-chevron">expand_more</span>
                    </button>

                    <div id="slot-date-picker-dropdown"
                         class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-xl z-50 hidden">
                        <div class="p-3" id="slot-mini-calendar-container">
                            ${slotDatePickerHtml}
                        </div>
                    </div>
                </div>

                <button type="button"
                        id="next-slot-date"
                        class="p-1.5 rounded-lg hover:bg-surface-2 dark:hover:bg-gray-600 transition-colors"
                        title="Next day">
                    <span class="material-symbols-outlined text-gray-600 dark:text-gray-300">chevron_right</span>
                </button>

                <button type="button"
                        id="today-slot-date"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors"
                        title="Go to today">
                    Today
                </button>
            </div>
        </div>
    `;
}

export function renderWeekRightPanel({ slotEngineHeaderHtml, filterPillsHtml, slotsHtml }) {
    return `
        <div class="week-right-panel p-3 md:p-4 order-2 bg-surface-1 dark:bg-gray-800/50">
            ${slotEngineHeaderHtml}
            <div class="slot-panel__filters">
                ${filterPillsHtml}
            </div>
            <div class="slot-panel__slots">
                <div class="slot-panel__slots-header">
                    <span class="slot-panel__slots-label">Time Slots</span>
                    <span class="slot-panel__slot-count" id="slot-count-label"></span>
                </div>
                <div id="time-slot-engine" class="slot-panel__slot-list">
                    ${slotsHtml}
                </div>
            </div>
        </div>
    `;
}

export function renderAppointmentSummaryCard({
    appointmentId,
    providerColor,
    providerInitials,
    customerName,
    status,
    statusColors,
    time,
    serviceName,
    locationName,
}) {
    return `
        <div class="appointment-summary-item flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-surface-2 dark:hover:bg-gray-700 transition-all"
             data-appointment-id="${appointmentId}"
             data-border-left-color="${providerColor}">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                     data-bg-color="${providerColor}">
                    ${providerInitials}
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-sm text-gray-900 dark:text-white truncate">${escapeHtml(customerName)}</span>
                    <span class="px-1.5 py-0.5 text-[10px] font-medium rounded"
                        data-bg-color="${statusColors.bg}"
                        data-text-color="${statusColors.text}">
                        ${status}
                    </span>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    <span class="font-medium">${time}</span> • ${escapeHtml(serviceName)}
                </div>
                ${locationName ? `
                <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 flex items-center gap-1 truncate">
                    <span class="material-symbols-outlined text-xs">location_on</span>
                    ${escapeHtml(locationName)}
                </div>` : ''}
            </div>
            <span class="material-symbols-outlined text-gray-400 text-lg">chevron_right</span>
        </div>
    `;
}

export function renderAppointmentSummaryEmptyState() {
    return `
        <div class="text-center py-6 text-gray-500 dark:text-gray-400">
            <span class="material-symbols-outlined text-3xl mb-2 block">event_available</span>
            <p class="text-sm">No appointments scheduled</p>
        </div>
    `;
}
