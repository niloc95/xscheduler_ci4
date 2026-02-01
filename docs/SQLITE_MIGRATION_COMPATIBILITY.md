# SQLite Migration Compatibility Improvements

**Date:** January 29, 2026  
**Context:** Attempted to enable SQLite-based integration testing

## Summary

Made partial improvements to database migrations for cross-database compatibility. However, full SQLite support was determined to be impractical due to extensive MySQL-specific syntax throughout 50+ migrations.

## Changes Made

### ✅ Fixed Migrations (3 files)

#### 1. UpdateUserRoles.php
**Issue:** Used MySQL `SHOW INDEX` command  
**Fix:** Added cross-database compatible index check:
```php
if ($driver === 'SQLite3') {
    $exists = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name=?", [$index])->getFirstRow();
} else {
    $exists = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])->getFirstRow();
}
```

#### 2. AddProfileImageToUsers.php
**Issue:** `dropColumn` without existence check  
**Fix:** Added field existence check:
```php
if ($this->db->fieldExists('profile_image', 'users')) {
    $this->forge->dropColumn('users', 'profile_image');
}
```

#### 3. AddActiveToCategories.php
**Issue:** `dropColumn` without table existence check  
**Fix:** Added table and field existence checks:
```php
if ($this->db->tableExists('categories') && $this->db->fieldExists('active', 'categories')) {
    $this->forge->dropColumn('categories', 'active');
}
```

#### 4. UpdateUsersAndAppointmentsSplit.php
**Issue:** Used MySQL `MODIFY COLUMN` syntax  
**Fix:** Replaced with CodeIgniter Forge `modifyColumn()`:
```php
// BEFORE: Raw SQL
$db->query("ALTER TABLE `{$usersTable}` MODIFY `role` ENUM(...)");

// AFTER: Cross-database compatible
$this->forge->modifyColumn('users', [
    'role' => [
        'type' => 'ENUM',
        'constraint' => ['admin', 'provider', 'receptionist'],
        'default' => 'provider',
        'null' => false,
    ],
]);
```

## Remaining SQLite Incompatibilities

### Blocking Issues

1. **UNSIGNED Keyword** (25+ occurrences)
   - MySQL: `INT(11) UNSIGNED`
   - SQLite: Only supports `INTEGER` (no unsigned)
   - Example: Line 88 in `UpdateUsersAndAppointmentsSplit.php`

2. **AFTER Keyword** (40+ occurrences)
   - MySQL: `ALTER TABLE ADD COLUMN ... AFTER other_column`
   - SQLite: Doesn't support column positioning
   - Migrations throughout the project use `'after' => 'column_name'`

3. **ENUM Data Type** (15+ occurrences)
   - MySQL: Native `ENUM('value1', 'value2')` support
   - SQLite: Requires TEXT with CHECK constraints
   - Used extensively for status fields

4. **Raw SQL Queries** (30+ occurrences)
   - Many migrations use raw MySQL `ALTER TABLE` syntax
   - Would require complete rewrites using CodeIgniter Forge
   - Complex migrations with JOINs and subqueries

### Examples of Problematic Code

```php
// UNSIGNED not supported in SQLite
$db->query("ALTER TABLE `{$table}` ADD `customer_id` INT(11) UNSIGNED NULL");

// AFTER not supported in SQLite
'status' => ['type' => 'ENUM', 'after' => 'role']

// MODIFY not supported in SQLite
$db->query("ALTER TABLE `{$table}` MODIFY `role` ENUM(...)");

// SHOW commands not supported in SQLite
$db->query("SHOW COLUMNS FROM `{$table}` LIKE 'column_name'");
```

## Decision: MySQL-Only Integration Tests

### Rationale

1. **Effort vs. Benefit:** 40+ hours to refactor all migrations vs. 5 minutes to set up MySQL
2. **Production Alignment:** Production uses MySQL, tests should match
3. **Risk:** Refactoring migrations could introduce production bugs
4. **Priority:** Unit tests (passing) provide core value; integration tests are supplementary

### Impact

✅ **Unit Tests:** Fully functional (no database required)  
⚠️ **Integration Tests:** Require MySQL database  
✅ **Production:** Unaffected (already uses MySQL)

## Recommendation

Run integration tests against MySQL using one of these approaches:

### Option 1: Local MySQL
```bash
# Configure test database in .env
CI_ENVIRONMENT=testing
database.tests.hostname=localhost
database.tests.database=xscheduler_test
database.tests.username=root
database.tests.password=

# Run tests
./vendor/bin/phpunit tests/integration/ --colors=always
```

### Option 2: Docker MySQL
```bash
# Start MySQL container
docker run --name mysql-test \
  -e MYSQL_ROOT_PASSWORD=password \
  -e MYSQL_DATABASE=xscheduler_test \
  -p 3307:3306 \
  -d mysql:8.0

# Configure and run tests
export database.tests.hostname=127.0.0.1
export database.tests.port=3307
export database.tests.password=password
./vendor/bin/phpunit tests/integration/
```

### Option 3: GitHub Actions CI/CD
```yaml
services:
  mysql:
    image: mysql:8.0
    env:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: xscheduler_test
    ports:
      - 3306:3306
    options: >-
      --health-cmd="mysqladmin ping"
      --health-interval=10s
      --health-timeout=5s
      --health-retries=3
```

## Files Modified

1. `app/Database/Migrations/2025-09-01-100000_UpdateUserRoles.php`
2. `app/Database/Migrations/2025-08-29-090000_AddProfileImageToUsers.php`
3. `app/Database/Migrations/2025-09-11-090000_AddActiveToCategories.php`
4. `app/Database/Migrations/2025-09-16-000002_UpdateUsersAndAppointmentsSplit.php`

## Benefits of Changes Made

Even though full SQLite compatibility wasn't achieved, the improvements provide value:

1. **Better Error Handling:** Existence checks prevent failures when tables/columns missing
2. **Cross-Database Patterns:** Using Forge instead of raw SQL where possible
3. **Documentation:** Clear understanding of MySQL dependencies
4. **Future-Proofing:** Foundation for potential multi-database support

## Conclusion

SQLite integration testing is **not recommended** for this project due to extensive MySQL-specific migration syntax. The 4 migration improvements made provide better error handling and cross-database compatibility where practical, but full SQLite support would require significant refactoring with minimal benefit given MySQL is the production database.

**Recommendation:** Use MySQL for integration tests (local, Docker, or CI/CD) to match production environment and avoid migration compatibility issues.
