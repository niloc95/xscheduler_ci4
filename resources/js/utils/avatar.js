/**
 * Shared avatar helpers used across modules.
 */

/**
 * Build a display name from an entity payload.
 *
 * @param {object} entity
 * @param {string} fallback
 * @returns {string}
 */
export function getDisplayName(entity = {}, fallback = '') {
    if (!entity || typeof entity !== 'object') {
        return fallback;
    }

    const explicitName = String(entity.name || '').trim();
    if (explicitName) {
        return explicitName;
    }

    const firstName = String(entity.first_name || '').trim();
    const lastName = String(entity.last_name || '').trim();
    const combined = `${firstName} ${lastName}`.trim();

    return combined || fallback;
}

/**
 * Compute avatar initials from a display name.
 *
 * Rules:
 * - Remove common prefixes/suffixes (Dr., Prof., MD, PhD, etc.)
 * - Multi-word: first letter of first + first letter of last
 * - Single-word: first two letters
 *
 * @param {string} name
 * @param {{ defaultInitial?: string }} options
 * @returns {string}
 */
export function getAvatarInitials(name, options = {}) {
    const defaultInitial = (options.defaultInitial || 'U').toUpperCase();
    if (!name || typeof name !== 'string') {
        return defaultInitial;
    }

    let cleaned = name.trim();
    if (!cleaned) {
        return defaultInitial;
    }

    cleaned = cleaned
        .replace(/^(dr|mr|mrs|ms|prof|rev)\.?\s+/i, '')
        .trim();

    // Remove one or more trailing credentials/suffixes.
    while (cleaned) {
        const updated = cleaned.replace(/(?:,?\s+|\.\s*)(md|phd|dds|do|rn|np|pa|dvm|jr|sr|ii|iii|iv)\.?$/i, '').trim();
        if (updated === cleaned) {
            break;
        }
        cleaned = updated;
    }

    const parts = cleaned.split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return defaultInitial;
    }

    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }

    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}
