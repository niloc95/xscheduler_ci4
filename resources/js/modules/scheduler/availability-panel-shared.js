import { withBaseUrl } from '../../utils/url-helpers.js';

export function buildAvailabilityContext({ activeFilters, visibleProviderIds = [] }) {
    const serviceId = activeFilters?.serviceId ?? null;
    let providerId = activeFilters?.providerId ?? null;

    if (!providerId && visibleProviderIds.length === 1) {
        providerId = visibleProviderIds[0];
    }

    if (!providerId) {
        return { ready: false, message: 'Select a provider to see availability.' };
    }

    if (!serviceId) {
        return { ready: false, message: 'Select a service to see availability.' };
    }

    return {
        ready: true,
        providerId,
        serviceId,
        locationId: activeFilters?.locationId ?? null,
    };
}

export function isAvailabilityDebugMode(scheduler) {
    return Boolean(scheduler?.isDebugEnabled?.() || window?.appConfig?.debug);
}

export function renderAvailabilityDebugPayload(scheduler, payload = null) {
    if (!isAvailabilityDebugMode(scheduler)) {
        return '';
    }

    if (!payload) {
        return '<div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">Debug payload unavailable</div>';
    }

    return `<div class="mt-2 rounded border border-dashed border-gray-300 dark:border-gray-600 p-2 text-[11px] text-gray-600 dark:text-gray-300">
        <div><span class="font-semibold">provider_id:</span> ${payload.providerId ?? '-'}</div>
        <div><span class="font-semibold">service_id:</span> ${payload.serviceId ?? '-'}</div>
        <div><span class="font-semibold">resolved location_id:</span> ${payload.locationId ?? '-'}</div>
        <div><span class="font-semibold">date:</span> ${payload.date ?? '-'}</div>
        <div><span class="font-semibold">timezone:</span> ${payload.timezone ?? '-'}</div>
    </div>`;
}

export function renderAvailabilitySlotList(slots, date, providerId, serviceId) {
    if (!slots.length) {
        return '<div class="text-sm text-gray-500 dark:text-gray-400">No available slots</div>';
    }

    return slots.map(slot => {
        const label = `${slot.start} - ${slot.end}`;
        const url = withBaseUrl(`/appointments/create?date=${date}&time=${slot.start}&provider_id=${providerId}&service_id=${serviceId}`);

        return `<div class="slot-item flex items-center justify-between gap-3 py-2">
            <span class="text-sm text-gray-700 dark:text-gray-200">${label}</span>
            <a class="text-xs text-blue-600 hover:underline" href="${url}">Book</a>
        </div>`;
    }).join('');
}
