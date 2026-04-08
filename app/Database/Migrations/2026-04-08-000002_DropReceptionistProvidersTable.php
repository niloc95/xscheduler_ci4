<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class DropReceptionistProvidersTable extends MigrationBase
{
    public function up()
    {
        // xs_receptionist_providers was a short-lived legacy table from when
        // "receptionist" was a first-class role. The role was converted to "staff"
        // in 2025-10-22-183826_ConvertReceptionistsToStaff. Active assignment logic
        // now lives in xs_provider_staff_assignments. Remove the legacy table.
        if ($this->db->tableExists('receptionist_providers')) {
            $this->forge->dropTable('receptionist_providers', true);
        }
    }

    public function down()
    {
        // Intentionally not reversible. The receptionist role no longer exists.
    }
}
