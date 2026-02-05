/**
 * Settings Manager
 * 
 * Centralized configuration manager for scheduler awareness.
 * Handles localization, booking settings, business hours, and provider schedules.
 */

import { DateTime } from 'luxon';
import { logger } from './logger.js';

const getBaseUrl = () => {
    const raw = typeof window !== 'undefined' ? window.__BASE_URL__ : '';
    return (raw || '').replace(/\/+$/, '');
};

const withBaseUrl = (path) => {
    const base = getBaseUrl();
    if (!base) return path;
    if (!path) return base + '/';
    if (path.startsWith('/')) return base + path;
    return base + '/' + path;
};

export class SettingsManager {
    constructor() {
        this.settings = {
            localization: null,
            booking: null,
            businessHours: null,
            providerSchedules: new Map()
        };
        
        this.cache = {
            lastFetch: null,
            ttl: 5 * 60 * 1000 // 5 minutes
        };
    }

    /**
     * Initialize all settings
     */
    async init() {
        try {
            await Promise.all([
                this.loadLocalizationSettings(),
                this.loadBookingSettings(),
                this.loadBusinessHours()
            ]);
            
            this.cache.lastFetch = Date.now();
            logger.debug('⚙️ Settings Manager initialized', this.settings);
            return true;
        } catch (error) {
            logger.error('❌ Failed to initialize settings:', error);
            return false;
        }
    }

    /**
     * Check if cache is valid
     */
    isCacheValid() {
        if (!this.cache.lastFetch) return false;
        return (Date.now() - this.cache.lastFetch) < this.cache.ttl;
    }

    /**
     * Refresh all settings
     */
    async refresh() {
        logger.debug('🔄 Refreshing settings...');
        this.cache.lastFetch = null;
        return await this.init();
    }

    // ============================================
    // Localization Settings
    // ============================================

    async loadLocalizationSettings() {
        try {
            const response = await fetch(`${getBaseUrl()}/api/v1/settings/localization`);
            if (!response.ok) throw new Error('Failed to load localization settings');
            
            const data = await response.json();
            this.settings.localization = data.data || data;
            
            // Apply timezone globally
            if (this.settings.localization.time_zone) {
                window.appTimezone = this.settings.localization.time_zone;
            }
            
            return this.settings.localization;
        } catch (error) {
            logger.error('Failed to load localization:', error);
            // Fallback to defaults - try to use browser timezone or UTC
            const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
            this.settings.localization = {
                timezone: browserTimezone,
                time_zone: browserTimezone, // Keep both for compatibility
                timeFormat: '12h',
                time_format: '12h',
                dateFormat: 'MM/DD/YYYY',
                date_format: 'MM/DD/YYYY',
                firstDayOfWeek: 0,
                first_day_of_week: 0
            };
            return this.settings.localization;
        }
    }

    getTimezone() {
        return this.settings.localization?.timezone || 
               this.settings.localization?.time_zone || 
               Intl.DateTimeFormat().resolvedOptions().timeZone || 
               'UTC';
    }

    getTimeFormat() {
        // Check both camelCase (from API) and snake_case (legacy) formats
        return this.settings.localization?.timeFormat || 
               this.settings.localization?.time_format || 
               '12h';
    }

    getDateFormat() {
        return this.settings.localization?.date_format || 'MM/DD/YYYY';
    }

    getFirstDayOfWeek() {
        // Check for numeric value first (preferred format: 0-6)
        // Try camelCase from API first, then snake_case from context
        const numericValue = this.settings.localization?.firstDayOfWeek ?? 
                            this.settings.localization?.first_day_of_week ??
                            this.settings.localization?.context?.first_day_of_week;
        if (typeof numericValue === 'number') {
            logger.debug(`📅 First day of week from settings: ${numericValue} (${['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][numericValue]})`);
            return numericValue;
        }
        
        // Fall back to string value from database (localization.first_day)
        const stringValue = this.settings.localization?.first_day;
        if (typeof stringValue === 'string') {
            // Convert day name to number: Sunday=0, Monday=1, etc.
            const dayMap = {
                'Sunday': 0, 'sunday': 0,
                'Monday': 1, 'monday': 1,
                'Tuesday': 2, 'tuesday': 2,
                'Wednesday': 3, 'wednesday': 3,
                'Thursday': 4, 'thursday': 4,
                'Friday': 5, 'friday': 5,
                'Saturday': 6, 'saturday': 6
            };
            const result = dayMap[stringValue] ?? 0;
            logger.debug(`📅 First day of week from string "${stringValue}": ${result}`);
            return result;
        }
        
        // Default to Sunday (0)
        logger.debug('📅 First day of week using default: 0 (Sunday)');
        return 0;
    }

