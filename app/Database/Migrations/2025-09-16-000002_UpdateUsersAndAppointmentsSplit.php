<?php
namespace App\Database\Migrations;

use App\Database\MigrationBase;

class UpdateUsersAndAppointmentsSplit extends MigrationBase
{
    public function up()
    {
        $db = \Config\Database::connect();
        // If users table not yet created (fresh install) just skip; initial seed will already match new model expectations.
        if (!$db->tableExists('users')) {
            return;
        }

        // 1. Add status & last_login to users if not exists
        $fields = [];
        if ($db->tableExists('users')) {
            $fields = $db->getFieldData('users');
        }
        $haveStatus = false; $haveLastLogin = false; $roleEnum = null; $haveRole = false;
        foreach ($fields as $f) {
            if ($f->name === 'status') $haveStatus = true;
            if ($f->name === 'last_login') $haveLastLogin = true;
            if ($f->name === 'role') { $haveRole = true; $roleEnum = $f; }
        }
        if (!$haveStatus) {
            $db->query("ALTER TABLE `users` ADD `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active' AFTER `role`");
        }
        if (!$haveLastLogin) {
            $db->query("ALTER TABLE `users` ADD `last_login` DATETIME NULL AFTER `status`");
        }

        // 2. Adjust role ENUM to remove customer/staff and add receptionist
        // MySQL ENUM alter requires full list
        $db->query("ALTER TABLE `users` MODIFY `role` ENUM('admin','provider','receptionist') NOT NULL DEFAULT 'provider'");

        // 3. Migrate existing customer users into customers table (only if any rows exist)
        $hasCustomerUsers = $db->query("SELECT COUNT(*) AS c FROM users WHERE role='customer'")->getFirstRow();
        if ($hasCustomerUsers && $hasCustomerUsers->c > 0) {
            if ($db->tableExists('customers')) {
                $db->query("INSERT INTO customers (first_name, last_name, email, phone, address, notes, created_at, updated_at)
                    SELECT 
                      TRIM(SUBSTRING_INDEX(name,' ',1)) as first_name,
                      NULLIF(TRIM(SUBSTRING(name, LOCATE(' ', name)+1)), '') as last_name,
                      email, phone, NULL, NULL, created_at, updated_at
                    FROM users WHERE role='customer'");
            }
        }

        // 4. Add & populate customer_id in appointments if appointments table exists
        if ($db->tableExists('appointments')) {
            $apptFields = $db->getFieldData('appointments');
            $haveCustomerId = false; $haveUserId = false;
            foreach ($apptFields as $af) { if ($af->name==='customer_id') $haveCustomerId=true; if ($af->name==='user_id') $haveUserId=true; }
            if (!$haveCustomerId) {
                $db->query("ALTER TABLE `appointments` ADD `customer_id` INT(11) UNSIGNED NULL AFTER `provider_id`");
                if ($hasCustomerUsers && $hasCustomerUsers->c > 0) {
                    $db->query("UPDATE appointments a 
                        JOIN users u ON a.user_id = u.id AND u.role='customer'
                        LEFT JOIN customers c ON c.email = u.email
                        SET a.customer_id = c.id");
                }
                $db->query("ALTER TABLE `appointments` ADD INDEX (`customer_id`)");
                try { $db->query("ALTER TABLE `appointments` ADD CONSTRAINT `fk_appointments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE"); } catch (\Throwable $e) {}
            }
            if ($hasCustomerUsers && $hasCustomerUsers->c > 0) {
                $db->query("DELETE FROM users WHERE role='customer'");
            }
            if ($haveUserId) {
                try {
                    $constraint = $db->query("SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='appointments' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users'")->getFirstRow();
                    if ($constraint && $constraint->name) {
                        $db->query("ALTER TABLE `appointments` DROP FOREIGN KEY `{$constraint->name}`");
                    }
                    $db->query("ALTER TABLE `appointments` DROP COLUMN `user_id`");
                } catch (\Throwable $e) {}
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        // Best-effort rollback: Re-add user_id column (nullable) if missing
        $fields = $db->getFieldData('appointments');
        $haveUserId = false; $haveCustomerId = false;
        foreach ($fields as $f) { if ($f->name==='user_id') $haveUserId=true; if ($f->name==='customer_id') $haveCustomerId=true; }
        if (!$haveUserId) {
            $db->query("ALTER TABLE `appointments` ADD `user_id` INT(11) UNSIGNED NULL AFTER `provider_id`");
        }
        if ($haveCustomerId) {
            // Attempt to repopulate user_id from any provider-owned customer mapping (cannot fully restore original)
            $db->query("UPDATE appointments a JOIN customers c ON a.customer_id = c.id SET a.user_id = NULL");
        }
        // Remove new FKs and columns
        try { $db->query("ALTER TABLE `appointments` DROP FOREIGN KEY `fk_appointments_customer`"); } catch (\Throwable $e) {}
        try { $db->query("ALTER TABLE `appointments` DROP COLUMN `customer_id`"); } catch (\Throwable $e) {}
        // Restore role enum (approximation)
        try { $db->query("ALTER TABLE `users` MODIFY `role` ENUM('customer','provider','admin','staff') NOT NULL DEFAULT 'customer'"); } catch (\Throwable $e) {}
        // Optionally remove added columns
        try { $db->query("ALTER TABLE `users` DROP COLUMN `status`"); } catch (\Throwable $e) {}
        try { $db->query("ALTER TABLE `users` DROP COLUMN `last_login`"); } catch (\Throwable $e) {}
        // Note: Customers table left in place (data loss risk if dropped)
    }
}
