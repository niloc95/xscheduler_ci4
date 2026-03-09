import { escapeHtml } from '../../utils/html.js';
import { getStatusLabel } from './appointment-colors.js';

export function renderSchedulerHeader({ currentDate, isBlocked, isNonWorkingDay }) {
    return `
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                ${currentDate.toFormat('EEEE')}'s Appointments
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                ${currentDate.toFormat('MMMM d, yyyy')}
                ${isBlocked ? ' • <span class="text-red-500">🚫 Blocked</span>' : ''}
                ${isNonWorkingDay && !isBlocked ? ' • <span class="text-gray-500">Non-working day</span>' : ''}
            </p>
        </div>
    `;
}

export function renderAppointmentCard({
    appointment,
    providerColor,
    statusColors,
    dateTimeDisplay,
    serviceName,
    location,
    providerInitial,
    providerName,
    isLast,
}) {
    const customerName = appointment.customerName || appointment.title || 'Unknown';

    return `
        <div class="appointment-row group flex items-center gap-4 p-4 hover:bg-surface-2 dark:hover:bg-gray-700/50 rounded-lg transition-colors cursor-pointer"
             data-appointment-id="${appointment.id}">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-semibold text-lg shadow-sm"
                     data-bg-color="${providerColor}"
                     title="${escapeHtml(providerName)}">
                    ${providerInitial}
                </div>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                        ${escapeHtml(customerName)}
                    </h4>
                    <span class="px-2 py-0.5 text-[10px] font-medium rounded-full flex-shrink-0"
                        data-bg-color="${statusColors.bg}"
                        data-text-color="${statusColors.text}"
                        data-border-color="${statusColors.border}">
                        ${getStatusLabel(appointment.status)}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">calendar_today</span>
                        <span>${dateTimeDisplay}</span>
                    </div>

                    ${location ? `
                        <div class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base">location_on</span>
                            <span class="truncate max-w-[150px]">${escapeHtml(location)}</span>
                        </div>
                    ` : `
                        <div class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base">spa</span>
                            <span class="truncate max-w-[150px]">${escapeHtml(serviceName)}</span>
                        </div>
                    `}
                </div>
            </div>

            <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                <button type="button"
                        class="appointment-menu-btn p-2 rounded-lg hover:bg-surface-3 dark:hover:bg-gray-600 transition-colors"
                        data-appointment-id="${appointment.id}"
                        title="More options">
                    <span class="material-symbols-outlined text-gray-500 dark:text-gray-400">more_horiz</span>
                </button>
            </div>
        </div>

        ${!isLast ? '<div class="border-b border-gray-200 dark:border-gray-700 mx-4"></div>' : ''}
    `;
}

export function renderProviderPanel({
    miniCalendarHtml,
    addAppointmentUrl,
    daySummaryHtml,
    timeColumnHtml,
    availabilityBlockHtml,
}) {
    return `
        <div class="w-full md:w-80 flex-shrink-0 order-1 md:order-2">
            <div class="md:sticky md:top-4 space-y-3">
                ${miniCalendarHtml}
                <a href="${addAppointmentUrl}"
                   id="day-view-add-event-btn"
                   class="w-full px-3 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">add</span>
                    Add Appointment
                </a>
                ${timeColumnHtml}
                ${daySummaryHtml}
                ${availabilityBlockHtml}
            </div>
        </div>
    `;
}

export function renderTimeColumn({ startTime, endTime, slotDuration }) {
    return `
        <div class="bg-surface-1 dark:bg-gray-700/50 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-200">Hours</span>
                <span class="text-gray-500 dark:text-gray-400">${startTime} - ${endTime}</span>
            </div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">${slotDuration} min slots</div>
        </div>
    `;
}

export function renderAvailabilityBlock({ panelHtml }) {
    return `
        <div class="bg-surface-1 dark:bg-gray-700/40 rounded-xl p-3" id="day-slot-panel">
            ${panelHtml}
        </div>
    `;
}
