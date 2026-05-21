<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddWebhookSupportToBusinessIntegrations extends MigrationBase
{
    private string $table = 'business_integrations';

    public function up(): void
    {
        $prefixed = $this->db->prefixTable($this->table);

        if (!$this->db->tableExists($prefixed)) {
            return;
        }

        // Broaden the unique index from (business_id, channel) to
        // (business_id, channel, provider_name) so a business can have
        // multiple webhook endpoints with distinct provider_name labels.
        $this->dropIndexIfExists($this->table, 'uniq_integration_business_channel');

        if (!$this->indexExists($this->table, 'uniq_integration_business_channel_provider')) {
            $this->db->query(
                "ALTER TABLE `{$prefixed}`
                 ADD UNIQUE KEY `uniq_integration_business_channel_provider`
                 (`business_id`, `channel`, `provider_name`)"
            );
        }

        // metadata column for webhook last-delivery tracking
        if (!$this->db->fieldExists('metadata', $prefixed)) {
            $this->forge->addColumn($this->table, $this->sanitiseFields([
                'metadata' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'encrypted_config',
                ],
            ]));
        }
    }

    public function down(): void
    {
        $prefixed = $this->db->prefixTable($this->table);

        if (!$this->db->tableExists($prefixed)) {
            return;
        }

        $this->dropIndexIfExists($this->table, 'uniq_integration_business_channel_provider');

        if (!$this->indexExists($this->table, 'uniq_integration_business_channel')) {
            $this->db->query(
                "ALTER TABLE `{$prefixed}`
                 ADD UNIQUE KEY `uniq_integration_business_channel`
                 (`business_id`, `channel`)"
            );
        }

        if ($this->db->fieldExists('metadata', $prefixed)) {
            $this->forge->dropColumn($this->table, 'metadata');
        }
    }
}
