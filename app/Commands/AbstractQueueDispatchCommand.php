<?php

namespace App\Commands;

use App\Services\NotificationQueueDispatcher;
use App\Services\NotificationQueueService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

abstract class AbstractQueueDispatchCommand extends BaseCommand
{
    protected function runQueue(int $businessId, int $limit, string $title): void
    {
        if ($businessId <= 0) {
            $businessId = NotificationQueueService::BUSINESS_ID_DEFAULT;
        }
        if ($limit <= 0) {
            $limit = 100;
        }

        CLI::newLine();
        CLI::write($title, 'yellow');
        CLI::write(str_repeat('=', max(10, strlen($title))), 'yellow');

        $queue = new NotificationQueueService();
        $enq = $queue->enqueueDueReminders($businessId);

        $dispatcher = new NotificationQueueDispatcher();
        $stats = $dispatcher->dispatch($businessId, $limit);

        CLI::newLine();
        CLI::write('Done.', 'green');
        CLI::write('Enqueue scanned:  ' . (int) ($enq['scanned'] ?? 0));
        CLI::write('Enqueue enqueued: ' . (int) ($enq['enqueued'] ?? 0));
        CLI::write('Enqueue skipped:  ' . (int) ($enq['skipped'] ?? 0));
        CLI::newLine();
        CLI::write('Claimed:   ' . (int) ($stats['claimed'] ?? 0));
        CLI::write('Sent:      ' . (int) ($stats['sent'] ?? 0));
        CLI::write('Cancelled: ' . (int) ($stats['cancelled'] ?? 0));
        CLI::write('Failed:    ' . (int) ($stats['failed'] ?? 0));
        CLI::write('Skipped:   ' . (int) ($stats['skipped'] ?? 0));
        CLI::newLine();
    }
}
