<?php

namespace App\Commands;

use App\Models\NotificationDeliveryLogModel;
use App\Services\NotificationPhase1;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PurgeNotificationDeliveryLogs extends BaseCommand
{
    protected $group = 'notifications';

    protected $name = 'notifications:purge-delivery-logs';

    protected $description = 'Phase 6: purge notification delivery logs older than N days.';

    /**
     * @param array<int, string> $params
     */
    public function run(array $params)
    {
        $businessId = (int) ($params[0] ?? NotificationPhase1::BUSINESS_ID_DEFAULT);
        if ($businessId <= 0) {
            $businessId = NotificationPhase1::BUSINESS_ID_DEFAULT;
        }

        $days = (int) ($params[1] ?? 90);
        if ($days <= 0) {
            $days = 90;
        }

        $before = (new \DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        $model = new NotificationDeliveryLogModel();

        $builder = $model->builder();
        $builder->where('business_id', $businessId);
        $builder->where('created_at <', $before);
        $deleted = $builder->delete();

        // delete() can return bool depending on driver.
        if ($deleted === false) {
            CLI::error('Purge failed.');
            return;
        }

        CLI::write('Purged delivery logs before ' . $before . ' for business_id=' . $businessId, 'green');
    }
}
