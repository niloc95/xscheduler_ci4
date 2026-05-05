<?php

namespace App\Commands;

use App\Services\NotificationCatalog;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Repairs xs_business_notification_rules and xs_business_integrations rows
 * that were written under the wrong business_id due to a now-fixed injection
 * vector in NotificationSettingsService::resolveBusinessId().
 *
 * Usage:
 *   php spark notifications:repair-business-id          # dry-run (default)
 *   php spark notifications:repair-business-id --apply  # apply changes
 *   php spark notifications:repair-business-id --from=101211 --to=1 --apply
 */
class RepairNotificationBusinessId extends BaseCommand
{
    protected $group = 'notifications';

    protected $name = 'notifications:repair-business-id';

    protected $description = 'Repair xs_business_notification_rules and xs_business_integrations rows saved under the wrong business_id.';

    protected $usage = 'notifications:repair-business-id [--from=<bad_id>] [--to=<good_id>] [--apply]';

    protected $options = [
        '--from'  => 'Source (bad) business_id to migrate away from. Defaults to auto-detect.',
        '--to'    => 'Target (good) business_id to migrate to. Defaults to ' . NotificationCatalog::BUSINESS_ID_DEFAULT . '.',
        '--apply' => 'Apply changes. Without this flag the command runs in dry-run mode.',
    ];

    /**
     * @param array<int, string> $params
     */
    public function run(array $params): void
    {
        $apply   = CLI::getOption('apply') !== null;
        $toId    = (int) (CLI::getOption('to') ?? NotificationCatalog::BUSINESS_ID_DEFAULT);
        $fromRaw = CLI::getOption('from');

        if ($toId <= 0) {
            $toId = NotificationCatalog::BUSINESS_ID_DEFAULT;
        }

        CLI::newLine();
        CLI::write('WebScheduler – Notification Business-ID Repair', 'yellow');
        CLI::write('Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN'), 'yellow');
        CLI::write('Target (good) business_id: ' . $toId, 'cyan');
        CLI::newLine();

        $db = \Config\Database::connect();

        // ── Detect bad business IDs ─────────────────────────────────────────
        if ($fromRaw !== null) {
            $badIds = [(int) $fromRaw];
        } else {
            $badIds = $this->detectBadBusinessIds($db, $toId);
        }

        if ($badIds === []) {
            CLI::write('✓ No foreign business_id rows detected. Nothing to repair.', 'green');
            CLI::newLine();
            return;
        }

        CLI::write('Foreign business_id values found: ' . implode(', ', $badIds), 'light_red');
        CLI::newLine();

        $totalRulesMigrated    = 0;
        $totalRulesDeleted     = 0;
        $totalIntegMigrated    = 0;
        $totalIntegDeleted     = 0;

        foreach ($badIds as $fromId) {
            CLI::write('── Processing business_id=' . $fromId . ' → ' . $toId . ' ──', 'cyan');

            [$rm, $rd] = $this->repairRules($db, $fromId, $toId, $apply);
            [$im, $id] = $this->repairIntegrations($db, $fromId, $toId, $apply);

            $totalRulesMigrated += $rm;
            $totalRulesDeleted  += $rd;
            $totalIntegMigrated += $im;
            $totalIntegDeleted  += $id;
        }

        CLI::newLine();
        CLI::write('── Summary ──────────────────────────────', 'yellow');
        CLI::write('Rules migrated/updated: ' . $totalRulesMigrated);
        CLI::write('Rules deleted (source): ' . $totalRulesDeleted);
        CLI::write('Integrations migrated:  ' . $totalIntegMigrated);
        CLI::write('Integrations deleted:   ' . $totalIntegDeleted);

        if (!$apply) {
            CLI::newLine();
            CLI::write('Dry-run complete. Run with --apply to execute changes.', 'yellow');
        } else {
            CLI::newLine();
            CLI::write('✓ Repair complete.', 'green');
            CLI::write('Tip: run "php spark notifications:dispatch-queue ' . $toId . '" to verify enqueue works.', 'light_gray');
        }

