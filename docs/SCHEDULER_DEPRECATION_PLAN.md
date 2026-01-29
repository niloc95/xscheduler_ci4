# Scheduler.php Legacy Controller - Deprecation & Removal Plan

**Status:** DEPRECATED  
**Sunset Date:** March 1, 2026  
**Current Date:** January 29, 2026  
**Days Remaining:** ~30 days

---

## Overview

The legacy `app/Controllers/Scheduler.php` controller (129 lines) is officially deprecated and scheduled for removal on March 1, 2026. This document outlines the deprecation strategy, migration path, and removal timeline.

## Current State

### What Scheduler.php Does (Now):
- **Admin/Staff Routes** (`/scheduler`, `/scheduler/index`):
  - Returns `308 Permanent Redirect` to `/appointments`
  - Sets `Sunset: Sat, 01 Mar 2026 00:00:00 GMT` header
  
- **Public Route** (`/scheduler/client`):
  - Returns `308 Permanent Redirect` to `/appointments`
  
- **API Endpoints** (`/scheduler/slots`, `/scheduler/book`):
  - Still functional for backwards compatibility
  - Marked `@deprecated` in PHPDoc
  - Sets deprecation headers:
    - `Deprecation: true`
    - `Sunset: Sat, 01 Mar 2026 00:00:00 GMT`
    - `Link: </api/availability>; rel="successor-version"`
    - `Link: </api/appointments>; rel="successor-version"`

### Internal Architecture:
- Uses `AvailabilityService::getAvailableSlots()` for slot calculation
- Uses `CustomerModel` for customer lookups
- No unique business logic (all delegated to services)

### Documentation:
- References: `docs/architecture/LEGACY_SCHEDULER_ARCHITECTURE.md`
- Last Updated: October 7, 2025
- Replacement: `app/Controllers/Appointments.php`

---

## Migration Path (For External Integrations)

### For Frontend Applications:

#### Old Scheduler Routes → New Appointments Routes
```javascript
// BEFORE (Deprecated):
GET /scheduler/slots?provider_id=1&service_id=2&date=2026-02-01
POST /scheduler/book { providerId, serviceId, date, start, customer... }

// AFTER (Current):
GET /api/availability/slots?provider_id=1&service_id=2&date=2026-02-01
POST /api/appointments { provider_id, service_id, date, start_time, customer... }
```

#### Response Format Changes:
```javascript
// Old Scheduler Response:
{
  "slots": [
    { "time": "09:00", "available": true },
    { "time": "09:30", "available": true }
  ]
}

// New Availability Response:
{
  "data": {
    "slots": [
      { "start": "2026-02-01T09:00:00Z", "end": "2026-02-01T10:00:00Z", "available": true },
      { "start": "2026-02-01T09:30:00Z", "end": "2026-02-01T10:30:00Z", "available": true }
    ]
  }
}
```

### For API Consumers:

If your application uses the scheduler API endpoints, update to new endpoints **before March 1, 2026**:

1. **Availability Check:**
   - Old: `GET /scheduler/slots`
   - New: `GET /api/availability/slots`
   - New: `POST /api/appointments/check-availability`

2. **Booking:**
   - Old: `POST /scheduler/book`
   - New: `POST /api/appointments`

---

## Removal Timeline

### Phase 1: Current State (Jan 29 - Feb 15, 2026)
✅ **Status:** Active Deprecation
- Scheduler routes redirect with 308 status
- API endpoints functional but deprecated
- Headers inform clients of sunset date
- Monitoring API endpoint usage (see logs)

**Actions:**
- [ ] Monitor `writable/logs/` for Scheduler API calls
- [ ] Identify active external integrations
- [ ] Send deprecation notices to API consumers

### Phase 2: Final Warning (Feb 15 - Mar 1, 2026)
⏳ **Status:** Last Call
- Continue redirects and deprecation headers
- Add warning banners if old routes accessed
- Send final migration reminders

**Actions:**
- [ ] Review logs for remaining Scheduler API usage
- [ ] Contact remaining integrations (if any)
- [ ] Update documentation to remove Scheduler references

