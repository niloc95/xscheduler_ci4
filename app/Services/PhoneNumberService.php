<?php

namespace App\Services;

use App\Models\SettingModel;

class PhoneNumberService
{
    private SettingModel $settings;

    public function __construct(?SettingModel $settings = null)
    {
        $this->settings = $settings ?? new SettingModel();
    }

    public function getDefaultCountryCode(): string
    {
        $values = $this->settings->getByKeys([
            'localization.default_phone_country_code',
            'localization.phone_country_code',
        ]);

        $configured = (string) ($values['localization.default_phone_country_code'] ?? $values['localization.phone_country_code'] ?? '');
        $normalized = $this->normalizeCountryCode($configured);

        return $normalized ?? '+27';
    }

    public function normalize(?string $rawPhone, ?string $countryCode = null): ?string
    {
        if ($rawPhone === null) {
            return null;
        }

        $rawPhone = trim($rawPhone);
        if ($rawPhone === '') {
            return null;
        }

        if (str_starts_with($rawPhone, '00')) {
            $rawPhone = '+' . substr($rawPhone, 2);
        }

        if (str_starts_with($rawPhone, '+')) {
            $digits = preg_replace('/\D+/', '', substr($rawPhone, 1));
            if ($digits === null || $digits === '' || $digits[0] === '0') {
                return null;
            }
            return '+' . substr($digits, 0, 15);
        }

        $digits = preg_replace('/\D+/', '', $rawPhone);
        if ($digits === null || $digits === '') {
            return null;
        }

        $resolvedCountry = $this->normalizeCountryCode($countryCode) ?? $this->getDefaultCountryCode();
        $countryDigits = ltrim($resolvedCountry, '+');
        $digitsNoLeadingZero = ltrim($digits, '0');

        if ($digitsNoLeadingZero === '') {
            return null;
        }

        if (str_starts_with($digits, $countryDigits)) {
            return '+' . substr($digits, 0, 15);
        }

        return '+' . substr($countryDigits . $digitsNoLeadingZero, 0, 15);
    }

    public function normalizeCountryCode(?string $countryCode): ?string
    {
        if ($countryCode === null) {
            return null;
        }

        $countryCode = trim($countryCode);
        if ($countryCode === '') {
            return null;
        }

        if (str_starts_with($countryCode, '00')) {
            $countryCode = '+' . substr($countryCode, 2);
        }

        $digits = preg_replace('/\D+/', '', $countryCode);
        if ($digits === null || $digits === '' || $digits[0] === '0') {
            return null;
        }

        return '+' . substr($digits, 0, 4);
    }
}