<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddCompositeIndexesToAppointments extends MigrationBase
{
    public function up()
    {
        // Add composite indexes to speed up counts and range queries with filters
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_appts_provider_start ON appointments (provider_id, start_time)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_appts_service_start ON appointments (service_id, start_time)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_appts_status_start ON appointments (status, start_time)');
    }

    public function down()
    {
        // Drop indexes if they exist
        $this->db->query('DROP INDEX IF EXISTS idx_appts_provider_start ON appointments');
        $this->db->query('DROP INDEX IF EXISTS idx_appts_service_start ON appointments');
        $this->db->query('DROP INDEX IF EXISTS idx_appts_status_start ON appointments');
    }
}
