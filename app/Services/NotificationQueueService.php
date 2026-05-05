<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\BusinessIntegrationModel;
use App\Models\BusinessNotificationRuleModel;
use App\Models\NotificationQueueModel;
use App\Models\UserModel;
use App\Services\Appointment\AppointmentStatus;
use App\Services\NotificationCatalog;

class NotificationQueueService
{
    public const BUSINESS_ID_DEFAULT = NotificationCatalog::BUSINESS_ID_DEFAULT;

    /**
     * Enqueue an appointment event notification for a customer channel.
     */
    public function enqueueAppointmentEvent(int $businessId, string $channel, string $eventType, int $appointmentId, ?\DateTimeImmutable $runAfter = null, ?string $idempotencyMarker = null): array
    {
        $channel   = trim($channel);
        $eventType = trim($eventType);
        if ($appointmentId <= 0) {
            return ['ok' => false, 'error' => 'Invalid appointmentId'];
        }
        $extraPayload = [];
        $marker = $idempotencyMarker;
        if ($eventType === 'appointment_rescheduled') {
            $marker = $this->resolveRescheduleFingerprint($appointmentId);
        }
        if ($eventType === 'appointment_reminder') {
            $reminderMetadata = $this->resolveReminderMetadata($appointmentId, $idempotencyMarker);
            $marker = $reminderMetadata['idempotency_marker'];
            $extraPayload = [
                'reminder_offset_minutes' => $reminderMetadata['offset_minutes'],
                'schedule_fingerprint' => $reminderMetadata['schedule_fingerprint'],
            ];
        }
        $idem = $this->buildIdempotencyKey($channel, $eventType, $appointmentId, $marker);
        return $this->enqueueRaw($businessId, $channel, $eventType, $appointmentId, $idem, $runAfter, 'customer', null, $extraPayload);
    }

    /**
     * Enqueue a notification for an internal recipient (provider or assigned staff).
     */
    public function enqueueInternalEvent(int $businessId, string $channel, string $eventType, int $appointmentId, int $recipientUserId, ?\DateTimeImmutable $runAfter = null, ?string $idempotencyMarker = null): array
    {
        if ($appointmentId <= 0 || $recipientUserId <= 0) {
            return ['ok' => false, 'error' => 'Invalid appointmentId or recipientUserId'];
        }
        $channel   = trim($channel);
        $eventType = trim($eventType);
        $extraPayload = [];
        $marker = $idempotencyMarker;
        if ($eventType === 'appointment_rescheduled') {
            $marker = $this->resolveRescheduleFingerprint($appointmentId);
        }
        if ($eventType === 'appointment_reminder') {
            $reminderMetadata = $this->resolveReminderMetadata($appointmentId, $idempotencyMarker);
            $marker = $reminderMetadata['idempotency_marker'];
            $extraPayload = [
                'reminder_offset_minutes' => $reminderMetadata['offset_minutes'],
                'schedule_fingerprint' => $reminderMetadata['schedule_fingerprint'],
            ];
        }
        $idemKey = $this->buildInternalIdempotencyKey($businessId, $channel, $eventType, $appointmentId, $recipientUserId, $marker);
        return $this->enqueueRaw($businessId, $channel, $eventType, $appointmentId, $idemKey, $runAfter, 'internal', $recipientUserId, $extraPayload);
    }

