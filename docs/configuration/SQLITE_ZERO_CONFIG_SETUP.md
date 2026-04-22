# SQLite Zero-Config Setup Feature

**Last Updated:** February 12, 2026  
**Feature Status:** ✅ Complete and Production-Ready

---

## Overview

The **Zero-Config SQLite Feature** enables instant application setup without any database server installation, configuration, or credentials.

When you select **SQLite** during setup:
1. Database file created automatically: `writable/database/webschedulr.db`
2. Schema initialized from migrations
3. Admin user created
4. Application ready to use

**Total setup time:** 30 seconds (vs. 5-10 minutes for MySQL)

---

## How It Works

### Setup Wizard Flow

```
1. User selects "SQLite" from database dropdown
                    ↓
2. Setup wizard detects SQLite selection
                    ↓
3. Database file created: writable/database/webschedulr.db
                    ↓
4. All 50 migrations run automatically
   (Cross-database compatible via MigrationBase)
                    ↓
5. Admin user created with provided password
                    ↓
6. .env file auto-generated (no manual editing needed)
                    ↓
7. Setup completion flag written
                    ↓
8. Redirect to login/dashboard
```

### Files Created

```
writable/
  └── database/
       └── webschedulr.db          ← SQLite database file (auto-created)

.env                               ← Generated automatically with:
                                      database.default.DBDriver = SQLite3
                                      database.default.database = writable/database/webschedulr.db
                                      database.default.DBPrefix = xs_
```

---

## Key Features

### ✅ Automatic Migration Compatibility

All 50 migrations are **cross-database compatible** and run without errors on SQLite:

