<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddPaymentFieldsToAppointments extends MigrationBase
{
    public function up(): void
    {
        if (!$this->db->tableExists($this->db->prefixTable('appointments'))) {
            return;
        }

        $additions = [
            'payment_status'    => [
                'type'       => 'ENUM',
                'constraint' => ['none', 'pending', 'paid', 'failed', 'refunded'],
                'null'       => false,
                'default'    => 'none',
                'after'      => 'status',
            ],
            'payment_amount'    => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
                'default'    => null,
                'after'      => 'payment_status',
            ],
            'payment_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
                'default'    => null,
                'after'      => 'payment_amount',
            ],
        ];

        foreach ($additions as $column => $definition) {
            if (!$this->db->fieldExists($column, $this->db->prefixTable('appointments'))) {
                $this->forge->addColumn('appointments', $this->sanitiseFields([$column => $definition]));
            }
        }
    }

    public function down(): void
    {
        if (!$this->db->tableExists($this->db->prefixTable('appointments'))) {
            return;
        }

        foreach (['payment_status', 'payment_amount', 'payment_reference'] as $col) {
            if ($this->db->fieldExists($col, $this->db->prefixTable('appointments'))) {
                $this->forge->dropColumn('appointments', $col);
            }
        }
    }
}
