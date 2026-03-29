<?php

namespace App\Services\Appointment;

use App\Models\AppointmentModel;
use App\Models\CustomerModel;
use App\Services\AppointmentBookingService;
use App\Services\AppointmentNotificationService;
use CodeIgniter\Validation\ValidationInterface;

class AppointmentFormMutationService
{
    private ValidationInterface $validation;
    private AppointmentDateTimeNormalizer $appointmentDateTimeNormalizer;
    private AppointmentFormSubmissionService $appointmentFormSubmissionService;
    private AppointmentBookingService $appointmentBookingService;
    private CustomerModel $customerModel;
    private AppointmentModel $appointmentModel;
    private AppointmentNotificationService $appointmentNotificationService;

    public function __construct(
        ?ValidationInterface $validation = null,
        ?AppointmentDateTimeNormalizer $appointmentDateTimeNormalizer = null,
        ?AppointmentFormSubmissionService $appointmentFormSubmissionService = null,
        ?AppointmentBookingService $appointmentBookingService = null,
        ?CustomerModel $customerModel = null,
        ?AppointmentModel $appointmentModel = null,
        ?AppointmentNotificationService $appointmentNotificationService = null,
    ) {
        $this->validation = $validation ?? \Config\Services::validation();
        $this->appointmentDateTimeNormalizer = $appointmentDateTimeNormalizer ?? new AppointmentDateTimeNormalizer();
        $this->appointmentFormSubmissionService = $appointmentFormSubmissionService ?? new AppointmentFormSubmissionService();
        $this->appointmentBookingService = $appointmentBookingService ?? new AppointmentBookingService();
        $this->customerModel = $customerModel ?? new CustomerModel();
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
        $this->appointmentNotificationService = $appointmentNotificationService ?? new AppointmentNotificationService();
    }

    public function createFromFormPayload(array $payload, string $clientTimezone): array
    {
        $customerId = $payload['customer_id'] ?? null;
        $rules = $this->getStoreValidationRules(!empty($customerId));

        if (!$this->validation->setRules($rules)->run($payload)) {
            return $this->validationFailure($this->validation->getErrors());
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

        $normalizedStart = $this->appointmentDateTimeNormalizer->normalizeDateAndTime(
            (string) ($payload['appointment_date'] ?? ''),
            (string) ($payload['appointment_time'] ?? ''),
            $clientTimezone
        );

        if (!$normalizedStart['success']) {
            return $this->errorResult(422, $normalizedStart['message'] ?? 'Invalid appointment date/time input.');
        }

        $startTimeStored = $normalizedStart['utc'] ?? '';
        $customerData = $this->appointmentFormSubmissionService->buildCustomerUpdateData($payload);
        $this->customerModel->update((int) $existingAppointment['customer_id'], $customerData);

        $appointmentData = $this->appointmentFormSubmissionService->buildUpdateAppointmentData($payload, $normalizedStart);
        $appointmentData['booking_channel'] = 'admin';
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

        if (($result['success'] ?? false) === true) {
            try {
                $this->appointmentNotificationService->resetReminderSentIfTimeChanged(
                    (int) $existingAppointment['id'],
                    (string) ($existingAppointment['start_at'] ?? ''),
                    (string) $startTimeStored
                );
            } catch (\Throwable $e) {
                log_message('error', '[AppointmentFormMutationService] Failed resetting reminder flag: {msg}', ['msg' => $e->getMessage()]);
            }
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
            'customer_first_name' => 'required|min_length[2]|max_length[120]',
            'customer_last_name' => 'permit_empty|max_length[160]',
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
            'customer_first_name' => 'required|min_length[2]|max_length[120]',
            'customer_last_name' => 'permit_empty|max_length[160]',
            'customer_email' => 'required|valid_email|max_length[255]',
            'customer_phone' => 'required|min_length[10]|max_length[32]',
            'customer_address' => 'permit_empty|max_length[255]',
            'notes' => 'permit_empty|max_length[1000]',
        ];
    }
}