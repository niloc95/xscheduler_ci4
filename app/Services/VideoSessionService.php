<?php

namespace App\Services;

class VideoSessionService
{
    public const VALID_MODES  = ['onsite', 'online_zoom', 'online_jitsi'];
    public const ONLINE_MODES = ['online_zoom', 'online_jitsi'];

    public static function isOnlineMode(string $mode): bool
    {
        return in_array($mode, self::ONLINE_MODES, true);
    }

    /**
     * Generate a video meeting link for an appointment.
     * Returns ['ok' => bool, 'join_url' => string|null, 'error' => string|null]
     *
     * Called AFTER the transaction commits so a failed video API call
     * never rolls back a successfully persisted appointment.
     */
    public function generateLink(int $businessId, string $deliveryMode, array $appointment): array
    {
        if ($deliveryMode === 'online_zoom') {
            return (new ZoomIntegrationService())->createMeeting($businessId, [
                'title'             => $appointment['title'] ?? ('Appointment #' . ($appointment['id'] ?? '')),
                'start_datetime'    => $appointment['start_at'] ?? '',
                'duration_minutes'  => $appointment['duration_minutes'] ?? 60,
                'timezone'          => $appointment['stored_timezone'] ?? 'UTC',
            ]);
        }

        if ($deliveryMode === 'online_jitsi') {
            return (new JitsiIntegrationService())->generateMeetingLink($businessId, [
                'hash'  => $appointment['hash'] ?? ($appointment['id'] ?? uniqid('appt', true)),
                'id'    => $appointment['id'] ?? null,
            ]);
        }

        return ['ok' => false, 'join_url' => null, 'error' => 'Unknown delivery mode: ' . $deliveryMode];
    }
}
