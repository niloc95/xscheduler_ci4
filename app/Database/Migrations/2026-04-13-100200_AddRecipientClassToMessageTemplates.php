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

        if (!$this->hasColumn($table, 'recipient_class')) {
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

        if ($this->hasColumn($table, 'recipient_class')) {
            $this->forge->dropColumn('message_templates', 'recipient_class');
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $query = $this->db->query(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$this->db->database, $table, $column]
        );

        return $query->getFirstRow() !== null;
    }
}
