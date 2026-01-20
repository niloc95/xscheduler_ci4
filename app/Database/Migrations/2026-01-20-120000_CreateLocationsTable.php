<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Creates the xs_locations table for provider multi-location support.
 * 
 * Each provider can have multiple locations with:
 * - Friendly name (primary identifier)
 * - Full physical address
 * - Contact number
 * - Assigned working days (stored in xs_location_days)
 * 
 * Working hours remain global to the provider across all locations.
 */
class CreateLocationsTable extends MigrationBase
{
    public function up()
    {
        // Main locations table
        $this->forge->addField([
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
                'comment'    => 'FK to xs_users (provider)',
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'comment'    => 'Friendly location name (e.g., Melrose Practice)',
            ],
            'address' => [
                'type'    => 'TEXT',
                'comment' => 'Full physical address',
            ],
            'contact_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'Location-specific contact number',
            ],
            'is_primary' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'comment'    => 'Primary/default location for this provider',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('provider_id');
        $this->forge->addKey('is_active');
        $this->forge->addForeignKey('provider_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('locations', true);

        // Location working days pivot table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'location_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'day_of_week' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'comment'    => '0=Sunday, 1=Monday, ... 6=Saturday',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('location_id');
        $this->forge->addKey(['location_id', 'day_of_week'], false, true); // Unique constraint
        $this->forge->addForeignKey('location_id', 'locations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('location_days', true);
    }

    public function down()
    {
        $this->forge->dropTable('location_days', true);
        $this->forge->dropTable('locations', true);
    }
}