    /**
     * Format time according to localization settings
     */
    formatTime(dateTime) {
        if (!(dateTime instanceof DateTime)) {
            dateTime = DateTime.fromISO(dateTime, { zone: this.getTimezone() });
        }
        
        const format = this.getTimeFormat() === '24h' ? 'HH:mm' : 'h:mm a';
        return dateTime.toFormat(format);
    }

    /**
     * Format date according to localization settings
     */
    formatDate(dateTime) {
        if (!(dateTime instanceof DateTime)) {
            dateTime = DateTime.fromISO(dateTime, { zone: this.getTimezone() });
        }
        
        // Convert PHP date format to Luxon format
        const phpFormat = this.getDateFormat();
        const luxonFormat = phpFormat
            .replace('YYYY', 'yyyy')
            .replace('DD', 'dd')
            .replace('MM', 'MM');
        
        return dateTime.toFormat(luxonFormat);
    }

    /**
     * Format datetime according to localization settings
     */
    formatDateTime(dateTime) {
        return `${this.formatDate(dateTime)} ${this.formatTime(dateTime)}`;
    }

    /**
     * Get currency code
     */
    getCurrency() {
        return this.settings.localization?.context?.currency || 'ZAR';
    }

    /**
     * Get currency symbol
     */
    getCurrencySymbol() {
        return this.settings.localization?.context?.currency_symbol || 'R';
    }

    /**
     * Format amount as currency
     * @param {number|string} amount - The amount to format
     * @param {number} decimals - Number of decimal places (default: 2)
     * @returns {string} Formatted currency string
     */
    formatCurrency(amount, decimals = 2) {
        const numAmount = parseFloat(amount) || 0;
        const symbol = this.getCurrencySymbol();
        const formatted = numAmount.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return `${symbol}${formatted}`;
    }

    // ============================================
    // Booking Settings
    // ============================================

    async loadBookingSettings() {
        try {
            const response = await fetch(`${getBaseUrl()}/api/v1/settings/booking`);
            if (!response.ok) throw new Error('Failed to load booking settings');
            
            const data = await response.json();
            this.settings.booking = data.data || data;
            
            return this.settings.booking;
        } catch (error) {
            logger.error('Failed to load booking settings:', error);
            // Fallback to defaults
            this.settings.booking = {
                enabled_fields: [
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'notes'
                ],
                required_fields: [
                    'first_name',
                    'last_name',
                    'email'
                ],
                min_booking_notice: 1, // hours
                max_booking_advance: 30, // days
                allow_cancellation: true,
                cancellation_deadline: 24 // hours
            };
            return this.settings.booking;
        }
    }

    getEnabledFields() {
        return this.settings.booking?.enabled_fields || [];
    }

    getRequiredFields() {
        return this.settings.booking?.required_fields || [];
    }

    isFieldEnabled(fieldName) {
        return this.getEnabledFields().includes(fieldName);
    }

    isFieldRequired(fieldName) {
        return this.getRequiredFields().includes(fieldName);
    }

    getMinBookingNotice() {
        return this.settings.booking?.min_booking_notice || 1; // hours
    }

    getMaxBookingAdvance() {
        return this.settings.booking?.max_booking_advance || 30; // days
    }

    /**
     * Get earliest bookable datetime
     */
    getEarliestBookableTime() {
        const minNotice = this.getMinBookingNotice();
        return DateTime.now()
            .setZone(this.getTimezone())
            .plus({ hours: minNotice });
    }

    /**
     * Get latest bookable datetime
     */
    getLatestBookableTime() {
        const maxAdvance = this.getMaxBookingAdvance();
        return DateTime.now()
            .setZone(this.getTimezone())
            .plus({ days: maxAdvance });
    }

    /**
     * Check if datetime is within booking window
     */
    isWithinBookingWindow(dateTime) {
        if (!(dateTime instanceof DateTime)) {
            dateTime = DateTime.fromISO(dateTime, { zone: this.getTimezone() });
        }
        
        const earliest = this.getEarliestBookableTime();
        const latest = this.getLatestBookableTime();
        
        return dateTime >= earliest && dateTime <= latest;
    }

