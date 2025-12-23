/**
 * Currency Formatting Utility
 * 
 * Provides currency formatting functionality based on application settings.
 * Can be used independently or integrated with the settings manager.
 */

class CurrencyFormatter {
    constructor() {
        this.currency = 'ZAR';
        this.symbol = 'R';
        this.loaded = false;
    }

    /**
     * Initialize currency settings from API
     */
    async init() {
        if (this.loaded) return;
        
        try {
            const baseUrl = String(window.__BASE_URL__ || '').replace(/\/+$/, '');
            const url = baseUrl ? `${baseUrl}/api/v1/settings/localization` : '/api/v1/settings/localization';
            const response = await fetch(url);
            if (response.ok) {
                const data = await response.json();
                const context = data.data?.context || data.data;
                this.currency = context.currency || 'ZAR';
                this.symbol = context.currency_symbol || 'R';
                this.loaded = true;
            }
        } catch (error) {
            console.error('Failed to load currency settings:', error);
            // Keep defaults
        }
    }

    /**
     * Get currency code
     */
    getCurrency() {
        return this.currency;
    }

    /**
     * Get currency symbol
     */
    getSymbol() {
        return this.symbol;
    }

    /**
     * Format amount as currency
     * @param {number|string} amount - The amount to format
     * @param {number} decimals - Number of decimal places (default: 2)
     * @returns {string} Formatted currency string
     */
    format(amount, decimals = 2) {
        const numAmount = parseFloat(amount) || 0;
        const formatted = numAmount.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return `${this.symbol}${formatted}`;
    }

    /**
     * Parse currency string to number
     * @param {string} currencyString - The currency string to parse
     * @returns {number} Parsed number value
     */
    parse(currencyString) {
        if (typeof currencyString === 'number') return currencyString;
        const cleaned = currencyString.replace(/[^\d.-]/g, '');
        return parseFloat(cleaned) || 0;
    }
}

// Create global instance
window.currencyFormatter = new CurrencyFormatter();

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.currencyFormatter.init());
} else {
    window.currencyFormatter.init();
}

// Export for module usage
export default CurrencyFormatter;
