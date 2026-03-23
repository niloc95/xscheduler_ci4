<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateBlockedTimesTableDuplicate extends MigrationBase
{
    public function up()
    {
        // Historical note:
        // This migration intentionally remains in the chain because some environments
        // may already record it as applied. The real blocked_times table was created
        // by 2025-07-13-120400_CreateBlockedTimesTable.php.
        // Duplicate migration detected. Original exists as 2025-07-13-120400_CreateBlockedTimesTable.php
        // Safe no-op: if table already exists, do nothing.
        if ($this->db->tableExists('blocked_times')) {
            return;
        }
    }

    public function down()
    {
        // Historical no-op: do not drop the table created by the original migration.
    }
}
