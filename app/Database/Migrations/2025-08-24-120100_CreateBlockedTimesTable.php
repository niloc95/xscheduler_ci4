<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBlockedTimesTableDuplicate extends Migration
{
    public function up()
    {
        // Duplicate migration detected. Original exists as 2025-07-13-120400_CreateBlockedTimesTable.php
        // Safe no-op: if table already exists, do nothing.
        if ($this->db->tableExists('blocked_times')) {
            return;
        }
    }

    public function down()
    {
        // No-op: do not drop table created by the original migration
    }
}
