# Immediate Hotfixes - Deploy Today
**Priority**: 🔴 CRITICAL  
**Estimated Time**: 7 hours  
**Risk Level**: 🟡 Medium (test thoroughly on staging first)

---

## Hotfix #1: Timezone Double-Conversion Bug (P0-1)
**Impact**: All appointments stored with 2-hour offset  
**Files**: `app/Controllers/Appointments.php`  
**Lines**: 226-239, 520-537 (store and update methods)

### Current Code (BROKEN):
```php
// Line 226-239 in store() method
$startDateTime = new \DateTime($startTimeLocal, new \DateTimeZone($clientTimezone));
// ... calculate end time ...
$startTimeUtc = TimezoneService::toUTC($startDateTime->format('Y-m-d H:i:s'), $clientTimezone);
$endTimeUtc = TimezoneService::toUTC($endDateTime->format('Y-m-d H:i:s'), $clientTimezone);
```

### Fixed Code:
```php
// Line 226-240 in store() method
$startDateTime = new \DateTime($startTimeLocal, new \DateTimeZone($clientTimezone));
$endDateTime = clone $startDateTime;
$endDateTime->modify('+' . (int) $service['duration_min'] . ' minutes');

// Convert to UTC using DateTime's native conversion (no double conversion)
$startDateTime->setTimezone(new \DateTimeZone('UTC'));
$endDateTime->setTimezone(new \DateTimeZone('UTC'));
$startTimeUtc = $startDateTime->format('Y-m-d H:i:s');
$endTimeUtc = $endDateTime->format('Y-m-d H:i:s');
```

**Apply same fix to `update()` method** (lines 520-537)

---

## Hotfix #2: Calendar Not Showing Appointments (P0-2)
**Impact**: Appointments created successfully but invisible in calendar  
**Files**: `resources/js/modules/scheduler/scheduler-core.js`  
**Lines**: 212-220

### Current Code (BROKEN):
```javascript
this.appointments = this.appointments.map(raw => {
    // ... other normalizations ...
    return {
        ...raw,
        id: id != null ? parseInt(id, 10) : undefined,
        providerId: providerId != null ? parseInt(providerId, 10) : undefined, // Missing proper normalization
        serviceId: serviceId != null ? parseInt(serviceId, 10) : undefined,
        // ...
    };
});
```

### Fixed Code:
```javascript
this.appointments = this.appointments.map(raw => {
    const id = raw.id ?? raw.appointment_id ?? raw.appointmentId;
    const providerId = raw.providerId ?? raw.provider_id; // ← CRITICAL: Handle both camelCase and snake_case
    const serviceId = raw.serviceId ?? raw.service_id;
    const customerId = raw.customerId ?? raw.customer_id;
    const startISO = raw.start ?? raw.start_time ?? raw.startTime;
    const endISO = raw.end ?? raw.end_time ?? raw.endTime;

    const startDateTime = startISO ? DateTime.fromISO(startISO, { zone: this.options.timezone }) : null;
    const endDateTime = endISO ? DateTime.fromISO(endISO, { zone: this.options.timezone }) : null;

    return {
        ...raw,
        id: id != null ? parseInt(id, 10) : undefined,
        providerId: providerId != null ? parseInt(providerId, 10) : undefined, // ← ENSURE numeric
        serviceId: serviceId != null ? parseInt(serviceId, 10) : undefined,
        customerId: customerId != null ? parseInt(customerId, 10) : undefined,
        startDateTime,
        endDateTime
    };
});
```

**Also normalize provider IDs when initializing visible providers** (lines 80-94):
```javascript
// Ensure provider IDs are numbers in Set
this.providers.forEach(p => {
    const providerId = typeof p.id === 'string' ? parseInt(p.id, 10) : p.id;
    this.visibleProviders.add(providerId);
    logger.debug(`   ✓ Adding provider ${p.name} (ID: ${providerId}, type: ${typeof providerId})`);
});
```

---

## Hotfix #3: Setup Wizard Database Crash (P0-3)
**Impact**: Fresh installations cannot complete setup  
**Files**: `app/Filters/TimezoneDetection.php`  
**Lines**: 45-50

### Current Code (BROKEN):
```php
public function before(RequestInterface $request, $arguments = null)
{
    $localizationService = new LocalizationSettingsService(); // ← Crashes if DB not configured
    // ...
}
```

### Fixed Code:
```php
public function before(RequestInterface $request, $arguments = null)
{
    // Skip timezone detection if setup is not complete
    if (!file_exists(WRITEPATH . 'setup_complete.flag') && 
        !file_exists(WRITEPATH . 'setup_completed.flag')) {
        log_message('debug', '[TimezoneDetection] Skipping - setup not complete');
        return;
    }
    
    $localizationService = new LocalizationSettingsService();
    // ... rest of existing code ...
}
```