    // ============================================
    // Business Hours
    // ============================================

    async loadBusinessHours() {
        try {
            const response = await fetch(`${getBaseUrl()}/api/v1/settings/business-hours`);
            if (!response.ok) throw new Error('Failed to load business hours');
            
            const data = await response.json();
            this.settings.businessHours = data.data || data;
            
            return this.settings.businessHours;
        } catch (error) {
            logger.error('Failed to load business hours:', error);
            // Fallback to defaults
            this.settings.businessHours = {
                enabled: true,
                schedule: {
                    monday: { enabled: true, start: '09:00', end: '17:00' },
                    tuesday: { enabled: true, start: '09:00', end: '17:00' },
                    wednesday: { enabled: true, start: '09:00', end: '17:00' },
                    thursday: { enabled: true, start: '09:00', end: '17:00' },
                    friday: { enabled: true, start: '09:00', end: '17:00' },
                    saturday: { enabled: false, start: '09:00', end: '17:00' },
                    sunday: { enabled: false, start: '09:00', end: '17:00' }
                },
                breaks: []
            };
            return this.settings.businessHours;
        }
    }

    getBusinessHours() {
        return this.settings.businessHours;
    }

    /**
     * Get business hours for a specific day
     * @param {number} dayOfWeek - 0=Sunday, 1=Monday, etc.
     */
    getBusinessHoursForDay(dayOfWeek) {
        const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const dayName = dayNames[dayOfWeek];
        
        // API returns data directly under day names (not nested under 'schedule')
        const dayHours = this.settings.businessHours?.[dayName] || 
                        this.settings.businessHours?.schedule?.[dayName];
        
        if (!dayHours) {
            return {
                enabled: false,
                isWorkingDay: false,
                start: '09:00',
                end: '17:00'
            };
        }
        
        // Normalize the response - API uses 'isWorkingDay', legacy uses 'enabled'
        return {
            enabled: dayHours.isWorkingDay ?? dayHours.enabled ?? false,
            isWorkingDay: dayHours.isWorkingDay ?? dayHours.enabled ?? false,
            start: dayHours.startTime || dayHours.start || '09:00',
            end: dayHours.endTime || dayHours.end || '17:00',
            breaks: dayHours.breaks || []
        };
    }

    /**
     * Check if a specific day is a working day
     * @param {number} dayOfWeek - 0=Sunday, 1=Monday, etc.
     */
    isWorkingDay(dayOfWeek) {
        const hours = this.getBusinessHoursForDay(dayOfWeek);
        return hours.enabled || hours.isWorkingDay;
    }

    /**
     * Check if a DateTime falls on a working day
     * @param {DateTime} dateTime - Luxon DateTime object
     */
    isWorkingDateTime(dateTime) {
        if (!(dateTime instanceof DateTime)) {
            dateTime = DateTime.fromISO(dateTime, { zone: this.getTimezone() });
        }
        // Luxon weekday: 1=Mon, 7=Sun. Convert to 0=Sun format
        const dayOfWeek = dateTime.weekday % 7;
        return this.isWorkingDay(dayOfWeek);
    }

    /**
     * Check if a datetime is within business hours
     */
    isWithinBusinessHours(dateTime) {
        if (!(dateTime instanceof DateTime)) {
            dateTime = DateTime.fromISO(dateTime, { zone: this.getTimezone() });
        }
        
        // Luxon weekday: 1=Mon, 7=Sun. Convert to 0=Sun format
        const dayOfWeek = dateTime.weekday % 7;
        const dayHours = this.getBusinessHoursForDay(dayOfWeek);
        
        if (!dayHours.enabled && !dayHours.isWorkingDay) return false;
        
        const [startHour, startMin] = dayHours.start.split(':').map(Number);
        const [endHour, endMin] = dayHours.end.split(':').map(Number);
        
        const startTime = dateTime.set({ hour: startHour, minute: startMin, second: 0 });
        const endTime = dateTime.set({ hour: endHour, minute: endMin, second: 0 });
        
        return dateTime >= startTime && dateTime <= endTime;
    }

