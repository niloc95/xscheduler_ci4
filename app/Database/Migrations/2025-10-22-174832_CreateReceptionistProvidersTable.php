<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Compatibility stub for the original CreateReceptionistProvidersTable migration.
 *
 * The receptionist_providers feature was later removed; this no-op stub
 * preserves the historical version record so MigrationRunner can match
 * the DB entry and roll back without raising a sequence-gap error.
 */
class CreateReceptionistProvidersTable extends MigrationBase
{
    public function up()
    {
        // Intentionally no-op.
    }

    public function down()
    {
        // Intentionally no-op.
    }
}
