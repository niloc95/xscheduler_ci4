<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeparateCustomersFinalize extends Migration
{
    public function up()
    {
        // 1) Insert customers from xs_users into xs_customers if not already present (by email or phone)
        $sqlInsertCustomers = <<<SQL
INSERT INTO xs_customers (first_name, last_name, email, phone, address, notes, created_at, updated_at)
SELECT 
  TRIM(SUBSTRING_INDEX(u.name,' ',1)) AS first_name,
  NULLIF(TRIM(SUBSTRING(u.name, LENGTH(SUBSTRING_INDEX(u.name,' ',1)) + 2)), '') AS last_name,
  u.email, u.phone, NULL AS address, NULL AS notes, NOW(), NOW()
FROM xs_users u
LEFT JOIN xs_customers c_e ON (u.email IS NOT NULL AND c_e.email = u.email)
LEFT JOIN xs_customers c_p ON (u.phone IS NOT NULL AND c_p.phone = u.phone)
WHERE u.role = 'customer'
  AND c_e.id IS NULL
  AND c_p.id IS NULL;
SQL;
        $this->db->query($sqlInsertCustomers);

        // 2) Update appointments.customer_id for appointments tied to customer users
        $sqlUpdateApptCustomer = <<<SQL
UPDATE xs_appointments a
JOIN xs_users u ON u.id = a.user_id AND u.role = 'customer'
LEFT JOIN xs_customers c ON (
    (u.email IS NOT NULL AND c.email = u.email)
    OR (u.phone IS NOT NULL AND c.phone = u.phone)
)
SET a.customer_id = c.id
WHERE (a.customer_id IS NULL OR a.customer_id = 0)
  AND c.id IS NOT NULL;
SQL;
        $this->db->query($sqlUpdateApptCustomer);

        // 3) Ensure user_id no longer points at customer users by moving it to provider_id when applicable
        //    This preserves referential consistency with system users only.
        $sqlRepointUserId = <<<SQL
UPDATE xs_appointments a
JOIN xs_users u ON u.id = a.user_id AND u.role = 'customer'
SET a.user_id = a.provider_id;
SQL;
        $this->db->query($sqlRepointUserId);

        // 4) Delete customer users from xs_users (data is preserved in xs_customers and appointments)
        $this->db->query("DELETE FROM xs_users WHERE role = 'customer'");

        // 5) Alter enum to remove 'customer' and set a safe default
        //    Note: MySQL/MariaDB specific. Adjust as needed for other DBs.
        $this->db->query("ALTER TABLE xs_users MODIFY role ENUM('admin','provider','staff') NOT NULL DEFAULT 'staff'");
    }

    public function down()
    {
        // Reverse step 5: restore enum to include 'customer' (default back to 'customer')
        $this->db->query("ALTER TABLE xs_users MODIFY role ENUM('admin','provider','staff','customer') NOT NULL DEFAULT 'customer'");

        // We do not restore deleted xs_users with role customer, or revert appointment user_id changes,
        // because that would require historical state. Down migration focuses on role enum only.
    }
}
