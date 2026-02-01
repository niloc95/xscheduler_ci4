<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class UpdateUserRoles extends MigrationBase
{
    public function up()
    {
        $db = $this->db;

        if (!$db->tableExists('users')) {
            return;
        }

        $usersTable = $db->prefixTable('users');

        // Update the users table to support new role structure (prefix-safe)
        $fields = [
            'role' => [
                'type' => 'ENUM',
                'constraint' => ['admin', 'provider', 'staff', 'customer'],
                'default' => 'customer',
                'null' => false,
            ],
        ];
        $this->forge->modifyColumn('users', $fields);

        // Add additional user fields if they don't exist
        $newFields = [];

        if (!$db->fieldExists('provider_id', 'users')) {
            $newFields['provider_id'] = [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ];
        }

        if (!$db->fieldExists('permissions', 'users')) {
            $newFields['permissions'] = [
                'type' => 'JSON',
                'null' => true,
            ];
        }

        if (!$db->fieldExists('is_active', 'users')) {
            $newFields['is_active'] = [
                'type' => 'BOOLEAN',
                'default' => true,
                'null' => false,
            ];
        }

        if (!empty($newFields)) {
            $this->forge->addColumn('users', $newFields);
        }

        // Add foreign key constraint for provider_id (staff belongs to provider) if it doesn't exist
        $foreignKeys = $db->getForeignKeyData('users');
        $hasProviderFK = false;

        foreach ($foreignKeys as $fk) {
            if ($fk->column_name === 'provider_id') {
                $hasProviderFK = true;
                break;
            }
        }

        if (!$hasProviderFK && $db->fieldExists('provider_id', 'users')) {
            try {
                $db->query("ALTER TABLE `{$usersTable}` ADD CONSTRAINT `users_provider_fk` FOREIGN KEY (`provider_id`) REFERENCES `{$usersTable}`(`id`) ON DELETE SET NULL ON UPDATE CASCADE");
            } catch (\Exception $e) {
                // FK might already exist or there might be data issues, continue
                log_message('warning', 'Could not add provider_id foreign key: ' . $e->getMessage());
            }
        }

        $this->createIndexIfMissing($usersTable, 'idx_users_role', ['role']);
        $this->createIndexIfMissing($usersTable, 'idx_users_provider_id', ['provider_id']);
        $this->createIndexIfMissing($usersTable, 'idx_users_active', ['is_active']);
    }

    public function down()
    {
    $db = $this->db;

        if (!$db->tableExists('users')) {
            return;
        }

        $usersTable = $db->prefixTable('users');

        // Remove foreign key if it exists
        try {
            $db->query("ALTER TABLE `{$usersTable}` DROP FOREIGN KEY `users_provider_fk`");
        } catch (\Exception $e) {
            // FK might not exist
        }
        
        // Remove added columns
        if ($db->fieldExists('provider_id', 'users')) {
            $this->forge->dropColumn('users', 'provider_id');
        }
        if ($db->fieldExists('permissions', 'users')) {
            $this->forge->dropColumn('users', 'permissions');
        }
        if ($db->fieldExists('is_active', 'users')) {
            $this->forge->dropColumn('users', 'is_active');
        }
        
        // Revert role enum to original values
        $fields = [
            'role' => [
                'type' => 'ENUM',
                'constraint' => ['customer', 'provider', 'admin'],
                'default' => 'customer',
            ],
        ];
        
        $this->forge->modifyColumn('users', $fields);
    }

    private function createIndexIfMissing(string $table, string $index, array $columns): void
    {
        $db = $this->db;
        
        // SQLite-compatible index existence check
        $driver = $db->DBDriver;
        
        if ($driver === 'SQLite3') {
            // For SQLite, query sqlite_master
            $exists = $db->query(
                "SELECT name FROM sqlite_master WHERE type='index' AND name=?",
                [$index]
            )->getFirstRow();
        } else {
            // For MySQL/other databases
            $exists = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])->getFirstRow();
        }
        
        if ($exists) {
            return;
        }

        $quotedColumns = array_map(static fn ($column) => "`{$column}`", $columns);
        $columnList = implode(', ', $quotedColumns);
        $db->query("CREATE INDEX `{$index}` ON `{$table}` ({$columnList})");
    }
}
