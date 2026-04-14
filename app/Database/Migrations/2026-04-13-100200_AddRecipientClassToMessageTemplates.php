<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddRecipientClassToMessageTemplates extends MigrationBase
{
    public function up(): void
    {
        $table = $this->db->prefixTable('message_templates');

        if (!$this->db->tableExists($table)) {
            return;
        }

        if (!$this->db->fieldExists('recipient_class', $table)) {
            $this->forge->addColumn('message_templates', [
                'recipient_class' => [
                    'type'       => 'ENUM',
                    'constraint' => ['customer', 'internal'],
                    'null'       => false,
                    'default'    => 'customer',
                    'after'      => 'locale',
                ],
            ]);
        }

        $this->createIndexIfMissing('message_templates', 'idx_mt_recipient_class', ['recipient_class']);
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('message_templates');

        if (!$this->db->tableExists($table)) {
            return;
        }

        if ($this->db->fieldExists('recipient_class', $table)) {
            $this->forge->dropColumn('message_templates', 'recipient_class');
        }
    }
}
