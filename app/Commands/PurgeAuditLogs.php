<?php

namespace App\Commands;

use App\Models\AuditLogModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PurgeAuditLogs extends BaseCommand
{
    protected $group = 'audit';

    protected $name = 'audit:purge-logs';

    protected $description = 'Purge audit logs older than N days (default 365).';

    protected $usage = 'audit:purge-logs [days] [--dry-run]';

    protected $arguments = [
        'days' => 'Retention in days. Defaults to 365.',
    ];

    protected $options = [
        '--dry-run' => 'Only count matching rows, do not delete.',
    ];

    /**
     * @param array<int, string> $params
     */
    public function run(array $params)
    {
        $days = (int) ($params[0] ?? 365);
        if ($days <= 0) {
            $days = 365;
        }

        $dryRun = CLI::getOption('dry-run') !== null;
        $before = (new \DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        $db = db_connect();
        $table = $db->prefixTable('audit_logs');
        if (!$db->tableExists($table)) {
            CLI::error('Audit log table not found: ' . $table);
            return;
        }

        $model = new AuditLogModel();
        $builder = $model->builder();
        $builder->where('created_at <', $before);

        $count = (int) $builder->countAllResults(false);
        if ($dryRun) {
            CLI::write('Dry run: ' . $count . ' audit logs older than ' . $before . ' would be purged.', 'yellow');
            return;
        }

        $deleted = $builder->delete();
        if ($deleted === false) {
            CLI::error('Purge failed.');
            return;
        }

        CLI::write('Purged ' . $count . ' audit logs older than ' . $before . '.', 'green');
    }
}
