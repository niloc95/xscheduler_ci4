<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class BackfillCustomerTimestamps extends MigrationBase
{
    public function up()
    {
        $table = $this->db->prefixTable('customers');
        $now   = date('Y-m-d H:i:s');

        // Customers created via the public booking flow before this fix had
        // created_at = '0000-00-00 00:00:00' because CustomerService::buildInsertPayload()
        // did not include timestamps and CustomerModel::$useTimestamps = false.
        //
        // The literal '0000-00-00 00:00:00' is rejected by MySQL strict mode in WHERE clauses,
        // so use a date-range comparison: any date before 1970-01-01 is a sentinel zero-date.
        $this->db->query("
            UPDATE `{$table}`
            SET created_at = '{$now}'
            WHERE created_at IS NULL
               OR created_at < '1970-01-01 00:00:01'
        ");

        $this->db->query("
            UPDATE `{$table}`
            SET updated_at = created_at
            WHERE updated_at IS NULL
               OR updated_at < '1970-01-01 00:00:01'
        ");
    }

    public function down()
    {
        // No safe rollback — cannot determine which rows were backfilled vs. originally set.
    }
}
