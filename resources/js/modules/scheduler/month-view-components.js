export function renderMonthShell({ dayHeadersHtml, calendarGridHtml, emptyStateHtml, slotPanelHtml }) {
    return `
        <div class="scheduler-month-view rounded-xl overflow-hidden bg-surface-0 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-7 px-1 pt-2 pb-1">
                ${dayHeadersHtml}
            </div>

            <div class="grid grid-cols-7 grid-rows-6 gap-px px-1 pb-1">
                ${calendarGridHtml}
            </div>

            ${emptyStateHtml}

            <div class="p-3 md:p-4 border-t border-gray-100 dark:border-gray-800 bg-surface-1 dark:bg-gray-800/40" id="month-slot-panel">
                ${slotPanelHtml}
            </div>
        </div>
    `;
}

export function renderMonthEmptyState() {
    return `
        <div class="px-6 py-8 text-center">
            <span class="material-symbols-outlined text-gray-300 dark:text-gray-600 text-5xl mb-3">event_available</span>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Appointments</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Click on any day to create a new appointment
            </p>
        </div>
    `;
}

export function renderMonthAppointmentBlock({ appointment, providerColor, time, ampm, hiddenClass }) {
    const title = appointment.customerName || appointment.title || 'Appointment';

    return `
        <div class="scheduler-appointment text-[11px] px-1.5 py-0.5 rounded cursor-pointer hover:bg-surface-2 dark:hover:bg-white/5 transition-all truncate border-l-2 flex items-center gap-1 text-gray-800 dark:text-gray-200 ${hiddenClass}"
             data-border-left-color="${providerColor}"
             data-appointment-id="${appointment.id}"
             title="${title} at ${time}${ampm} - ${appointment.status}">
            <span class="font-semibold flex-shrink-0 tabular-nums">${time}${ampm ? `<span class="font-normal opacity-70">${ampm}</span>` : ''}</span>
            <span class="truncate">${escapeHtml(title)}</span>
        </div>
    `;
}

export function renderMonthModelAppointmentChip({ appointmentId, providerColor, statusColor, customerName }) {
    return `
        <div class="appointment-chip group flex items-center gap-1.5 px-2 py-1 rounded-md text-[11px] font-medium text-white shadow-sm" data-appointment-id="${appointmentId}" data-bg-color="${providerColor}">
            <span class="inline-block w-1.5 h-1.5 rounded-full" data-bg-color="${statusColor}"></span>
            <span class="truncate">${escapeHtml(customerName)}</span>
        </div>
    `;
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
