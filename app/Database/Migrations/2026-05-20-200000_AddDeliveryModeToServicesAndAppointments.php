<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddDeliveryModeToServicesAndAppointments extends MigrationBase
{
    public function up(): void
    {
        if ($this->db->tableExists($this->db->prefixTable('services'))) {
            if (!$this->db->fieldExists('delivery_modes', $this->db->prefixTable('services'))) {
                $this->forge->addColumn('services', $this->sanitiseFields([
                    'delivery_modes' => [
                        'type'    => 'VARCHAR',
                        'constraint' => 255,
                        'null'    => true,
                        'default' => null,
                        'after'   => 'active',
                    ],
                ]));
            }
        }

        if ($this->db->tableExists($this->db->prefixTable('appointments'))) {
            if (!$this->db->fieldExists('delivery_mode', $this->db->prefixTable('appointments'))) {
                $this->forge->addColumn('appointments', $this->sanitiseFields([
                    'delivery_mode' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 20,
                        'null'       => true,
                        'default'    => null,
                        'after'      => 'notes',
                    ],
                ]));
            }

            if (!$this->db->fieldExists('video_link', $this->db->prefixTable('appointments'))) {
                $this->forge->addColumn('appointments', $this->sanitiseFields([
                    'video_link' => [
                        'type'  => 'TEXT',
                        'null'  => true,
                        'after' => 'delivery_mode',
                    ],
                ]));
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists($this->db->prefixTable('services'))) {
            if ($this->db->fieldExists('delivery_modes', $this->db->prefixTable('services'))) {
                $this->forge->dropColumn('services', 'delivery_modes');
            }
        }

        if ($this->db->tableExists($this->db->prefixTable('appointments'))) {
            foreach (['delivery_mode', 'video_link'] as $col) {
                if ($this->db->fieldExists($col, $this->db->prefixTable('appointments'))) {
                    $this->forge->dropColumn('appointments', $col);
                }
            }
        }
    }
}
