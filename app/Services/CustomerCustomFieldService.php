<?php

namespace App\Services;

use App\Models\CustomerModel;

class CustomerCustomFieldService
{
    private CustomerModel $customers;

    public function __construct(?CustomerModel $customers = null)
    {
        $this->customers = $customers ?? new CustomerModel();
    }

    /**
     * @return array<string, string>
     */
    public function getForCustomer(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $customer = $this->customers->find($customerId);

        return is_array($customer)
            ? $this->decodeValues($customer['custom_fields'] ?? null)
            : [];
    }

    /**
     * @param array<string, mixed>|string|null $storedValues
     * @return array<string, string>
     */
    public function decodeValues(array|string|null $storedValues): array
    {
        if (is_array($storedValues)) {
            $decoded = $storedValues;
        } elseif (is_string($storedValues) && trim($storedValues) !== '') {
            $decoded = json_decode($storedValues, true);
        } else {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $values = [];
        foreach ($decoded as $fieldKey => $fieldValue) {
            $normalizedKey = trim((string) $fieldKey);
            if ($normalizedKey === '') {
                continue;
            }

            $values[$normalizedKey] = $this->normalizeFieldValue($fieldValue);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function encodeValues(array $values): string
    {
        $normalized = [];
        foreach ($values as $fieldKey => $fieldValue) {
            $normalizedKey = trim((string) $fieldKey);
            if ($normalizedKey === '') {
                continue;
            }

            $normalized[$normalizedKey] = $this->normalizeFieldValue($fieldValue);
        }

        $json = json_encode(
            $normalized === [] ? (object) [] : $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return $json === false ? '{}' : $json;
    }

    /**
     * @param array<string, mixed>|string|null $storedValues
     */
    public function hasStoredValue(array|string|null $storedValues, string $fieldKey): bool
    {
        $normalizedKey = trim($fieldKey);
        if ($normalizedKey === '') {
            return false;
        }

        $values = $this->decodeValues($storedValues);

        return array_key_exists($normalizedKey, $values)
            && trim((string) $values[$normalizedKey]) !== '';
    }

    /**
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $clearFlags
     */
    public function mergeForCustomer(int $customerId, array $newValues, array $clearFlags = []): void
    {
        if ($customerId <= 0) {
            return;
        }

        $customer = $this->customers->find($customerId);
        if (!is_array($customer)) {
            return;
        }

        $merged = $this->decodeValues($customer['custom_fields'] ?? null);

        foreach ($clearFlags as $fieldKey => $flag) {
            if (!$this->isTruthy($flag)) {
                continue;
            }

            unset($merged[(string) $fieldKey]);
        }

        foreach ($newValues as $fieldKey => $fieldValue) {
            $normalizedKey = trim((string) $fieldKey);
            if ($normalizedKey === '') {
                continue;
            }

            $normalizedValue = $this->normalizeFieldValue($fieldValue);
            if ($normalizedValue === '') {
                continue;
            }

            $merged[$normalizedKey] = $normalizedValue;
        }

        if ($this->decodeValues($customer['custom_fields'] ?? null) === $merged) {
            return;
        }

        $this->customers
            ->builder()
            ->where('id', $customerId)
            ->update([
                'custom_fields' => $this->encodeValues($merged),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * @param array<string, array<string, mixed>> $config
     * @param array<string, mixed>|string|null $storedValues
     * @return array<int, array<string, mixed>>
     */
    public function buildPublicPayload(array $config, array|string|null $storedValues): array
    {
        helper('app');

        $values = $this->decodeValues($storedValues);
        $fields = [];

        foreach ($config as $fieldKey => $fieldMeta) {
            $value = (string) ($values[$fieldKey] ?? '');
            $isSensitive = !empty($fieldMeta['is_sensitive']);

            $fields[] = [
                'field_key' => $fieldKey,
                'value_masked' => $value === '' ? '' : ($isSensitive ? mask_sensitive_value($value) : $value),
                'is_sensitive' => $isSensitive,
            ];
        }

        return $fields;
    }

    private function normalizeFieldValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}