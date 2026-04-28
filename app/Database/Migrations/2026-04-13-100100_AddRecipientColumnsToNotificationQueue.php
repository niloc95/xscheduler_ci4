<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddRecipientColumnsToNotificationQueue extends MigrationBase
{
    public function up(): void
    {
        $table = $this->db->prefixTable('notification_queue');

        if (!$this->hasColumn($table, 'recipient_type')) {
            $this->forge->addColumn('notification_queue', [
                'recipient_type' => [
                    'type'       => 'ENUM',
                    'constraint' => ['customer', 'internal'],
                    'null'       => false,
                    'default'    => 'customer',
                    'after'      => 'appointment_id',
                ],
            ]);
        }

        if (!$this->hasColumn($table, 'recipient_user_id')) {
            $this->forge->addColumn('notification_queue', [
                'recipient_user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'recipient_type',
                ],
            ]);
        }

        $this->createIndexIfMissing('notification_queue', 'idx_nq_recipient_type', ['recipient_type']);

        // Foreign key: recipient_user_id → xs_users(id) ON DELETE SET NULL
        $prefixedQueue = $this->db->prefixTable('notification_queue');
        $prefixedUsers = $this->db->prefixTable('users');

        if ($this->hasColumn($prefixedQueue, 'recipient_user_id') && !$this->foreignKeyExists($prefixedQueue, 'fk_nq_recipient_user')) {
            $this->db->query(
                "ALTER TABLE `{$prefixedQueue}`
                 ADD CONSTRAINT `fk_nq_recipient_user`
                 FOREIGN KEY (`recipient_user_id`)
                 REFERENCES `{$prefixedUsers}` (`id`)
                 ON DELETE SET NULL"
            );
        }
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('notification_queue');

        if ($this->foreignKeyExists($table, 'fk_nq_recipient_user')) {
            $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `fk_nq_recipient_user`");
        }

        if ($this->hasColumn($table, 'recipient_user_id')) {
            $this->forge->dropColumn('notification_queue', 'recipient_user_id');
        }

        if ($this->hasColumn($table, 'recipient_type')) {
            $this->forge->dropColumn('notification_queue', 'recipient_type');
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

    private function foreignKeyExists(string $table, string $keyName): bool
    {
        $query = $this->db->query(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$this->db->database, $table, $keyName, 'FOREIGN KEY']
        );

        return $query->getFirstRow() !== null;
    }
}
