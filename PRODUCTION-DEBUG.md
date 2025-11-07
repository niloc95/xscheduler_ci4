# Production 500 Error Debugging

## Problem
- Setup form loads ✅
- Test connection returns "HTTP 500:" with no message ❌
- No log files being created ❌

## Root Cause
The 500 error happens BEFORE any PHP code runs, which means:
1. **Fatal PHP error** (syntax, missing class, etc.)
2. **Permission error** (can't write logs)
3. **Server configuration** (PHP settings)

## Quick Diagnostics

### Step 1: Check Permissions
SSH into your server and run:
```bash
cd /path/to/your/deployment
ls -la writable/
ls -la writable/logs/
```

**Expected output:**
```
drwxrwxrwx  writable/
drwxrwxrwx  writable/logs/
```

**If permissions are wrong:**
```bash
chmod -R 775 writable/
chown -R www-data:www-data writable/  # or your web server user
```

### Step 2: Enable PHP Error Display (TEMPORARY)
Create a file `debug.php` in your public/ directory:
```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Writable: " . (is_writable('../writable/logs') ? 'YES' : 'NO') . "<br>";

// Try to test connection
$_POST['mysql_hostname'] = 'localhost';
$_POST['mysql_database'] = 'test';
$_POST['mysql_username'] = 'test';
$_POST['mysql_password'] = '';
$_POST['mysql_port'] = '3306';

try {
    require_once '../app/Controllers/Setup.php';
    $setup = new \App\Controllers\Setup();
    echo "Setup controller loaded successfully!<br>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
```

Access: `https://your-domain.com/debug.php`

### Step 3: Check Server Error Logs
Most hosting providers have error logs in:
- cPanel: Error Log viewer
- Plesk: Logs & Statistics
- Direct SSH: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`

Look for recent 500 errors with actual PHP error messages.

### Step 4: Use Diagnostic Script
We've included `test-setup.php` in the deployment root.

Access: `https://your-domain.com/test-setup.php`

This will show:
- PHP version and extensions
- Directory permissions
- .env file status
- Database connection test

## Common Issues

### Issue 1: Writable Directory Not Writable
**Solution:**
```bash
chmod -R 775 writable/
chown -R www-data:www-data writable/
```

### Issue 2: PDO Extension Missing
**Error:** "Class 'PDO' not found"
**Solution:** Contact hosting provider to enable PDO extension

### Issue 3: Empty Database Password
**Error:** "Uninitialized string offset 0"
**Solution:** Already fixed in latest deployment (commit 2528ff3)

### Issue 4: .htaccess Issues
**Error:** 500 on all requests
**Solution:** Check if mod_rewrite is enabled:
```apache
# In public/.htaccess
RewriteEngine On
```

## After Fixing

1. **Delete debug files:**
   ```bash
   rm public/debug.php
   rm test-setup.php
   ```

2. **Test again:**
   - Go to setup page
   - Fill in database credentials
   - Click "Test Connection"
   - Should see success message

## Contact

If issues persist, provide:
1. Output from `test-setup.php`
2. Last 20 lines from server error log
3. Screenshot of setup form with error
