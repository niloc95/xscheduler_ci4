<?php

/**
 * =============================================================================
 * CURRENCY HELPER
 * =============================================================================
 * 
 * @file        app/Helpers/currency_helper.php
 * @description Global helper functions for currency formatting based on
 *              application localization settings.
 * 
 * LOADING:
 * -----------------------------------------------------------------------------
 * Loaded automatically via BaseController or manually:
 *     helper('currency');
 * 
 * AVAILABLE FUNCTIONS:
 * -----------------------------------------------------------------------------
 * format_currency($amount, $code, $decimals)
 *   Formats number as currency with symbol
 *   Example: format_currency(150.00) => "R150.00"
 * 
 * get_currency_symbol($code)
 *   Gets symbol for currency code
 *   Example: get_currency_symbol('USD') => "$"
 * 
 * get_currency_code()
 *   Gets configured currency code from settings
 *   Example: get_currency_code() => "ZAR"
 * 
 * SUPPORTED CURRENCIES:
 * -----------------------------------------------------------------------------
 * - USD ($)  : US Dollar
 * - EUR (€)  : Euro
 * - GBP (£)  : British Pound
 * - ZAR (R)  : South African Rand
 * - INR (₹)  : Indian Rupee
 * - AUD ($)  : Australian Dollar
 * - CAD ($)  : Canadian Dollar
 * - And many more...
 * 
 * USAGE:
 * -----------------------------------------------------------------------------
 *     // Uses configured currency
 *     echo format_currency(150.00); // "R150.00"
 * 
 *     // Override currency
 *     echo format_currency(150.00, 'USD'); // "$150.00"
 * 
 *     // Custom decimals
 *     echo format_currency(150.00, null, 0); // "R150"
 * 
 * @see         app/Services/LocalizationSettingsService.php
 * @package     App\Helpers
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

if (! function_exists('format_currency')) {
    /**
     * Format a number as currency using the application's currency setting
     *
     * @param float|string $amount The amount to format
     * @param string|null $currencyCode Optional currency code to override the setting
     * @param int $decimals Number of decimal places (default: 2)
     * @return string Formatted currency string
     */
    function format_currency($amount, ?string $currencyCode = null, int $decimals = 2): string
    {
        // Get currency from settings if not provided
        if ($currencyCode === null) {
            $currencyCode = setting('Localization.currency', 'ZAR');
        }
        
        // Ensure amount is numeric
        $amount = is_numeric($amount) ? (float)$amount : 0.00;
        
        // Get currency symbol
        $symbol = get_currency_symbol($currencyCode);
        
        // Format the number with thousands separator
        $formatted = number_format($amount, $decimals, '.', ',');
        
        // Return symbol + formatted amount
        return $symbol . $formatted;
    }
}

if (! function_exists('get_currency_symbol')) {
    /**
     * Get the currency symbol for a given currency code
     *
     * @param string $currencyCode The ISO 4217 currency code
     * @return string The currency symbol
     */
    function get_currency_symbol(string $currencyCode): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => '¥',
            'CHF' => 'CHF ',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'NZD' => 'NZ$',
            'ZAR' => 'R',
            'INR' => '₹',
            'BRL' => 'R$',
            'RUB' => '₽',
            'KRW' => '₩',
            'MXN' => 'Mex$',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'PLN' => 'zł',
            'TRY' => '₺',
            'AED' => 'د.إ',
            'SAR' => 'ر.س',
        ];
        
        return $symbols[$currencyCode] ?? ($currencyCode . ' ');
    }
}

if (! function_exists('get_app_currency')) {
    /**
     * Get the application's configured currency code
     *
     * @return string Currency code (e.g., 'ZAR', 'USD')
     */
    function get_app_currency(): string
    {
        return setting('Localization.currency', 'ZAR');
    }
}

if (! function_exists('get_app_currency_symbol')) {
    /**
     * Get the application's configured currency symbol
     *
     * @return string Currency symbol (e.g., 'R', '$')
     */
    function get_app_currency_symbol(): string
    {
        $currencyCode = get_app_currency();
        return get_currency_symbol($currencyCode);
    }
}

if (! function_exists('parse_currency')) {
    /**
     * Parse a currency string to a float value
     * Removes currency symbols and formatting
     *
     * @param string $currencyString The currency string to parse
     * @return float The numeric value
     */
    function parse_currency(string $currencyString): float
    {
        // Remove all non-numeric characters except dots and negative signs
        $cleaned = preg_replace('/[^\d.-]/', '', $currencyString);
        return (float)$cleaned;
    }
}
