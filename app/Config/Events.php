<?php

namespace Config;

use App\Services\NotificationCatalog;
use App\Services\NotificationReminderHeartbeatService;
use App\Services\NotificationQueueService;
use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }

    // Fallback reminder heartbeat: keeps reminder queue moving on active sites
    // when OS-level cron is missing or temporarily down.
    // Guard: skip entirely if setup hasn't completed — DB credentials don't
    // exist yet and MySQLi::connect() will crash on an empty hostname string.
    if (!is_cli()) {
        helper('setup');
        if (is_setup_completed()) {
            try {
                (new NotificationReminderHeartbeatService())->runIfDue();
            } catch (\Throwable $e) {
                log_message('error', '[Events::pre_system reminder heartbeat] ' . $e->getMessage());
            }
        }
    }
});

Events::on('appointment.event', static function (array $payload): void {
    $appointmentId = (int) ($payload['appointment_id'] ?? 0);
    $eventType = trim((string) ($payload['event_type'] ?? ''));
    $channels = $payload['channels'] ?? [];
    $businessId = (int) ($payload['business_id'] ?? NotificationCatalog::BUSINESS_ID_DEFAULT);

    if ($appointmentId <= 0 || $eventType === '') {
        return;
    }

    if (!is_array($channels) || empty($channels)) {
        $channels = ['email', 'whatsapp'];
    }

    $channels = NotificationCatalog::normalizeChannels($channels);
    if (empty($channels)) {
        return;
    }

    $queue = new NotificationQueueService();
    foreach ($channels as $channel) {
        $queue->enqueueAppointmentEvent($businessId, $channel, $eventType, $appointmentId);
    }
});
