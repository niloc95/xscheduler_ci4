<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateUserRoles extends Migration
{
    public function up()
    {
        // Update the users table to support new role structure
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
        
        if (!$this->db->fieldExists('provider_id', 'users')) {
            $newFields['provider_id'] = [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'role'
            ];
        }
        
        if (!$this->db->fieldExists('permissions', 'users')) {
            $newFields['permissions'] = [
                'type' => 'JSON',
                'null' => true,
                'after' => isset($newFields['provider_id']) ? 'provider_id' : 'role'
            ];
        }
        
        if (!$this->db->fieldExists('is_active', 'users')) {
            $newFields['is_active'] = [
                'type' => 'BOOLEAN',
                'default' => true,
                'null' => false,
                'after' => isset($newFields['permissions']) ? 'permissions' : (isset($newFields['provider_id']) ? 'provider_id' : 'role')
            ];
        }
        
        if (!empty($newFields)) {
            $this->forge->addColumn('users', $newFields);
        }
        
        // Add foreign key constraint for provider_id (staff belongs to provider) if it doesn't exist
        $foreignKeys = $this->db->getForeignKeyData('users');
        $hasProviderFK = false;
        
        foreach ($foreignKeys as $fk) {
            if ($fk->column_name === 'provider_id') {
                $hasProviderFK = true;
                break;
            }
        }
        
        if (!$hasProviderFK && $this->db->fieldExists('provider_id', 'users')) {
            try {
                $this->forge->addForeignKey('provider_id', 'users', 'id', 'SET NULL', 'CASCADE', 'users_provider_fk');
            } catch (\Exception $e) {
                // FK might already exist or there might be data issues, continue
                log_message('warning', 'Could not add provider_id foreign key: ' . $e->getMessage());
            }
        }
        
        // Add indexes safely
        try {
            $this->db->query('ALTER TABLE users ADD INDEX idx_users_role (role)');
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        try {
            $this->db->query('ALTER TABLE users ADD INDEX idx_users_provider_id (provider_id)');
        } catch (\Exception $e) {
            // Index might already exist or field doesn't exist
        }
        
        try {
            $this->db->query('ALTER TABLE users ADD INDEX idx_users_active (is_active)');
        } catch (\Exception $e) {
            // Index might already exist or field doesn't exist
        }
    }

    public function down()
    {
        // Remove foreign key if it exists
        try {
            $this->forge->dropForeignKey('users', 'users_provider_fk');
        } catch (\Exception $e) {
            // FK might not exist
        }
        
        // Remove added columns
        if ($this->db->fieldExists('provider_id', 'users')) {
            $this->forge->dropColumn('users', 'provider_id');
        }
        if ($this->db->fieldExists('permissions', 'users')) {
            $this->forge->dropColumn('users', 'permissions');
        }
        if ($this->db->fieldExists('is_active', 'users')) {
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
}