    /**
     * Enqueue due appointment reminders across channels (email/sms/whatsapp).
     */
    public function enqueueDueReminders(int $businessId = self::BUSINESS_ID_DEFAULT): array
    {
        $stats = ['scanned' => 0, 'enqueued' => 0, 'skipped' => 0];
        $channels = ['email', 'sms', 'whatsapp'];
        $enabledChannels = [];
        foreach ($channels as $channel) {
            $offsets = $this->getReminderOffsetsMinutes($businessId, $channel);
            if ($offsets === []) {
                log_message('info', '[NotificationQueueService] Skipping channel {ch}: no reminder offsets configured.', ['ch' => $channel]);
                continue;
            }
            if (!$this->isChannelEnabledForEvent($businessId, 'appointment_reminder', $channel)) {
                log_message('info', '[NotificationQueueService] Skipping channel {ch}: appointment_reminder rule not enabled.', ['ch' => $channel]);
                continue;
            }
            if (!$this->isIntegrationActive($businessId, $channel)) {
                log_message('info', '[NotificationQueueService] Skipping channel {ch}: integration not active.', ['ch' => $channel]);
                continue;
            }
            $enabledChannels[$channel] = $offsets;
        }
        if (empty($enabledChannels)) {
            log_message('warning', '[NotificationQueueService] enqueueDueReminders: no enabled channels for business {bid}. Check xs_business_notification_rules and xs_business_integrations.', ['bid' => $businessId]);
            return $stats;
        }
        $now       = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $windowEnd = $now->modify('+30 days');

        // Lookback buffer: scan appointments up to 48 h in the past (still UPCOMING).
        // This catches reminders that became due while cron was briefly stopped — e.g.
        // a 24 h-offset reminder for a 9 am appointment when cron missed an overnight
        // run. Idempotency keys prevent double-sending for already-sent offsets.
        $lookbackStart = $now->modify('-48 hours');

        $model   = new AppointmentModel();
        $builder = $model->builder();
        $builder->select('xs_appointments.id, xs_appointments.start_at');
        $builder->whereIn('xs_appointments.status', AppointmentStatus::UPCOMING);
        $builder->where('xs_appointments.start_at >=', $lookbackStart->format('Y-m-d H:i:s'));
        $builder->where('xs_appointments.start_at <=', $windowEnd->format('Y-m-d H:i:s'));
        $appointments = $builder->get()->getResultArray();
        foreach ($appointments as $appt) {
            $stats['scanned']++;
            $appointmentId = (int) $appt['id'];
            try {
                $start = new \DateTimeImmutable($appt['start_at'], new \DateTimeZone('UTC'));
            } catch (\Throwable $e) {
                $stats['skipped']++;
                continue;
            }
            foreach ($enabledChannels as $channel => $offsetMinutesList) {
                foreach ($offsetMinutesList as $offsetMinutes) {
                    $dueAt = $start->modify('-' . ((int) $offsetMinutes) . ' minutes');
                    if ($now < $dueAt) {
                        $stats['skipped']++;
                        continue;
                    }

                    $marker = 'offset:' . (int) $offsetMinutes;
                    $enq = $this->enqueueAppointmentEvent($businessId, $channel, 'appointment_reminder', $appointmentId, $now, $marker);
                    if (!empty($enq['inserted'])) {
                        $stats['enqueued']++;
                    } else {
                        $stats['skipped']++;
                    }

                    if ($channel === 'email') {
                        $this->enqueueInternalRemindersForAppointment($businessId, $appointmentId, $now, (int) $offsetMinutes);
                    }
                }
            }
        }
        return $stats;
    }

    /**
     * Enqueue internal email reminders for all notifiable providers/staff on an appointment.
     */
    private function enqueueInternalRemindersForAppointment(int $businessId, int $appointmentId, \DateTimeImmutable $now, int $offsetMinutes): void
    {
        try {
            if (!$this->isIntegrationActive($businessId, 'email')) {
                return;
            }
            $apptRow    = (new AppointmentModel())->select('provider_id')->find($appointmentId);
            $providerId = (int) ($apptRow['provider_id'] ?? 0);
            if ($providerId <= 0) {
                return;
            }
            $users = (new UserModel())->getNotifiableUsersForProvider($providerId);
            $marker = 'offset:' . $offsetMinutes;
            foreach ($users as $user) {
                $this->enqueueInternalEvent($businessId, 'email', 'appointment_reminder', $appointmentId, (int) $user['id'], $now, $marker);
            }
        } catch (\Throwable $e) {
            log_message('error', '[NotificationQueueService] enqueueInternalRemindersForAppointment: ' . $e->getMessage());
        }
    }

