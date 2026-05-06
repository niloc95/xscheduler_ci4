<?php

namespace App\Commands;

use App\Services\NotificationCatalog;
use App\Services\NotificationQueueDispatcher;
use App\Services\NotificationQueueService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AuditReminderPipeline extends BaseCommand
{
    protected $group = 'audit';

    protected $name = 'audit:reminder-pipeline';

    protected $description = 'Inspect reminder automation health and optionally trigger a dry run of enqueue/dispatch stats.';

    protected $usage = 'audit:reminder-pipeline [businessId]';

    /**
     * @param array<int,string> $params
     */
    public function run(array $params)
    {
        $businessId = (int) ($params[0] ?? NotificationCatalog::BUSINESS_ID_DEFAULT);
        if ($businessId <= 0) {
            $businessId = NotificationCatalog::BUSINESS_ID_DEFAULT;
        }

        $db = \Config\Database::connect();
        $rulesTable = $db->prefixTable('business_notification_rules');
        $integrationsTable = $db->prefixTable('business_integrations');
        $queueTable = $db->prefixTable('notification_queue');

        CLI::newLine();
        CLI::write('Reminder Pipeline Audit', 'yellow');
        CLI::write('======================', 'yellow');
        CLI::write('Business ID: ' . $businessId);

        $rules = $db->table($rulesTable)
            ->select('channel, is_enabled, reminder_offset_minutes, reminder_offsets_json')
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->orderBy('channel', 'ASC')
            ->get()
            ->getResultArray();

        $integrations = $db->table($integrationsTable)
            ->select('channel, is_active')
            ->where('business_id', $businessId)
            ->orderBy('channel', 'ASC')
            ->get()
            ->getResultArray();

        CLI::newLine();
        CLI::write('Rules', 'cyan');
        if (empty($rules)) {
            CLI::write('- No appointment_reminder rules found', 'red');
        } else {
            foreach ($rules as $rule) {
                CLI::write(sprintf(
                    '- %s enabled=%s legacy_offset=%s offsets_json=%s',
                    (string) ($rule['channel'] ?? '-'),
                    ((int) ($rule['is_enabled'] ?? 0) === 1 ? 'yes' : 'no'),
                    (string) ($rule['reminder_offset_minutes'] ?? '-'),
                    (string) ($rule['reminder_offsets_json'] ?? '-')
                ));
            }
        }

        CLI::newLine();
        CLI::write('Integrations', 'cyan');
        if (empty($integrations)) {
            CLI::write('- No integrations found', 'red');
        } else {
            foreach ($integrations as $integration) {
                CLI::write(sprintf(
                    '- %s active=%s',
                    (string) ($integration['channel'] ?? '-'),
                    ((int) ($integration['is_active'] ?? 0) === 1 ? 'yes' : 'no')
                ));
            }
        }

        $queued = (int) ($db->table($queueTable)
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->where('status', 'queued')
            ->countAllResults());

        $sentToday = (int) ($db->table($queueTable)
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->where('status', 'sent')
            ->where('updated_at >=', gmdate('Y-m-d 00:00:00'))
            ->countAllResults());

        CLI::newLine();
        CLI::write('Queue status', 'cyan');
        CLI::write('- queued reminders: ' . $queued);
        CLI::write('- sent today (UTC): ' . $sentToday);

        CLI::newLine();
        CLI::write('Live probe (enqueue + dispatch stats)', 'cyan');
        $queueService = new NotificationQueueService();
        $dispatcher = new NotificationQueueDispatcher();

        $enq = $queueService->enqueueDueReminders($businessId);
        $disp = $dispatcher->dispatch($businessId, 50, 'appointment_reminder');

        CLI::write(sprintf(
            '- enqueue scanned=%d enqueued=%d skipped=%d',
            (int) ($enq['scanned'] ?? 0),
            (int) ($enq['enqueued'] ?? 0),
            (int) ($enq['skipped'] ?? 0)
        ));

        CLI::write(sprintf(
            '- dispatch claimed=%d sent=%d cancelled=%d failed=%d skipped=%d',
            (int) ($disp['claimed'] ?? 0),
            (int) ($disp['sent'] ?? 0),
            (int) ($disp['cancelled'] ?? 0),
            (int) ($disp['failed'] ?? 0),
            (int) ($disp['skipped'] ?? 0)
        ));

        CLI::newLine();
        CLI::write('Done.', 'green');
    }
}
