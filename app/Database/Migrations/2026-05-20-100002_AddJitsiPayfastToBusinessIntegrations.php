<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddJitsiPayfastToBusinessIntegrations extends MigrationBase
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
             ENUM('email','sms','whatsapp','webhook','google_calendar','stripe','zoom','slack','jitsi','payfast') NOT NULL"
        );
    }

    public function down(): void
    {
        $prefixed = $this->db->prefixTable($this->table);

        if (!$this->db->tableExists($prefixed)) {
            return;
        }

        $this->db->query(
            "DELETE FROM `{$prefixed}` WHERE `channel` IN ('jitsi','payfast')"
        );

        $this->mysqlOnly(
            "ALTER TABLE `{$prefixed}` MODIFY COLUMN `channel`
             ENUM('email','sms','whatsapp','webhook','google_calendar','stripe','zoom','slack') NOT NULL"
        );
    }
}
