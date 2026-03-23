/**
 * Currency Formatting Utility
 * 
 * Provides currency formatting functionality based on application settings.
 * Can be used independently or integrated with the settings manager.
 */
import { getBaseUrl } from './utils/url-helpers.js';

const DEFAULT_CURRENCY = 'ZAR';
const DEFAULT_SYMBOL = 'R';

function normalizeAmount(amount) {
    return parseFloat(amount) || 0;
}

function formatWithSymbol(amount, symbol, decimals = 2) {
    const numAmount = normalizeAmount(amount);
    const formatted = numAmount.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return `${symbol}${formatted}`;
}

class CurrencyFormatter {
    constructor() {
        this.currency = DEFAULT_CURRENCY;
        this.symbol = DEFAULT_SYMBOL;
        this.loaded = false;
    }

    /**
     * Initialize currency settings from API
     */
    async init() {
        if (this.loaded) return;
        
        try {
            const baseUrl = getBaseUrl();
            const url = `${baseUrl}/api/v1/settings/localization`;
            const response = await fetch(url);
            if (response.ok) {
                const data = await response.json();
                const context = data.data?.context || data.data;
                this.currency = context.currency || DEFAULT_CURRENCY;
                this.symbol = context.currency_symbol || DEFAULT_SYMBOL;
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
        return formatWithSymbol(amount, this.symbol, decimals);
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

export const currencyFormatter = window.currencyFormatter || new CurrencyFormatter();

export function getCurrencySymbol(fallbackSymbol = DEFAULT_SYMBOL) {
    return currencyFormatter.getSymbol?.() || fallbackSymbol;
}

export function formatCurrency(amount, options = {}) {
    const {
        decimals = 2,
        currencySymbol = null,
    } = options;

    if (currencySymbol) {
        return formatWithSymbol(amount, currencySymbol, decimals);
    }

    if (currencyFormatter && typeof currencyFormatter.format === 'function') {
        return currencyFormatter.format(amount, decimals);
    }

    return formatWithSymbol(amount, getCurrencySymbol(), decimals);
}

window.currencyFormatter = currencyFormatter;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => currencyFormatter.init());
} else {
    currencyFormatter.init();
}

// Export for module usage
export default CurrencyFormatter;