        CLI::newLine();
    }

    /**
     * Detect business_id values in notification tables that are not $canonicalId.
     *
     * @return int[]
     */
    private function detectBadBusinessIds(\CodeIgniter\Database\BaseConnection $db, int $canonicalId): array
    {
        $bad = [];

        foreach (['xs_business_notification_rules', 'xs_business_integrations'] as $table) {
            if (!$db->tableExists($table)) {
                continue;
            }
            $rows = $db->table($table)
                ->select('business_id')
                ->where('business_id !=', $canonicalId)
                ->groupBy('business_id')
                ->orderBy('business_id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $id = (int) $row['business_id'];
                if ($id > 0 && !in_array($id, $bad, true)) {
                    $bad[] = $id;
                }
            }
        }

        return $bad;
    }

    /**
     * Migrate xs_business_notification_rules rows from $fromId to $toId.
     *
     * Strategy:
     *   - For each (event_type, channel) pair from source:
     *     - If no target row exists → insert copy with $toId
     *     - If target row exists but is_enabled=0 and source is_enabled=1 → update target to match source config
     *     - If target row exists and is already enabled → skip (trust existing config)
     *   - Delete source rows after migration (apply only)
     *
     * @return array{int, int} [migrated, deleted]
     */
    private function repairRules(\CodeIgniter\Database\BaseConnection $db, int $fromId, int $toId, bool $apply): array
    {
        $table = 'xs_business_notification_rules';
        if (!$db->tableExists($table)) {
            CLI::write('  [rules] Table not found — skip.', 'light_gray');
            return [0, 0];
        }

        $sourceRows = $db->table($table)
            ->where('business_id', $fromId)
            ->get()
            ->getResultArray();

        if ($sourceRows === []) {
            CLI::write('  [rules] No source rows for business_id=' . $fromId, 'light_gray');
            return [0, 0];
        }

        CLI::write('  [rules] Source rows: ' . count($sourceRows));

        $migrated = 0;
        $fields   = $db->getFieldNames($table);

        foreach ($sourceRows as $src) {
            $eventType = $src['event_type'];
            $channel   = $src['channel'];

            $target = $db->table($table)
                ->where('business_id', $toId)
                ->where('event_type', $eventType)
                ->where('channel', $channel)
                ->get()
                ->getRowArray();

            if ($target === null) {
                // No target row — insert a copy.
                $payload = ['business_id' => $toId];
                foreach (['event_type', 'channel', 'is_enabled', 'reminder_offset_minutes'] as $f) {
                    if (in_array($f, $fields, true)) {
                        $payload[$f] = $src[$f] ?? null;
                    }
                }
                if (in_array('reminder_offsets_json', $fields, true)) {
                    $payload['reminder_offsets_json'] = $src['reminder_offsets_json'] ?? null;
                }
                $payload['created_at'] = date('Y-m-d H:i:s');
                $payload['updated_at'] = date('Y-m-d H:i:s');

                CLI::write('  [rules] INSERT ' . $channel . '/' . $eventType . ' (is_enabled=' . (int) $src['is_enabled'] . ')');
                if ($apply) {
                    $db->table($table)->insert($payload);
                }
                $migrated++;
            } elseif ((int) ($target['is_enabled'] ?? 0) === 0 && (int) ($src['is_enabled'] ?? 0) === 1) {
                // Target row exists but is disabled; source is enabled — promote.
                $update = ['is_enabled' => 1, 'updated_at' => date('Y-m-d H:i:s')];
                if (in_array('reminder_offset_minutes', $fields, true)) {
                    $update['reminder_offset_minutes'] = $src['reminder_offset_minutes'] ?? null;
                }
                if (in_array('reminder_offsets_json', $fields, true)) {
                    $update['reminder_offsets_json'] = $src['reminder_offsets_json'] ?? null;
                }

                CLI::write('  [rules] UPDATE ' . $channel . '/' . $eventType . ' → enable + copy offsets');
                if ($apply) {
                    $db->table($table)
                        ->where('id', (int) $target['id'])
                        ->update($update);
                }
                $migrated++;
            } else {
                CLI::write('  [rules] SKIP ' . $channel . '/' . $eventType . ' (target already configured)', 'light_gray');
            }
        }

        $deleted = 0;
        if ($apply) {
            $db->table($table)->where('business_id', $fromId)->delete();
            $deleted = count($sourceRows);
            CLI::write('  [rules] Deleted ' . $deleted . ' source rows (business_id=' . $fromId . ')');
        } else {
            CLI::write('  [rules] Would delete ' . count($sourceRows) . ' source rows (dry-run)');
        }

        return [$migrated, $deleted];
    }

    /**
     * Migrate xs_business_integrations rows from $fromId to $toId.
     *
     * Strategy:
     *   - For each channel from source:
     *     - If no target row exists → insert copy with $toId
     *     - If target row exists but is_active=0 and source is_active=1 → update target
     *     - Otherwise skip (trust existing config)
     *   - Delete source rows after migration (apply only)
     *
     * @return array{int, int} [migrated, deleted]
     */
    private function repairIntegrations(\CodeIgniter\Database\BaseConnection $db, int $fromId, int $toId, bool $apply): array
    {
        $table = 'xs_business_integrations';
        if (!$db->tableExists($table)) {
            CLI::write('  [integrations] Table not found — skip.', 'light_gray');
            return [0, 0];
        }

        $sourceRows = $db->table($table)
            ->where('business_id', $fromId)
            ->get()
            ->getResultArray();

        if ($sourceRows === []) {
            CLI::write('  [integrations] No source rows for business_id=' . $fromId, 'light_gray');
            return [0, 0];
        }

        CLI::write('  [integrations] Source rows: ' . count($sourceRows));

        $migrated = 0;
        $fields   = $db->getFieldNames($table);

        foreach ($sourceRows as $src) {
            $channel = $src['channel'];

            $target = $db->table($table)
                ->where('business_id', $toId)
                ->where('channel', $channel)
                ->get()
                ->getRowArray();

            if ($target === null) {
                // No target row — insert copy.
                $payload = ['business_id' => $toId];
                foreach ($fields as $f) {
                    if (in_array($f, ['id', 'business_id', 'created_at', 'updated_at'], true)) {
                        continue;
                    }
                    $payload[$f] = $src[$f] ?? null;
                }
                $payload['created_at'] = date('Y-m-d H:i:s');
                $payload['updated_at'] = date('Y-m-d H:i:s');

                CLI::write('  [integrations] INSERT channel=' . $channel . ' (is_active=' . (int) ($src['is_active'] ?? 0) . ')');
                if ($apply) {
                    $db->table($table)->insert($payload);
                }
                $migrated++;
            } elseif ((int) ($target['is_active'] ?? 0) === 0 && (int) ($src['is_active'] ?? 0) === 1) {
                // Target exists but inactive; source is active — promote.
                $update = ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
                // Copy non-null encrypted config fields from source if target has none.
                foreach ($fields as $f) {
                    if (in_array($f, ['id', 'business_id', 'channel', 'is_active', 'created_at', 'updated_at'], true)) {
                        continue;
                    }
                    if (!empty($src[$f]) && empty($target[$f])) {
                        $update[$f] = $src[$f];
                    }
                }

                CLI::write('  [integrations] UPDATE channel=' . $channel . ' → activate + copy config fields');
                if ($apply) {
                    $db->table($table)
                        ->where('id', (int) $target['id'])
                        ->update($update);
                }
                $migrated++;
            } else {
                CLI::write('  [integrations] SKIP channel=' . $channel . ' (target already configured)', 'light_gray');
            }
        }

        $deleted = 0;
        if ($apply) {
            $db->table($table)->where('business_id', $fromId)->delete();
            $deleted = count($sourceRows);
            CLI::write('  [integrations] Deleted ' . $deleted . ' source rows (business_id=' . $fromId . ')');
        } else {
            CLI::write('  [integrations] Would delete ' . count($sourceRows) . ' source rows (dry-run)');
        }

        return [$migrated, $deleted];
    }
}
