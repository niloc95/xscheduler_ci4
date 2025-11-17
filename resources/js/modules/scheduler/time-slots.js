/**
 * Time Slots Utility
 *
 * Generates hour-based time slots between business hours and
 * formats hour labels based on provided time format ('12h' | '24h').
 */

export function formatTimeForHour(hour, timeFormat = '12h') {
    if (timeFormat === '24h') {
        return `${hour.toString().padStart(2, '0')}:00`;
    }
    if (hour === 0) return '12:00 AM';
    if (hour === 12) return '12:00 PM';
    if (hour < 12) return `${hour}:00 AM`;
    return `${hour - 12}:00 PM`;
}

export function formatTime(hour, minute, timeFormat = '12h') {
    if (timeFormat === '24h') {
        return `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
    }
    const period = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 === 0 ? 12 : hour % 12;
    return `${hour12}:${minute.toString().padStart(2, '0')} ${period}`;
}

export function generateTimeSlots(businessHours, timeFormat = '12h', intervalMinutes = 60) {
    const slots = [];
    const parseTimeToMinutes = (timeStr, fallback = '09:00') => {
        const str = timeStr || fallback;
        const [h, m] = str.split(':').map((v) => parseInt(v, 10));
        return (h * 60) + (m || 0);
    };

    const startMin = parseTimeToMinutes(businessHours?.startTime, '09:00');
    const endMin = parseTimeToMinutes(businessHours?.endTime, '17:00');

    // Generate slots where each slot represents the start of the interval
    for (let t = startMin; t + intervalMinutes <= endMin; t += intervalMinutes) {
        const hour = Math.floor(t / 60);
        const minute = t % 60;
        const time = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
        const display = formatTime(hour, minute, timeFormat);
        slots.push({ time, display, hour, minute });
    }

    return slots;
}

export default { generateTimeSlots, formatTimeForHour, formatTime };
