<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateApiKeysTable extends MigrationBase
{
    private string $table = 'api_keys';

    public function up(): void
    {
        if ($this->db->tableExists($this->db->prefixTable($this->table))) {
            return;
        }

        $this->forge->addField($this->sanitiseFields([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // Forward-compatibility only: there is no businesses table yet and
            // every row resolves to 1. See architecture skill §8.
            'business_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 1,
            ],
            // Every key is bound to an xs_users row; the bound user supplies the
            // roles and provider scope the token request runs under.
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            // Non-secret lookup handle; the secret half is never stored.
            'key_prefix' => [
                'type'       => 'VARCHAR',
                'constraint' => 12,
                'null'       => false,
            ],
            'key_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            // JSON array. NULL means "inherit the bound user's role permissions".
            'scopes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'last_used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_used_ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]));

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('key_prefix');
        $this->forge->addKey(['user_id', 'revoked_at']);

        $this->forge->createTable($this->table);
    }

    public function down(): void
    {
        if ($this->db->tableExists($this->db->prefixTable($this->table))) {
            $this->forge->dropTable($this->table);
        }
    }
}