### Phase 3: Removal (Mar 1, 2026)
🔴 **Status:** Deletion
- Delete `app/Controllers/Scheduler.php`
- Remove routes from `app/Config/Routes.php`
- Update tests to remove Scheduler references
- Archive documentation to `docs/_archive/`

**Actions:**
- [ ] Delete file: `app/Controllers/Scheduler.php`
- [ ] Remove routes: Search `Routes.php` for `/scheduler`
- [ ] Update `.htaccess` / `nginx.conf` if needed
- [ ] Run full test suite
- [ ] Deploy to production

---

## Technical Considerations

### Routes to Remove (March 1, 2026):

Check `app/Config/Routes.php` for these entries:
```php
// These routes currently redirect to /appointments
$routes->get('scheduler', 'Scheduler::index');
$routes->get('scheduler/client', 'Scheduler::client');

// These API endpoints will be removed entirely
$routes->get('scheduler/slots', 'Scheduler::slots');
$routes->post('scheduler/book', 'Scheduler::book');
```

### Potential Breaking Changes:

**For Internal Code:**
- ✅ No internal code references Scheduler (verified)
- ✅ All views use `/appointments` routes

**For External Integrations:**
- ⚠️ Check server access logs for API calls to `/scheduler/slots` or `/scheduler/book`
- ⚠️ Check webhooks/callbacks that might reference old URLs

### Monitoring Scheduler API Usage:

```bash
# Check last 30 days for Scheduler API calls
grep "Scheduler::" writable/logs/*.log | wc -l

# Find unique IP addresses accessing Scheduler endpoints
grep "Scheduler::" writable/logs/*.log | awk '{print $1}' | sort -u

# Check for external API consumers
grep "POST /scheduler/book" writable/logs/access-*.log
```

---

## Rollback Plan (If Needed)

If critical integrations cannot migrate by March 1, 2026:

### Option 1: Extend Sunset Date
- Update `Sunset` header to new date (e.g., April 1, 2026)
- Add another 30-day grace period
- Document the extension

### Option 2: Proxy Old Endpoints
```php
// In Scheduler.php - keep as thin proxy
public function book()
{
    log_message('warning', 'Legacy Scheduler::book called - proxying to new API');
    
    // Transform request to new format
    $payload = $this->transformLegacyBookingRequest($this->request->getJSON(true));
    
    // Call new controller
    $appointmentsApi = new \App\Controllers\Api\Appointments();
    return $appointmentsApi->store();
}
```

---

## Checklist for March 1, 2026

### Pre-Removal (Feb 28, 2026):
- [ ] Verify no active API consumers (check logs)
- [ ] Backup Scheduler.php to `docs/_archive/legacy-code/`
- [ ] Update CHANGELOG.md with removal notice
- [ ] Prepare deployment announcement

### Removal Day (Mar 1, 2026):
- [ ] Delete `app/Controllers/Scheduler.php`
- [ ] Remove scheduler routes from `app/Config/Routes.php`
- [ ] Remove Scheduler tests (if any)
- [ ] Update API documentation (remove Scheduler endpoints)
- [ ] Git commit: `git commit -m "BREAKING: Remove deprecated Scheduler controller (sunset date reached)"`
- [ ] Deploy to production

### Post-Removal (Mar 1-7, 2026):
- [ ] Monitor error logs for 404s on old routes
- [ ] Check support tickets for migration issues
- [ ] Update QUICK_REFERENCE.md (remove Scheduler)

---

## References

- **Architecture Doc:** `docs/architecture/LEGACY_SCHEDULER_ARCHITECTURE.md`
- **Replacement:** `app/Controllers/Appointments.php`
- **New API Endpoints:**
  - `app/Controllers/Api/Appointments.php`
  - `app/Controllers/Api/Availability.php`
- **Investigation Report:** `docs/APPOINTMENT_SCHEDULING_INVESTIGATION.md` (Issue #6)

---

## Contact

**Questions about migration?**
- Check `docs/api/` for current API documentation
- Review `app/Controllers/Api/Appointments.php` for new endpoint specs
- See `APPOINTMENT_SCHEDULING_INVESTIGATION.md` for refactoring details

---

**Last Updated:** January 29, 2026  
**Next Review:** February 15, 2026 (2 weeks before sunset)  
**Status:** ⚠️ 30 days until removal
