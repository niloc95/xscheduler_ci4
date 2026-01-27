<?php

namespace App\Services;

use App\Models\AppointmentModel;
use App\Models\BusinessIntegrationModel;
use App\Models\BusinessNotificationRuleModel;
use App\Models\NotificationQueueModel;

class NotificationQueueService
{
    public const BUSINESS_ID_DEFAULT = NotificationPhase1::BUSINESS_ID_DEFAULT;

    /**
     * Enqueue an appointment event for a given channel.
     */
    public function enqueueAppointmentEvent(int $businessId, string $channel, string $eventType, int $appointmentId, ?\DateTimeImmutable $runAfter = null): array
    {
        $channel = trim($channel);
        $eventType = trim($eventType);
        if ($appointmentId <= 0) {
            return ['ok' => false, 'error' => 'Invalid appointmentId'];
        }

        $idem = $this->buildIdempotencyKey($channel, $eventType, $appointmentId, null);
        return $this->enqueueRaw($businessId, $channel, $eventType, $appointmentId, $idem, $runAfter);
    }

    /**
     * Enqueue due appointment reminders across channels (email/sms/whatsapp).
     * Returns stats: ['scanned' => int, 'enqueued' => int, 'skipped' => int]
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

        $now = new \DateTimeImmutable('now');
        $windowEnd = $now->modify('+30 days');

        $model = new AppointmentModel();
        $builder = $model->builder();

        $db = \Config\Database::connect();
        $fields = $db->getFieldNames($model->table);
        $hasReminderSent = in_array('reminder_sent', $fields, true);

        $builder->select('xs_appointments.id, xs_appointments.start_time');
        $builder->whereIn('xs_appointments.status', ['pending', 'confirmed']);
        $builder->where('xs_appointments.start_time >', $now->format('Y-m-d H:i:s'));
        $builder->where('xs_appointments.start_time <=', $windowEnd->format('Y-m-d H:i:s'));
        if ($hasReminderSent) {
            $builder->where('xs_appointments.reminder_sent', 0);
        }

        $rows = $builder->get()->getResultArray();
        foreach ($rows as $row) {
            $appointmentId = (int) ($row['id'] ?? 0);
            $startTime = (string) ($row['start_time'] ?? '');
            if ($appointmentId <= 0 || $startTime === '') {
                continue;
            }

            $stats['scanned']++;

            try {
                $start = new \DateTimeImmutable($startTime);
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

                $idem = $this->buildIdempotencyKey($channel, 'appointment_reminder', $appointmentId, $startTime);
                $enq = $this->enqueueRaw($businessId, $channel, 'appointment_reminder', $appointmentId, $idem, $now);
                if ($enq['ok'] ?? false) {
                    if (!empty($enq['inserted'])) {
                        $stats['enqueued']++;
                    } else {
                        $stats['skipped']++;
                    }
                } else {
                    $stats['skipped']++;
                }
            }
        }

        return $stats;
    }

    private function enqueueRaw(int $businessId, string $channel, string $eventType, int $appointmentId, string $idempotencyKey, ?\DateTimeImmutable $runAfter): array
    {
        $now = date('Y-m-d H:i:s');
        $model = new NotificationQueueModel();

        $correlationId = bin2hex(random_bytes(16));

        $payload = [
            'business_id' => $businessId,
            'channel' => $channel,
            'event_type' => $eventType,
            'appointment_id' => $appointmentId,
            'status' => 'queued',
            'attempts' => 0,
            'max_attempts' => 5,
            'run_after' => $runAfter ? $runAfter->format('Y-m-d H:i:s') : $now,
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => $correlationId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            $id = $model->insert($payload);
            if (!$id) {
                // Could be validation error or duplicate; fall back to checking for existing
                $existing = $model
                    ->where('business_id', $businessId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return ['ok' => true, 'inserted' => false];
                }
                return ['ok' => false, 'error' => 'Failed to enqueue notification.'];
            }
            return ['ok' => true, 'inserted' => true, 'id' => (int) $id];
        } catch (\Throwable $e) {
            // Most common: unique constraint violation
            $existing = $model
                ->where('business_id', $businessId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return ['ok' => true, 'inserted' => false];
            }
            log_message('error', 'Queue enqueue failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Failed to enqueue notification.'];
        }
    }

    private function buildIdempotencyKey(string $channel, string $eventType, int $appointmentId, ?string $startTime): string
    {
        $base = $channel . ':' . $eventType . ':appt:' . $appointmentId;
        if ($eventType === 'appointment_reminder' && $startTime) {
            $base .= ':start:' . $startTime;
        }
        // Keep under 128 chars: hash if necessary
        if (strlen($base) > 120) {
            return $channel . ':' . $eventType . ':' . sha1($base);
        }
        return $base;
    }

    private function isChannelEnabledForEvent(int $businessId, string $eventType, string $channel): bool
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

    private function getReminderOffsetMinutes(int $businessId, string $channel): ?int
    {
        helper('notification');
        return notification_get_reminder_offset_minutes($businessId, $channel);
    }
}