    /**
     * Insert a row into xs_notification_queue.
     */
    private function enqueueRaw(int $businessId, string $channel, string $eventType, int $appointmentId, string $idempotencyKey, ?\DateTimeImmutable $runAfter, string $recipientType = 'customer', ?int $recipientUserId = null, array $extraPayload = []): array
    {
        $now   = date('Y-m-d H:i:s');
        $model = new NotificationQueueModel();

        // Fast-path dedupe check.
        // Block re-enqueue only for in-flight or successfully sent rows.
        // Allow re-enqueue when the existing row is cancelled or failed — those
        // terminal-negative rows must not permanently block future reminder attempts.
        $existing = $model
            ->where('business_id', $businessId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (is_array($existing)) {
            $existingStatus = (string) ($existing['status'] ?? 'queued');
            if (in_array($existingStatus, ['queued', 'processing', 'sent'], true)) {
                // Already in-flight or successfully delivered — do not duplicate.
                return ['ok' => true, 'inserted' => false];
            }
            // Row is cancelled or failed — purge the stale row so a fresh one
            // is inserted below with a new correlation_id and attempts = 0.
            $model->delete((int) $existing['id']);
        }

        $correlationId = bin2hex(random_bytes(16));
        $payload = [
            'business_id'       => $businessId,
            'channel'           => $channel,
            'event_type'        => $eventType,
            'appointment_id'    => $appointmentId,
            'recipient_type'    => $recipientType,
            'recipient_user_id' => $recipientUserId,
            'status'            => 'queued',
            'attempts'          => 0,
            'max_attempts'      => 5,
            'run_after'         => $runAfter ? $runAfter->format('Y-m-d H:i:s') : $now,
            'idempotency_key'   => $idempotencyKey,
            'correlation_id'    => $correlationId,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];
        foreach ($extraPayload as $field => $value) {
            $payload[$field] = $value;
        }
        try {
            $id = $model->insert($payload);
            if (!$id) {
                $existing = $model->where('business_id', $businessId)->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return ['ok' => true, 'inserted' => false];
                }
                return ['ok' => false, 'error' => 'Failed to enqueue notification.'];
            }
            return ['ok' => true, 'inserted' => true, 'id' => (int) $id];
        } catch (\Throwable $e) {
            if ($this->isDuplicateIdempotencyException($e)) {
                return ['ok' => true, 'inserted' => false];
            }
            $existing = $model->where('business_id', $businessId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return ['ok' => true, 'inserted' => false];
            }
            log_message('error', 'Queue enqueue failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Failed to enqueue notification.'];
        }
    }

    private function buildIdempotencyKey(string $channel, string $eventType, int $appointmentId, ?string $marker): string
    {
        $base = $channel . ':' . $eventType . ':appt:' . $appointmentId;
        if ($eventType === 'appointment_reminder' && $marker) {
            $base .= ':off:' . $marker;
        }
        if ($eventType === 'appointment_rescheduled' && $marker) {
            $base .= ':chg:' . $marker;
        }
        if (strlen($base) > 120) {
            return $channel . ':' . $eventType . ':' . sha1($base);
        }
        return $base;
    }

