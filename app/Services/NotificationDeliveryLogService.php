<?php

namespace App\Services;

use App\Models\BusinessIntegrationModel;
use App\Models\NotificationDeliveryLogModel;

class NotificationDeliveryLogService
{
    public function logAttempt(
        int $businessId,
        ?int $queueId,
        ?string $correlationId,
        string $channel,
        string $eventType,
        ?int $appointmentId,
        ?string $recipient,
        string $status,
        int $attempt,
        ?string $errorMessage = null
    ): void {
        try {
            $model = new NotificationDeliveryLogModel();

            $model->insert([
                'business_id' => $businessId,
                'queue_id' => $queueId,
                'correlation_id' => $correlationId,
                'channel' => $channel,
                'event_type' => $eventType,
                'appointment_id' => $appointmentId,
                'recipient' => $recipient,
                'provider' => $this->getProviderName($businessId, $channel),
                'status' => $status,
                'attempt' => max(1, $attempt),
                'error_message' => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            // Never block dispatch due to logging failures.
            log_message('error', 'Delivery log insert failed: {msg}', ['msg' => $e->getMessage()]);
        }
    }

    public function getProviderName(int $businessId, string $channel): ?string
    {
        try {
            $model = new BusinessIntegrationModel();
            $row = $model
                ->where('business_id', $businessId)
                ->where('channel', $channel)
                ->first();

            $provider = (string) ($row['provider_name'] ?? '');
            return trim($provider) !== '' ? $provider : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
