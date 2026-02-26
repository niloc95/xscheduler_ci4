/**
 * Shared Calendar Utilities
 * 
 * Provides common calendar data normalization and slot helpers used by:
 * - admin time-slots-ui.js
 * - public-booking.js
 * 
 * Single source of truth for calendar API response parsing.
 */

/**
 * Normalize a calendar API response into a consistent structure.
 * Handles both snake_case (API response) and camelCase (existing state) property names.
 * 
 * @param {Object|null} source - Raw calendar API response or existing calendar state
 * @returns {Object} Normalized calendar state
 */
export function normalizeCalendarPayload(source = null) {
  const base = {
    availableDates: [],
    slotsByDate: {},
    startDate: null,
    endDate: null,
    timezone: null,
    generatedAt: null,
    defaultDate: null,
  };

  if (!source || typeof source !== 'object') {
    return { ...base };
  }

  // Parse available dates array
  const availableDates = Array.isArray(source.availableDates) 
    ? [...source.availableDates] 
    : [];

  // Deep-copy slots by date to avoid mutation
  const rawSlots = (source.slotsByDate && typeof source.slotsByDate === 'object') 
    ? source.slotsByDate 
    : {};
  const slotsByDate = Object.keys(rawSlots).reduce((acc, date) => {
    const slots = Array.isArray(rawSlots[date]) 
      ? rawSlots[date].map(slot => ({ ...slot })) 
      : [];
    acc[date] = slots;
    return acc;
  }, {});

  return {
    ...base,
    availableDates,
    slotsByDate,
    startDate: source.start_date ?? source.startDate ?? null,
    endDate: source.end_date ?? source.endDate ?? null,
    timezone: source.timezone ?? null,
    generatedAt: source.generated_at ?? source.generatedAt ?? null,
    defaultDate: source.default_date ?? source.defaultDate ?? (availableDates[0] ?? null),
  };
}

/**
 * Extract the time portion (HH:MM) from a slot object.
 * 
 * @param {Object} slot - Slot object from API
 * @returns {string} Time string in HH:MM format, or empty string
 */
export function slotTimeValue(slot) {
  if (!slot) return '';
  
  if (slot.startFormatted) {
    return slot.startFormatted;
  }
  
  if (slot.start) {
    try {
      const date = new Date(slot.start);
      if (!Number.isNaN(date.getTime())) {
        return date.toISOString().slice(11, 16);
      }
    } catch (_) {
      // ignore parsing error
    }
  }
  
  return '';
}

/**
 * Get a display label for a slot.
 * 
 * @param {Object} slot - Slot object from API
 * @returns {string} Human-readable slot label
 */
export function slotLabel(slot) {
  if (!slot) return 'Available slot';
  
  if (slot.label) {
    return slot.label;
  }
  
  const value = slotTimeValue(slot);
  if (slot.endFormatted) {
    return `${value} - ${slot.endFormatted}`;
  }
  
  return value || 'Available slot';
}

/**
 * Get slots for a specific date from a calendar object.
 * 
 * @param {Object} calendar - Normalized calendar state
 * @param {string} date - Date in YYYY-MM-DD format
 * @returns {Array} Array of slot objects
 */
export function getSlotsForDate(calendar, date) {
  if (!calendar || !calendar.slotsByDate) {
    return [];
  }
  return Array.isArray(calendar.slotsByDate[date]) ? calendar.slotsByDate[date] : [];
}

/**
 * Format a date string for short display (e.g., "Mon, Jan 15").
 * 
 * @param {string} dateStr - Date in YYYY-MM-DD format
 * @param {Object} options - Optional Intl.DateTimeFormat options
 * @returns {string} Formatted date string
 */
export function formatDateShort(dateStr, options = { weekday: 'short', month: 'short', day: 'numeric' }) {
  if (!dateStr) return '';
  
  try {
    const date = new Date(dateStr + 'T00:00:00');
    if (Number.isNaN(date.getTime())) {
      return dateStr;
    }
    return new Intl.DateTimeFormat(undefined, options).format(date);
  } catch {
    return dateStr;
  }
}

/**
 * Select a date from calendar availability, with fallback to first available.
 * 
 * @param {Object} calendar - Normalized calendar state
 * @param {string|null} desiredDate - Preferred date, or null for auto-selection
 * @returns {{ date: string, autoSelected: boolean }} Selected date and whether it was auto-selected
 */
export function selectAvailableDate(calendar, desiredDate) {
  const availableDates = calendar?.availableDates ?? [];
  
  if (!availableDates.length) {
    return { date: desiredDate || '', autoSelected: false };
  }

  // If desired date is available, use it
  if (desiredDate && availableDates.includes(desiredDate)) {
    return { date: desiredDate, autoSelected: false };
  }

  // Fall back to default date or first available
  const fallback = calendar.defaultDate || availableDates[0];
  return { date: fallback, autoSelected: true };
}

/**
 * Build a cache key for calendar data.
 * 
 * @param {string|number} providerId 
 * @param {string|number} serviceId 
 * @param {string|null} startDate 
 * @param {string|number|null} excludeAppointmentId 
 * @returns {string} Cache key
 */
export function buildCalendarCacheKey(providerId, serviceId, startDate, excludeAppointmentId = null) {
  const keyParts = [providerId, serviceId, startDate || 'auto'];
  if (excludeAppointmentId) {
    keyParts.push(`exclude:${excludeAppointmentId}`);
  }
  return keyParts.join('|');
}


