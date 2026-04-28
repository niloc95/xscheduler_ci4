<?php

/**
 * =============================================================================
 * APPOINTMENT NOTIFICATION SERVICE
 * =============================================================================
 * 
 * @file        app/Services/AppointmentNotificationService.php
 * @description High-level service for sending appointment-related notifications.
 *              Orchestrates email, SMS, and WhatsApp notifications for events.
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides a simple interface for sending appointment notifications.
 * Immediate/manual sends still route through dedicated channel services,
 * while reminder processing now delegates to the canonical queue pipeline.
 * 
 * NOTIFICATION EVENTS:
 * -----------------------------------------------------------------------------
 * - appointment_confirmed  : New appointment booked
 * - appointment_cancelled  : Appointment cancelled
 * - appointment_rescheduled: Appointment time changed
 * - appointment_reminder   : Upcoming appointment reminder
 * - appointment_no_show    : Customer didn't show up
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * sendEventEmail($eventType, $appointmentId, $businessId)
 *   Send email notification for an appointment event
 * 
 * sendDueReminderEmails($businessId)
 *   Legacy compatibility shim for reminder processing.
 *   Delegates to the canonical queue enqueue + dispatch flow.
 *   Returns: ['scanned' => int, 'sent' => int, 'skipped' => int]
 * 
 * NOTIFICATION FLOW:
 * -----------------------------------------------------------------------------
 * 1. Check if channel enabled for event (business rules)
 * 2. Get channel integration config
 * 3. Load appointment context (customer, service, provider)
 * 4. Build message from template
 * 5. Send via appropriate channel service
 * 6. Let the owning queue/dispatcher pipeline update compatibility state when applicable
 * 
 * REMINDER PROCESSING:
 * -----------------------------------------------------------------------------
 * Legacy reminder entry points are retained only as compatibility shims.
 * Canonical reminder behavior is owned by NotificationQueueService and
 * NotificationQueueDispatcher.
 * 
 * @see         app/Services/NotificationEmailService.php
 * @see         app/Services/NotificationSmsService.php
 * @see         app/Commands/DispatchNotificationQueue.php
 * @package     App\Services
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\BusinessNotificationRuleModel;
use App\Services\NotificationCatalog;

class AppointmentNotificationService
{
    public const BUSINESS_ID_DEFAULT = NotificationCatalog::BUSINESS_ID_DEFAULT;

    public function sendEventEmail(string $eventType, int $appointmentId, int $businessId = self::BUSINESS_ID_DEFAULT): bool
    {
        if (!$this->isEmailEnabledForEvent($businessId, $eventType)) {
            return false;
        }

        $emailSvc = new NotificationEmailService();
        $integration = $emailSvc->getPublicIntegration($businessId);
        if (empty($integration['is_active'])) {
            return false;
        }

        $appt = $this->getAppointmentContext($appointmentId);
        if (!$appt) {
            return false;
        }

        $to = (string) ($appt['customer_email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        [$subject, $body] = $this->buildEmailMessage($eventType, $appt);

        $res = $emailSvc->sendEmail($businessId, $to, $subject, $body);
        return (bool) ($res['ok'] ?? false);
    }

    /**
     * Legacy compatibility shim for reminder processing.
     *
     * Returns stats: ['scanned' => int, 'sent' => int, 'skipped' => int]
     */
    public function sendDueReminderEmails(int $businessId = self::BUSINESS_ID_DEFAULT): array
    {
        $enqueueStats = $this->getNotificationQueueService()->enqueueDueReminders($businessId);

        if ((int) ($enqueueStats['enqueued'] ?? 0) <= 0) {
            return [
                'scanned' => (int) ($enqueueStats['scanned'] ?? 0),
                'sent' => 0,
                'skipped' => (int) ($enqueueStats['skipped'] ?? 0),
            ];
        }

        $dispatchStats = $this->getNotificationQueueDispatcher()->dispatch($businessId, 100, 'appointment_reminder');

        return [
            'scanned' => (int) ($enqueueStats['scanned'] ?? 0),
            'sent' => (int) ($dispatchStats['sent'] ?? 0),
            'skipped' => (int) ($enqueueStats['skipped'] ?? 0)
                + (int) ($dispatchStats['skipped'] ?? 0)
                + (int) ($dispatchStats['cancelled'] ?? 0)
                + (int) ($dispatchStats['failed'] ?? 0),
        ];
    }

    public function resetReminderSentIfTimeChanged(int $appointmentId, ?string $oldStartTime, ?string $newStartTime): void
    {
        $oldStartTime = $oldStartTime ? trim($oldStartTime) : '';
        $newStartTime = $newStartTime ? trim($newStartTime) : '';

        if ($oldStartTime === '' || $newStartTime === '' || $oldStartTime === $newStartTime) {
            return;
        }

        $model = new AppointmentModel();
        $db = \Config\Database::connect();
        $fields = $db->getFieldNames($model->table);
        $hasReminderSent = in_array('reminder_sent', $fields, true);
        if (!$hasReminderSent) {
            return;
        }

        $model->update($appointmentId, ['reminder_sent' => 0]);
    }

    private function isEmailEnabledForEvent(int $businessId, string $eventType): bool
    {
        $model = new BusinessNotificationRuleModel();
        $row = $model
            ->where('business_id', $businessId)
            ->where('event_type', $eventType)
            ->where('channel', 'email')
            ->first();

        return (int) ($row['is_enabled'] ?? 0) === 1;
    }

    protected function getNotificationQueueService(): NotificationQueueService
    {
        return new NotificationQueueService();
    }

    protected function getNotificationQueueDispatcher(): NotificationQueueDispatcher
    {
        return new NotificationQueueDispatcher();
    }

    /**
     * Fetches appointment details with customer/service/provider fields.
     */
    private function getAppointmentContext(int $appointmentId): ?array
    {
        $model = new AppointmentModel();
        $builder = $model->builder();

        $row = $builder
            ->select('xs_appointments.*, c.first_name as customer_first_name, c.last_name as customer_last_name, c.email as customer_email, c.phone as customer_phone, s.name as service_name, s.duration_min as service_duration, u.name as provider_name', false)
            ->join('xs_customers c', 'c.id = xs_appointments.customer_id', 'left')
            ->join('xs_services s', 's.id = xs_appointments.service_id', 'left')
            ->join('xs_users u', 'u.id = xs_appointments.provider_id', 'left')
            ->where('xs_appointments.id', $appointmentId)
            ->get()
            ->getFirstRow('array');

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $appt
     * @return array{0:string,1:string}
     */
    private function buildEmailMessage(string $eventType, array $appt): array
    {
        $bookingLinkService = new BookingLinkService();
        $templateSvc = new NotificationTemplateService();

        $customerName = trim((string) ($appt['customer_first_name'] ?? '') . ' ' . (string) ($appt['customer_last_name'] ?? ''));
        if ($customerName === '') {
            $customerName = trim((string) ($appt['customer_name'] ?? ''));
        }
        if ($customerName === '') {
            $customerName = 'Customer';
        }

        $displayTimezone = TimezoneService::businessTimezone();
        $appointmentRef = (string) ($appt['hash'] ?? '');
        if ($appointmentRef === '') {
            $appointmentRef = (string) ($appt['id'] ?? '');
        }

        $bookedTimestamp = '';
        if (!empty($appt['created_at'])) {
            $bookedTimestamp = TimezoneService::toDisplay((string) $appt['created_at'], $displayTimezone);
        }

        $bookedVia = (string) ($appt['booking_channel'] ?? 'web');
        if ($bookedVia === 'public') {
            $bookedVia = 'Web';
        } elseif ($bookedVia === 'internal') {
            $bookedVia = 'Admin';
        }

        $templateData = [
            'appointment_id' => (int) ($appt['id'] ?? 0),
            'booking_id' => (string) ($appt['id'] ?? ''),
            'customer_name' => $customerName,
            'customer_email' => (string) ($appt['customer_email'] ?? ''),
            'customer_phone' => (string) ($appt['customer_phone'] ?? ''),
            'service_name' => (string) ($appt['service_name'] ?? 'Service'),
            'service_duration' => (string) ($appt['service_duration'] ?? ''),
            'provider_name' => (string) ($appt['provider_name'] ?? 'Provider'),
            'start_datetime' => !empty($appt['start_at'])
                ? TimezoneService::toDisplay((string) $appt['start_at'], $displayTimezone)
                : '',
            'display_timezone' => $displayTimezone,
            'reschedule_link' => $bookingLinkService->manageReferenceUrl((string) ($appt['hash'] ?? ''), (string) ($appt['public_token'] ?? '')),
            'booking_url' => $bookingLinkService->bookingHomeUrl(),
            'appointment_hash' => (string) ($appt['hash'] ?? ''),
            'internal_view_link' => $appointmentRef !== '' ? base_url('appointments?open=' . rawurlencode($appointmentRef)) : base_url('appointments'),
            'internal_edit_link' => $appointmentRef !== '' ? base_url('appointments/edit/' . $appointmentRef) : base_url('appointments'),
            'internal_contact_link' => !empty($appt['customer_email']) ? ('mailto:' . (string) $appt['customer_email']) : '',
            'booked_via' => $bookedVia,
            'booked_timestamp' => $bookedTimestamp,
            'location_name' => (string) ($appt['location_name'] ?? ''),
            'location_address' => (string) ($appt['location_address'] ?? ''),
            'location_contact' => (string) ($appt['location_contact'] ?? ''),
        ];

        $rendered = $templateSvc->render($eventType, 'email', $templateData, 'customer');

        $subject = trim((string) ($rendered['subject'] ?? ''));
        $body = trim((string) ($rendered['body'] ?? ''));

        if ($subject === '') {
            $subject = 'Appointment Update';
        }
        if ($body === '') {
            $body = "Your appointment has been updated.\n\n— WebScheduler";
        }

        return [$subject, $body];
    }
}
