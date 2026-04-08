<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

/**
 * Create xs_user_roles table — the foundation for multi-role user support.
 *
 * A single user (identified by email) may now hold multiple roles simultaneously.
 * This table is the authoritative source for role assignments; xs_users.role is
 * kept as the derived primary (highest-privilege) role for backwards compat.
 *
 * On upgrade (up): creates the table and backfills one row per existing xs_users
 * record from xs_users.role so existing data is preserved.
 *
 * On rollback (down): drops the table cleanly.
 */
class CreateUserRolesTable extends MigrationBase
{
    public function up(): void
    {
        // xs_user_roles: one row per (user, role) pair
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['admin', 'provider', 'staff'],
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        // Unique per (user_id, role) — prevents double-assignment
        $this->forge->addKey(['user_id', 'role'], false, true, 'uq_user_role');
        $this->forge->addKey('user_id', false, false, 'idx_user_roles_user_id');

        $this->forge->createTable('user_roles', true);

        // FK: cascade deletions so removing a user cleans up their roles
        $usersTable    = $this->db->prefixTable('users');
        $userRolesTable = $this->db->prefixTable('user_roles');

        try {
            $this->db->query(
                "ALTER TABLE `{$userRolesTable}`
                 ADD CONSTRAINT `fk_user_roles_user`
                 FOREIGN KEY (`user_id`) REFERENCES `{$usersTable}`(`id`)
                 ON DELETE CASCADE ON UPDATE CASCADE"
            );
        } catch (\Throwable $e) {
            // FK may already exist or DB doesn't support it — not fatal
            log_message('warning', '[CreateUserRolesTable] Could not add FK: ' . $e->getMessage());
        }

        // Backfill: one row per existing user from xs_users.role
        $now   = date('Y-m-d H:i:s');
        $users = $this->db->table($usersTable)
            ->select('id, role')
            ->whereIn('role', ['admin', 'provider', 'staff'])
            ->get()
            ->getResultArray();

        if (!empty($users)) {
            $rows = array_map(
                static fn(array $u): array => [
                    'user_id'    => (int) $u['id'],
                    'role'       => $u['role'],
                    'created_at' => $now,
                ],
                $users
            );
            $this->db->table($userRolesTable)->insertBatch($rows);
            log_message('info', '[CreateUserRolesTable] Backfilled ' . count($rows) . ' user-role rows from xs_users.');
        }
    }

    public function down(): void
    {
        $userRolesTable = $this->db->prefixTable('user_roles');

        try {
            $this->db->query("ALTER TABLE `{$userRolesTable}` DROP FOREIGN KEY `fk_user_roles_user`");
        } catch (\Throwable) {
            // FK may not exist — safe to continue
        }

        $this->forge->dropTable('user_roles', true);
    }
}
