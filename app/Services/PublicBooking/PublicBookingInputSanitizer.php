<?php

/**
 * Normalises and sanitises untrusted public booking input: customer fields,
 * appointment custom fields, free text and phone numbers.
 *
 * Collaborator of App\Services\PublicBookingService (the public booking facade).
 */

namespace App\Services\PublicBooking;

use App\Exceptions\PublicBookingException;
use App\Services\CustomerCustomFieldService;
use App\Services\PhoneNumberService;

class PublicBookingInputSanitizer
{
    private PhoneNumberService $phoneNumberService;
    private CustomerCustomFieldService $customerCustomFieldService;

    public function __construct(
        PhoneNumberService $phoneNumberService,
        CustomerCustomFieldService $customerCustomFieldService
    ) {
        $this->phoneNumberService = $phoneNumberService;
        $this->customerCustomFieldService = $customerCustomFieldService;
    }

    public function extractCustomerFields(array $payload, array $fieldConfig, array $customConfig): array
    {
        $fields = [];
        $firstName = $this->sanitizeString($payload['first_name'] ?? null, 100);
        $lastName = $this->sanitizeString($payload['last_name'] ?? null, 100);
        $email = $this->sanitizeString($payload['email'] ?? null, 150);
        $rawPhone = trim((string) ($payload['phone'] ?? ''));
        $phone = $this->normalizePhone($payload['phone'] ?? null, $payload['phone_country_code'] ?? null);
        $address = $this->sanitizeString($payload['address'] ?? null, 255);
        $notes = $this->sanitizeString($payload['customer_notes'] ?? $payload['notes'] ?? null, 1000);

        if (($fieldConfig['first_name']['required'] ?? false) && $firstName === null) {
            throw new PublicBookingException('First name is required.', 422, ['first_name' => 'required']);
        }
        if ($firstName !== null && !preg_match("/^[\p{L}\p{M}\'-\. ]+$/u", $firstName)) {
            throw new PublicBookingException('First name must contain only letters, spaces, hyphens, apostrophes, or periods.', 422, ['first_name' => 'invalid']);
        }
        if (($fieldConfig['last_name']['required'] ?? false) && $lastName === null) {
            throw new PublicBookingException('Last name is required.', 422, ['last_name' => 'required']);
        }
        if ($lastName !== null && !preg_match("/^[\p{L}\p{M}\'-\. ]+$/u", $lastName)) {
            throw new PublicBookingException('Last name must contain only letters, spaces, hyphens, apostrophes, or periods.', 422, ['last_name' => 'invalid']);
        }
        if (($fieldConfig['email']['required'] ?? false) && $email === null) {
            throw new PublicBookingException('Email is required.', 422, ['email' => 'required']);
        }
        if ($rawPhone !== '' && $phone === null) {
            throw new PublicBookingException(
                'Please provide a valid phone number in E.164 format (for example +27821234567).',
                422,
                ['phone' => 'invalid']
            );
        }
        if (($fieldConfig['phone']['required'] ?? false) && $phone === null) {
            throw new PublicBookingException('Phone number is required.', 422, ['phone' => 'required']);
        }
        if (($fieldConfig['address']['required'] ?? false) && $address === null) {
            throw new PublicBookingException('Address is required.', 422, ['address' => 'required']);
        }
        if (($fieldConfig['notes']['required'] ?? false) && $notes === null) {
            throw new PublicBookingException('Notes are required.', 422, ['notes' => 'required']);
        }

        if (($fieldConfig['first_name']['display'] ?? true) && $firstName !== null) {
            $fields['first_name'] = $firstName;
        }
        if (($fieldConfig['last_name']['display'] ?? true) && $lastName !== null) {
            $fields['last_name'] = $lastName;
        }
        if (($fieldConfig['email']['display'] ?? true) && $email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new PublicBookingException('Please provide a valid email address.', 422, ['email' => 'invalid']);
            }
            $fields['email'] = strtolower($email);
        }
        if (($fieldConfig['phone']['display'] ?? true) && $phone !== null) {
            $fields['phone'] = $phone;
        }
        if (($fieldConfig['address']['display'] ?? false) && $address !== null) {
            $fields['address'] = $address;
        }
        if (($fieldConfig['notes']['display'] ?? false) && $notes !== null) {
            $fields['notes'] = $notes;
        }

        if (empty($fields['first_name']) && !empty($fields['last_name'])) {
            $fields['first_name'] = 'Guest';
        }
        if (empty($fields['first_name']) && empty($fields['last_name'])) {
            $fields['first_name'] = 'Guest';
        }

        if (empty($fields['email']) && empty($fields['phone'])) {
            throw new PublicBookingException('Please supply at least one contact method (email or phone).', 422, ['contact' => 'required']);
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    public function extractAppointmentCustomFieldValues(
        array $payload,
        array $customConfig,
        bool $respectRequired,
        array|string|null $storedValues = null
    ): array
    {
        $values = [];

        foreach ($customConfig as $field => $config) {
            $rawValue = array_key_exists($field, $payload) ? $payload[$field] : null;
            $value = $this->sanitizeCustomField($rawValue, $config['type']);
            $hasStoredValue = $this->customerCustomFieldService->hasStoredValue($storedValues, $field);

            if ($value === null) {
                if ($respectRequired && !empty($config['required']) && !$hasStoredValue) {
                    throw new PublicBookingException('Please complete all required custom fields.', 422, [$field => 'required']);
                }
                continue;
            }

            $values[$field] = $value;
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    public function extractAppointmentCustomFieldClearFlags(array $payload, array $customConfig): array
    {
        $flags = [];

        foreach ($customConfig as $field => $_config) {
            $clearKey = 'clear__' . $field;
            if (!array_key_exists($clearKey, $payload)) {
                continue;
            }

            $rawValue = strtolower(trim((string) $payload[$clearKey]));
            if (in_array($rawValue, ['1', 'true', 'yes', 'on'], true)) {
                $flags[$field] = '1';
            }
        }

        return $flags;
    }

    public function sanitizeString(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim(strip_tags($value));
        if ($trimmed === '') {
            return null;
        }
        return substr($trimmed, 0, $maxLength);
    }

    public function sanitizeCustomField($value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if ($type === 'checkbox') {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        return substr(strip_tags($value), 0, 255);
    }

    public function normalizePhone(?string $value, ?string $countryCode = null): ?string
    {
        return $this->phoneNumberService->normalize($value, $countryCode);
    }
}
