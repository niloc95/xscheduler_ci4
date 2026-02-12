# SQLite Migration Compatibility — COMPLETED

**Date:** July 2025 (initial), Updated January 2026  
**Status:** ✅ COMPLETE — All 50 migrations are now cross-database compatible

## Summary

All 50 database migrations have been refactored for full SQLite + MySQL
compatibility. The setup wizard can now select either **MySQLi** or **SQLite3**
and all migrations will run without error.

The original blocker was the `UNSIGNED` syntax error:

```
Migration failed: Database migration failed: near "UNSIGNED": syntax error
```

This has been fully resolved.

---

## Architecture

### MigrationBase (`app/Database/MigrationBase.php`)

Every migration extends `MigrationBase` instead of `CodeIgniter\Database\Migration`.
This class provides six cross-database helper methods:

| Method | Purpose |
|--------|---------|
| `isSQLite(): bool` | Check if current driver is SQLite3 |
| `sanitiseFields(array $fields): array` | Strip/convert MySQL-only syntax when on SQLite |
| `indexExists(string $table, string $indexName): bool` | Cross-DB index existence check |
| `createIndexIfMissing(string $table, string $indexName, array $columns): void` | Create index if absent |
| `dropIndexIfExists(string $table, string $indexName): void` | Drop index if present |
| `mysqlOnly(string $sql): void` | Execute raw SQL only on MySQL |

### `sanitiseFields()` Transformations (SQLite only)

| MySQL syntax | SQLite equivalent |
|-------------|-------------------|
| `'unsigned' => true` | Stripped |
| `'after' => 'column'` | Stripped |
| `'type' => 'ENUM'` | `VARCHAR(255)` |
| `'type' => 'LONGBLOB'` | `BLOB` |
| `'type' => 'JSON'` | `TEXT` |

On MySQL the fields are returned unchanged.

### Table Prefix Handling

`indexExists`, `createIndexIfMissing`, and `dropIndexIfExists` accept
**unprefixed** table names (e.g. `'appointments'`) and call
`$this->db->prefixTable()` internally. Do **not** pre-prefix when calling them.

---

## Patterns Used

### 1. Wrap field arrays with `sanitiseFields()`

```php
// CREATE TABLE
$this->forge->addField($this->sanitiseFields([
    'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
    // ...
]));

// ADD COLUMN
$this->forge->addColumn('table', $this->sanitiseFields([...]));

// MODIFY COLUMN
$this->forge->modifyColumn('table', $this->sanitiseFields([...]));
```

### 2. Guard raw MySQL SQL

```php
// Foreign key constraints — MySQL only
$this->mysqlOnly("ALTER TABLE {$prefix}table ADD CONSTRAINT ...");

// Complex MySQL-specific queries
if (!$this->isSQLite()) {
    $db->query("UPDATE ... JOIN ...");
}
```

### 3. Cross-database index operations

```php
$this->createIndexIfMissing('appointments', 'idx_name', ['col1', 'col2']);
$this->dropIndexIfExists('appointments', 'idx_name');
```

### 4. Cross-database datetime functions

```php
if ($this->isSQLite()) {
    $db->query("UPDATE {$table} SET start_time = datetime(start_time, '-2 hours') ...");
} else {
    $db->query("UPDATE {$table} SET start_time = DATE_SUB(start_time, INTERVAL 2 HOUR) ...");
}
```

---

## Files Modified (50 migrations + MigrationBase)

All files in `app/Database/Migrations/` now extend `MigrationBase`.

Key categories:

- **Core table creation** (7 files): Users, Services, ProvidersServices,
  Appointments, BlockedTimes, BusinessHours, Customers — all `addField` wrapped
- **Settings/Files** (4 files): SettingsFiles, SettingsTableIfMissing,
  AddUpdatedByToSettings, AddBookingFieldSettings
- **User management** (6 files): UpdateUserRoles, AddStatusToUsers,
  AddResetTokenToUsers, AddProfileImageToUsers, AddColorToUsers,
  ConvertReceptionistsToStaff
- **Appointments** (6 files): UpdateAppointmentStatusEnum, AddHashToAppointments,
  AddPublicTokenToAppointments, AddReminderSentToAppointments,
  AddLocationToAppointments, FixAppointmentTimezoneOffsets
- **Customer separation** (4 files): CreateCustomersTable,
  UpdateUsersAndAppointmentsSplit, SeparateCustomersFinalize, AddHashToCustomers
- **Categories** (3 files): CreateCategoriesTable, AlterServicesAddCategoryAndActive,
  AddActiveToCategories, AlterCategoriesAddActive
- **Provider management** (4 files): ProviderSchedules, ProviderStaffAssignments,
  EnhanceProviderStaffAssignments, ReceptionistProvidersTable
- **Notifications** (5 files): Phase1Tables, Queue, DeliveryLogs, OptOuts,
  AddCorrelationId
- **Indexes** (4 files): CalendarPerformanceIndexes, DashboardIndexes,
  CompositeIndexesToAppointments, CustomerHistoryIndexes
- **Other** (5 files): AuditLogs, Locations, CustomFields, MigrateCustomFieldsNaming,
  AddContactFieldsToSettings, UpdateLocalizationSettings

---

## Writing New Migrations

```php
<?php

namespace App\Database\Migrations;

use App\Database\MigrationBase;

class CreateExampleTable extends MigrationBase
{
    public function up()
    {
        $this->forge->addField($this->sanitiseFields([
            'id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
        ]));
        $this->forge->addKey('id', true);
        $this->forge->createTable('example', true);

        // Index helper (unprefixed table name)
        $this->createIndexIfMissing('example', 'idx_name', ['name']);
    }

    public function down()
    {
        $this->dropIndexIfExists('example', 'idx_name');
        $this->forge->dropTable('example', true);
    }
}
```

**Rules:**
1. Always extend `MigrationBase`, never `Migration` directly
2. Wrap all `addField()` / `addColumn()` / `modifyColumn()` arrays with `$this->sanitiseFields()`
3. Use `$this->mysqlOnly()` for raw MySQL-only SQL (foreign keys, etc.)
4. Use `$this->createIndexIfMissing()` / `$this->dropIndexIfExists()` for index operations
5. Pass **unprefixed** table names to MigrationBase helpers
6. For date arithmetic, branch with `$this->isSQLite()` (use `datetime()` vs `DATE_SUB/ADD`)
