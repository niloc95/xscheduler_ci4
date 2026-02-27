<?php

/**
 * =============================================================================
 * APPOINTMENT EVENT SERVICE
 * =============================================================================
 *
 * @file        app/Services/AppointmentEventService.php
 * @description Emits appointment lifecycle events for async listeners.
 *
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides a single entry point to emit appointment lifecycle events.
 * Notification listeners can subscribe without coupling controllers/services
 * directly to queueing logic.
 *
 * @package     App\Services
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Services;

use CodeIgniter\Events\Events;

class AppointmentEventService
{
    public const DEFAULT_CHANNELS = ['email', 'whatsapp'];

    /**
     * Dispatch appointment lifecycle event.
     */
    public function dispatch(
        string $eventType,
        int $appointmentId,
        array $channels = self::DEFAULT_CHANNELS,
        int $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT
    ): void {
        $channels = array_values(array_filter($channels, 'is_string'));

        Events::trigger('appointment.event', [
            'event_type' => $eventType,
            'appointment_id' => $appointmentId,
            'channels' => $channels,
            'business_id' => $businessId,
        ]);
    }
}
