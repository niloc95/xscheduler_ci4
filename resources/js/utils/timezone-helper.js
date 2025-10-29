/**
 * Timezone Helper Utilities
 * 
 * Client-side timezone detection and conversion utilities for handling
 * appointment times correctly across different user timezones.
 * 
 * Features:
 * - Automatic browser timezone detection
 * - Local time to UTC conversion
 * - UTC to local time conversion
 * - Timezone header injection for HTTP requests
 * 
 * @module timezone-helper
 * @version 1.0.0
 * @created October 24, 2025
 */

/**
 * Detect browser's timezone using Intl API
 * 
 * Modern browsers support Intl.DateTimeFormat which can detect the
 * user's timezone reliably, including DST awareness.
 * 
 * @returns {string} IANA timezone identifier (e.g., 'America/New_York')
 * 
 * @example
 * const tz = getBrowserTimezone();
 * console.log(tz); // 'America/New_York'
 */
export function getBrowserTimezone() {
  // Use Intl API for reliable timezone detection (modern browsers)
  if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
    try {
      const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (tz) {
        return tz;
      }
    } catch (error) {
      console.warn('[timezone-helper] Intl timezone detection failed:', error);
    }
  }
  
  // Fallback: calculate offset (less reliable, doesn't account for DST properly)
  const offset = getTimezoneOffset();
  const sign = offset > 0 ? '-' : '+';
  const hours = Math.floor(Math.abs(offset) / 60);
  const mins = Math.abs(offset) % 60;
  const tzName = `UTC${sign}${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
  
  console.warn('[timezone-helper] Using fallback timezone:', tzName);
  return tzName;
}

/**
 * Get browser's timezone offset in minutes from UTC
 * 
 * Negative values indicate timezones east of UTC (e.g., UTC+8 = -480 minutes)
 * Positive values indicate west of UTC (e.g., UTC-5 = 300 minutes)
 * 
 * Note: This automatically accounts for Daylight Saving Time at the current date
 * 
 * @returns {number} Offset in minutes from UTC
 * 
 * @example
 * const offset = getTimezoneOffset();
 * console.log(offset); // -240 (EDT in summer, UTC-4)
 */
export function getTimezoneOffset() {
  return new Date().getTimezoneOffset();
}

/**
 * Prepare timezone headers for HTTP requests
 * 
 * Returns an object containing timezone headers that should be sent
 * with every HTTP request to the backend. This allows the backend to
 * know the client's timezone and perform correct conversions.
 * 
 * @returns {Object} Headers object with X-Client-Timezone and X-Client-Offset
 * 
 * @example
 * const headers = attachTimezoneHeaders();
 * fetch('/api/appointments', {
 *   headers: {
 *     ...headers,
 *     'Content-Type': 'application/json'
 *   }
 * });
 */
export function attachTimezoneHeaders() {
  const timezone = getBrowserTimezone();
  const offset = getTimezoneOffset();
  
  // Store globally for reference
  window.__timezone = { timezone, offset };
  
  console.log('[timezone-helper] Timezone headers prepared:', {
    timezone,
    offset: `${offset} minutes`,
    offsetHours: `UTC${offset > 0 ? '+' : '-'}${Math.abs(offset / 60)}`
  });
  
  return {
    'X-Client-Timezone': timezone,
    'X-Client-Offset': offset.toString()
  };
}

/**
 * Convert local datetime string to UTC ISO string
 * 
 * Takes a local datetime (YYYY-MM-DD HH:mm:ss or ISO format) and converts
 * it to UTC for transmission to the backend.
 * 
 * The conversion assumes the input time is in the browser's local timezone.
 * 
 * @param {string|Date} localDateTime - Local datetime in 'YYYY-MM-DDTHH:mm:ss' or 'YYYY-MM-DD HH:mm:ss' format
 * @returns {string} ISO 8601 UTC datetime string (format: 2025-10-24T14:30:00Z)
 * 
 * @example
 * // User in NYC enters: 10:30 AM
 * const local = '2025-10-24 10:30:00';
 * const utc = toBrowserUTC(local);
 * console.log(utc); // '2025-10-24T14:30:00Z' (EDT to UTC)
 */
export function toBrowserUTC(localDateTime) {
  // Normalize format (replace space with T if needed)
  let dateStr = localDateTime;
  if (typeof localDateTime === 'string') {
    dateStr = localDateTime.replace(' ', 'T');
  }
  
  // Create Date object (interpreted as local time)
  const date = new Date(dateStr);
  
  if (isNaN(date.getTime())) {
    throw new Error(`Invalid datetime format: ${localDateTime}. Use YYYY-MM-DD HH:mm:ss or YYYY-MM-DDTHH:mm:ss`);
  }
  
  // Convert to UTC ISO string
  return date.toISOString();
}

/**
 * Convert UTC datetime string to local display format
 * 
 * Takes a UTC datetime and converts it to local time for display.
 * 
 * @param {string} utcDateTime - UTC datetime (ISO string: 2025-10-24T14:30:00Z, or 2025-10-24 14:30:00)
 * @param {string} format - Output format string. Available tokens:
 *                         YYYY, MM, DD, HH, mm, ss
 *                         Default: 'YYYY-MM-DD HH:mm:ss'
 * @returns {string} Formatted local datetime
 * 
 * @example
 * // Backend returns: '2025-10-24T14:30:00Z' (UTC)
 * // Display in NYC local time:
 * const local = fromUTC('2025-10-24T14:30:00Z');
 * console.log(local); // '2025-10-24 10:30:00' (EDT display)
 */
export function fromUTC(utcDateTime, format = 'YYYY-MM-DD HH:mm:ss') {
  // Normalize format
  let dateStr = utcDateTime;
  
  // Add Z if not present (to ensure UTC interpretation)
  if (typeof dateStr === 'string' && !dateStr.endsWith('Z')) {
    dateStr = dateStr.replace(' ', 'T') + 'Z';
  }
  
  const date = new Date(dateStr);
  
  if (isNaN(date.getTime())) {
    console.error('[timezone-helper] Invalid UTC datetime:', utcDateTime);
    return utcDateTime;
  }
  
  // Extract local components
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  const seconds = String(date.getSeconds()).padStart(2, '0');
  
  // Apply format
  return format
    .replace('YYYY', year)
    .replace('MM', month)
    .replace('DD', day)
    .replace('HH', hours)
    .replace('mm', minutes)
    .replace('ss', seconds);
}

/**
 * Get current time in browser's timezone
 * 
 * Returns formatted current local time.
 * 
 * @param {string} format - Output format (default: 'YYYY-MM-DD HH:mm:ss')
 * @returns {string} Current local datetime
 */
export function nowLocal(format = 'YYYY-MM-DD HH:mm:ss') {
  return fromUTC(new Date().toISOString(), format);
}

/**
 * Create a debug logger for timezone debugging
 * 
 * Logs detailed timezone information and event times for debugging purposes.
 * 
 * @returns {Object} Debug object with utility methods
 * 
 * @example
 * const debug = createTimezoneDebugger();
 * debug.logEvent(calendarEvent);
 * debug.logTime('2025-10-24T14:30:00Z');
 */
export function createTimezoneDebugger() {
  const timezone = getBrowserTimezone();
  const offset = getTimezoneOffset();
  
  return {
    timezone,
    offset,
    
    logInfo() {
      console.group('%c[TIMEZONE DEBUG]', 'color: blue; font-weight: bold; font-size: 14px');
      console.log('Browser Timezone:', this.timezone);
      console.log('UTC Offset:', this.offset, 'minutes');
      console.log('Offset (hours):', `UTC${this.offset > 0 ? '+' : '-'}${Math.abs(this.offset / 60)}`);
      console.log('Current Local Time:', new Date().toString());
      console.log('Current UTC Time:', new Date().toUTCString());
      console.groupEnd();
    },
    
    logEvent(event) {
      console.group(`%c[TIMEZONE DEBUG] Event: ${event.title || event.id}`, 'color: green; font-weight: bold');
      console.log('Event ID:', event.id);
      console.log('Start (UTC):', event.startStr || event.start);
      console.log('Start (Local):', fromUTC(event.startStr || event.start));
      console.log('End (UTC):', event.endStr || event.end);
      console.log('End (Local):', fromUTC(event.endStr || event.end));
      console.log('Duration:', Math.round((new Date(event.endStr || event.end) - new Date(event.startStr || event.start)) / 60000), 'minutes');
      console.log('Browser Timezone:', this.timezone);
      console.log('UTC Offset:', this.offset, 'minutes');
      console.groupEnd();
    },
    
    logTime(utcTime) {
      const local = fromUTC(utcTime);
      console.group(`%c[TIMEZONE DEBUG] Time Conversion`, 'color: orange; font-weight: bold');
      console.log('UTC:', utcTime);
      console.log('Local:', local);
      console.log('Timezone:', this.timezone);
      console.groupEnd();
    },
    
    compare(localTime, expectedLocal) {
      console.group(`%c[TIMEZONE DEBUG] Time Mismatch Check`, 'color: red; font-weight: bold');
      console.log('Expected (Local):', expectedLocal);
      console.log('Actual (Local):', localTime);
      const match = localTime === expectedLocal;
      console.log('Match:', match ? '✅ YES' : '❌ NO');
      if (!match) {
        console.log('⚠️ MISMATCH DETECTED - Check timezone conversion');
      }
      console.groupEnd();
    }
  };
}

/**
 * Initialize timezone debugging in window scope
 * 
 * Adds a global DEBUG_TIMEZONE object that can be used from browser console
 * for debugging timezone issues.
 * 
 * @example
 * // In browser console:
 * window.DEBUG_TIMEZONE.logInfo();
 * window.DEBUG_TIMEZONE.logEvent(someEvent);
 */
export function initTimezoneDebug() {
  window.DEBUG_TIMEZONE = createTimezoneDebugger();
  // Debug logs removed for production
}

// Auto-initialize debug on module load (if in development)
if (typeof window !== 'undefined' && (process.env.NODE_ENV === 'development' || window.location.hostname === 'localhost')) {
  initTimezoneDebug();
}
