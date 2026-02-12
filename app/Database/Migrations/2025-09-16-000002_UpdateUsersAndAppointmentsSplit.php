<?php
namespace App\Database\Migrations;

use App\Database\MigrationBase;

class UpdateUsersAndAppointmentsSplit extends MigrationBase
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('users')) {
            return;
        }

        $usersTable        = $db->prefixTable('users');
        $customersTable    = $db->prefixTable('customers');
        $appointmentsTable = $db->prefixTable('appointments');

        $fields        = $db->getFieldData('users');
        $haveStatus    = false;
        $haveLastLogin = false;

        foreach ($fields as $field) {
            if ($field->name === 'status') {
                $haveStatus = true;
            }

            if ($field->name === 'last_login') {
                $haveLastLogin = true;
            }
        }

        if (!$haveStatus) {
            if ($this->isSQLite()) {
                $this->forge->addColumn('users', [
                    'status' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 16,
                        'default'    => 'active',
                        'null'       => false,
                    ],
                ]);
            } else {
                $db->query("ALTER TABLE `{$usersTable}` ADD `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active'");
            }
        }

        if (!$haveLastLogin) {
            $this->forge->addColumn('users', [
                'last_login' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
        }

        // Use modifyEnumColumn for cross-database compatibility (skips on SQLite)
        $this->modifyEnumColumn('users', [
            'role' => [
                'type' => 'ENUM',
                'constraint' => ['admin', 'provider', 'receptionist'],
                'default' => 'provider',
                'null' => false,
            ],
        ]);

        $hasCustomerUsers = $db->query("SELECT COUNT(*) AS c FROM `{$usersTable}` WHERE role = 'customer'")->getFirstRow();

        // Data migration from users→customers is MySQL-specific (uses SUBSTRING_INDEX, JOIN UPDATE)
        if (!$this->isSQLite() && $hasCustomerUsers && (int) $hasCustomerUsers->c > 0 && $db->tableExists('customers')) {
            $insertSql = <<<SQL
INSERT INTO `{$customersTable}` (first_name, last_name, email, phone, address, notes, created_at, updated_at)
SELECT
    TRIM(SUBSTRING_INDEX(name, ' ', 1)) AS first_name,
    NULLIF(TRIM(SUBSTRING(name, LOCATE(' ', name) + 1)), '') AS last_name,
    email,
    phone,
    NULL,
    NULL,
    created_at,
    updated_at
FROM `{$usersTable}`
WHERE role = 'customer'
SQL;
            $db->query($insertSql);
        }

        if ($db->tableExists('appointments')) {
            $apptFields     = $db->getFieldData('appointments');
            $haveCustomerId = false;
            $haveUserId     = false;

            foreach ($apptFields as $field) {
                if ($field->name === 'customer_id') {
                    $haveCustomerId = true;
                }

                if ($field->name === 'user_id') {
                    $haveUserId = true;
                }
            }

            if (!$haveCustomerId) {
                // Add customer_id column using Forge for cross-database compatibility
                $this->forge->addColumn('appointments', $this->sanitiseFields([
                    'customer_id' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'unsigned'   => true,
                        'null'       => true,
                    ],
                ]));

                // Data migration (MySQL-specific JOIN UPDATE syntax)
                if (!$this->isSQLite() && $hasCustomerUsers && (int) $hasCustomerUsers->c > 0) {
                    $updateSql = <<<SQL
UPDATE `{$appointmentsTable}` a
JOIN `{$usersTable}` u ON a.user_id = u.id AND u.role = 'customer'
LEFT JOIN `{$customersTable}` c ON c.email = u.email
SET a.customer_id = c.id
SQL;
                    $db->query($updateSql);
                }

                // Add index (cross-database)
                $this->createIndexIfMissing('appointments', 'idx_appointments_customer_id', ['customer_id']);

                // Add FK constraint (MySQL only)
                try {
                    $this->mysqlOnly("ALTER TABLE `{$appointmentsTable}` ADD CONSTRAINT `fk_appointments_customer` FOREIGN KEY (`customer_id`) REFERENCES `{$customersTable}`(`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                } catch (\Throwable $e) {
                    // Constraint may already exist; ignore
                }
            }

            if ($hasCustomerUsers && (int) $hasCustomerUsers->c > 0) {
                $db->query("DELETE FROM `{$usersTable}` WHERE role = 'customer'");
            }

            if ($haveUserId) {
                try {
                    // Drop FK constraint (MySQL only — information_schema not available on SQLite)
                    if (!$this->isSQLite()) {
                        $constraint = $db->query(
                            "SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = ?",
                            [$appointmentsTable, $usersTable]
                        )->getFirstRow();

                        if ($constraint && $constraint->name) {
                            $db->query("ALTER TABLE `{$appointmentsTable}` DROP FOREIGN KEY `{$constraint->name}`");
                        }
                    }

                    $this->forge->dropColumn('appointments', 'user_id');
                } catch (\Throwable $e) {
                    // Ignore failures to drop legacy constraint/column
                }
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('appointments')) {
            return;
        }

        $appointmentsTable = $db->prefixTable('appointments');
        $customersTable    = $db->prefixTable('customers');
        $usersTable        = $db->prefixTable('users');

        $fields         = $db->getFieldData('appointments');
        $haveUserId     = false;
        $haveCustomerId = false;

        foreach ($fields as $field) {
            if ($field->name === 'user_id') {
                $haveUserId = true;
            }

            if ($field->name === 'customer_id') {
                $haveCustomerId = true;
            }
        }

        if (!$haveUserId) {
            $this->forge->addColumn('appointments', $this->sanitiseFields([
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
            ]));
        }

        // MySQL-specific JOIN UPDATE — skip on SQLite
        if (!$this->isSQLite() && $haveCustomerId && $db->tableExists('customers')) {
            $rollbackSql = <<<SQL
UPDATE `{$appointmentsTable}` a
JOIN `{$customersTable}` c ON a.customer_id = c.id
SET a.user_id = NULL
SQL;
            $db->query($rollbackSql);
        }

        // Drop FK constraint (MySQL only)
        try {
            $this->mysqlOnly("ALTER TABLE `{$appointmentsTable}` DROP FOREIGN KEY `fk_appointments_customer`");
        } catch (\Throwable $e) {
            // Ignore missing foreign key
        }

        // Drop customer_id column
        try {
            if ($db->fieldExists('customer_id', 'appointments')) {
                $this->forge->dropColumn('appointments', 'customer_id');
            }
        } catch (\Throwable $e) {
            // Ignore missing column
        }

        // Revert role column (skips on SQLite where ENUM is VARCHAR)
        try {
            $this->modifyEnumColumn('users', [
                'role' => [
                    'type'       => 'ENUM',
                    'constraint' => ['customer', 'provider', 'admin', 'staff'],
                    'default'    => 'customer',
                    'null'       => false,
                ],
            ]);
        } catch (\Throwable $e) {
            // If enum cannot be reverted, ignore
        }

        // Drop status column
        try {
            if ($db->fieldExists('status', 'users')) {
                $this->forge->dropColumn('users', 'status');
            }
        } catch (\Throwable $e) {
            // Column may not exist
        }

        // Drop last_login column
        try {
            if ($db->fieldExists('last_login', 'users')) {
                $this->forge->dropColumn('users', 'last_login');
            }
        } catch (\Throwable $e) {
            // Column may not exist
        }
    }
}
