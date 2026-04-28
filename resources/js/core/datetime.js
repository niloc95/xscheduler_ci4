/**
 * Shared browser timezone and date utilities.
 */

export function getBrowserTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
}

export function getTimezoneOffsetForTimezone(timezone = getBrowserTimezone()) {
    try {
        const now = new Date();
        const localMs = new Date(
            now.toLocaleString('en-CA', { timeZone: timezone, hour12: false })
                .replace(' ', 'T') + 'Z'
        ).getTime();

        return Math.round((now.getTime() - localMs) / 60000);
    } catch {
        return new Date().getTimezoneOffset();
    }
}

export function getBrowserTimezoneHeaders() {
    const timezone = getBrowserTimezone();

    return {
        'X-Client-Timezone': timezone,
        'X-Client-Offset': String(getTimezoneOffsetForTimezone(timezone)),
    };
}

export function toIsoDate(value = new Date()) {
    if (value instanceof Date) {
        return value.toISOString().slice(0, 10);
    }

    return new Date(value).toISOString().slice(0, 10);
}
