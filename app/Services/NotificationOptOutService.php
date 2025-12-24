<?php

namespace App\Services;

use App\Models\NotificationOptOutModel;

class NotificationOptOutService
{
    public function isOptedOut(int $businessId, string $channel, string $recipient): bool
    {
        $recipient = trim($recipient);
        if ($recipient === '') {
            return false;
        }

        try {
            $model = new NotificationOptOutModel();
            $row = $model
                ->where('business_id', $businessId)
                ->where('channel', $channel)
                ->where('recipient', $recipient)
                ->first();

            return is_array($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function optOut(int $businessId, string $channel, string $recipient, ?string $reason = null): array
    {
        $recipient = trim($recipient);
        $reason = $reason !== null ? trim($reason) : null;
        if ($recipient === '') {
            return ['ok' => false, 'error' => 'Missing recipient'];
        }

        try {
            $model = new NotificationOptOutModel();

            // Upsert-ish: try insert, fall back to update.
            $existing = $model
                ->where('business_id', $businessId)
                ->where('channel', $channel)
                ->where('recipient', $recipient)
                ->first();

            if (!empty($existing['id'])) {
                $model->update((int) $existing['id'], ['reason' => $reason]);
                return ['ok' => true, 'updated' => true];
            }

            $model->insert([
                'business_id' => $businessId,
                'channel' => $channel,
                'recipient' => $recipient,
                'reason' => $reason,
            ]);

            return ['ok' => true, 'inserted' => true];
        } catch (\Throwable $e) {
            log_message('error', 'Opt-out save failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Failed to save opt-out'];
        }
    }
}
