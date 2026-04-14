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
    public function enqueueAppointmentEvent(int $businessId, string $channel, string $eventType, int $appointmentId, ?\DateTimeImmutable $runAfter = null): array
    {
        $channel   = trim($channel);
        $eventType = trim($eventType);
        if ($appointmentId <= 0) {
            return ['ok' => false, 'error' => 'Invalid appointmentId'];
        }
        $fingerprint = null;
        if ($eventType === 'appointment_rescheduled') {
            $fingerprint = $this->resolveRescheduleFingerprint($appointmentId);
        }
        $idem = $this->buildIdempotencyKey($channel, $eventType, $appointmentId, $fingerprint);
        return $this->enqueueRaw($businessId, $channel, $eventType, $appointmentId, $idem, $runAfter);
    }

    /**
     * Enqueue a notification for an internal recipient (provider or assigned staff).
     */
    public function enqueueInternalEvent(int $businessId, string $channel, string $eventType, int $appointmentId, int $recipientUserId, ?\DateTimeImmutable $runAfter = null): array
    {
        if ($appointmentId <= 0 || $recipientUserId <= 0) {
            return ['ok' => false, 'error' => 'Invalid appointmentId or recipientUserId'];
        }
        $channel   = trim($channel);
        $eventType = trim($eventType);
        $idemKey   = substr('internal:' . $businessId . ':' . $channel . ':' . $eventType . ':appt:' . $appointmentId . ':user:' . $recipientUserId, 0, 128);
        return $this->enqueueRaw($businessId, $channel, $eventType, $appointmentId, $idemKey, $runAfter, 'internal', $recipientUserId);
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
            $offset = $this->getReminderOffsetMinutes($businessId, $channel);
            if ($offset === null) {
                continue;
            }
            if (!$this->isChannelEnabledForEvent($businessId, 'appointment_reminder', $channel)) {
                continue;
            }
            if (!$this->isIntegrationActive($businessId, $channel)) {
                continue;
            }
            $enabledChannels[$channel] = $offset;
        }
        if (empty($enabledChannels)) {
            return $stats;
        }
        $now       = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $windowEnd = $now->modify('+30 days');
        $model     = new AppointmentModel();
        $builder   = $model->builder();
        $db        = \Config\Database::connect();
        $fields    = $db->getFieldNames($model->table);
        $hasReminderSent = in_array('reminder_sent', $fields, true);
        $builder->select('xs_appointments.id, xs_appointments.start_at');
        $builder->whereIn('xs_appointments.status', AppointmentStatus::UPCOMING);
        $builder->where('xs_appointments.start_at >', $now->format('Y-m-d H:i:s'));
        $builder->where('xs_appointments.start_at <=', $windowEnd->format('Y-m-d H:i:s'));
        if ($hasReminderSent) {
            $builder->where('xs_appointments.reminder_sent', 0);
        }
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
            foreach ($enabledChannels as $channel => $offsetMinutes) {
                $dueAt = $start->modify('-' . ((int) $offsetMinutes) . ' minutes');
                if ($now < $dueAt) {
                    $stats['skipped']++;
                    continue;
                }
                $enq = $this->enqueueAppointmentEvent($businessId, $channel, 'appointment_reminder', $appointmentId, $now);
                if (!empty($enq['inserted'])) {
                    $stats['enqueued']++;
                } else {
                    $stats['skipped']++;
                }
            }
            $this->enqueueInternalRemindersForAppointment($businessId, $appointmentId, $appt['start_at'], $now);
        }
        return $stats;
    }

    /**
     * Enqueue internal email reminders for all notifiable providers/staff on an appointment.
     */
    private function enqueueInternalRemindersForAppointment(int $businessId, int $appointmentId, string $startTime, \DateTimeImmutable $now): void
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
            foreach ($users as $user) {
                $this->enqueueInternalEvent($businessId, 'email', 'appointment_reminder', $appointmentId, (int) $user['id'], $now);
            }
        } catch (\Throwable $e) {
            log_message('error', '[NotificationQueueService] enqueueInternalRemindersForAppointment: ' . $e->getMessage());
        }
    }

    /**
     * Insert a row into xs_notification_queue.
     */
    private function enqueueRaw(int $businessId, string $channel, string $eventType, int $appointmentId, string $idempotencyKey, ?\DateTimeImmutable $runAfter, string $recipientType = 'customer', ?int $recipientUserId = null): array
    {
        $now   = date('Y-m-d H:i:s');
        $model = new NotificationQueueModel();
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
            $existing = $model->where('business_id', $businessId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return ['ok' => true, 'inserted' => false];
            }
            log_message('error', 'Queue enqueue failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Failed to enqueue notification.'];
        }
    }

    private function buildIdempotencyKey(string $channel, string $eventType, int $appointmentId, ?string $fingerprint): string
    {
        $base = $channel . ':' . $eventType . ':appt:' . $appointmentId;
        if ($eventType === 'appointment_reminder' && $fingerprint) {
            $base .= ':start:' . $fingerprint;
        }
        if ($eventType === 'appointment_rescheduled' && $fingerprint) {
            $base .= ':chg:' . $fingerprint;
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

    private function isChannelEnabledForEvent(int $businessId, string $eventType, string $channel): bool
    {
        $row = (new BusinessNotificationRuleModel())->where('business_id', $businessId)->where('event_type', $eventType)->where('channel', $channel)->first();
        return (int) ($row['is_enabled'] ?? 0) === 1;
    }

    private function isIntegrationActive(int $businessId, string $channel): bool
    {
        $row = (new BusinessIntegrationModel())->where('business_id', $businessId)->where('channel', $channel)->first();
        return (bool) ($row['is_active'] ?? false);
    }

    private function getReminderOffsetMinutes(int $businessId, string $channel): ?int
    {
        helper('notification');
        return notification_get_reminder_offset_minutes($businessId, $channel);
    }
}