---

## Deployment Checklist

### Pre-Deployment
- [ ] **Backup production database**
  ```bash
  php spark db:backup
  ```
- [ ] Test all three fixes on local/staging environment
- [ ] Create test appointment and verify it appears in calendar
- [ ] Test setup wizard flow on clean database

### Deployment Steps
1. **Apply code changes** (copy fixed code to files)
2. **Clear all caches**
   ```bash
   php spark cache:clear
   php spark route:cache
   npm run build  # Rebuild JS assets
   ```
3. **Verify database migration status**
   ```bash
   php spark migrate:status
   ```
   Ensure `20251116170000_fix_appointment_timezone_offsets` is applied

4. **Test appointment creation flow**
   - Create new appointment
   - Check it appears in calendar immediately
   - Verify time matches expected local time

5. **Monitor logs** for 24 hours
   ```bash
   tail -f writable/logs/*.log | grep -E "Appointments|AvailabilityService|scheduler"
   ```

### Post-Deployment Verification
- [ ] Create test appointment as staff user → Verify appears in calendar
- [ ] Create test appointment as admin user → Verify appears in calendar
- [ ] Check existing appointments display with correct times
- [ ] Test edit form service dropdown → Verify populates correctly
- [ ] Verify no 500 errors in `/api/appointments` endpoint
- [ ] Check browser console for JavaScript errors

### Rollback Plan (If Issues Found)
```bash
# 1. Restore database backup
php spark db:restore --file=backup_TIMESTAMP.sql

# 2. Revert code changes via git
git checkout HEAD~1 app/Controllers/Appointments.php
git checkout HEAD~1 app/Filters/TimezoneDetection.php
git checkout HEAD~1 resources/js/modules/scheduler/scheduler-core.js

# 3. Rebuild assets
npm run build

# 4. Clear caches
php spark cache:clear
```

---

## Testing Script

Run this after deployment to verify fixes:

```bash
#!/bin/bash
echo "🔍 Testing Hotfixes..."

# Test 1: API returns appointments with correct format
echo "Test 1: Checking API response format..."
RESPONSE=$(curl -s "http://localhost:8080/api/appointments?start=2025-11-01&end=2025-11-30")
echo $RESPONSE | jq '.data[] | {id, providerId, start}'

# Test 2: Check database times are in UTC
echo "Test 2: Verifying UTC storage..."
mysql -u root -p ws_04 -e "SELECT id, provider_id, start_time, end_time FROM xs_appointments ORDER BY id DESC LIMIT 5;"

# Test 3: Verify setup flag exists
echo "Test 3: Setup wizard protection..."
if [ -f "writable/setup_complete.flag" ]; then
    echo "✅ Setup flag exists - filter will work"
else
    echo "⚠️  Setup flag missing - create it manually or run setup"
fi

echo "✅ Tests complete"
```

---

## Expected Outcomes

### Before Hotfixes:
- ❌ Appointments invisible after creation (0% visibility)
- ❌ Times stored with 2-hour offset (100% incorrect)
- ❌ Setup wizard crashes (0% success rate)

### After Hotfixes:
- ✅ Appointments visible immediately after creation (100%)
- ✅ Times stored correctly in UTC (100% accurate)
- ✅ Setup wizard completes successfully (100%)

---

## Additional Monitoring

Add these log messages for debugging (optional):

```php
// In Appointments.php after timezone conversion:
log_message('info', '[Appointments::store] Timezone validation:', [
    'input_local' => $startTimeLocal,
    'client_tz' => $clientTimezone,
    'output_utc' => $startTimeUtc,
    'expected_offset_hours' => $startDateTime->getOffset() / 3600
]);
```

```javascript
// In scheduler-core.js after loading appointments:
logger.info('🔍 Provider ID types after normalization:', {
    providerIds: Array.from(this.visibleProviders),
    appointmentProviderIds: this.appointments.map(a => ({ id: a.id, providerId: a.providerId, type: typeof a.providerId })),
    matchesExpected: this.appointments.every(a => typeof a.providerId === 'number')
});
```

---

## Contact for Issues

If any issues arise during deployment:
1. Check `writable/logs/log-{date}.log` for errors
2. Verify browser console for JavaScript errors (F12)
3. Test API manually: `GET /api/appointments?start=2025-11-01&end=2025-11-30`
4. Compare database times with displayed times (should differ by timezone offset)

**Estimated deployment time**: 30-45 minutes  
**Recommended deployment window**: Off-peak hours or maintenance window  
**Backup retention**: Keep for 7 days post-deployment
