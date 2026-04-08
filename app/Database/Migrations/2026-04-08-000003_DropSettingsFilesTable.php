<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class DropSettingsFilesTable extends MigrationBase
{
    public function up()
    {
        // xs_settings_files stored branding assets (logos, favicons) as MEDIUMBLOB.
        // In practice the table was always empty — logo/icon uploads stored the file
        // on disk under public/assets/settings/ and wrote the relative path to
        // xs_settings. The DB-blob path was a never-triggered fallback.
        // Remove the table; branding files are served exclusively from disk.
        if ($this->db->tableExists('settings_files')) {
            $this->forge->dropTable('settings_files', true);
        }
    }

    public function down()
    {
        // Intentionally not reversible. Files are now disk-only.
    }
}
