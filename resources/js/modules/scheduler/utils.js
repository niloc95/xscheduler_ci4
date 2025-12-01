/**
 * Scheduler Utils
 */

export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

/**
 * Check if a Luxon DateTime falls within blocked periods
 * blockedPeriods: [{start: 'YYYY-MM-DD', end: 'YYYY-MM-DD'}]
 */
export function isDateBlocked(dateTime, blockedPeriods) {
    if (!blockedPeriods || blockedPeriods.length === 0) return false;
    const checkDate = dateTime.toISODate();
    return blockedPeriods.some(period => checkDate >= period.start && checkDate <= period.end);
}

export function getBlockedPeriodInfo(dateTime, blockedPeriods) {
    if (!blockedPeriods || blockedPeriods.length === 0) return null;
    const checkDate = dateTime.toISODate();
    return blockedPeriods.find(p => checkDate >= p.start && checkDate <= p.end) || null;
}

export default { escapeHtml, isDateBlocked, getBlockedPeriodInfo };