    private function resolveRescheduleFingerprint(int $appointmentId): ?string
    {
        try {
            $row = (new AppointmentModel())->select('start_at, end_at, updated_at')->find($appointmentId);
            if (!is_array($row)) {
                return null;
            }
            $raw = implode('|', [(string) ($row['start_at'] ?? ''), (string) ($row['end_at'] ?? ''), (string) ($row['updated_at'] ?? '')]);
            return $raw !== '||' ? sha1($raw) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{idempotency_marker:?string,offset_minutes:?int,schedule_fingerprint:?string}
     */
    private function resolveReminderMetadata(int $appointmentId, ?string $marker): array
    {
        $offsetMinutes = $this->extractReminderOffsetMinutes($marker);
        $scheduleFingerprint = $this->extractScheduleFingerprint($marker) ?? $this->resolveReminderScheduleFingerprint($appointmentId);

        $parts = [];
        if ($offsetMinutes !== null) {
            $parts[] = 'offset:' . $offsetMinutes;
        } elseif (is_string($marker) && trim($marker) !== '') {
            $parts[] = trim($marker);
        }
        if ($scheduleFingerprint) {
            $parts[] = 'sch:' . $scheduleFingerprint;
        }

        return [
            'idempotency_marker' => $parts !== [] ? implode(':', $parts) : null,
            'offset_minutes' => $offsetMinutes,
            'schedule_fingerprint' => $scheduleFingerprint,
        ];
    }

    private function extractReminderOffsetMinutes(?string $marker): ?int
    {
        if (!is_string($marker) || trim($marker) === '') {
            return null;
        }

        if (preg_match('/(?:^|:)offset:(\d+)(?:$|:)/', $marker, $matches) === 1) {
            return (int) $matches[1];
        }

        return ctype_digit(trim($marker)) ? (int) trim($marker) : null;
    }

    private function extractScheduleFingerprint(?string $marker): ?string
    {
        if (!is_string($marker) || trim($marker) === '') {
            return null;
        }

        if (preg_match('/(?:^|:)sch:([0-9a-f]{40})(?:$|:)/i', $marker, $matches) === 1) {
            return strtolower((string) $matches[1]);
        }

        return null;
    }

    private function resolveReminderScheduleFingerprint(int $appointmentId): ?string
    {
        try {
            $row = (new AppointmentModel())->select('start_at, end_at, updated_at')->find($appointmentId);
            if (!is_array($row)) {
                return null;
            }

            $raw = implode('|', [
                (string) ($row['start_at'] ?? ''),
                (string) ($row['end_at'] ?? ''),
                (string) ($row['updated_at'] ?? ''),
            ]);

            return $raw !== '||' ? sha1($raw) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildInternalIdempotencyKey(int $businessId, string $channel, string $eventType, int $appointmentId, int $recipientUserId, ?string $marker): string
    {
        $base = 'internal:' . $businessId . ':' . $channel . ':' . $eventType . ':appt:' . $appointmentId . ':user:' . $recipientUserId;
        if ($eventType === 'appointment_reminder' && $marker) {
            $base .= ':off:' . $marker;
        }
        if ($eventType === 'appointment_rescheduled' && $marker) {
            $base .= ':chg:' . $marker;
        }
        if (strlen($base) > 120) {
            return 'internal:' . sha1($base);
        }
        return $base;
    }

    private function isDuplicateIdempotencyException(\Throwable $e): bool
    {
        $code = (int) $e->getCode();
        if ($code === 1062) {
            return true;
        }

        $message = strtolower($e->getMessage());
        return str_contains($message, 'duplicate entry')
            && (str_contains($message, 'uniq_notification_queue_idem') || str_contains($message, 'idempotency'));
    }

    private function isChannelEnabledForEvent(int $businessId, string $eventType, string $channel): bool
    {
        $row = (new BusinessNotificationRuleModel())->where('business_id', $businessId)->where('event_type', $eventType)->where('channel', $channel)->first();
        return (int) ($row['is_enabled'] ?? 0) === 1;
    }

    private function isIntegrationActive(int $businessId, string $channel): bool
    {
        $row = (new BusinessIntegrationModel())->where('business_id', $businessId)->where('channel', $channel)->first();

        if ((bool) ($row['is_active'] ?? false)) {
            return true;
        }

        // Development-only fallback: allow email notifications to use Config\Email
        // (.env) when no active xs_business_integrations row exists.
        if ($channel === 'email') {
            return (new NotificationEmailService())->canUseDevelopmentFallbackSmtp();
        }

        return false;
    }

    /**
     * @return array<int, int>
     */
    private function getReminderOffsetsMinutes(int $businessId, string $channel): array
    {
        helper('notification');
        return notification_get_reminder_offsets_minutes($businessId, $channel);
    }
}
