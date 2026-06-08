<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreatePaymentTransactionsTable extends MigrationBase
{
    private string $table = 'payment_transactions';

    public function up(): void
    {
        if ($this->db->tableExists($this->db->prefixTable($this->table))) {
            return;
        }

        $this->forge->addField($this->sanitiseFields([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'business_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 1,
            ],
            'appointment_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'gateway' => [
                'type'       => 'ENUM',
                'constraint' => ['payfast', 'stripe'],
                'null'       => false,
            ],
            'gateway_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => false,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => false,
            ],
            'currency' => [
                'type'       => 'VARCHAR',
                'constraint' => 3,
                'null'       => false,
                'default'    => 'ZAR',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'complete', 'failed', 'cancelled', 'refunded'],
                'null'       => false,
                'default'    => 'pending',
            ],
            'raw_payload' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]));

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('appointment_id');
        $this->forge->addKey('gateway_reference');
        $this->forge->addKey(['gateway', 'status']);

        $this->forge->createTable($this->table);
    }

    public function down(): void
    {
        if ($this->db->tableExists($this->db->prefixTable($this->table))) {
            $this->forge->dropTable($this->table);
        }
    }
}
