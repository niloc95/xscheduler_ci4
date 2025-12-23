/**
 * Time Format Handler
 * 
 * Dynamically formats time inputs based on localization settings.
 * Converts between 24h and 12h formats for business hours display.
 */

export class TimeFormatHandler {
    constructor() {
        this.timeFormat = '24h'; // Default
        this.initialized = false;
    }

    /**
     * Initialize by fetching localization settings
     */
    async init() {
        if (this.initialized) return;
        
        try {
            const baseUrl = (window.__BASE_URL__ || '').replace(/\/+$/, '');
            const response = await fetch(`${baseUrl}/api/v1/settings`);
            if (!response.ok) throw new Error('Failed to fetch settings');
            
            const settings = await response.json();
            this.timeFormat = settings['localization.time_format'] || '24h';
            this.initialized = true;
        } catch (error) {
            console.warn('[TimeFormatHandler] Failed to load settings, using default 24h format:', error);
            this.timeFormat = '24h';
            this.initialized = true;
        }
    }

    /**
     * Convert 24h time string (HH:mm) to display format based on settings
     * @param {string} time24 - Time in 24h format (e.g., "14:30")
     * @returns {string} - Formatted time string
     */
    formatTimeDisplay(time24) {
        if (!time24) return '';
        
        if (this.timeFormat === '24h') {
            return time24;
        }
        
        // Convert to 12h format
        const [hours, minutes] = time24.split(':').map(Number);
        const period = hours >= 12 ? 'PM' : 'AM';
        const hours12 = hours % 12 || 12;
        
        return `${hours12}:${minutes.toString().padStart(2, '0')} ${period}`;
    }

    /**
     * Convert 12h time string with AM/PM to 24h format
     * @param {string} time12 - Time in 12h format (e.g., "2:30 PM")
     * @returns {string} - Time in 24h format (HH:mm)
     */
    parseTime12to24(time12) {
        const match = time12.match(/(\d+):(\d+)\s*(AM|PM)/i);
        if (!match) return time12;
        
        let [, hours, minutes, period] = match;
        hours = parseInt(hours);
        minutes = parseInt(minutes);
        
        if (period.toUpperCase() === 'PM' && hours !== 12) {
            hours += 12;
        } else if (period.toUpperCase() === 'AM' && hours === 12) {
            hours = 0;
        }
        
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    }

    /**
     * Update time input fields to show formatted time labels
     * @param {HTMLInputElement} input - The time input element
     */
    enhanceTimeInput(input) {
        if (!input || input.type !== 'time') return;
        
        const wrapper = document.createElement('div');
        wrapper.className = 'relative';
        
        // Create display label
        const label = document.createElement('div');
        label.className = 'absolute inset-0 flex items-center px-3 text-gray-700 dark:text-gray-300 pointer-events-none';
        label.style.background = 'transparent';
        
        // Update label text based on format
        const updateLabel = () => {
            if (input.value) {
                label.textContent = this.formatTimeDisplay(input.value);
            }
        };
        
        // Only show custom label if 12h format is active
        if (this.timeFormat === '12h') {
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            wrapper.appendChild(label);
            
            // Hide the native time picker UI when 12h format is selected
            input.addEventListener('focus', () => {
                label.style.display = 'none';
            });
            
            input.addEventListener('blur', () => {
                updateLabel();
                label.style.display = 'flex';
            });
            
            input.addEventListener('change', updateLabel);
            updateLabel();
        }
    }

    /**
     * Apply time format to all time inputs on the page
     */
    applyToAllTimeInputs() {
        const timeInputs = document.querySelectorAll('input[type="time"]');
        timeInputs.forEach(input => this.enhanceTimeInput(input));
    }

    /**
     * Add text display next to time inputs showing formatted time
     */
    addFormattedDisplays() {
        const timeInputs = document.querySelectorAll('input[type="time"]');
        
        timeInputs.forEach(input => {
            // Skip if already has display
            if (input.nextElementSibling?.classList.contains('time-format-display')) {
                return;
            }
            
            // Create display span
            const display = document.createElement('span');
            display.className = 'time-format-display ml-2 text-sm text-gray-600 dark:text-gray-400';
            
            const updateDisplay = () => {
                if (input.value) {
                    const formatted = this.formatTimeDisplay(input.value);
                    display.textContent = `(${formatted})`;
                } else {
                    display.textContent = '';
                }
            };
            
            input.addEventListener('change', updateDisplay);
            input.addEventListener('blur', updateDisplay);
            
            // Insert after input
            if (input.parentElement.classList.contains('form-input')) {
                input.parentElement.parentElement.appendChild(display);
            } else {
                input.parentElement.appendChild(display);
            }
            
            updateDisplay();
        });
    }

    /**
     * Listen for settings changes and update format
     */
    watchSettingsChanges() {
        // Listen for custom event when settings are saved
        document.addEventListener('settingsSaved', async (event) => {
            if (event.detail?.includes('localization.time_format')) {
                this.initialized = false;
                await this.init();
                this.refreshTimeDisplays();
            }
        });
    }

    /**
     * Refresh all time displays after format change
     */
    refreshTimeDisplays() {
        // Remove existing displays
        document.querySelectorAll('.time-format-display').forEach(el => el.remove());
        
        // Re-apply format
        this.addFormattedDisplays();
    }

    /**
     * Get current time format
     * @returns {string} '12h' or '24h'
     */
    getFormat() {
        return this.timeFormat;
    }

    /**
     * Check if using 12h format
     * @returns {boolean}
     */
    is12Hour() {
        return this.timeFormat === '12h';
    }
}

// Create and export singleton instance
const timeFormatHandler = new TimeFormatHandler();

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', async () => {
        await timeFormatHandler.init();
        timeFormatHandler.addFormattedDisplays();
        timeFormatHandler.watchSettingsChanges();
    });
} else {
    // DOM already loaded
    timeFormatHandler.init().then(() => {
        timeFormatHandler.addFormattedDisplays();
        timeFormatHandler.watchSettingsChanges();
    });
}

export default timeFormatHandler;

// Re-apply formatting after SPA navigation.
document.addEventListener('spa:navigated', () => {
    timeFormatHandler.addFormattedDisplays();
});
