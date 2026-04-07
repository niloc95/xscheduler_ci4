<?php

namespace App\Services\Appointment;

class AppointmentFormSubmissionService
{
    public function buildCreateBookingData(array $input, array $normalizedStart): array
    {
        $bookingData = [
            'provider_id' => $this->toOptionalInt($input['provider_id'] ?? null),
            'service_id' => $this->toOptionalInt($input['service_id'] ?? null),
            'location_id' => $this->toOptionalInt($input['location_id'] ?? null),
            'appointment_date' => $normalizedStart['app_date'] ?? null,
            'appointment_time' => $normalizedStart['app_time'] ?? null,
            'customer_id' => $this->toOptionalInt($input['customer_id'] ?? null),
            'customer_first_name' => $input['customer_first_name'] ?? null,
            'customer_last_name' => $input['customer_last_name'] ?? null,
            'customer_email' => $input['customer_email'] ?? null,
            'customer_phone' => $input['customer_phone'] ?? null,
            'customer_phone_country_code' => $input['customer_phone_country_code'] ?? null,
            'customer_address' => $input['customer_address'] ?? null,
            'customer_notes' => $input['notes'] ?? null,
            'notes' => $input['notes'] ?? null,
            'notification_types' => ['email', 'whatsapp'],
        ];

        return array_merge($bookingData, $this->extractCustomFieldValues($input));
    }

    public function buildCustomerUpdateData(array $input): array
    {
        $customerData = [
            'first_name' => $input['customer_first_name'] ?? null,
            'last_name' => $input['customer_last_name'] ?? null,
            'email' => $input['customer_email'] ?? null,
            'phone' => $input['customer_phone'] ?? null,
            'address' => $input['customer_address'] ?? null,
        ];

        $customFields = $this->extractCustomFieldValues($input);
        if ($customFields !== []) {
            $customerData['custom_fields'] = json_encode($customFields);
        }

        return $customerData;
    }

    public function buildUpdateAppointmentData(array $input, array $normalizedStart): array
    {
        $status = (string) ($input['status'] ?? '');

        return [
            'provider_id' => (int) ($input['provider_id'] ?? 0),
            'service_id' => (int) ($input['service_id'] ?? 0),
            'appointment_date' => $normalizedStart['app_date'] ?? null,
            'appointment_time' => $normalizedStart['app_time'] ?? null,
            'status' => AppointmentStatus::normalize($status) ?? $status,
            'notes' => (string) ($input['notes'] ?? ''),
            'location_id' => $this->toOptionalInt($input['location_id'] ?? null),
        ];
    }

    private function extractCustomFieldValues(array $input): array
    {
        $customFields = [];

        for ($i = 1; $i <= 6; $i++) {
            $key = "custom_field_{$i}";
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            if ($value === null || $value === '') {
                continue;
            }

            $customFields[$key] = $value;
        }

        return $customFields;
    }

    private function toOptionalInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}