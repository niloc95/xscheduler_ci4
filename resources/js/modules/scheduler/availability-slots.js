import { DateTime } from 'luxon';
import { withBaseUrl } from '../../utils/url-helpers.js';

export async function fetchSlotsForDate({ providerIds = [], dateIso, serviceId, locationId = null }) {
    if (!Array.isArray(providerIds) || !providerIds.length || !serviceId || !dateIso) {
        return [];
    }

    const slots = [];

    await Promise.all(
        providerIds.map(async (providerId) => {
            try {
                const params = new URLSearchParams({
                    provider_id: String(providerId),
                    date: String(dateIso),
                    service_id: String(serviceId),
                });

                const normalizedLocation = Number(locationId || 0);
                if (normalizedLocation > 0) {
                    params.set('location_id', String(normalizedLocation));
                }

                const response = await fetch(withBaseUrl(`/api/availability/slots?${params.toString()}`));
                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                const rawSlots = data?.data?.slots || [];

                rawSlots.forEach((slot) => {
                    const start = slot.start || slot.startTime;
                    const end = slot.end || slot.endTime;
                    if (!start || !end) {
                        return;
                    }

                    slots.push({
                        providerId: Number(providerId),
                        slot: {
                            ...slot,
                            start,
                            end,
                            label: slot.label || null,
                        },
                    });
                });
            } catch {
                // Partial failures are acceptable so users still see available providers.
            }
        }),
    );

    return slots.sort((a, b) => DateTime.fromISO(a.slot.start).toMillis() - DateTime.fromISO(b.slot.start).toMillis());
}

export function groupSlotsByHour(slots = [], timezone = 'UTC') {
    const grouped = {};

    slots.forEach(({ providerId, provider, slot }) => {
        const startTime = DateTime.fromISO(slot.start, { setZone: true }).setZone(timezone);
        if (!startTime.isValid) {
            return;
        }

        const hourKey = startTime.hour;
        if (!grouped[hourKey]) {
            grouped[hourKey] = {
                hour: hourKey,
                slots: [],
            };
        }

        grouped[hourKey].slots.push({
            ...slot,
            providerId: Number(providerId),
            provider: provider || null,
        });
    });

    return grouped;
}
