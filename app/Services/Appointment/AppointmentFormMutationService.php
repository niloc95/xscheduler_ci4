<?php

namespace App\Services\Appointment;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Services\AppointmentBookingService;
use App\Services\AppointmentNotificationService;
use App\Services\BookingSettingsService;
use App\Services\CustomerCustomFieldService;
use App\Services\CustomerService;
use App\Services\PhoneNumberService;
use CodeIgniter\Validation\ValidationInterface;
use Config\Database;

class AppointmentFormMutationService
{
    private ValidationInterface $validation;
    private AppointmentDateTimeNormalizer $appointmentDateTimeNormalizer;
    private AppointmentFormSubmissionService $appointmentFormSubmissionService;
    private AppointmentBookingService $appointmentBookingService;
    private CustomerModel $customerModel;
    private AppointmentModel $appointmentModel;
    private AppointmentNotificationService $appointmentNotificationService;
    private PhoneNumberService $phoneNumberService;
    private CustomerService $customerService;
    private BookingSettingsService $bookingSettingsService;
    private CustomerCustomFieldService $customerCustomFieldService;

    public function __construct(
        ?ValidationInterface $validation = null,
        ?AppointmentDateTimeNormalizer $appointmentDateTimeNormalizer = null,
        ?AppointmentFormSubmissionService $appointmentFormSubmissionService = null,
        ?AppointmentBookingService $appointmentBookingService = null,
        ?CustomerModel $customerModel = null,
        ?AppointmentModel $appointmentModel = null,
        ?AppointmentNotificationService $appointmentNotificationService = null,
        ?PhoneNumberService $phoneNumberService = null,
        ?CustomerService $customerService = null,
        ?BookingSettingsService $bookingSettingsService = null,
        ?CustomerCustomFieldService $customerCustomFieldService = null,
    ) {
        $this->validation = $validation ?? \Config\Services::validation();
        $this->appointmentDateTimeNormalizer = $appointmentDateTimeNormalizer ?? new AppointmentDateTimeNormalizer();
        $this->appointmentFormSubmissionService = $appointmentFormSubmissionService ?? new AppointmentFormSubmissionService();
        $this->appointmentBookingService = $appointmentBookingService ?? new AppointmentBookingService();
        $this->customerModel = $customerModel ?? new CustomerModel();
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
        $this->appointmentNotificationService = $appointmentNotificationService ?? new AppointmentNotificationService();
        $this->phoneNumberService = $phoneNumberService ?? new PhoneNumberService();
        $this->customerService = $customerService ?? new CustomerService($this->customerModel, $this->phoneNumberService);
        $this->bookingSettingsService = $bookingSettingsService ?? new BookingSettingsService();
        $this->customerCustomFieldService = $customerCustomFieldService ?? new CustomerCustomFieldService($this->customerModel);
    }

    public function createFromFormPayload(array $payload, string $clientTimezone): array
    {
        $customerId = $payload['customer_id'] ?? null;
        $rules = $this->getStoreValidationRules(!empty($customerId));

        if (!$this->validation->setRules($rules)->run($payload)) {
            return $this->validationFailure($this->validation->getErrors());
        }

        if (empty($customerId)) {
            $normalizedPhone = $this->phoneNumberService->normalize(
                $payload['customer_phone'] ?? null,
                $payload['customer_phone_country_code'] ?? null
            );

            if ($normalizedPhone === null) {
                return $this->validationFailure([
                    'customer_phone' => 'Please enter a valid phone number for the selected country code.',
                ]);
            }

            // Keep normalized value so downstream create path is deterministic.
            $payload['customer_phone'] = $normalizedPhone;
        }

        $normalizedStart = $this->appointmentDateTimeNormalizer->normalizeDateAndTime(
            (string) ($payload['appointment_date'] ?? ''),
            (string) ($payload['appointment_time'] ?? ''),
            $clientTimezone
        );

        if (!$normalizedStart['success']) {
            return $this->errorResult(422, $normalizedStart['message'] ?? 'Invalid appointment date/time.');
        }

        $bookingData = $this->appointmentFormSubmissionService->buildCreateBookingData($payload, $normalizedStart);
        $bookingData['booking_channel'] = 'admin';
        $result = $this->appointmentBookingService->createAppointment($bookingData, 'UTC');

        if (($result['success'] ?? false) === true) {
            $customConfig = $this->bookingSettingsService->getCustomFieldConfiguration();
            [$customFieldValues] = $this->extractCustomFieldValuesAndClear($payload, $customConfig);
            $appointmentId = (int) ($result['appointmentId'] ?? 0);
            if ($appointmentId > 0) {
                $appointment = $this->appointmentModel->find($appointmentId);
                $customerId = is_array($appointment) ? (int) ($appointment['customer_id'] ?? 0) : 0;

                if ($customerId > 0 && $customFieldValues !== []) {
                    $this->customerCustomFieldService->mergeForCustomer($customerId, $customFieldValues, []);
                }
            }
        }

        return $this->normalizeBookingResult($result, true);
    }

