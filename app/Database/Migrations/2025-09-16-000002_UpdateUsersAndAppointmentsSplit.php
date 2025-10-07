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
            $db->query("ALTER TABLE `{$usersTable}` ADD `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active' AFTER `role`");
        }

        if (!$haveLastLogin) {
            $db->query("ALTER TABLE `{$usersTable}` ADD `last_login` DATETIME NULL AFTER `status`");
        }

        $db->query("ALTER TABLE `{$usersTable}` MODIFY `role` ENUM('admin','provider','receptionist') NOT NULL DEFAULT 'provider'");

        $hasCustomerUsers = $db->query("SELECT COUNT(*) AS c FROM `{$usersTable}` WHERE role = 'customer'")->getFirstRow();

        if ($hasCustomerUsers && (int) $hasCustomerUsers->c > 0 && $db->tableExists('customers')) {
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
                $db->query("ALTER TABLE `{$appointmentsTable}` ADD `customer_id` INT(11) UNSIGNED NULL AFTER `provider_id`");

                if ($hasCustomerUsers && (int) $hasCustomerUsers->c > 0) {
                    $updateSql = <<<SQL
UPDATE `{$appointmentsTable}` a
JOIN `{$usersTable}` u ON a.user_id = u.id AND u.role = 'customer'
LEFT JOIN `{$customersTable}` c ON c.email = u.email
SET a.customer_id = c.id
SQL;
                    $db->query($updateSql);
                }

                $db->query("ALTER TABLE `{$appointmentsTable}` ADD INDEX `idx_appointments_customer_id` (`customer_id`)");

                try {
                    $db->query("ALTER TABLE `{$appointmentsTable}` ADD CONSTRAINT `fk_appointments_customer` FOREIGN KEY (`customer_id`) REFERENCES `{$customersTable}`(`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                } catch (\Throwable $e) {
                    // Constraint may already exist; ignore
                }
            }

            if ($hasCustomerUsers && (int) $hasCustomerUsers->c > 0) {
                $db->query("DELETE FROM `{$usersTable}` WHERE role = 'customer'");
            }

            if ($haveUserId) {
                try {
                    $constraint = $db->query(
                        "SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = ?",
                        [$appointmentsTable, $usersTable]
                    )->getFirstRow();

                    if ($constraint && $constraint->name) {
                        $db->query("ALTER TABLE `{$appointmentsTable}` DROP FOREIGN KEY `{$constraint->name}`");
                    }

                    $db->query("ALTER TABLE `{$appointmentsTable}` DROP COLUMN `user_id`");
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
            $db->query("ALTER TABLE `{$appointmentsTable}` ADD `user_id` INT(11) UNSIGNED NULL AFTER `provider_id`");
        }

        if ($haveCustomerId && $db->tableExists('customers')) {
            $rollbackSql = <<<SQL
UPDATE `{$appointmentsTable}` a
JOIN `{$customersTable}` c ON a.customer_id = c.id
SET a.user_id = NULL
SQL;
            $db->query($rollbackSql);
        }

        try {
            $db->query("ALTER TABLE `{$appointmentsTable}` DROP FOREIGN KEY `fk_appointments_customer`");
        } catch (\Throwable $e) {
            // Ignore missing foreign key
        }

        try {
            $db->query("ALTER TABLE `{$appointmentsTable}` DROP COLUMN `customer_id`");
        } catch (\Throwable $e) {
            // Ignore missing column
        }

        try {
            $db->query("ALTER TABLE `{$usersTable}` MODIFY `role` ENUM('customer','provider','admin','staff') NOT NULL DEFAULT 'customer'");
        } catch (\Throwable $e) {
            // If enum cannot be reverted, ignore
        }

        try {
            $db->query("ALTER TABLE `{$usersTable}` DROP COLUMN `status`");
        } catch (\Throwable $e) {
            // Column may not exist
        }

        try {
            $db->query("ALTER TABLE `{$usersTable}` DROP COLUMN `last_login`");
        } catch (\Throwable $e) {
            // Column may not exist
        }
    }
}
