<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateProviderStaffAssignments extends MigrationBase
{
    public function up()
    {
        $this->forge->addField($this->sanitiseFields([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'provider_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'staff_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'assigned_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]));

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['provider_id', 'staff_id'], 'provider_staff_unique');
        $this->forge->addForeignKey('provider_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('staff_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('provider_staff_assignments', true);

        // Seed existing staff assignments based on legacy provider_id value
        $db = Database::connect();
        $builder = $db->table('users');

        $staffRows = $builder
            ->select('id as staff_id, provider_id')
            ->whereIn('role', ['staff', 'receptionist'])
            ->where('provider_id IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        if (!empty($staffRows)) {
            $assignBuilder = $db->table('provider_staff_assignments');
            foreach ($staffRows as $row) {
                if (empty($row['provider_id'])) {
                    continue;
                }

                try {
                    $assignBuilder->insert([
                        'provider_id' => (int) $row['provider_id'],
                        'staff_id'    => (int) $row['staff_id'],
                    ], false);
                } catch (\Throwable $e) {
                    // Ignore duplicates or constraint violations during backfill
                }
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('provider_staff_assignments', true);
    }
}
