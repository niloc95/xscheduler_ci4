<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddCompositeIndexesToAppointments extends MigrationBase
{
    public function up()
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $table = 'appointments';

        // Add composite indexes to speed up counts and range queries with filters
        $this->createIndexIfMissing($table, 'idx_appts_provider_start', ['provider_id', 'start_time']);
        $this->createIndexIfMissing($table, 'idx_appts_service_start', ['service_id', 'start_time']);
        $this->createIndexIfMissing($table, 'idx_appts_status_start', ['status', 'start_time']);
    }

    public function down()
    {
        if (!$this->db->tableExists('appointments')) {
            return;
        }

        $table = 'appointments';

        // Keep provider/service composite indexes in place because MySQL may
        // rely on them to satisfy foreign key index requirements.
        $this->dropIndexIfExists($table, 'idx_appts_status_start');
    }
}