    public function updateFromFormPayload(string $appointmentHash, array $payload, string $clientTimezone): array
    {
        $existingAppointment = $this->appointmentModel->findByHash($appointmentHash);
        if ($existingAppointment === null) {
            return [
                'notFound' => true,
                'message' => 'Appointment not found',
            ];
        }

        if (!$this->validation->setRules($this->getUpdateValidationRules())->run($payload)) {
            return $this->validationFailure($this->validation->getErrors());
        }

        $normalizedPhone = $this->phoneNumberService->normalize(
            $payload['customer_phone'] ?? null,
            $payload['customer_phone_country_code'] ?? null
        );

        if ($normalizedPhone === null) {
            return $this->validationFailure([
                'customer_phone' => 'Please enter a valid phone number for the selected country code.',
            ]);
        }

        $normalizedStart = $this->appointmentDateTimeNormalizer->normalizeDateAndTime(
            (string) ($payload['appointment_date'] ?? ''),
            (string) ($payload['appointment_time'] ?? ''),
            $clientTimezone
        );

        if (!$normalizedStart['success']) {
            return $this->errorResult(422, $normalizedStart['message'] ?? 'Invalid appointment date/time input.');
        }

        $customConfig = $this->bookingSettingsService->getCustomFieldConfiguration();

        try {
            [$customFieldValues, $customFieldClearFlags] = $this->extractCustomFieldValuesAndClear($payload, $customConfig);
        } catch (\InvalidArgumentException $e) {
            return $this->validationFailure([
                'custom_fields' => $e->getMessage(),
            ]);
        }

        $startTimeStored = $normalizedStart['utc'] ?? '';
        $db = Database::connect();
        $db->transStart();

        try {
            $customerData = $this->appointmentFormSubmissionService->buildCustomerUpdateData($payload);
            $customerData['phone'] = $normalizedPhone;
            $customerData['phone_country_code'] = $payload['customer_phone_country_code'] ?? null;
            $customerData['customer_id'] = (int) ($existingAppointment['customer_id'] ?? 0);
            $customerUpsert = $this->customerService->upsertCustomer($customerData);
        } catch (\InvalidArgumentException $e) {
            $db->transRollback();
            return $this->validationFailure([
                'customer_phone' => 'Please enter a valid phone number in E.164 format.',
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->errorResult(422, 'Unable to save customer details.', [
                'customer' => 'update_failed',
            ]);
        }

        $appointmentData = $this->appointmentFormSubmissionService->buildUpdateAppointmentData($payload, $normalizedStart);
        $appointmentData['booking_channel'] = 'admin';
        $appointmentData['customer_id'] = (int) ($customerUpsert['id'] ?? $existingAppointment['customer_id']);
        $status = (string) ($payload['status'] ?? '');
        $timeChanged = $startTimeStored !== (string) ($existingAppointment['start_at'] ?? '');
        $event = $timeChanged
            ? 'appointment_rescheduled'
            : AppointmentStatus::notificationEvent($status, '');

        $result = $this->appointmentBookingService->updateAppointment(
            (int) $existingAppointment['id'],
            $appointmentData,
            'UTC',
            $event,
            ['email', 'whatsapp']
        );

        if (($result['success'] ?? false) !== true) {
            $db->transRollback();
            return $this->normalizeBookingResult($result, false);
        }

        $this->customerCustomFieldService->mergeForCustomer(
            (int) ($customerUpsert['id'] ?? $existingAppointment['customer_id'] ?? 0),
            $customFieldValues,
            $customFieldClearFlags
        );

        $db->transComplete();
        if (!$db->transStatus()) {
            return $this->errorResult(422, 'Unable to save appointment changes.', [
                'appointment' => 'update_failed',
            ]);
        }

        try {
            $this->appointmentNotificationService->resetReminderSentIfTimeChanged(
                (int) $existingAppointment['id'],
                (string) ($existingAppointment['start_at'] ?? ''),
                (string) $startTimeStored
            );
        } catch (\Throwable $e) {
            log_message('error', '[AppointmentFormMutationService] Failed resetting reminder flag: {msg}', ['msg' => $e->getMessage()]);
        }

        return $this->normalizeBookingResult($result, false);
    }

    private function validationFailure(array $errors): array
    {
        return [
            'success' => false,
            'statusCode' => 422,
            'message' => 'Validation failed',
            'errors' => $errors,
            'flashType' => 'errors',
        ];
    }

    private function errorResult(int $statusCode, string $message, array $errors = [], array $conflicts = []): array
    {
        return [
            'success' => false,
            'statusCode' => $statusCode,
            'message' => $message,
            'errors' => $errors,
            'conflicts' => $conflicts,
            'flashType' => 'error',
        ];
    }

    private function normalizeBookingResult(array $result, bool $includeAppointmentId): array
    {
        if (($result['success'] ?? false) !== true) {
            $conflicts = $result['conflicts'] ?? [];

            return $this->errorResult(
                $conflicts !== [] ? 409 : 400,
                $result['message'] ?? 'Mutation failed',
                $result['errors'] ?? [],
                $conflicts
            );
        }

        $normalized = [
            'success' => true,
            'statusCode' => 200,
            'message' => $result['message'] ?? ($includeAppointmentId ? 'Appointment booked successfully!' : 'Appointment updated successfully!'),
            'redirect' => base_url('appointments'),
            'flashType' => 'success',
        ];

        if ($includeAppointmentId && isset($result['appointmentId'])) {
            $normalized['appointmentId'] = $result['appointmentId'];
        }

        return $normalized;
    }

    /**
    * Single-pass extraction: non-empty values go into $values; non-sensitive
    * fields present in the payload but submitted empty are treated as clears.
    * Sensitive blank inputs remain a no-op so "leave blank to keep" works.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function extractCustomFieldValuesAndClear(array $payload, array $customConfig): array
    {
        $values = [];
        $clearFlags = [];

        foreach ($customConfig as $fieldKey => $fieldMeta) {
            if (!array_key_exists($fieldKey, $payload)) {
                continue;
            }

            $value = $this->sanitizeCustomFieldValue($payload[$fieldKey], (string) ($fieldMeta['type'] ?? 'text'));
            $isSensitiveField = !empty($fieldMeta['is_sensitive']);

            if ($value === '') {
                if (!$isSensitiveField) {
                    $clearFlags[$fieldKey] = '1';
                }
                continue;
            }

            $values[$fieldKey] = $value;
        }

        // Also honour explicit clear__ checkboxes still present in the payload.
        foreach ($customConfig as $fieldKey => $_fieldMeta) {
            $clearKey = 'clear__' . $fieldKey;
            if (!array_key_exists($clearKey, $payload)) {
                continue;
            }
            $raw = strtolower(trim((string) $payload[$clearKey]));
            if (in_array($raw, ['1', 'true', 'yes', 'on'], true)) {
                unset($values[$fieldKey]);
                $clearFlags[$fieldKey] = '1';
            }
        }

        return [$values, $clearFlags];
    }

    /**
     * @deprecated Use extractCustomFieldValuesAndClear instead.
     */
    private function extractCustomFieldValues(array $payload, array $customConfig): array
    {
        return $this->extractCustomFieldValuesAndClear($payload, $customConfig)[0];
    }

    private function sanitizeCustomFieldValue($value, string $type): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if ($type === 'checkbox') {
            return in_array(strtolower($text), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        return substr(strip_tags($text), 0, 255);
    }

    private function getStoreValidationRules(bool $hasExistingCustomer): array
    {
        $rules = [
            'provider_id' => 'required|is_natural_no_zero',
            'service_id' => 'required|is_natural_no_zero',
            'appointment_date' => 'required|valid_date',
            'appointment_time' => 'required',
            'notes' => 'permit_empty|max_length[1000]',
        ];

        if ($hasExistingCustomer) {
            $rules['customer_id'] = 'required|is_natural_no_zero';
            return $rules;
        }

        return array_merge($rules, [
            'customer_first_name' => "required|min_length[2]|max_length[120]|regex_match[/^[\\p{L}\\p{M}'\\-\\. ]+$/u]",
            'customer_last_name' => "permit_empty|max_length[160]|regex_match[/^[\\p{L}\\p{M}'\\-\\. ]+$/u]",
            'customer_email' => 'required|valid_email|max_length[255]',
            'customer_phone' => 'required|min_length[10]|max_length[32]',
            'customer_address' => 'permit_empty|max_length[255]',
        ]);
    }

    private function getUpdateValidationRules(): array
    {
        return [
            'provider_id' => 'required|is_natural_no_zero',
            'service_id' => 'required|is_natural_no_zero',
            'appointment_date' => 'required|valid_date',
            'appointment_time' => 'required',
            'status' => AppointmentStatus::VALIDATION_RULE,
            'customer_first_name' => "required|min_length[2]|max_length[120]|regex_match[/^[\\p{L}\\p{M}'\\-\\. ]+$/u]",
            'customer_last_name' => "permit_empty|max_length[160]|regex_match[/^[\\p{L}\\p{M}'\\-\\. ]+$/u]",
            'customer_email' => 'required|valid_email|max_length[255]',
            'customer_phone' => 'required|min_length[10]|max_length[32]',
            'customer_address' => 'permit_empty|max_length[255]',
            'notes' => 'permit_empty|max_length[1000]',
        ];
    }
}