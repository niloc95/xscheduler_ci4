<?php

namespace App\Services\Appointment;

use App\Models\AppointmentModel;
use App\Services\AppointmentBookingService;
use App\Services\TimezoneService;

class AppointmentMutationService
{
    private AppointmentModel $appointmentModel;
    private AppointmentBookingService $bookingService;
    private AppointmentDateTimeNormalizer $dateTimeNormalizer;

    public function __construct(
        ?AppointmentModel $appointmentModel = null,
        ?AppointmentBookingService $bookingService = null,
        ?AppointmentDateTimeNormalizer $dateTimeNormalizer = null,
    ) {
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
        $this->bookingService = $bookingService ?? new AppointmentBookingService();
        $this->dateTimeNormalizer = $dateTimeNormalizer ?? new AppointmentDateTimeNormalizer();
    }

    public function createFromApiPayload(array $payload, ?string $inputTimezone = null): array
    {
        $normalizedStart = $this->dateTimeNormalizer->normalizeDateAndTime(
            (string) ($payload['date'] ?? ''),
            (string) ($payload['start'] ?? ''),
            $inputTimezone
        );

        if (!$normalizedStart['success']) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => $normalizedStart['message'] ?? 'Invalid appointment datetime',
            ];
        }

        $bookingPayload = [
            'provider_id' => (int) ($payload['providerId'] ?? $payload['provider_id'] ?? 0),
            'service_id' => (int) ($payload['serviceId'] ?? $payload['service_id'] ?? 0),
            'appointment_date' => $normalizedStart['app_date'],
            'appointment_time' => $normalizedStart['app_time'],
            'customer_first_name' => $payload['name'] ?? '',
            'customer_last_name' => '',
            'customer_email' => $payload['email'] ?? '',
            'customer_phone' => $payload['phone'] ?? '',
            'notes' => $payload['notes'] ?? null,
            'notification_types' => ['email', 'whatsapp'],
            'booking_channel' => 'api',
        ];

        if (!empty($payload['location_id'])) {
            $bookingPayload['location_id'] = (int) $payload['location_id'];
        }

        $result = $this->bookingService->createAppointment($bookingPayload, 'UTC');
        if (!$result['success']) {
            return [
                'success' => false,
                'statusCode' => 409,
                'message' => $result['message'] ?? 'Unable to create appointment',
                'code' => 'CONFLICT',
                'errors' => $result['errors'] ?? [],
            ];
        }

        return [
            'success' => true,
            'data' => $result,
            'message' => 'Appointment created successfully',
        ];
    }

    public function updateFromApiPayload(int $appointmentId, array $payload, ?string $inputTimezone = null): array
    {
        $appointment = $this->appointmentModel->find($appointmentId);
        if (!$appointment) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'Appointment not found',
                'errors' => ['appointment_id' => $appointmentId],
            ];
        }

        $update = [];
        if (!empty($payload['status'])) {
            $normalizedStatus = AppointmentStatus::normalize((string) $payload['status']);
            if ($normalizedStatus === null) {
                return [
                    'success' => false,
                    'statusCode' => 400,
                    'message' => 'Invalid status',
                    'errors' => ['valid_statuses' => AppointmentStatus::ALL],
                ];
            }
            $update['status'] = $normalizedStatus;
        }

        if (array_key_exists('notes', $payload)) {
            $update['notes'] = (string) ($payload['notes'] ?? '');
        }
        if (!empty($payload['providerId']) || !empty($payload['provider_id'])) {
            $update['provider_id'] = (int) ($payload['providerId'] ?? $payload['provider_id']);
        }
        if (!empty($payload['serviceId']) || !empty($payload['service_id'])) {
            $update['service_id'] = (int) ($payload['serviceId'] ?? $payload['service_id']);
        }
        if (array_key_exists('location_id', $payload)) {
            $update['location_id'] = $payload['location_id'] === null || $payload['location_id'] === ''
                ? null
                : (int) $payload['location_id'];
        }

        if (!empty($payload['start'])) {
            if (!empty($payload['date']) && preg_match('/^\d{2}:\d{2}$/', (string) $payload['start'])) {
                $normalizedStart = $this->dateTimeNormalizer->normalizeDateAndTime(
                    (string) $payload['date'],
                    (string) $payload['start'],
                    $inputTimezone
                );
            } else {
                $normalizedStart = $this->dateTimeNormalizer->normalizeDateTimeString((string) $payload['start'], $inputTimezone);
            }

            if (!$normalizedStart['success']) {
                return [
                    'success' => false,
                    'statusCode' => 400,
                    'message' => $normalizedStart['message'] ?? 'Invalid start datetime',
                ];
            }

            $update['appointment_date'] = $normalizedStart['app_date'];
            $update['appointment_time'] = $normalizedStart['app_time'];
        }

        if (!empty($payload['end'])) {
            $normalizedEnd = $this->dateTimeNormalizer->normalizeDateTimeString((string) $payload['end'], $inputTimezone);
            if (!$normalizedEnd['success']) {
                return [
                    'success' => false,
                    'statusCode' => 400,
                    'message' => $normalizedEnd['message'] ?? 'Invalid end datetime',
                ];
            }
        }

        if (empty($update)) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => 'No updatable fields provided',
            ];
        }

        $notificationEvent = (!empty($update['appointment_date']) || !empty($update['appointment_time']))
            ? 'appointment_rescheduled'
            : AppointmentStatus::notificationEvent($update['status'] ?? null, '');

        $result = $this->bookingService->updateAppointment(
            $appointmentId,
            array_merge($update, ['booking_channel' => 'api']),
            TimezoneService::businessTimezone(),
            $notificationEvent,
            ['email', 'whatsapp']
        );

        if (!$result['success']) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => $result['message'] ?? 'Update failed',
                'errors' => $result['errors'] ?? [],
            ];
        }

        return [
            'success' => true,
            'data' => ['ok' => true],
            'message' => 'Appointment updated successfully',
        ];
    }

    public function updateStatus(int $appointmentId, string $newStatus): array
    {
        $appointment = $this->appointmentModel->find($appointmentId);
        if (!$appointment) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'Appointment not found',
                'errors' => ['appointment_id' => $appointmentId],
            ];
        }

        $normalizedStatus = AppointmentStatus::normalize($newStatus);
        if ($normalizedStatus === null) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => 'Invalid status',
                'errors' => ['valid_statuses' => AppointmentStatus::ALL],
            ];
        }

        $notificationEvent = AppointmentStatus::notificationEvent($normalizedStatus, '');
        $result = $this->bookingService->updateAppointment(
            $appointmentId,
            ['status' => $normalizedStatus, 'booking_channel' => 'api'],
            TimezoneService::businessTimezone(),
            $notificationEvent,
            ['email', 'whatsapp']
        );

        if (!$result['success']) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => $result['message'] ?? 'Failed to update appointment status',
                'errors' => $result['errors'] ?? [],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $appointmentId,
                'status' => $normalizedStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'message' => 'Appointment status updated successfully',
        ];
    }

    public function updateNotes(int $appointmentId, string $notes): array
    {
        $appointment = $this->appointmentModel->find($appointmentId);
        if (!$appointment) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'Appointment not found',
                'errors' => ['appointment_id' => $appointmentId],
            ];
        }

        $result = $this->bookingService->updateAppointment(
            $appointmentId,
            ['notes' => $notes, 'booking_channel' => 'api'],
            TimezoneService::businessTimezone(),
            '',
            []
        );

        if (!$result['success']) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => $result['message'] ?? 'Failed to update appointment notes',
                'errors' => $result['errors'] ?? [],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $appointmentId,
                'notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'message' => 'Appointment notes updated successfully',
        ];
    }

    public function cancelAppointment(int $appointmentId): array
    {
        $appointment = $this->appointmentModel->find($appointmentId);
        if (!$appointment) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'Appointment not found',
                'errors' => ['appointment_id' => $appointmentId],
            ];
        }

        $result = $this->bookingService->updateAppointment(
            $appointmentId,
            ['status' => 'cancelled', 'booking_channel' => 'api'],
            TimezoneService::businessTimezone(),
            'appointment_cancelled',
            ['email', 'whatsapp']
        );

        if (!$result['success']) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => $result['message'] ?? 'Delete failed',
                'errors' => $result['errors'] ?? [],
            ];
        }

        return [
            'success' => true,
            'data' => ['ok' => true],
            'message' => 'Appointment cancelled successfully',
        ];
    }
}