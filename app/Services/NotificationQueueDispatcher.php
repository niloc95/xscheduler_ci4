<?php

/**
 * =============================================================================
 * NOTIFICATION QUEUE DISPATCHER
 * =============================================================================
 * 
 * @file        app/Services/NotificationQueueDispatcher.php
 * @description Processes queued notifications and dispatches them via the
 *              appropriate channel (email, SMS, WhatsApp).
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Background worker service that:
 * - Claims queued notifications for processing
 * - Validates appointment still exists
 * - Checks opt-out status
 * - Routes to correct channel service
 * - Handles retries on failure
 * - Logs delivery attempts
 * 
 * KEY METHODS:
 * -----------------------------------------------------------------------------
 * dispatch($businessId, $limit)
 *   Process queued notifications
 *   Returns: ['claimed'=>int,'sent'=>int,'failed'=>int,'cancelled'=>int,'skipped'=>int]
 * 
 * DISPATCH FLOW:
 * -----------------------------------------------------------------------------
 * 1. Claim batch of queued notifications (with lock token)
 * 2. For each notification:
 *    a. Validate appointment still exists
 *    b. Check if recipient opted out
 *    c. Build message from template
 *    d. Send via channel service
 *    e. Update queue status (sent/failed)
 *    f. Log delivery attempt
 * 3. Release stale locks (>15 min old)
 * 
 * LOCKING MECHANISM:
 * -----------------------------------------------------------------------------
 * Uses optimistic locking with random tokens:
 * - lock_token: Random hex string
 * - locked_at: Timestamp when claimed
 * Prevents duplicate processing in multi-worker scenarios.
 * Stale locks (>15 min) are auto-released.
 * 
 * RETRY HANDLING:
 * -----------------------------------------------------------------------------
 * - Max 3 attempts per notification
 * - Exponential backoff between retries
 * - Final failure marks as 'failed'
 * 
 * CHANNELS:
 * -----------------------------------------------------------------------------
 * - email    : Uses NotificationEmailService
 * - sms      : Uses NotificationSmsService
 * - whatsapp : Uses NotificationWhatsAppService
 * 
 * @see         app/Commands/DispatchNotificationQueue.php
 * @see         app/Models/NotificationQueueModel.php
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\BusinessIntegrationModel;
use App\Models\BusinessNotificationRuleModel;
use App\Models\NotificationQueueModel;
use App\Services\NotificationDeliveryLogService;
use App\Services\NotificationOptOutService;

class NotificationQueueDispatcher
{
    public const BUSINESS_ID_DEFAULT = NotificationPhase1::BUSINESS_ID_DEFAULT;

    /**
     * Dispatch queued notifications.
     * Returns stats: ['claimed'=>int,'sent'=>int,'failed'=>int,'cancelled'=>int,'skipped'=>int]
     */
    public function dispatch(int $businessId = self::BUSINESS_ID_DEFAULT, int $limit = 100): array
    {
        $stats = ['claimed' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'skipped' => 0];

        $limit = max(1, min(500, $limit));
        $lockToken = bin2hex(random_bytes(16));

        $model = new NotificationQueueModel();
        $now = new \DateTimeImmutable('now');
        $nowStr = $now->format('Y-m-d H:i:s');
        $staleBefore = $now->modify('-15 minutes')->format('Y-m-d H:i:s');

        $ids = $model
            ->select('id')
            ->where('business_id', $businessId)
            ->where('status', 'queued')
            ->groupStart()
                ->where('run_after IS NULL', null, false)
                ->orWhere('run_after <=', $nowStr)
            ->groupEnd()
            ->groupStart()
                ->where('lock_token IS NULL', null, false)
                ->orWhere('locked_at <', $staleBefore)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->findColumn('id');

        if (empty($ids)) {
            return $stats;
        }

        $claimedIds = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }

            $updated = $model
                ->where('id', $id)
                ->groupStart()
                    ->where('lock_token IS NULL', null, false)
                    ->orWhere('locked_at <', $staleBefore)
                ->groupEnd()
                ->set([
                    'locked_at' => $nowStr,
                    'lock_token' => $lockToken,
                    'updated_at' => $nowStr,
                ])
                ->update();

            if ($updated) {
                $claimedIds[] = $id;
            }
        }

        if (empty($claimedIds)) {
            return $stats;
        }

        $stats['claimed'] = count($claimedIds);

        $rows = $model
            ->where('business_id', $businessId)
            ->whereIn('id', $claimedIds)
            ->where('lock_token', $lockToken)
            ->findAll();

        $sentPerChannel = ['email' => 0, 'sms' => 0, 'whatsapp' => 0];
        $perChannelLimit = ['email' => 100, 'sms' => 60, 'whatsapp' => 60];

        $logSvc = new NotificationDeliveryLogService();
        $optOutSvc = new NotificationOptOutService();

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $channel = (string) ($row['channel'] ?? '');
            $eventType = (string) ($row['event_type'] ?? '');
            $appointmentId = (int) ($row['appointment_id'] ?? 0);
            $attempts = (int) ($row['attempts'] ?? 0);
            $maxAttempts = (int) ($row['max_attempts'] ?? 5);
            $correlationId = (string) ($row['correlation_id'] ?? '');
            $attemptNumber = $attempts + 1;

            if (trim($correlationId) === '') {
                $correlationId = bin2hex(random_bytes(16));
                $model->update($id, [
                    'correlation_id' => $correlationId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if ($id <= 0 || $appointmentId <= 0 || $channel === '' || $eventType === '') {
                $this->markFailed($model, $row, 'Invalid queue row', true);
                $logSvc->logAttempt($businessId, $id, $correlationId ?: null, $channel ?: 'email', $eventType ?: 'unknown', $appointmentId ?: null, null, 'failed', $attemptNumber, 'Invalid queue row');
                $stats['failed']++;
                continue;
            }

            if (!isset($perChannelLimit[$channel])) {
                $this->markFailed($model, $row, 'Unsupported channel', true);
                $logSvc->logAttempt($businessId, $id, $correlationId ?: null, $channel, $eventType, $appointmentId, null, 'failed', $attemptNumber, 'Unsupported channel');
                $stats['failed']++;
                continue;
            }

            if ($sentPerChannel[$channel] >= $perChannelLimit[$channel]) {
                // Leave queued for next run
                $this->releaseLock($model, $row);
                $logSvc->logAttempt($businessId, $id, $correlationId ?: null, $channel, $eventType, $appointmentId, null, 'skipped', $attemptNumber, 'Per-run channel limit reached');
                $stats['skipped']++;
                continue;
            }

            if (!$this->isRuleEnabled($businessId, $eventType, $channel)) {
                $this->markCancelled($model, $row, 'Rule disabled');
                $logSvc->logAttempt($businessId, $id, $correlationId ?: null, $channel, $eventType, $appointmentId, null, 'cancelled', $attemptNumber, 'Rule disabled');
                $stats['cancelled']++;
                continue;
            }

            if (!$this->isIntegrationActive($businessId, $channel)) {
                $this->markCancelled($model, $row, 'Integration inactive');
                $logSvc->logAttempt($businessId, $id, $correlationId ?: null, $channel, $eventType, $appointmentId, null, 'cancelled', $attemptNumber, 'Integration inactive');
                $stats['cancelled']++;
                continue;
            }

            $appt = $this->getAppointmentContext($appointmentId);
            if (!$appt) {
                $this->markFailed($model, $row, 'Appointment not found', true);
                $logSvc->logAttempt($businessId, $id, $correlationId ?: null, $channel, $eventType, $appointmentId, null, 'failed', $attemptNumber, 'Appointment not found');
                $stats['failed']++;
                continue;
            }

            $recipient = $this->getRecipientForChannel($channel, $appt);
            if ($recipient !== null && $optOutSvc->isOptedOut($businessId, $channel, $recipient)) {
                $this->markCancelled($model, $row, 'Recipient opted out');
                $logSvc->logAttempt($businessId, $id, $correlationId ?: null, $channel, $eventType, $appointmentId, $recipient, 'cancelled', $attemptNumber, 'Recipient opted out');
                $stats['cancelled']++;
                continue;
            }

            $send = ['ok' => false, 'error' => 'Unknown error'];
            try {
                if ($channel === 'email') {
                    $send = $this->sendEmail($businessId, $eventType, $appt);
                } elseif ($channel === 'sms') {
                    if ($eventType !== 'appointment_reminder') {
                        $send = ['ok' => false, 'error' => 'SMS is only supported for appointment_reminder in this phase.'];
                    } else {
                        $send = $this->sendSmsReminder($businessId, $appt);
                    }
                } elseif ($channel === 'whatsapp') {
                    $send = $this->sendWhatsApp($businessId, $eventType, $appt);
                }
            } catch (\Throwable $e) {
                $send = ['ok' => false, 'error' => $e->getMessage()];
            }

            if (($send['ok'] ?? false) === true) {
                $sentPerChannel[$channel]++;
                $this->markSent($model, $row);
                $logSvc->logAttempt($businessId, $id, $correlationId ?: null, $channel, $eventType, $appointmentId, $recipient, 'success', $attemptNumber, null);
                $stats['sent']++;

                if ($eventType === 'appointment_reminder') {
                    $this->markReminderSentIfSupported($appointmentId);
                }
                continue;
            }

            $attempts++;
            if ($attempts >= max(1, $maxAttempts)) {
                $this->markFailed($model, $row, (string) ($send['error'] ?? 'Failed'), true, $attempts);
                $logSvc->logAttempt($businessId, $id, $correlationId ?: null, $channel, $eventType, $appointmentId, $recipient, 'failed', $attemptNumber, (string) ($send['error'] ?? 'Failed'));
                $stats['failed']++;
                continue;
            }

            $backoffMinutes = (int) min(60, max(1, pow(2, min(6, $attempts - 1))));
            $nextRun = (new \DateTimeImmutable('now'))->modify('+' . $backoffMinutes . ' minutes')->format('Y-m-d H:i:s');
            $this->requeueWithBackoff($model, $row, (string) ($send['error'] ?? 'Failed'), $attempts, $nextRun);
            $logSvc->logAttempt($businessId, $id, $correlationId ?: null, $channel, $eventType, $appointmentId, $recipient, 'failed', $attemptNumber, (string) ($send['error'] ?? 'Failed'));
            $stats['skipped']++;
        }

        return $stats;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getAppointmentContext(int $appointmentId): ?array
    {
        $model = new AppointmentModel();
        $builder = $model->builder();

        $row = $builder
            ->select('xs_appointments.*, c.first_name as customer_first_name, c.last_name as customer_last_name, c.email as customer_email, c.phone as customer_phone, s.name as service_name, u.name as provider_name')
            ->join('xs_customers c', 'c.id = xs_appointments.customer_id', 'left')
            ->join('xs_services s', 's.id = xs_appointments.service_id', 'left')
            ->join('xs_users u', 'u.id = xs_appointments.provider_id', 'left')
            ->where('xs_appointments.id', $appointmentId)
            ->get()
            ->getFirstRow('array');

        return is_array($row) ? $row : null;
    }

    private function isRuleEnabled(int $businessId, string $eventType, string $channel): bool
    {
        $model = new BusinessNotificationRuleModel();
        $row = $model
            ->where('business_id', $businessId)
            ->where('event_type', $eventType)
            ->where('channel', $channel)
            ->first();
        return (int) ($row['is_enabled'] ?? 0) === 1;
    }

    private function isIntegrationActive(int $businessId, string $channel): bool
    {
        $model = new BusinessIntegrationModel();
        $row = $model
            ->where('business_id', $businessId)
            ->where('channel', $channel)
            ->first();
        return (bool) ($row['is_active'] ?? false);
    }

    private function sendEmail(int $businessId, string $eventType, array $appt): array
    {
        $to = (string) ($appt['customer_email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Missing/invalid customer email.'];
        }

        // Prepare data for template rendering
        $customerName = trim((string) ($appt['customer_first_name'] ?? '') . ' ' . (string) ($appt['customer_last_name'] ?? ''));
        if ($customerName === '') {
            $customerName = 'Customer';
        }

        $templateData = [
            'customer_name' => $customerName,
            'customer_email' => $to,
            'customer_phone' => $appt['customer_phone'] ?? '',
            'service_name' => (string) ($appt['service_name'] ?? 'Service'),
            'provider_name' => (string) ($appt['provider_name'] ?? 'Provider'),
            'start_datetime' => !empty($appt['start_at'])
                ? TimezoneService::toDisplay($appt['start_at'])
                : '',
        ];

        // Render template with placeholders
        $templateSvc = new NotificationTemplateService();
        $rendered = $templateSvc->render($eventType, 'email', $templateData);

        $subject = $rendered['subject'] ?: 'Appointment Update';
        $body = $rendered['body'] ?: "Your appointment has been updated.\n\n— WebSchedulr";

        $svc = new NotificationEmailService();
        return $svc->sendEmail($businessId, $to, $subject, $body);
    }

    private function sendSmsReminder(int $businessId, array $appt): array
    {
        $to = trim((string) ($appt['customer_phone'] ?? ''));
        if (!$this->isValidE164($to)) {
            return ['ok' => false, 'error' => 'Missing/invalid customer phone (+E.164).'];
        }

        // Prepare data for template rendering
        $customerName = trim((string) ($appt['customer_first_name'] ?? '') . ' ' . (string) ($appt['customer_last_name'] ?? ''));
        if ($customerName === '') {
            $customerName = 'Customer';
        }

        $templateData = [
            'customer_name' => $customerName,
            'customer_phone' => $to,
            'service_name' => (string) ($appt['service_name'] ?? 'Service'),
            'provider_name' => (string) ($appt['provider_name'] ?? 'Provider'),
            'start_datetime' => !empty($appt['start_at'])
                ? TimezoneService::toDisplay($appt['start_at'])
                : '',
        ];

        // Render template with placeholders
        $templateSvc = new NotificationTemplateService();
        $rendered = $templateSvc->render('appointment_reminder', 'sms', $templateData);

        $message = $rendered['body'] ?: "Reminder: {$templateData['service_name']} with {$templateData['provider_name']}";

        // Ensure SMS doesn't exceed character limit
        if (strlen($message) > 248) {
            $message = substr($message, 0, 245) . '...';
        }

        $svc = new NotificationSmsService();
        return $svc->sendSms($businessId, $to, $message);
    }

    private function sendWhatsApp(int $businessId, string $eventType, array $appt): array
    {
        $to = trim((string) ($appt['customer_phone'] ?? ''));
        if (!$this->isValidE164($to)) {
            return ['ok' => false, 'error' => 'Missing/invalid customer phone (+E.164).'];
        }

        $customerName = trim((string) ($appt['customer_first_name'] ?? '') . ' ' . (string) ($appt['customer_last_name'] ?? ''));
        if ($customerName === '') {
            $customerName = 'Customer';
        }

        // Prepare data for template rendering
        $templateData = [
            'customer_name' => $customerName,
            'customer_phone' => $to,
            'service_name' => (string) ($appt['service_name'] ?? 'Service'),
            'provider_name' => (string) ($appt['provider_name'] ?? 'Provider'),
            'start_datetime' => !empty($appt['start_at'])
                ? TimezoneService::toDisplay($appt['start_at'])
                : '',
        ];

        // Render template with placeholders
        $templateSvc = new NotificationTemplateService();
        $rendered = $templateSvc->render($eventType, 'whatsapp', $templateData);

        $message = $rendered['body'] ?: "Your appointment has been updated.";

        // For Meta Cloud API, we still need the params array; for other providers, use rendered message
        $params = [$customerName, $templateData['service_name'], $templateData['provider_name'], $templateData['start_datetime']];
        
        $svc = new NotificationWhatsAppService();
        return $svc->sendTemplateMessage($businessId, $to, $eventType, $params, $message);
    }

    private function markReminderSentIfSupported(int $appointmentId): void
    {
        $model = new AppointmentModel();
        $db = \Config\Database::connect();
        $fields = $db->getFieldNames($model->table);
        if (!in_array('reminder_sent', $fields, true)) {
            return;
        }
        $model->update($appointmentId, ['reminder_sent' => 1]);
    }

    private function releaseLock(NotificationQueueModel $model, array $row): void
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $model->update($id, [
            'locked_at' => null,
            'lock_token' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function markSent(NotificationQueueModel $model, array $row): void
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $model->update($id, [
            'status' => 'sent',
            'sent_at' => $now,
            'last_error' => null,
            'locked_at' => null,
            'lock_token' => null,
            'updated_at' => $now,
        ]);
    }

    private function markCancelled(NotificationQueueModel $model, array $row, string $reason): void
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $model->update($id, [
            'status' => 'cancelled',
            'last_error' => $reason,
            'locked_at' => null,
            'lock_token' => null,
            'updated_at' => $now,
        ]);
    }

    private function markFailed(NotificationQueueModel $model, array $row, string $error, bool $final, ?int $attempts = null): void
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $payload = [
            'status' => $final ? 'failed' : 'queued',
            'last_error' => $error,
            'locked_at' => null,
            'lock_token' => null,
            'updated_at' => $now,
        ];
        if ($attempts !== null) {
            $payload['attempts'] = $attempts;
        }
        $model->update($id, $payload);
    }

    private function requeueWithBackoff(NotificationQueueModel $model, array $row, string $error, int $attempts, string $nextRun): void
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $model->update($id, [
            'status' => 'queued',
            'attempts' => $attempts,
            'last_error' => $error,
            'run_after' => $nextRun,
            'locked_at' => null,
            'lock_token' => null,
            'updated_at' => $now,
        ]);
    }

    private function isValidE164(string $phone): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $phone);
    }

    private function getRecipientForChannel(string $channel, array $appt): ?string
    {
        if ($channel === 'email') {
            $to = trim((string) ($appt['customer_email'] ?? ''));
            return $to !== '' ? $to : null;
        }

        if ($channel === 'sms' || $channel === 'whatsapp') {
            $to = trim((string) ($appt['customer_phone'] ?? ''));
            return $to !== '' ? $to : null;
        }

        return null;
    }
}