    /**
     * Get time range for business hours display
     */
    getBusinessHoursRange() {
        const schedule = this.settings.businessHours?.schedule || {};
        const workingDays = Object.values(schedule).filter(day => day.enabled);
        
        if (workingDays.length === 0) {
            return { start: '09:00', end: '17:00' };
        }
        
        // Get earliest start and latest end
        const starts = workingDays.map(d => d.start);
        const ends = workingDays.map(d => d.end);
        
        return {
            start: starts.sort()[0],
            end: ends.sort().reverse()[0]
        };
    }

    // ============================================
    // Provider Schedules
    // ============================================

    async loadProviderSchedule(providerId) {
        try {
            const response = await fetch(withBaseUrl(`/api/providers/${providerId}/schedule`));
            if (!response.ok) throw new Error('Failed to load provider schedule');
            
            const data = await response.json();
            const raw = data.schedule || data.data || data;
            
            // Normalize DB format to day-name keyed format
            const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            const schedule = {};
            dayNames.forEach(name => {
                schedule[name] = { enabled: false, start: '09:00', end: '17:00' };
            });
            
            if (raw && typeof raw === 'object') {
                Object.entries(raw).forEach(([key, entry]) => {
                    if (!entry) return;
                    // key may be a day name ("monday") or numeric index
                    let dayName = dayNames.includes(key) ? key : null;
                    if (!dayName) {
                        const dayIndex = parseInt(entry.day_of_week ?? key, 10);
                        dayName = !isNaN(dayIndex) ? dayNames[dayIndex] : null;
                    }
                    // Also accept day_of_week as a day name string
                    if (!dayName && typeof entry.day_of_week === 'string' && dayNames.includes(entry.day_of_week)) {
                        dayName = entry.day_of_week;
                    }
                    if (!dayName) return;
                    schedule[dayName] = {
                        enabled: !!(entry.is_active ?? entry.is_working ?? entry.enabled),
                        start: (entry.start_time || entry.start || '09:00').substring(0, 5),
                        end: (entry.end_time || entry.end || '17:00').substring(0, 5),
                    };
                });
            }
            
            this.settings.providerSchedules.set(providerId, schedule);
            return schedule;
        } catch (error) {
            logger.error(`Failed to load schedule for provider ${providerId}:`, error);
            return null;
        }
    }

    getProviderSchedule(providerId) {
        return this.settings.providerSchedules.get(providerId);
    }

    /**
     * Check if provider is available at a specific datetime
     */
    async isProviderAvailable(providerId, dateTime) {
        if (!(dateTime instanceof DateTime)) {
            dateTime = DateTime.fromISO(dateTime, { zone: this.getTimezone() });
        }
        
        // Load schedule if not cached
        if (!this.settings.providerSchedules.has(providerId)) {
            await this.loadProviderSchedule(providerId);
        }
        
        const schedule = this.getProviderSchedule(providerId);
        if (!schedule) return true; // Assume available if no schedule found
        
        const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const dayName = dayNames[dateTime.weekday % 7];
        const daySchedule = schedule[dayName];
        
        if (!daySchedule || !daySchedule.enabled) return false;
        
        const [startHour, startMin] = daySchedule.start.split(':').map(Number);
        const [endHour, endMin] = daySchedule.end.split(':').map(Number);
        
        const startTime = dateTime.set({ hour: startHour, minute: startMin, second: 0 });
        const endTime = dateTime.set({ hour: endHour, minute: endMin, second: 0 });
        
        return dateTime >= startTime && dateTime <= endTime;
    }

    /**
     * Get available time slots for a provider on a specific date
     */
    async getAvailableSlots(providerId, date, duration = 60) {
        const dateTime = typeof date === 'string' 
            ? DateTime.fromISO(date, { zone: this.getTimezone() })
            : date;
        
        const schedule = await this.getProviderSchedule(providerId) || 
                        this.getBusinessHoursForDay(dateTime.weekday % 7);
        
        if (!schedule.enabled) return [];
        
        const [startHour, startMin] = schedule.start.split(':').map(Number);
        const [endHour, endMin] = schedule.end.split(':').map(Number);
        
        const slots = [];
        let current = dateTime.set({ hour: startHour, minute: startMin, second: 0 });
        const end = dateTime.set({ hour: endHour, minute: endMin, second: 0 });
        
        while (current.plus({ minutes: duration }) <= end) {
            slots.push({
                start: current,
                end: current.plus({ minutes: duration }),
                available: true // Will be checked against appointments
            });
            current = current.plus({ minutes: 30 }); // 30-minute intervals
        }
        
        return slots;
    }
}
