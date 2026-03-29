<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SchemaParityRepairSeeder extends Seeder
{
    public function run(): void
    {
        $this->repairServicesBufferColumns();
        $this->repairNotificationQueueCorrelationId();
    }

    private function repairServicesBufferColumns(): void
    {
        if (!$this->db->tableExists($this->db->prefixTable('services'))) {
            return;
        }

        if (!$this->db->fieldExists('buffer_before', 'services')) {
            $this->forge->addColumn('services', [
                'buffer_before' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                    'null'       => false,
                    'after'      => 'duration_min',
                ],
            ]);
        }

        if (!$this->db->fieldExists('buffer_after', 'services')) {
            $this->forge->addColumn('services', [
                'buffer_after' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                    'null'       => false,
                    'after'      => 'buffer_before',
                ],
            ]);
        }
    }

    private function repairNotificationQueueCorrelationId(): void
    {
        if (!$this->db->tableExists($this->db->prefixTable('notification_queue'))) {
            return;
        }

        if (!$this->db->fieldExists('correlation_id', 'notification_queue')) {
            $this->forge->addColumn('notification_queue', [
                'correlation_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'after'      => 'idempotency_key',
                ],
            ]);
        }

        $prefixedTable = $this->db->prefixTable('notification_queue');
        $indexRows = $this->db->query("SHOW INDEX FROM {$prefixedTable} WHERE Key_name = 'idx_notification_queue_correlation_id'")->getResultArray();

        if ($indexRows === []) {
            $this->db->query("CREATE INDEX idx_notification_queue_correlation_id ON {$prefixedTable} (correlation_id)");
        }
    }
}
