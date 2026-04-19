<?php

namespace App\Commands;

use App\Models\AppointmentModel;
use App\Models\NotificationQueueModel;
use App\Services\NotificationCatalog;
use App\Services\NotificationQueueDispatcher;
use App\Services\NotificationQueueService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestAppointmentReminder extends BaseCommand
{
    protected $group = 'notifications';

    protected $name = 'notifications:test-reminder';

    protected $description = 'Development helper: force an appointment into reminder-due window, then enqueue and dispatch reminders.';

    protected $usage = 'notifications:test-reminder <appointmentId> [businessId] [minutesUntilStart]';

    protected $arguments = [
        'appointmentId' => 'Appointment ID to test reminder against.',
        'businessId' => 'Business ID (default: 1).',
        'minutesUntilStart' => 'Minutes from now to set start_at (default: 45).',
    ];

    /**
     * @param array<int, string> $params
     */
    public function run(array $params)
    {
        if (ENVIRONMENT !== 'development') {
            CLI::error('This command is restricted to development environment.');
            return;
        }

        $appointmentId = (int) ($params[0] ?? 0);
        if ($appointmentId <= 0) {
            CLI::error('Missing or invalid appointmentId. Usage: php spark ' . $this->usage);
            return;
        }

        $businessId = (int) ($params[1] ?? NotificationCatalog::BUSINESS_ID_DEFAULT);
        if ($businessId <= 0) {
            $businessId = NotificationCatalog::BUSINESS_ID_DEFAULT;
        }

        $minutesUntilStart = (int) ($params[2] ?? 45);
        if ($minutesUntilStart < 1) {
            $minutesUntilStart = 45;
        }

        $appointmentModel = new AppointmentModel();
        $appt             = $appointmentModel->find($appointmentId);
        if (!$appt) {
            CLI::error('Appointment not found: ' . $appointmentId);
            return;
        }

        $db     = \Config\Database::connect();
        $fields = $db->getFieldNames($appointmentModel->table);

        $nowUtc   = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startUtc = $nowUtc->modify('+' . $minutesUntilStart . ' minutes');

        $durationMinutes = 60;
        if (!empty($appt['start_at']) && !empty($appt['end_at'])) {
            try {
                $oldStart = new \DateTimeImmutable((string) $appt['start_at'], new \DateTimeZone('UTC'));
                $oldEnd   = new \DateTimeImmutable((string) $appt['end_at'], new \DateTimeZone('UTC'));
                $diffMin  = (int) floor(($oldEnd->getTimestamp() - $oldStart->getTimestamp()) / 60);
                if ($diffMin > 0) {
                    $durationMinutes = $diffMin;
                }
            } catch (\Throwable $e) {
                // Keep default duration when existing values are not parseable.
            }
        }

        $endUtc = $startUtc->modify('+' . $durationMinutes . ' minutes');

        $update = [
            'start_at' => $startUtc->format('Y-m-d H:i:s'),
            'end_at' => $endUtc->format('Y-m-d H:i:s'),
        ];

        if (in_array('status', $fields, true)) {
            $update['status'] = 'confirmed';
        }
        if (in_array('reminder_sent', $fields, true)) {
            $update['reminder_sent'] = 0;
        }

        $appointmentModel->update($appointmentId, $update);

        CLI::newLine();
        CLI::write('Reminder test setup', 'yellow');
        CLI::write('===================', 'yellow');
        CLI::write('Appointment ID:     ' . $appointmentId);
        CLI::write('Business ID:        ' . $businessId);
        CLI::write('Now (UTC):          ' . $nowUtc->format('Y-m-d H:i:s'));
        CLI::write('Start set to (UTC): ' . $startUtc->format('Y-m-d H:i:s'));
        CLI::write('End set to (UTC):   ' . $endUtc->format('Y-m-d H:i:s'));

        $queueService = new NotificationQueueService();
        $enqStats     = $queueService->enqueueDueReminders($businessId);

        $dispatcher = new NotificationQueueDispatcher();
        $dispStats  = $dispatcher->dispatch($businessId, 100);

        CLI::newLine();
        CLI::write('Enqueue due reminders', 'cyan');
        CLI::write('Scanned:  ' . (int) ($enqStats['scanned'] ?? 0));
        CLI::write('Enqueued: ' . (int) ($enqStats['enqueued'] ?? 0));
        CLI::write('Skipped:  ' . (int) ($enqStats['skipped'] ?? 0));

        CLI::newLine();
        CLI::write('Dispatch queue', 'cyan');
        CLI::write('Claimed:   ' . (int) ($dispStats['claimed'] ?? 0));
        CLI::write('Sent:      ' . (int) ($dispStats['sent'] ?? 0));
        CLI::write('Cancelled: ' . (int) ($dispStats['cancelled'] ?? 0));
        CLI::write('Failed:    ' . (int) ($dispStats['failed'] ?? 0));
        CLI::write('Skipped:   ' . (int) ($dispStats['skipped'] ?? 0));

        $queueModel = new NotificationQueueModel();
        $rows = $queueModel
            ->where('appointment_id', $appointmentId)
            ->where('event_type', 'appointment_reminder')
            ->orderBy('id', 'DESC')
            ->findAll(10);

        CLI::newLine();
        CLI::write('Latest queue rows for appointment_reminder', 'green');
        if (empty($rows)) {
            CLI::write('- none');
            CLI::newLine();
            CLI::write('Tip: confirm xs_business_notification_rules + active integrations for business ' . $businessId . '.', 'light_gray');
            return;
        }

        foreach ($rows as $row) {
            CLI::write(sprintf(
                '- #%d channel=%s status=%s attempts=%d run_after=%s',
                (int) ($row['id'] ?? 0),
                (string) ($row['channel'] ?? ''),
                (string) ($row['status'] ?? ''),
                (int) ($row['attempts'] ?? 0),
                (string) ($row['run_after'] ?? '')
            ));
        }

        CLI::newLine();
        CLI::write('Done. Check Mailpit (http://localhost:8025) for email reminders.', 'green');
    }
}
