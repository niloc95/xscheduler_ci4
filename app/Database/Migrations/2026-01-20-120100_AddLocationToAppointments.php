<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Adds location snapshot fields to xs_appointments.
 * 
 * Stores location data at booking time for:
 * - Historical accuracy (location details may change)
 * - Notification placeholders
 * - Reporting purposes
 */
class AddLocationToAppointments extends MigrationBase
{
    public function up()
    {
        // Guard: skip if appointments table doesn't exist yet
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $fields = $this->sanitiseFields([
            'location_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'FK to xs_locations (nullable for legacy appointments)',
            ],
            'location_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Snapshot: Friendly location name at booking time',
            ],
            'location_address' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Snapshot: Full address at booking time',
            ],
            'location_contact' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'comment'    => 'Snapshot: Contact number at booking time',
            ],
        ]);

        $this->forge->addColumn('appointments', $fields);

        // Add index for location queries (cross-database)
        $this->createIndexIfMissing('appointments', 'idx_location_id', ['location_id']);
    }

    public function down()
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $this->forge->dropColumn('appointments', ['location_id', 'location_name', 'location_address', 'location_contact']);
    }
}
