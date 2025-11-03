# Database Prefix Best Practices

## Overview
This project uses the `xs_` prefix for all database tables, configured in `.env`:
```
database.default.DBPrefix = xs_
```

## CRITICAL RULES

### ✅ DO: Let CodeIgniter handle the prefix automatically

#### In Migrations (using Forge)
```php
// ✅ CORRECT - Forge automatically adds the prefix
$this->forge->createTable('appointments');
$this->forge->addColumn('users', [...]);
$this->forge->modifyColumn('customers', [...]);
```

#### In Query Builder
```php
// ✅ CORRECT - Query builder automatically adds the prefix
$db = \Config\Database::connect();
$builder = $db->table('appointments');
$appointments = $builder->select('*')->get()->getResultArray();
```

#### In Models
```php
// ✅ CORRECT - Model automatically uses prefixed table
class AppointmentModel extends Model
{
    protected $table = 'appointments'; // NOT 'xs_appointments'
}
```

### ❌ DON'T: Hardcode the prefix

#### In Query Builder
```php
// ❌ WRONG - This creates xs_xs_appointments (double prefix)
$builder = $db->table('xs_appointments');
```

#### In Migrations
```php
// ❌ WRONG - This creates xs_xs_users
$this->forge->createTable('xs_users');
```

## When You MUST Use Hardcoded Table Names

### Raw SQL Queries
When using raw SQL with `$this->db->query()`, you MUST include the prefix manually:

```php
// ✅ CORRECT - Get prefix dynamically
public function up()
{
    $prefix = $this->db->DBPrefix; // Gets 'xs_'
    
    $this->db->query("
        ALTER TABLE {$prefix}appointments
        ADD CONSTRAINT fk_customer 
        FOREIGN KEY (customer_id) 
        REFERENCES {$prefix}customers(id)
    ");
}
```

```php
// ❌ WRONG - Hardcoded prefix (won't work if prefix changes)
$this->db->query("
    ALTER TABLE xs_appointments
    ADD CONSTRAINT fk_customer 
    FOREIGN KEY (customer_id) 
    REFERENCES xs_customers(id)
");
```

## Common Mistakes Fixed

### Issue 1: Double Prefix in Migrations
**Problem**: `$db->table('xs_appointments')` created `xs_xs_appointments`

**Fix**:
```php
// Before (WRONG)
$builder = $db->table('xs_appointments');

// After (CORRECT)
$builder = $db->table('appointments');
```

**Files Fixed**:
- `app/Database/Migrations/2025-10-30-183558_AddHashToCustomers.php`
- `app/Database/Migrations/2025-10-31-070104_AddHashToAppointments.php`

### Issue 2: Hardcoded Prefix in Raw SQL
**Problem**: Raw queries with `xs_` prefix won't work if prefix changes

**Fix**:
```php
// Before (WRONG)
$this->db->query('ALTER TABLE xs_users ADD COLUMN...');

// After (CORRECT)
$prefix = $this->db->DBPrefix;
$this->db->query("ALTER TABLE {$prefix}users ADD COLUMN...");
```

**Files Fixed**:
- `app/Database/Migrations/2025-10-21-173900_EnhanceProviderStaffAssignments.php`

## Verification Checklist

When creating new code involving database tables:

- [ ] **Migrations**: Use unprefixed table names with Forge methods
- [ ] **Query Builder**: Use unprefixed table names with `$db->table()`
- [ ] **Models**: Set `$table` property to unprefixed name
- [ ] **Raw SQL**: Use `$this->db->DBPrefix` or `$db->DBPrefix` to get prefix dynamically
- [ ] **Comments/Logs**: OK to mention prefixed names for clarity (e.g., "xs_appointments table")

## Testing
To verify prefix handling works correctly:

```bash
# Run migrations
php spark migrate -n App

# Check for double-prefixed tables in database
mysql -u root -p -e "SHOW TABLES LIKE 'xs_xs_%'" ws_04
# Should return empty result

# Verify all tables have single prefix
mysql -u root -p -e "SHOW TABLES LIKE 'xs_%'" ws_04
# Should show all project tables
```

## Related Files
- `.env` - Database prefix configuration
- `app/Config/Database.php` - Database connection settings
- `app/Database/Migrations/` - All migration files
- `app/Models/` - All model files with table definitions
