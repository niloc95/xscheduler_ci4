<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;
use App\Services\NotificationCatalog;
use App\Services\NotificationPolicyService;

class SeedDefaultNotificationRules extends MigrationBase
{
    public function up()
    {
        $businessId = NotificationCatalog::BUSINESS_ID_DEFAULT;
        $table = $this->db->prefixTable('business_notification_rules');

        if (!$this->db->tableExists($table)) {
            return;
        }

        $existingRules = $this->db->table('business_notification_rules')
            ->where('business_id', $businessId)
            ->countAllResults();

        if ($existingRules > 0) {
            return;
        }

        $whatsAppStatus = (new NotificationPolicyService())->getIntegrationStatus($businessId)['whatsapp'] ?? [];
        $whatsAppEnabled = !empty($whatsAppStatus['configured']) && !empty($whatsAppStatus['is_active']);
        $now = date('Y-m-d H:i:s');
        $rows = [];

        foreach (array_keys(NotificationCatalog::EVENTS) as $eventType) {
            foreach (NotificationCatalog::CHANNELS as $channel) {
                $isEnabled = 0;

                if ($channel === 'email' && in_array($eventType, ['appointment_pending', 'appointment_confirmed'], true)) {
                    $isEnabled = 1;
                }

                if ($channel === 'whatsapp'
                    && $whatsAppEnabled
                    && in_array($eventType, ['appointment_pending', 'appointment_confirmed'], true)) {
                    $isEnabled = 1;
                }

                $rows[] = [
                    'business_id' => $businessId,
                    'event_type' => $eventType,
                    'channel' => $channel,
                    'is_enabled' => $isEnabled,
                    'reminder_offset_minutes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            $this->db->table('business_notification_rules')->insertBatch($rows);
        }
    }

    public function down()
    {
        // No-op: do not delete rule rows that may have been modified after seeding.
    }
}