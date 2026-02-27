<?php

namespace Config;

use App\Services\NotificationPhase1;
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
});

Events::on('appointment.event', static function (array $payload): void {
    $appointmentId = (int) ($payload['appointment_id'] ?? 0);
    $eventType = trim((string) ($payload['event_type'] ?? ''));
    $channels = $payload['channels'] ?? [];
    $businessId = (int) ($payload['business_id'] ?? NotificationPhase1::BUSINESS_ID_DEFAULT);

    if ($appointmentId <= 0 || $eventType === '') {
        return;
    }

    if (!is_array($channels) || empty($channels)) {
        $channels = ['email', 'whatsapp'];
    }

    $channels = array_values(array_intersect($channels, NotificationPhase1::CHANNELS));
    if (empty($channels)) {
        return;
    }

    $queue = new NotificationQueueService();
    foreach ($channels as $channel) {
        $queue->enqueueAppointmentEvent($businessId, $channel, $eventType, $appointmentId);
    }
});
