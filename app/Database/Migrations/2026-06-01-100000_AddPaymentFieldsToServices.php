<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddPaymentFieldsToServices extends MigrationBase
{
    public function up(): void
    {
        if (!$this->db->tableExists($this->db->prefixTable('services'))) {
            return;
        }

        $additions = [
            'payment_enabled'    => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'delivery_modes',
            ],
            'payfast_enabled'    => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'payment_enabled',
            ],
            'stripe_enabled'     => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'payfast_enabled',
            ],
            'deposit_percentage' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => true,
                'default'    => null,
                'after'      => 'stripe_enabled',
            ],
        ];

        foreach ($additions as $column => $definition) {
            if (!$this->db->fieldExists($column, $this->db->prefixTable('services'))) {
                $this->forge->addColumn('services', $this->sanitiseFields([$column => $definition]));
            }
        }
    }

    public function down(): void
    {
        if (!$this->db->tableExists($this->db->prefixTable('services'))) {
            return;
        }

        foreach (['payment_enabled', 'payfast_enabled', 'stripe_enabled', 'deposit_percentage'] as $col) {
            if ($this->db->fieldExists($col, $this->db->prefixTable('services'))) {
                $this->forge->dropColumn('services', $col);
            }
        }
    }
}
