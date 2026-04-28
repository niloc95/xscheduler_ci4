<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Compatibility stub for the original CreateSettingsFiles migration.
 *
 * The xs_settings_files table was later removed via DropSettingsFilesTable.
 * This no-op stub preserves the historical version record so MigrationRunner
 * can match the DB entry during rollback without raising a sequence-gap error.
 */
class CreateSettingsFiles extends MigrationBase
{
    public function up(): void
    {
        // No-op: table was removed in a later migration.
    }

    public function down(): void
    {
        // No-op.
    }
}