**Automatic Conversions (MigrationBase):**
- `UNSIGNED` integer declaration → `INT` (SQLite doesn't support UNSIGNED)
- `ENUM` field type → `VARCHAR(255)` (SQLite storage)
- `LONGBLOB` → `BLOB` (simplified binary storage)
- `JSON` type → `TEXT` (SQLite JSON handling)
- `AFTER` column positioning → Ignored (SQLite ignores order)

**Result:** Same database schema works identically on SQLite and MySQL

### ✅ Structured Table Prefix

Automatically sets table prefix to `xs_` (verified during setup):
```mysql
xs_users
xs_appointments
xs_services
xs_settings
x_provider_staff_assignments
-- etc.
```

**Benefit:** Prevents conflicts if SQLite file is in shared directory

### ✅ Performance Optimizations

**WAL Mode (Write-Ahead Logging)**
```sql
PRAGMA journal_mode = WAL;
PRAGMA busy_timeout = 5000;
```
- Allows concurrent reads while writes are pending
- Automatically enabled during connection
- 5-second timeout for lock contention

### ✅ Auto-Repair for Missing Columns

If a migration fails during setup (e.g., "database is locked"), the `UserModel` auto-repairs missing columns on next access:

```php
// app/Models/UserModel.php constructor
public function __construct()
{
    parent::__construct();
    $this->ensureColorColumnExists();  // Auto-adds missing 'color' column
}
```

**Benefit:** Graceful recovery without user intervention

### ✅ Single-File Deployment

Entire database is one file:
```bash
writable/database/webschedulr.db  # ~5-20 MB typical
```

**Benefit:**
- Easy backup: `cp webschedulr.db webschedulr.db.backup`
- Easy restore: `cp webschedulr.db.backup webschedulr.db`
- Simple to replicate across environments

---

## Technical Implementation

### Setup Controller (app/Controllers/Setup.php)

**Key Methods:**

| Method | Purpose |
|--------|---------|
| `process()` | Main setup entry point |
| `applyRuntimeDatabaseConfig()` | Loads DB config from form input |
| `testSQLiteConnection()` | Verifies SQLite accessibility |
| `runMigrations()` | Executes all 50 migrations |
| `verifyRequiredTables()` | Confirms xs_users, xs_appointments, xs_services, xs_settings exist |
| `runSeeders()` | Seeds default data (optional) |
| `createAdminUser()` | Creates first admin account |
| `writeSetupCompletedFlag()` | Writes `.setup-complete` marker |

### Configuration Generation

The `.env` file is generated with:

```php
// Database driver selection
database.default.DBDriver = SQLite3

// SQLite-specific: full path to database file
database.default.database = {WRITEPATH}/database/webschedulr.db

// Table prefix to avoid conflicts
database.default.DBPrefix = xs_

// Other standard settings
app.baseURL = ''                    // Auto-detection
app.indexPage = ''                  // Clean URLs
```

### Migration Compatibility Layer (MigrationBase)

```php
namespace App\Database;

abstract class MigrationBase extends Migration
{
    // Sanitizes field arrays for SQLite compatibility
    protected function sanitiseFields(array $fields): array
    {
        if ($this->isSQLite()) {
            // Remove MySQL-only syntax
            // Convert ENUM→VARCHAR, LONGBLOB→BLOB, etc.
        }
        return $fields;
    }
}
```

**Usage in migrations:**
```php
$this->forge->addField($this->sanitiseFields([
    'id' => ['type' => 'INT', 'unsigned' => true],  ← Cleaned
    'role' => ['type' => 'ENUM', 'constraint' => [...]]  ← Converted
]));
```

---

## Database Locks & Concurrency

### The "database is locked" Error

**Cause:** Multiple simultaneous write operations on SQLite (single-threaded database).

**Prevention (Built-in):**
```sql
PRAGMA busy_timeout = 5000;        -- Wait 5 seconds for lock release
PRAGMA journal_mode = WAL;         -- Allow concurrent reads + 1 writer
```

**Implementation in Setup.php:**
```php
protected function setSQLitePragmas($db): void
{
    if ($db->DBDriver === 'SQLite3') {
        $db->query("PRAGMA busy_timeout = 5000");
        $db->query("PRAGMA journal_mode = WAL");
    }
}
```

**When to worry:** Only with 100+ simultaneous write-heavy operations (rare for scheduling app)

---

## Backup & Recovery

### Simple Backup
```bash
# Copy single file
cp writable/database/webschedulr.db writable/database/webschedulr.db.$(date +%Y%m%d)
```

### Scheduled Backup
```bash
# Via cron (daily backup at 2 AM)
0 2 * * * cp /home/user/public_html/writable/database/webschedulr.db /home/user/backups/webschedulr.db.$(date +\%Y\%m\%d)
```

### Recovery
```bash
# Restore from backup
cp writable/database/webschedulr.db.20260212 writable/database/webschedulr.db

# App automatically detects schema on next load
```

---

## When to Migrate to MySQL

**Consider upgrading to MySQL if:**

✏️ **Data volume grows:**
- > 1,000,000 records
- > 50 MB database file
- > 100 concurrent users
- Complex reporting/analytics queries

✏️ **Business requirements change:**
- Multiple locations needing data sync
- Separate database backups for compliance
- Advanced replication/failover setup
- Scheduled automated exports

✏️ **Performance becomes an issue:**
- Setup takes > 5 seconds
- Page loads slow with heavy concurrent users
- Query execution times exceed 100ms regularly

**Migration Process:**
1. Enable MySQL on hosting account
2. Run setup wizard again, select MySQL
3. Use admin panel to export data
4. Import into new database
5. Update `.env` with new credentials

See [Database Selection Guide](./DATABASE_SELECTION_GUIDE.md#switching-databases)

---

## Use Cases

### ✅ Perfect For:

- **Solo Practitioners:** 1-5 providers, 10-50 appointments/day
- **Small Salons/Clinics:** 1-10 staff, < 500 appointments/month
- **Testing/Demo:** Rapid prototyping, trial deployments
- **Early Stage:** MVP validation, investor demos
- **Budget Constrained:** Free hosting (InfinityFree, 000webhost)
- **Non-Profit Groups:** No database hosting fees

### ⚠️ Consider MySQL For:

- **Growing Practices:** > 50 staff, 1000+ appointments/month
- **Multi-Location:** Franchise or chain operations
- **High Availability:** SLA requirements (99.9% uptime)
- **Compliance:** HIPAA, GDPR auditing requirements
- **Data Volume:** > 1 MB database size expected

---

## Troubleshooting

### Setup Fails: "Unable to create database file"

**Cause:** `writable/database/` directory doesn't exist or has wrong permissions

**Fix:**
```bash
# Create directory
mkdir -p writable/database

# Set permissions
chmod 755 writable/database
chmod 644 writable/database/webschedulr.db
```

### Setup Fails: "Database is locked"

**Cause:** Concurrent access during migration

**Fix:**
- Delete partial database file: `rm writable/database/webschedulr.db`
- Retry setup (auto-creates new file)
- Backend now includes lock timeout handling

### Login Fails: "no such column: color"

**Cause:** Migration didn't complete fully

**Fix:**
- Refresh page (UserModel auto-repair adds column)
- Or restart application

### File Size Growing Unexpectedly

**Cause:** SQLite WAL mode creates `.db-wal` and `.db-shm` files

**Expected files:**
```
webschedulr.db          # Main database (~5-20 MB)
webschedulr.db-wal      # Write-ahead log (⚠️ temporary, cleaned up)
webschedulr.db-shm      # Shared memory file (temporary, cleaned up)
```

**Normal behavior:** WAL files are temporary and cleaned up automatically

---

## References

- [Database Selection Guide](./DATABASE_SELECTION_GUIDE.md)
- [SQLite Migration Compatibility](./SQLITE_MIGRATION_COMPATIBILITY.md)
- [Setup Completion Report](../configuration/SETUP_COMPLETION_REPORT.md)
- [MigrationBase Implementation](../../app/Database/MigrationBase.php)
- [Setup Controller Implementation](../../app/Controllers/Setup.php)
