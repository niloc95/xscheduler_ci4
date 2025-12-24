<?php

namespace App\Commands;

use App\Services\NotificationPhase1;
use App\Services\NotificationQueueDispatcher;
use App\Services\NotificationQueueService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DispatchNotificationQueue extends BaseCommand
{
    protected $group = 'notifications';

    protected $name = 'notifications:dispatch-queue';

    protected $description = 'Phase 5: enqueue due reminders and dispatch queued notifications (intended for cron).';

    /**
     * @param array<int, string> $params
     */
    public function run(array $params)
    {
        $businessId = (int) ($params[0] ?? NotificationPhase1::BUSINESS_ID_DEFAULT);
        if ($businessId <= 0) {
            $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT;
        }

        $limit = (int) ($params[1] ?? 100);
        if ($limit <= 0) {
            $limit = 100;
        }

        CLI::newLine();
        CLI::write('WebSchedulr - Notification Queue Dispatcher (Phase 5)', 'yellow');
        CLI::write('================================================', 'yellow');

        $queue = new NotificationQueueService();
        $enq = $queue->enqueueDueReminders($businessId);

        CLI::newLine();
        CLI::write('Enqueue due reminders:', 'cyan');
        CLI::write('Scanned:   ' . (int) ($enq['scanned'] ?? 0));
        CLI::write('Enqueued:  ' . (int) ($enq['enqueued'] ?? 0));
        CLI::write('Skipped:   ' . (int) ($enq['skipped'] ?? 0));

        $dispatcher = new NotificationQueueDispatcher();
        $stats = $dispatcher->dispatch($businessId, $limit);

        CLI::newLine();
        CLI::write('Dispatch queue:', 'cyan');
        CLI::write('Claimed:   ' . (int) ($stats['claimed'] ?? 0));
        CLI::write('Sent:      ' . (int) ($stats['sent'] ?? 0));
        CLI::write('Cancelled: ' . (int) ($stats['cancelled'] ?? 0));
        CLI::write('Failed:    ' . (int) ($stats['failed'] ?? 0));
        CLI::write('Skipped:   ' . (int) ($stats['skipped'] ?? 0));
        CLI::newLine();
    }
}
