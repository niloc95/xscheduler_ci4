<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class ExpandBusinessIntegrationsChannel extends MigrationBase
{
    private string $table = 'business_integrations';

    public function up(): void
    {
        $prefixed = $this->db->prefixTable($this->table);

        if (!$this->db->tableExists($prefixed)) {
            return;
        }

        $this->mysqlOnly(
            "ALTER TABLE `{$prefixed}` MODIFY COLUMN `channel`
             ENUM('email','sms','whatsapp','webhook','google_calendar','stripe','zoom','slack') NOT NULL"
        );
    }

    public function down(): void
    {
        $prefixed = $this->db->prefixTable($this->table);

        if (!$this->db->tableExists($prefixed)) {
            return;
        }

        // Remove any rows using the new channel values before reverting
        $this->db->query(
            "DELETE FROM `{$prefixed}` WHERE `channel` NOT IN ('email','sms','whatsapp')"
        );

        $this->mysqlOnly(
            "ALTER TABLE `{$prefixed}` MODIFY COLUMN `channel`
             ENUM('email','sms','whatsapp') NOT NULL"
        );
    }
}
