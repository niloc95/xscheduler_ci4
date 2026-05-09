import { apiRequest } from '../../core/api.js';
import { withBaseUrl } from '../../utils/url-helpers.js';

function buildBookingUrl(providerId, date, time, serviceId, locationId) {
    const params = new URLSearchParams({
        provider_id: String(providerId || ''),
        date: String(date || ''),
        time: String(time || ''),
    });

    if (serviceId) {
        params.set('service_id', String(serviceId));
    }

    if (locationId) {
        params.set('location_id', String(locationId));
    }

    return withBaseUrl(`/appointments/create?${params.toString()}`);
}

function createSlotLink({ providerId, date, time, serviceId, locationId }) {
    const link = document.createElement('a');
    link.href = buildBookingUrl(providerId, date, time, serviceId, locationId);
    link.className = 'provider-slot-link no-underline hover:no-underline focus:no-underline';

    const timeEl = document.createElement('span');
    timeEl.className = 'provider-slot-time';
    timeEl.textContent = time;

    const bookEl = document.createElement('span');
    bookEl.className = 'provider-slot-book';
    bookEl.textContent = 'Book';

    link.appendChild(timeEl);
    link.appendChild(bookEl);

    return link;
}

function renderSlots(listEl, payload, selected) {
    listEl.innerHTML = '';

    const slots = Array.isArray(payload?.slots) ? payload.slots : [];
    if (slots.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'provider-slot-empty text-xs text-gray-500 dark:text-gray-400';
        empty.textContent = 'No slots for this date.';
        listEl.appendChild(empty);
        return;
    }

    slots.forEach((slot) => {
        const time = String(slot?.time || '').trim();
        if (!time) return;

        const link = createSlotLink({
            providerId: selected.providerId,
            date: selected.date,
            time,
            serviceId: selected.serviceId,
            locationId: selected.locationId,
        });

        listEl.appendChild(link);
    });
}

function setLoadingState(listEl) {
    listEl.innerHTML = '';
    const loading = document.createElement('p');
    loading.className = 'provider-slot-empty text-xs text-gray-500 dark:text-gray-400';
    loading.textContent = 'Loading slots...';
    listEl.appendChild(loading);
}

function setErrorState(listEl) {
    listEl.innerHTML = '';
    const error = document.createElement('p');
    error.className = 'provider-slot-empty text-xs text-red-600 dark:text-red-400';
    error.textContent = 'Unable to load slots. Try again.';
    listEl.appendChild(error);
}

function wireProviderCard(card) {
    if (!(card instanceof HTMLElement) || card.dataset.providerCardWired === 'true') {
        return;
    }

    const providerId = Number(card.dataset.providerId || 0);
    if (!providerId) {
        return;
    }

    const dateInput = card.querySelector('[data-provider-date]');
    const serviceSelect = card.querySelector('[data-provider-service]');
    const locationSelect = card.querySelector('[data-provider-location]');
    const slotsList = card.querySelector('[data-provider-slots-list]');

    if (!(dateInput instanceof HTMLInputElement) || !(serviceSelect instanceof HTMLSelectElement) || !(locationSelect instanceof HTMLSelectElement) || !(slotsList instanceof HTMLElement)) {
        return;
    }

    let requestId = 0;

    const loadSlots = async () => {
        const date = dateInput.value || card.dataset.initialDate || '';
        if (!date) {
            return;
        }

        const currentRequestId = ++requestId;
        setLoadingState(slotsList);

        const params = new URLSearchParams({
            provider_id: String(providerId),
            date,
        });

        const serviceId = serviceSelect.value || card.dataset.defaultServiceId || '';
        const locationId = locationSelect.value || '';

        if (serviceId) {
            params.set('service_id', serviceId);
        }

        if (locationId) {
            params.set('location_id', locationId);
        }

        try {
            const { response, payload } = await apiRequest(withBaseUrl(`/api/dashboard/provider-slots?${params.toString()}`), {
                method: 'GET',
            });

            if (currentRequestId !== requestId) {
                return;
            }

            if (!response.ok) {
                setErrorState(slotsList);
                return;
            }

            renderSlots(slotsList, payload?.data || {}, {
                providerId,
                date,
                serviceId,
                locationId,
            });
        } catch (_) {
            if (currentRequestId !== requestId) {
                return;
            }

            setErrorState(slotsList);
        }
    };

    dateInput.addEventListener('change', loadSlots);
    serviceSelect.addEventListener('change', loadSlots);
    locationSelect.addEventListener('change', loadSlots);

    // Apply provider color as CSS custom property for the left-border accent
    const providerColor = card.dataset.providerColor;
    if (providerColor) {
        card.style.setProperty('--provider-color', providerColor);
    }

    card.dataset.providerCardWired = 'true';
}

export function initDashboardProviderCards() {
    const cards = document.querySelectorAll('[data-provider-card="true"]');
    if (!cards.length) {
        return;
    }

    cards.forEach((card) => wireProviderCard(card));
}
