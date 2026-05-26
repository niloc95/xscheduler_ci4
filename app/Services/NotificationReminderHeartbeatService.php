<?php

namespace App\Services;

use App\Services\NotificationCatalog;

/**
 * Lightweight fallback runner for reminder enqueue/dispatch.
 *
 * This does not replace cron; it provides a safety net for environments
 * where cron is unavailable or intermittently failing.
 */
class NotificationReminderHeartbeatService
{
    private const CACHE_LAST_RUN_KEY = 'notifications_reminder_heartbeat_last_run';
    private const CACHE_LOCK_KEY = 'notifications_reminder_heartbeat_lock';

    /**
     * Read current heartbeat telemetry from cache.
     *
     * @return array{last_run_ts:int|null,is_locked:bool,interval_seconds:int,is_stale:bool}
     */
    public function getStatus(int $intervalSeconds = 300): array
    {
        $intervalSeconds = max(60, $intervalSeconds);
        $cache = cache();
        $lastRun = (int) ($cache->get(self::CACHE_LAST_RUN_KEY) ?? 0);

        return [
            'last_run_ts' => $lastRun > 0 ? $lastRun : null,
            'is_locked' => !empty($cache->get(self::CACHE_LOCK_KEY)),
            'interval_seconds' => $intervalSeconds,
            'is_stale' => $lastRun <= 0 || ((time() - $lastRun) > ($intervalSeconds * 2)),
        ];
    }

    /**
     * Attempt to run reminder queue processing if due.
     *
     * @return array{ran:bool,reason?:string,enqueued?:int,sent?:int,claimed?:int}
     */
    public function runIfDue(
        int $businessId = NotificationCatalog::BUSINESS_ID_DEFAULT,
        int $intervalSeconds = 300,
        int $dispatchLimit = 50
    ): array {
        if (is_cli() || ENVIRONMENT === 'testing') {
            return ['ran' => false, 'reason' => 'not_web'];
        }

        $intervalSeconds = max(60, $intervalSeconds);
        $dispatchLimit = max(1, min(200, $dispatchLimit));

        $cache = cache();
        $now = time();

        $lockValue = $cache->get(self::CACHE_LOCK_KEY);
        if (!empty($lockValue)) {
            return ['ran' => false, 'reason' => 'locked'];
        }

        $lastRun = (int) ($cache->get(self::CACHE_LAST_RUN_KEY) ?? 0);
        if ($lastRun > 0 && ($now - $lastRun) < $intervalSeconds) {
            return ['ran' => false, 'reason' => 'interval'];
        }

        // Best-effort lock for this process window.
        $cache->save(self::CACHE_LOCK_KEY, (string) $now, 30);

        try {
            $queueService = new NotificationQueueService();
            $dispatcher = new NotificationQueueDispatcher();

            $enqueueStats = $queueService->enqueueDueReminders($businessId);
            $dispatchStats = $dispatcher->dispatch($businessId, $dispatchLimit, 'appointment_reminder');

            $cache->save(self::CACHE_LAST_RUN_KEY, $now, 86400);

            return [
                'ran' => true,
                'enqueued' => (int) ($enqueueStats['enqueued'] ?? 0),
                'claimed' => (int) ($dispatchStats['claimed'] ?? 0),
                'sent' => (int) ($dispatchStats['sent'] ?? 0),
            ];
        } catch (\Throwable $e) {
            log_message('error', '[NotificationReminderHeartbeatService] ' . $e->getMessage());
            return ['ran' => false, 'reason' => 'exception'];
        } finally {
            try {
                $cache->delete(self::CACHE_LOCK_KEY);
            } catch (\Throwable $ignored) {
                // Lock file may have already expired; no action needed.
            }
        }
    }
}
