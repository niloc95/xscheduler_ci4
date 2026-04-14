<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class AddRecipientColumnsToNotificationQueue extends MigrationBase
{
    public function up(): void
    {
        $table = $this->db->prefixTable('notification_queue');

        if (!$this->db->fieldExists('recipient_type', $table)) {
            $this->forge->addColumn('notification_queue', [
                'recipient_type' => [
                    'type'       => 'ENUM',
                    'constraint' => ['customer', 'internal'],
                    'null'       => false,
                    'default'    => 'customer',
                    'after'      => 'appointment_id',
                ],
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

        $fkExists = $this->db->query(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_NAME = ? AND COLUMN_NAME = 'recipient_user_id'
               AND REFERENCED_TABLE_NAME = ?
               AND TABLE_SCHEMA = DATABASE()",
            [$prefixedQueue, $prefixedUsers]
        )->getFirstRow();

        if (!$fkExists) {
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

        // Drop FK first
        try {
            $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `fk_nq_recipient_user`");
        } catch (\Throwable $e) {
            // FK may not exist
        }

        if ($this->db->fieldExists('recipient_user_id', $table)) {
            $this->forge->dropColumn('notification_queue', 'recipient_user_id');
        }

        if ($this->db->fieldExists('recipient_type', $table)) {
            $this->forge->dropColumn('notification_queue', 'recipient_type');
        }
    }
}
