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
 * Provides a simple interface for sending appointment notifications across
 * multiple channels (email, SMS, WhatsApp) based on business notification rules.
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
 *   Process and send all due reminder emails
 *   Returns: ['scanned' => int, 'sent' => int, 'skipped' => int]
 * 
 * sendEventSms($eventType, $appointmentId, $businessId)
 *   Send SMS notification for an appointment event
 * 
 * sendDueReminderSms($businessId)
 *   Process and send all due SMS reminders
 * 
 * sendEventWhatsApp($eventType, $appointmentId, $businessId)
 *   Send WhatsApp notification for an appointment event
 * 
 * NOTIFICATION FLOW:
 * -----------------------------------------------------------------------------
 * 1. Check if channel enabled for event (business rules)
 * 2. Get channel integration config
 * 3. Load appointment context (customer, service, provider)
 * 4. Build message from template
 * 5. Send via appropriate channel service
 * 6. Update appointment flags (e.g., reminder_sent)
 * 
 * REMINDER PROCESSING:
 * -----------------------------------------------------------------------------
 * Called by scheduled commands to process reminders:
 * - Gets offset minutes from business rules
 * - Finds appointments due for reminder
 * - Sends notifications and marks as sent
 * 
 * @see         app/Services/NotificationEmailService.php
 * @see         app/Services/NotificationSmsService.php
 * @see         app/Commands/SendAppointmentReminders.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\BusinessNotificationRuleModel;

class AppointmentNotificationService
{
    public const BUSINESS_ID_DEFAULT = NotificationPhase1::BUSINESS_ID_DEFAULT;

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
     * Sends due reminder emails and marks reminder_sent = 1.
     *
     * Returns stats: ['scanned' => int, 'sent' => int, 'skipped' => int]
     */
    public function sendDueReminderEmails(int $businessId = self::BUSINESS_ID_DEFAULT): array
    {
        $stats = ['scanned' => 0, 'sent' => 0, 'skipped' => 0];

        if (!$this->isEmailEnabledForEvent($businessId, 'appointment_reminder')) {
            return $stats;
        }

        $emailSvc = new NotificationEmailService();
        $integration = $emailSvc->getPublicIntegration($businessId);
        if (empty($integration['is_active'])) {
            return $stats;
        }

        $offsetMinutes = $this->getReminderOffsetMinutes($businessId);
        if ($offsetMinutes === null) {
            return $stats;
        }

        $now = new \DateTimeImmutable('now');
        $windowEnd = $now->modify('+30 days');

        $model = new AppointmentModel();
        $builder = $model->builder();

        // Avoid breaking if reminder_sent column doesn't exist.
        $db = \Config\Database::connect();
        $fields = $db->getFieldNames($model->table);
        $hasReminderSent = in_array('reminder_sent', $fields, true);

        $builder->select('xs_appointments.id');
        $builder->whereIn('xs_appointments.status', ['pending', 'confirmed']);
        $builder->where('xs_appointments.start_at >', $now->format('Y-m-d H:i:s'));
        $builder->where('xs_appointments.start_at <=', $windowEnd->format('Y-m-d H:i:s'));
        if ($hasReminderSent) {
            $builder->where('xs_appointments.reminder_sent', 0);
        }

        $ids = array_map(static fn($row) => (int) ($row['id'] ?? 0), $builder->get()->getResultArray());

        foreach ($ids as $appointmentId) {
            if ($appointmentId <= 0) {
                continue;
            }

            $appt = $this->getAppointmentContext($appointmentId);
            $stats['scanned']++;

            if (!$appt || empty($appt['start_at'])) {
                $stats['skipped']++;
                continue;
            }

            try {
                $start = new \DateTimeImmutable((string) $appt['start_at']);
            } catch (\Throwable $e) {
                $stats['skipped']++;
                continue;
            }

            $dueAt = $start->modify('-' . $offsetMinutes . ' minutes');
            if ($now < $dueAt) {
                $stats['skipped']++;
                continue;
            }

            $to = (string) ($appt['customer_email'] ?? '');
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $stats['skipped']++;
                continue;
            }

            [$subject, $body] = $this->buildEmailMessage('appointment_reminder', $appt);
            $send = $emailSvc->sendEmail($businessId, $to, $subject, $body);
            if (!($send['ok'] ?? false)) {
                $stats['skipped']++;
                continue;
            }

            if ($hasReminderSent) {
                $model->update($appointmentId, ['reminder_sent' => 1]);
            }

            $stats['sent']++;
        }

        return $stats;
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

    private function getReminderOffsetMinutes(int $businessId): ?int
    {
        helper('notification');
        return notification_get_reminder_offset_minutes($businessId, 'email');
    }

    /**
     * Fetches appointment details with customer/service/provider fields.
     */
    private function getAppointmentContext(int $appointmentId): ?array
    {
        $model = new AppointmentModel();
        $builder = $model->builder();

        $row = $builder
            ->select('xs_appointments.*, c.first_name as customer_first_name, c.last_name as customer_last_name, c.email as customer_email, s.name as service_name, u.name as provider_name')
            ->join('xs_customers c', 'c.id = xs_appointments.customer_id', 'left')
            ->join('xs_services s', 's.id = xs_appointments.service_id', 'left')
            ->join('xs_users u', 'u.id = xs_appointments.provider_id', 'left')
            ->where('xs_appointments.id', $appointmentId)
            ->get()
            ->getFirstRow('array');

        return is_array($row) ? $row : null;
    }

    /**
     * Phase 2: placeholder content only.
     * Later phases should use message_templates + localization.
     *
     * @param array<string,mixed> $appt
     * @return array{0:string,1:string}
     */
    private function buildEmailMessage(string $eventType, array $appt): array
    {
        $customerName = trim((string) ($appt['customer_first_name'] ?? '') . ' ' . (string) ($appt['customer_last_name'] ?? ''));
        if ($customerName === '') {
            $customerName = 'Customer';
        }

        $providerName = (string) ($appt['provider_name'] ?? 'Provider');
        $serviceName = (string) ($appt['service_name'] ?? 'Service');
        // DB stores UTC — convert to local time for customer-facing notification
        $start = !empty($appt['start_at'])
            ? TimezoneService::toDisplay($appt['start_at'])
            : '';

        $subjectMap = [
            'appointment_confirmed' => 'Appointment Confirmed',
            'appointment_reminder' => 'Appointment Reminder',
            'appointment_cancelled' => 'Appointment Cancelled',
        ];
        $subject = $subjectMap[$eventType] ?? 'Appointment Update';

        $lines = [];
        $lines[] = "Hi {$customerName},";
        $lines[] = '';

        if ($eventType === 'appointment_cancelled') {
            $lines[] = "Your appointment has been cancelled.";
        } elseif ($eventType === 'appointment_reminder') {
            $lines[] = "This is a reminder for your upcoming appointment.";
        } else {
            $lines[] = "Your appointment is confirmed.";
        }

        $lines[] = '';
        $lines[] = "Provider: {$providerName}";
        $lines[] = "Service: {$serviceName}";
        $lines[] = "When: {$start}";
        $lines[] = '';
        $lines[] = '— WebSchedulr';

        return [$subject, implode("\n", $lines)];
    }
}
