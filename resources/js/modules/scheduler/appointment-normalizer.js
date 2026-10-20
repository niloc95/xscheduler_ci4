/**
 * Appointment payload normalisation for SchedulerCore: coerces API rows into the
 * canonical shape the views render (ids, datetimes, provider colour, flags).
 *
 * Mixed into SchedulerCore.prototype — methods use `this` as the scheduler.
 */

import { DateTime } from 'luxon';
import { DEFAULT_PROVIDER_COLOR } from './constants.js';

export const appointmentNormalizerMethods = {
    parseOptionalInteger(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        const parsed = Number.parseInt(String(value), 10);
        return Number.isNaN(parsed) ? null : parsed;
    },

    parseAppointmentDateTime(value) {
        if (!value) {
            return null;
        }

        const isoDateTimeUtc = DateTime.fromISO(String(value), { zone: 'utc', setZone: true });
        if (isoDateTimeUtc.isValid) {
            return isoDateTimeUtc.setZone(this.options.timezone);
        }

        const isoDateTime = DateTime.fromISO(String(value), { zone: this.options.timezone, setZone: true });
        if (isoDateTime.isValid) {
            return isoDateTime;
        }

        const sqlDateTimeUtc = DateTime.fromSQL(String(value), { zone: 'utc' });
        if (sqlDateTimeUtc.isValid) {
            return sqlDateTimeUtc.setZone(this.options.timezone);
        }

        const sqlDateTime = DateTime.fromSQL(String(value), { zone: this.options.timezone });
        return sqlDateTime.isValid ? sqlDateTime : null;
    },

    normalizeAppointment(rawAppointment = {}) {
        const start = rawAppointment.start ?? rawAppointment.start_at ?? rawAppointment.start_time ?? rawAppointment.startTime ?? rawAppointment.startDateTime ?? null;
        const end = rawAppointment.end ?? rawAppointment.end_at ?? rawAppointment.end_time ?? rawAppointment.endTime ?? rawAppointment.endDateTime ?? null;
        const customerName = rawAppointment.customerName ?? rawAppointment.customer_name ?? rawAppointment.name ?? null;
        const customerEmail = rawAppointment.customerEmail ?? rawAppointment.customer_email ?? rawAppointment.email ?? null;
        const customerPhone = rawAppointment.customerPhone ?? rawAppointment.customer_phone ?? rawAppointment.phone ?? null;
        const providerName = rawAppointment.providerName ?? rawAppointment.provider_name ?? null;
        const serviceName = rawAppointment.serviceName ?? rawAppointment.service_name ?? null;
        const locationName = rawAppointment.locationName ?? rawAppointment.location_name ?? null;
        const providerColor = rawAppointment.providerColor ?? rawAppointment.provider_color ?? DEFAULT_PROVIDER_COLOR;
        const title = rawAppointment.title ?? rawAppointment.customer_name ?? rawAppointment.name ?? null;
        const deliveryMode = rawAppointment.deliveryMode ?? rawAppointment.delivery_mode ?? 'onsite';
        const videoLink    = rawAppointment.videoLink    ?? rawAppointment.video_link    ?? '';

        return {
            ...rawAppointment,
            id: this.parseOptionalInteger(rawAppointment.id),
            providerId: this.parseOptionalInteger(rawAppointment.providerId ?? rawAppointment.provider_id),
            serviceId: this.parseOptionalInteger(rawAppointment.serviceId ?? rawAppointment.service_id),
            customerId: this.parseOptionalInteger(rawAppointment.customerId ?? rawAppointment.customer_id),
            locationId: this.parseOptionalInteger(rawAppointment.locationId ?? rawAppointment.location_id),
            start,
            end,
            start_at: rawAppointment.start_at ?? start,
            start_time: rawAppointment.start_time ?? start,
            end_at: rawAppointment.end_at ?? end,
            end_time: rawAppointment.end_time ?? end,
            title,
            name: customerName,
            customerName,
            customer_name: rawAppointment.customer_name ?? customerName,
            email: customerEmail,
            customerEmail,
            customer_email: rawAppointment.customer_email ?? customerEmail,
            phone: customerPhone,
            customerPhone,
            customer_phone: rawAppointment.customer_phone ?? customerPhone,
            providerName,
            provider_name: rawAppointment.provider_name ?? providerName,
            serviceName,
            service_name: rawAppointment.service_name ?? serviceName,
            locationName,
            location_name: rawAppointment.location_name ?? locationName,
            providerColor,
            provider_color: rawAppointment.provider_color ?? providerColor,
            deliveryMode,
            delivery_mode: rawAppointment.delivery_mode ?? deliveryMode,
            videoLink,
            video_link:    rawAppointment.video_link    ?? videoLink,
            startDateTime: this.parseAppointmentDateTime(start),
            endDateTime: this.parseAppointmentDateTime(end)
        };
    }
};
