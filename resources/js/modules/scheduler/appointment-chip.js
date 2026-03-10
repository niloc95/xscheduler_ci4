import { escapeHtml } from '../../utils/html.js';

/**
 * Shared appointment chip renderer used by month/week views.
 */
export function renderAppointmentChip({ appointmentId, providerColor, statusColor, customerName, isHidden = false }) {
    const hiddenClass = isHidden ? 'hidden' : '';
    return `
        <div class="scheduler-appointment appointment-chip group flex items-center gap-1.5 px-2 py-1 rounded-md text-[11px] font-medium text-white shadow-sm ${hiddenClass}" data-appointment-id="${appointmentId}" data-bg-color="${providerColor}">
            <span class="inline-block w-1.5 h-1.5 rounded-full" data-bg-color="${statusColor}"></span>
            <span class="truncate">${escapeHtml(customerName)}</span>
        </div>
    `;
}
