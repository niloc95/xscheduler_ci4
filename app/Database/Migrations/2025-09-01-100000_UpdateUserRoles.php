<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class UpdateUserRoles extends MigrationBase
{
    public function up()
    {
        $db = $this->db;
        $usersTable = $db->prefixTable('users');

        if (!$db->tableExists($usersTable)) {
            return;
        }

        // Update the users table to support new role structure (prefix-safe)
        $this->modifyEnumColumn('users', [
            'role' => [
                'type' => 'ENUM',
                'constraint' => ['admin', 'provider', 'staff', 'customer'],
                'default' => 'customer',
                'null' => false,
            ],
        ]);

        // Add additional user fields if they don't exist
        $newFields = [];

        if (!$this->hasColumn($usersTable, 'provider_id')) {
            $newFields['provider_id'] = [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ];
        }

        if (!$this->hasColumn($usersTable, 'permissions')) {
            $newFields['permissions'] = [
                'type' => 'TEXT',
                'null' => true,
            ];
        }

        if (!$this->hasColumn($usersTable, 'is_active')) {
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
        $foreignKeys = $db->getForeignKeyData($usersTable);
        $hasProviderFK = false;

        foreach ($foreignKeys as $fk) {
            if ($fk->column_name === 'provider_id') {
                $hasProviderFK = true;
                break;
            }
        }

        if (!$hasProviderFK && $this->hasColumn($usersTable, 'provider_id')) {
            try {
                $this->mysqlOnly("ALTER TABLE `{$usersTable}` ADD CONSTRAINT `users_provider_fk` FOREIGN KEY (`provider_id`) REFERENCES `{$usersTable}`(`id`) ON DELETE SET NULL ON UPDATE CASCADE");
            } catch (\Exception $e) {
                // FK might already exist or there might be data issues, continue
                log_message('warning', 'Could not add provider_id foreign key: ' . $e->getMessage());
            }
        }

        if ($this->hasColumn($usersTable, 'role')) {
            $this->createIndexIfMissing('users', 'idx_users_role', ['role']);
        }

        if ($this->hasColumn($usersTable, 'is_active')) {
            $this->createIndexIfMissing('users', 'idx_users_active', ['is_active']);
        }
    }

    public function down()
    {
        $db = $this->db;
        $usersTable = $db->prefixTable('users');

        if (!$db->tableExists($usersTable)) {
            return;
        }

        // Remove foreign key if it exists
        try {
            $this->mysqlOnly("ALTER TABLE `{$usersTable}` DROP FOREIGN KEY `users_provider_fk`");
        } catch (\Exception $e) {
            // FK might not exist
        }
        
        // Remove added columns
        if ($this->hasColumn($usersTable, 'provider_id')) {
            try {
                $this->forge->dropColumn('users', 'provider_id');
            } catch (\Throwable $e) {
                // Column may already be absent on some refresh paths.
            }
        }
        if ($this->hasColumn($usersTable, 'permissions')) {
            try {
                $this->forge->dropColumn('users', 'permissions');
            } catch (\Throwable $e) {
                // Column may already be absent on some refresh paths.
            }
        }
        if ($this->hasColumn($usersTable, 'is_active')) {
            try {
                $this->forge->dropColumn('users', 'is_active');
            } catch (\Throwable $e) {
                // Column may already be absent on some refresh paths.
            }
        }
        
        // Revert role enum to original values
        $this->modifyEnumColumn('users', [
            'role' => [
                'type' => 'ENUM',
                'constraint' => ['customer', 'provider', 'admin'],
                'default' => 'customer',
            ],
        ]);
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
