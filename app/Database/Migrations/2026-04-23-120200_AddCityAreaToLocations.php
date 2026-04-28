<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddCityAreaToLocations extends MigrationBase
{
    public function up(): void
    {
        $table = $this->db->prefixTable('locations');

        if (!$this->db->fieldExists('city', $table)) {
            $this->forge->addColumn('locations', [
                'city' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'address',
                ],
            ]);
        }

        if (!$this->db->fieldExists('area', $table)) {
            $this->forge->addColumn('locations', [
                'area' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'city',
                ],
            ]);
        }
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('locations');

        foreach (['area', 'city'] as $column) {
            if ($this->db->fieldExists($column, $table)) {
                $this->forge->dropColumn('locations', $column);
            }
        }
    }
}
