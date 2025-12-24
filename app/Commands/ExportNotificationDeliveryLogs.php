<?php

namespace App\Commands;

use App\Models\NotificationDeliveryLogModel;
use App\Services\NotificationPhase1;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ExportNotificationDeliveryLogs extends BaseCommand
{
    protected $group = 'notifications';

    protected $name = 'notifications:export-delivery-logs';

    protected $description = 'Phase 6: export notification delivery logs to CSV.';

    /**
     * @param array<int, string> $params
     */
    public function run(array $params)
    {
        $businessId = (int) ($params[0] ?? NotificationPhase1::BUSINESS_ID_DEFAULT);
        if ($businessId <= 0) {
            $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT;
        }

        $days = (int) ($params[1] ?? 30);
        if ($days <= 0) {
            $days = 30;
        }

        $outPath = (string) ($params[2] ?? '');
        $outPath = trim($outPath);

        $since = (new \DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        $model = new NotificationDeliveryLogModel();
        $rows = $model
            ->where('business_id', $businessId)
            ->where('created_at >=', $since)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $dir = WRITEPATH . 'exports';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if ($outPath === '') {
            $outPath = $dir . '/notification_delivery_logs_' . date('Ymd_His') . '.csv';
        }

        $fh = @fopen($outPath, 'wb');
        if (!$fh) {
            CLI::error('Failed to open output file: ' . $outPath);
            return;
        }

        $headers = [
            'created_at',
            'status',
            'channel',
            'event_type',
            'attempt',
            'recipient',
            'provider',
            'appointment_id',
            'queue_id',
            'correlation_id',
            'error_message',
        ];

        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = (string) ($row[$h] ?? '');
            }
            fputcsv($fh, $line);
        }

        fclose($fh);

        CLI::write('Exported ' . count($rows) . ' rows to: ' . $outPath, 'green');
    }
}
