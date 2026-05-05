<?php

namespace App\Services\Appointment;

use App\Models\AppointmentCustomFieldModel;

class AppointmentCustomFieldService
{
    private AppointmentCustomFieldModel $model;

    public function __construct(?AppointmentCustomFieldModel $model = null)
    {
        $this->model = $model ?? new AppointmentCustomFieldModel();
    }

    /**
     * @return array<string, string>
     */
    public function getForAppointment(int $appointmentId): array
    {
        if ($appointmentId <= 0) {
            return [];
        }

        $rows = $this->model
            ->where('appointment_id', $appointmentId)
            ->findAll();

        $values = [];
        foreach ($rows as $row) {
            $key = (string) ($row['field_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $values[$key] = (string) ($row['value'] ?? '');
        }

        return $values;
    }

    public function mergeForAppointment(int $appointmentId, array $newValues, array $clearFlags): void
    {
        if ($appointmentId <= 0) {
            return;
        }

        foreach ($clearFlags as $fieldKey => $flag) {
            if (!$this->isTruthy($flag)) {
                continue;
            }

            $this->model
                ->where('appointment_id', $appointmentId)
                ->where('field_key', (string) $fieldKey)
                ->delete();
        }

        foreach ($newValues as $fieldKey => $rawValue) {
            $fieldKey = (string) $fieldKey;
            if ($fieldKey === '') {
                continue;
            }

            $value = is_string($rawValue) ? trim($rawValue) : (is_scalar($rawValue) ? trim((string) $rawValue) : '');
            if ($value === '') {
                continue;
            }

            $existing = $this->model
                ->where('appointment_id', $appointmentId)
                ->where('field_key', $fieldKey)
                ->first();

            if (is_array($existing)) {
                if ((string) ($existing['value'] ?? '') !== $value) {
                    $this->model->update((int) $existing['id'], ['value' => $value]);
                }
                continue;
            }

            $this->model->insert([
                'appointment_id' => $appointmentId,
                'field_key' => $fieldKey,
                'value' => $value,
            ]);
        }
    }

    private function isTruthy($value): bool
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
