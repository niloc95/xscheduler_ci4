<?php

namespace App\Services;

class NotificationAutomationStatusService
{
    private LocalizationSettingsService $localizationService;

    public function __construct(?LocalizationSettingsService $localizationService = null)
    {
        $this->localizationService = $localizationService ?? new LocalizationSettingsService();
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(int $businessId = NotificationCatalog::BUSINESS_ID_DEFAULT): array
    {
        $heartbeat = (new NotificationReminderHeartbeatService())->getStatus(300);
        $db = \Config\Database::connect();
        $rulesTable = $db->prefixTable('business_notification_rules');
        $integrationsTable = $db->prefixTable('business_integrations');
        $queueTable = $db->prefixTable('notification_queue');
        $logsTable = $db->prefixTable('notification_delivery_logs');

        $ruleRows = $db->table($rulesTable)
            ->select('channel, is_enabled, reminder_offset_minutes, reminder_offsets_json')
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->get()
            ->getResultArray();

        $integrationRows = $db->table($integrationsTable)
            ->select('channel, is_active')
            ->where('business_id', $businessId)
            ->get()
            ->getResultArray();

        $enabledRuleChannels = [];
        $configuredOffsets = [];

        foreach ($ruleRows as $row) {
            $channel = (string) ($row['channel'] ?? '');
            if ($channel === '' || (int) ($row['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $enabledRuleChannels[] = $channel;
            $offsets = [];
            $offsetsJson = $row['reminder_offsets_json'] ?? null;
            if (is_string($offsetsJson) && $offsetsJson !== '') {
                $decoded = json_decode($offsetsJson, true);
                if (is_array($decoded)) {
                    $offsets = array_map(static fn($value): int => (int) $value, $decoded);
                }
            }

            if ($offsets === [] && isset($row['reminder_offset_minutes']) && $row['reminder_offset_minutes'] !== null) {
                $offsets = [(int) $row['reminder_offset_minutes']];
            }

            foreach ($offsets as $offset) {
                if ($offset >= 0) {
                    $configuredOffsets[] = $offset;
                }
            }
        }

        $activeIntegrations = [];
        foreach ($integrationRows as $row) {
            $channel = (string) ($row['channel'] ?? '');
            if ($channel !== '' && (int) ($row['is_active'] ?? 0) === 1) {
                $activeIntegrations[] = $channel;
            }
        }

        $enabledRuleChannels = array_values(array_unique($enabledRuleChannels));
        sort($enabledRuleChannels);
        $activeIntegrations = array_values(array_unique($activeIntegrations));
        sort($activeIntegrations);
        $configuredOffsets = array_values(array_unique($configuredOffsets));
        rsort($configuredOffsets);

        $activeReminderChannels = array_values(array_intersect($enabledRuleChannels, $activeIntegrations));
        sort($activeReminderChannels);

        $queuedCount = (int) $db->table($queueTable)
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->where('status', 'queued')
            ->countAllResults();

        $sentToday = (int) $db->table($queueTable)
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->where('status', 'sent')
            ->where('updated_at >=', gmdate('Y-m-d 00:00:00'))
            ->countAllResults();

        $lastDelivery = $db->table($logsTable)
            ->select('created_at, channel')
            ->where('business_id', $businessId)
            ->where('event_type', 'appointment_reminder')
            ->where('status', 'success')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getFirstRow('array');

        $summary = 'Automation active';
        $tone = 'success';

        if ($enabledRuleChannels === []) {
            $summary = 'No reminder rules enabled';
            $tone = 'warning';
        } elseif ($activeReminderChannels === []) {
            $summary = 'Rules enabled but integrations inactive';
            $tone = 'warning';
        } elseif (($heartbeat['last_run_ts'] ?? null) === null) {
            $summary = 'Waiting for first heartbeat run';
            $tone = 'warning';
        } elseif (!empty($heartbeat['is_stale'])) {
            $summary = $queuedCount > 0 ? 'Heartbeat stale with queued reminders' : 'Heartbeat stale';
            $tone = $queuedCount > 0 ? 'danger' : 'warning';
        }

        return [
            'summary' => $summary,
            'tone' => $tone,
            'last_run_label' => $this->formatTimestamp($heartbeat['last_run_ts'] ?? null),
            'last_delivery_label' => $this->formatTimestamp($lastDelivery['created_at'] ?? null),
            'queued_count' => $queuedCount,
            'sent_today' => $sentToday,
            'enabled_rule_channels' => $enabledRuleChannels,
            'active_channels' => $activeReminderChannels,
            'configured_offsets' => $configuredOffsets,
            'is_locked' => !empty($heartbeat['is_locked']),
            'heartbeat_interval_seconds' => (int) ($heartbeat['interval_seconds'] ?? 300),
        ];
    }

    /**
     * @param int|string|null $value
     */
    private function formatTimestamp($value): string
    {
        if ($value === null || $value === '') {
            return 'Not recorded yet';
        }

        $timezone = new \DateTimeZone($this->localizationService->getTimezone());

        try {
            if (is_numeric($value)) {
                $dt = (new \DateTimeImmutable('@' . (int) $value))->setTimezone($timezone);
            } else {
                $dt = new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
                $dt = $dt->setTimezone($timezone);
            }
        } catch (\Throwable $e) {
            return 'Unavailable';
        }

        return $dt->format('M j, Y g:i A T');
    }
}