<?php

namespace App\Services\Appointment;

use App\Models\AppointmentModel;
use App\Services\AppointmentEventService;
use App\Services\AppointmentNotificationService;
use App\Services\NotificationCatalog;
use App\Services\NotificationSmsService;
use App\Services\NotificationTemplateService;

class AppointmentManualNotificationService
{
    private const VALID_CHANNELS = ['email', 'sms', 'whatsapp'];

    private AppointmentModel $appointmentModel;
    private AppointmentQueryService $appointmentQueryService;
    private AppointmentNotificationService $appointmentNotificationService;
    private NotificationSmsService $notificationSmsService;
    private NotificationTemplateService $notificationTemplateService;
    private AppointmentEventService $appointmentEventService;

    public function __construct(
        ?AppointmentModel $appointmentModel = null,
        ?AppointmentQueryService $appointmentQueryService = null,
        ?AppointmentNotificationService $appointmentNotificationService = null,
        ?NotificationSmsService $notificationSmsService = null,
        ?NotificationTemplateService $notificationTemplateService = null,
        ?AppointmentEventService $appointmentEventService = null,
    ) {
        $this->appointmentModel = $appointmentModel ?? new AppointmentModel();
        $this->appointmentQueryService = $appointmentQueryService ?? new AppointmentQueryService($this->appointmentModel);
        $this->appointmentNotificationService = $appointmentNotificationService ?? new AppointmentNotificationService();
        $this->notificationSmsService = $notificationSmsService ?? new NotificationSmsService();
        $this->notificationTemplateService = $notificationTemplateService ?? new NotificationTemplateService();
        $this->appointmentEventService = $appointmentEventService ?? new AppointmentEventService();
    }

    public function send(int $appointmentId, string $channel, ?string $eventType = null): array
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

        $normalizedChannel = strtolower(trim($channel));
        if (!in_array($normalizedChannel, self::VALID_CHANNELS, true)) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => 'Invalid channel. Must be one of: email, sms, whatsapp',
            ];
        }

        $resolvedEventType = trim((string) $eventType);
        if ($resolvedEventType === '') {
            $status = $appointment['status'] ?? AppointmentStatus::PENDING;
            $resolvedEventType = AppointmentStatus::notificationEvent($status, 'appointment_confirmed');
        }

        return match ($normalizedChannel) {
            'email' => $this->sendEmail($appointmentId, $resolvedEventType),
            'sms' => $this->sendSms($appointmentId, $resolvedEventType),
            'whatsapp' => $this->queueWhatsApp($appointmentId, $resolvedEventType),
        };
    }

    private function sendEmail(int $appointmentId, string $eventType): array
    {
        $sent = $this->appointmentNotificationService->sendEventEmail(
            $eventType,
            $appointmentId,
            NotificationCatalog::BUSINESS_ID_DEFAULT
        );

        if (!$sent) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => 'Email not sent. Check if email is enabled for this event type and SMTP is configured.',
            ];
        }

        return [
            'success' => true,
            'data' => [
                'ok' => true,
                'channel' => 'email',
                'event_type' => $eventType,
                'message' => 'Email notification sent successfully',
            ],
        ];
    }

    private function sendSms(int $appointmentId, string $eventType): array
    {
        $appointment = $this->appointmentQueryService->getDetailById($appointmentId);
        if (!$appointment || empty($appointment['customer_phone'])) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => 'SMS not sent. Customer phone number not available.',
            ];
        }

        $templateData = [
            'customer_name' => $appointment['customer_name'] ?? '',
            'customer_email' => $appointment['customer_email'] ?? '',
            'customer_phone' => $appointment['customer_phone'] ?? '',
            'service_name' => $appointment['service_name'] ?? '',
            'service_duration' => $appointment['service_duration'] ?? '',
            'provider_name' => $appointment['provider_name'] ?? '',
            'start_datetime' => $appointment['start_at'] ?? null,
            'location_name' => $appointment['location_name'] ?? '',
            'location_address' => $appointment['location_address'] ?? '',
            'location_contact' => $appointment['location_contact'] ?? '',
        ];

        $rendered = $this->notificationTemplateService->render($eventType, 'sms', $templateData);
        $message = trim((string) ($rendered['body'] ?? ''));
        if ($message === '') {
            $message = 'Your appointment has been updated.';
        }

        $result = $this->notificationSmsService->sendSms(
            NotificationCatalog::BUSINESS_ID_DEFAULT,
            (string) $appointment['customer_phone'],
            $message
        );

        if (!($result['ok'] ?? false)) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' => 'SMS not sent: ' . ($result['error'] ?? 'Unknown error'),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'ok' => true,
                'channel' => 'sms',
                'event_type' => $eventType,
                'message' => 'SMS notification sent successfully',
            ],
        ];
    }

    private function queueWhatsApp(int $appointmentId, string $eventType): array
    {
        $this->appointmentEventService->dispatch(
            $eventType,
            $appointmentId,
            ['whatsapp'],
            NotificationCatalog::BUSINESS_ID_DEFAULT
        );

        return [
            'success' => true,
            'data' => [
                'ok' => true,
                'channel' => 'whatsapp',
                'event_type' => $eventType,
                'message' => 'WhatsApp notification queued for delivery',
            ],
        ];
    }
}