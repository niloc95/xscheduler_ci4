# WebSchedulr — Pre-Calendar Rebuild Audit & Safe Refactor Plan

**Date:** 2026-02-25  
**Branch:** `calendar-refactor`  
**Status:** Pre-rebuild analysis — do not delete anything until this audit is signed off

---

## EXECUTIVE SUMMARY

The current calendar operates as a **hybrid architecture**:

- Backend API layer is well-structured (AvailabilityService, SchedulingService, AppointmentBookingService)
- Frontend contains a **complete parallel scheduling engine** (scheduling-utils.js, slot-engine.js) that duplicates backend logic
- The `Api/Appointments.php` controller is significantly overweight at 1,145 lines
- Database schema has structural inconsistencies that must be corrected before rebuild
- Notification flow is correct (commit → enqueue → async dispatch)
- Role enforcement is correct (filter-level, with AuthorizationService backup)
- The `Scheduler.php` controller is already archived/deprecated — safe to remove

The rebuild must move all slot generation, overlap detection, and availability calculation **entirely to the backend API**. The JS layer must become a pure render/event-dispatch layer.

---

## PART 1 — DATABASE AUDIT

### 1.1 `xs_appointments` (3,109 rows)

| Field | Type | Notes |
|-------|------|-------|
| `id` | INT UNSIGNED PK | |
| `hash` | VARCHAR(64) UNIQUE | Public-safe URL slug — generated on insert |
| `public_token` | CHAR(36) UNIQUE | Customer portal link token |
| `public_token_expires_at` | DATETIME NULL | |
| `provider_id` | INT UNSIGNED FK | NOT NULL, indexed |
| `location_id` | INT UNSIGNED FK | NULLABLE — points to `xs_locations` |
| `location_name/address/contact` | VARCHAR/TEXT | **Denormalized snapshot** — intentional for history |
| `customer_id` | INT UNSIGNED FK | NULLABLE — should be NOT NULL |
| `service_id` | INT UNSIGNED FK | NOT NULL |
| `start_time` | DATETIME | NOT NULL |
| `end_time` | DATETIME | NOT NULL |
| `status` | ENUM | `pending`, `confirmed`, `completed`, `cancelled`, `no-show` |
| `notes` | TEXT | NULLABLE |
| `reminder_sent` | TINYINT(1) | |

**Index status — EXCELLENT:**
```
idx_appts_provider_start     (provider_id, start_time)   ← key query index
idx_appts_status_start       (status, start_time)
idx_provider_start_status    (provider_id, start_time, status)  ← covering index
idx_start_end_time           (start_time, end_time)
idx_appts_customer_start     (customer_id, start_time)
idx_appts_start_provider     (start_time, provider_id)
idx_location_id              (location_id)
```

**Issues Found:**

| # | Issue | Severity | Fix |
|---|-------|----------|-----|
| A1 | Field name `start_time`/`end_time` — architecture doc uses `start_datetime`/`end_datetime` | Low | Standardize naming in services; no migration needed if kept consistent |
| A2 | `customer_id` is nullable — all non-legacy appointments MUST have a customer | Medium | Add NOT NULL + default constraint migration |
| A3 | No timezone column — dates stored in `Africa/Johannesburg` local time, not UTC | High | Document this clearly; add `stored_timezone` column or enforce UTC in service layer |
| A4 | `user_id` referenced in legacy `Scheduler::book()` but not visible in schema | Low | Confirm removed; update legacy code |

### 1.2 `xs_provider_schedules` — Working Hours (44 rows)

| Field | Type | Notes |
|-------|------|-------|
| `provider_id` | INT FK | |
| `location_id` | INT FK NULLABLE | Location-specific schedule |
| `day_of_week` | ENUM string | `'monday','tuesday',...,'sunday'` |
| `start_time` | TIME | |
| `end_time` | TIME | |
| `break_start` | TIME NULLABLE | Single break window per day |
| `break_end` | TIME NULLABLE | |
| `is_active` | TINYINT(1) | |

**Issues Found:**

| # | Issue | Severity | Fix |
|---|-------|----------|-----|
| B1 | `day_of_week` is ENUM string — `xs_business_hours` uses integer `weekday` (0–6) | **High** | Two tables have contradictory day representations. `scheduling-utils.js` indexes into `['sunday','monday',...]` array from Luxon's `.weekday`. Must align before rebuild. |
| B2 | Single break slot per schedule row — no multi-break support | Medium | For advanced scheduling, extract breaks to separate `xs_provider_breaks` table |
| B3 | `xs_business_hours` vs `xs_provider_schedules` — two working-hours tables | Medium | Clarify architectural role of each; `CalendarConfigService` queries `business_hours` directly with raw SQL |

### 1.3 `xs_blocked_times` (7 rows)

| Field | Type | Notes |
|-------|------|-------|
| `provider_id` | INT FK NULL | NULL = global block |
| `start_time` | DATETIME | |
| `end_time` | DATETIME | |
| `reason` | VARCHAR(255) | |

**Issues Found:**

| # | Issue | Severity | Fix |
|---|-------|----------|-----|
| C1 | No `is_recurring` flag — all blocks are one-time only | Medium | Add recurring holiday support before rebuild |
| C2 | No business-scoped index | Low | Add index on `start_time, end_time` for range queries |

### 1.4 `xs_services` (26 rows)

| Field | Type | Notes |
|-------|------|-------|
| `duration_min` | INT UNSIGNED | |
| `price` | DECIMAL(10,2) | |
| `active` | TINYINT | |

**Issues Found:**

| # | Issue | Severity | Fix |
|---|-------|----------|-----|
| D1 | No `buffer_before`/`buffer_after` columns — buffers not per-service in DB | Medium | `AvailabilityService` calculates buffer from global settings. Add per-service buffer columns for precision scheduling |
| D2 | No `slot_resolution` per service — all services use global resolution | Low | Add optional per-service override |

### 1.5 `xs_settings` — Scheduling Dependencies

Settings that must remain intact through the rebuild:

| Key | Value | Used By |
|-----|-------|---------|
| `localization.timezone` | `Africa/Johannesburg` | `AvailabilityService`, `CalendarConfigService` |
| `localization.time_format` | `24h` | `CalendarConfigService`, JS |
| `localization.first_day` | `Monday` | `CalendarConfigService`, JS |
| `business.work_start` | (from business_hours table) | `CalendarConfigService::getSlotMinTime()` |
| `business.work_end` | (from business_hours table) | `CalendarConfigService::getSlotMaxTime()` |

**Missing settings (must be added):**

| Key | Needed For |
|-----|-----------|
| `booking.time_resolution` | Slot grid interval (15/30/60 min) |
| `booking.cancellation_window_hours` | Public booking cancellation limit |
| `booking.reschedule_window_hours` | Public booking reschedule limit |
| `calendar.default_view` | Which view to open on load |

---

## PART 2 — INTEGRATION POINTS

### 2.1 Notification Flow

**Status: CORRECT** — commit-before-notify pattern is enforced.

Flow:
```
validate → insert/update → commit → enqueue (xs_notification_queue)
    → cron: php spark notifications:dispatch-queue
    → NotificationQueueDispatcher → Email/SMS/WhatsApp services
    → xs_notification_delivery_logs
```

Trigger points:
- `Api/Appointments::create()` → line 741 — `appointment_confirmed`
- `Api/Appointments::updateStatus()` → line 581 — `appointment_confirmed` or `appointment_cancelled`
- `Api/Appointments::update()` → line 831 — `appointment_rescheduled` (if time changed)
- `Api/Appointments::delete()` → line 881 — `appointment_cancelled`
- `PublicBookingService::reschedule()` → line 317 — `appointment_rescheduled`
- `Controllers/Appointments.php` → line 622 — `resetReminderSentIfTimeChanged()` — resets the `reminder_sent` flag after a time change so reminders re-fire. Called **after** `$appointmentModel->update()` — **safe, not a pre-commit notification.** ✅

**Notification path is clean across all entry points.**

### 2.2 Public Booking Flow

Full path:
```
GET  /book/{provider-hash}          → PublicSite/BookingController
GET  /api/availability/slots        → Api/Availability (server-computed)
POST /api/appointments (or booking) → validates → insert → enqueue
```

**Status: CORRECT** — availability is server-generated; slots re-validated before insert.

**Risk items:**

| # | Risk | Severity |
|---|------|----------|
| P1 | `Scheduler::book()` (legacy) still accepts POST at `/api/book` — sunset date was 2026-03-01 (past) | High | Remove now |
| P2 | Public booking does NOT use the API Appointments endpoint — it uses `PublicBookingService::create()` directly. This means create-path notification and conflict logic is **partially duplicated** | Medium | Consolidate into single AppointmentBookingService |

---

## PART 3 — ROLE ENFORCEMENT AUDIT

### 3.1 Role Structure

Four roles: `admin`, `provider`, `staff`, `customer`

Enforcement layers:
1. **Route filter** — `['filter' => 'role:admin']` applied at route definition level ✅
2. **RoleFilter.php** — session-based role check, returns 403 or redirect ✅
3. **AuthorizationService.php** — service-level authorization for complex checks ✅
4. **ApiAuthFilter.php** — API endpoints have separate auth ✅

### 3.2 Permission Matrix (Calendar-Relevant)

| Action | Admin | Provider | Staff | Customer |
|--------|-------|----------|-------|----------|
| View any appointment | ✅ | Own only | Assigned providers | Own only |
| Edit appointment | ✅ | Own | Assigned | No |
| Cancel appointment | ✅ | Own | Assigned | Via portal |
| View all providers | ✅ | No | Assigned | No |
| Manage working hours | ✅ | Own | No | No |
| Manage blocked times | ✅ | Own | No | No |

**Issues Found:**

| # | Issue | Severity |
|---|-------|----------|
| R1 | Provider scoping in `Api/Appointments::index()` — uses `provider_id` filter from query param but does **not verify** the requesting provider can only see their own appointments | High |
| R2 | Staff role scoping — not consistently enforced across all API endpoints | Medium |

---

## PART 4 — SETTINGS DEPENDENCIES

All scheduling rules are stored in `xs_settings` (DB) — **not hardcoded**. ✅

**Settings queried at runtime by CalendarConfigService:**
- `localization.time_format`, `localization.timezone`, `localization.first_day`
- `business.work_start`, `business.work_end`
- `calendar.slot_duration`, `calendar.slot_min_time`, `calendar.slot_max_time`
- `calendar.default_view`, `calendar.show_weekends`

**Problem:** `CalendarConfigService` has a raw DB query for `business_hours` table instead of using a model. This bypasses the model layer and will break if the table is modified.

```php
// CalendarConfigService.php line ~190 — RAW QUERY (should use BusinessHourModel)
$businessHours = $db->table('business_hours')
    ->select('weekday, MIN(start_time) as start_time, MAX(end_time) as end_time')...
```

**Duplicated in JS:** Business hours are passed as JSON to the frontend and `scheduling-utils.js` uses them for client-side slot generation. This duplication is the central architectural problem.

---

## PART 5 — PUBLIC BOOKING FLOW REVIEW

```
1. Customer visits /book/{provider-slug}
2. Selects service → JS fetches /api/availability/calendar (server range check ✅)
3. Selects date → JS fetches /api/availability/slots (server slot check ✅)
4. Fills form → POST /booking/submit or POST /api/appointments
5. PublicBookingService::create() validates:
   - Provider exists + active
   - Location strictness check (no auto-select)
   - isSlotAvailable() via AvailabilityService (race condition guard ✅)
6. Insert appointment (transaction)
7. Enqueue notification
```

**Status: CORRECT** for the main public path. The legacy `/api/book` endpoint (Scheduler::book) also does a final slot check before insert.

**Race condition guard:** AvailabilityService::isSlotAvailable() is called immediately before insert — this handles concurrent bookings correctly.

---

## PART 6 — CONTROLLER & VIEW AUDIT

### 6.1 Fat Controller: `Api/Appointments.php` (1,145 lines)

This controller does too much:

| What's in the controller | Where it should be |
|---|---|
| Inline SQL JOIN query building (lines ~100-160) | `AppointmentModel::getListWithRelations()` |
| Data transformation / response shaping (lines ~160-210) | `AppointmentFormatter` service or model method |
| Availability check logic (line 293) | Thin call to `AvailabilityService` |
| Direct notification enqueue (lines 581, 741, 831, 881) | `AppointmentBookingService` side-effect |
| Booking creation validation (lines 715-780) | `AppointmentBookingService::create()` |

### 6.2 JavaScript Business Logic Leaks — **CRITICAL**

**`resources/js/utils/scheduling-utils.js`** (326 lines) — contains a **complete scheduling engine**:

| Function | What it does | Backend Equivalent |
|----------|-------------|-------------------|
| `doTimeRangesOverlap()` | Interval overlap check | `AvailabilityService::hasConflict()` |
| `findOverlappingAppointments()` | Conflict detection | `AvailabilityService` |
| `isProviderWorkingForSlot()` | Working hours enforcement | `AvailabilityService` |
| `getProviderAvailabilityForSlot()` | Per-provider availability | `AvailabilityService::getAvailableSlots()` |
| `generateSlotsWithAvailability()` | Full slot grid generation | `AvailabilityService::getAvailableSlots()` |
| `checkForConflicts()` | Conflict validation | `AvailabilityService::isSlotAvailable()` |

**`resources/js/modules/scheduler/slot-engine.js`** (483 lines):

| Function | What it does | Problem |
|----------|-------------|---------|
| `generateSlots()` | Generates slot array from local state | Uses locally cached appointments — can be stale |
| `computeDayAvailability()` | Availability indicator for month grid | Computed client-side from cached data — not authoritative |
| `renderAvailabilityBadge()` | Renders slot count badge | OK (rendering only) |

**The fundamental problem:** The scheduler loads a batch of appointments once, then uses the JS engine to re-compute availability for every slot render. This means:
- Slot availability shown to staff may be stale (race condition risk)
- The entire availability algorithm is duplicated in two languages
- Any change to availability rules must be made in two places

### 6.3 `CalendarConfigService` Legacy Contamination

Still contains FullCalendar config keys:
- `headerToolbar`, `navLinks`, `editable`, `selectable`, `selectMirror`, `dayMaxEvents`
- `timeGridWeek`, `timeGridDay` view names
- These are FullCalendar-specific — must be cleaned for new custom renderer

### 6.4 Views — No Logic Leaks

PHP views only use `strtotime()` + `date()` for display formatting — acceptable. No model instantiation, no availability logic, no business rules in views. ✅

---

## DEPENDENCY MAP

```
Calendar UI (appointments/index.php + JS scheduler modules)
    │
    ├── API calls (read)
    │   ├── GET /api/appointments         → Api/Appointments::index()
    │   │       └── AppointmentModel::builder() [direct SQL]
    │   ├── GET /api/availability/slots   → Api/Availability::slots()
    │   │       └── AvailabilityService::getAvailableSlots()
    │   └── GET /api/availability/calendar→ Api/Availability::calendar()
    │           └── AvailabilityService::getCalendarAvailability()
    │
    ├── API calls (write)
    │   ├── POST   /api/appointments      → Api/Appointments::create()
    │   │       └── AppointmentBookingService::create()
    │   │           └── AvailabilityService::isSlotAvailable() [race guard]
    │   │           └── NotificationQueueService::enqueue() [after commit]
    │   ├── PATCH  /api/appointments/:id  → updateStatus()
    │   └── PUT    /api/appointments/:id  → update()
    │
    └── LOCAL JS engine (PROBLEM — must be eliminated)
        ├── scheduling-utils.js → generates slots from local state
        └── slot-engine.js     → computes availability from local state
```

```
Notification Chain (safe, do not break)
    appointment event
    → NotificationQueueService::enqueue()
    → xs_notification_queue (DB)
    → cron: notifications:dispatch-queue
    → NotificationQueueDispatcher::dispatch()
    → NotificationEmailService / NotificationSmsService / NotificationWhatsAppService
    → xs_notification_delivery_logs
```

---

## RISK REGISTER

| ID | Risk | Severity | Impact | Mitigation |
|----|------|----------|--------|------------|
| RISK-01 | Removing JS slot engine breaks week/day availability indicators | **Critical** | UI shows no slot info | Replace with API call to `/api/availability/calendar` before removing |
| RISK-02 | `Scheduler::book()` legacy endpoint still active past sunset (2026-03-01) | High | Unvalidated booking path | Remove in first PR |
| RISK-03 | ~~`Controllers/Appointments.php` line 622 may use direct notification send~~ | **Resolved** | Not a notification — it is `resetReminderSentIfTimeChanged()` called post-update. Safe. ✅ | No action needed |
| RISK-04 | `customer_id` is nullable in DB — new code assumes NOT NULL | Medium | Null reference errors | Run migration to backfill + enforce NOT NULL |
| RISK-05 | `day_of_week` ENUM strings vs integer weekday — two tables disagree | High | Wrong hours loaded for day | Standardize to integer before AvailabilityService rebuild |
| RISK-06 | Provider scoping gap in API endpoints | High | Provider can view other providers' appointments | Add `AuthorizationService` check in API index |
| RISK-07 | `CalendarConfigService` uses raw DB query for business_hours | Medium | Bypasses model caching | Replace with `BusinessHourModel` |
| RISK-08 | Timezone not normalized (stored as local, not UTC) | High | Availability bugs at DST change | Decide: store UTC + display convert, OR always store+query in fixed tz |
| RISK-09 | No DB transaction in legacy `Scheduler::book()` | Medium | Partial insert without notification | Moot if RISK-02 resolved |
| RISK-10 | `service.duration_min` vs `service.duration_minutes` naming | Low | API consumers may use wrong field | Add alias in AppointmentFormatter |

---

## PART 7 — SAFE REMOVAL PLAN

### What "the calendar" actually is

The calendar is **not a backend module** — the backend is already well-structured (API + Services + Models). The calendar is:

```
app/Views/appointments/index.php          ← render shell
resources/js/modules/scheduler/           ← 9 JS modules (4,000+ lines)
resources/js/utils/scheduling-utils.js   ← business logic (326 lines) ← REMOVE
resources/css/scheduler.css               ← styles
```

The backend API, services, and models are **safe to keep as-is** for the rebuild.

### What MUST stay untouched during rebuild

```
app/Services/AvailabilityService.php        ← DO NOT TOUCH
app/Services/AppointmentBookingService.php  ← DO NOT TOUCH
app/Services/PublicBookingService.php       ← DO NOT TOUCH
app/Services/Notification*.php              ← DO NOT TOUCH
app/Controllers/Api/Availability.php        ← DO NOT TOUCH
app/Controllers/Api/Appointments.php        ← refactor only (no rename/move)
app/Models/*                                ← DO NOT TOUCH
app/Filters/*                               ← DO NOT TOUCH
resources/js/modules/appointments/          ← DO NOT TOUCH (booking form UI)
```

### Removal Sequence (phased — zero downtime)

```
PHASE 0 — DB Fixes (migrations required before rebuild)
────────────────────────────────────────────────────────
  [ ] Migrate: day_of_week ENUM string → store both or convert to INT
  [ ] Migrate: customer_id NOT NULL enforcement
  [ ] Add: booking.time_resolution setting
  [ ] Add: booking.cancellation_window_hours setting
  [ ] Document: timezone storage policy (local vs UTC)

PHASE 1 — Remove Legacy Dead Code
────────────────────────────────────────────────────────
  [ ] Remove: app/Controllers/Scheduler.php (already deprecated)
  [ ] Remove: /api/book and /api/slots routes from Routes.php
  (Note: Controllers/Appointments.php line 622 is safe — not a notification send)

PHASE 2 — Extract Service Layer (before touching UI)
────────────────────────────────────────────────────────
  [ ] Create: AppointmentQueryService (extract JOIN query from Api/Appointments)
  [ ] Create: AppointmentFormatterService (extract response shaping)
  [ ] Create: CalendarRangeService (generateMonthGrid, generateWeekRange, generateDayRange)
  [ ] Create: DayViewService (time grid + block positioning)
  [ ] Create: WeekViewService (aggregates DayViewService × 7)
  [ ] Create: MonthViewService (CalendarRange + AppointmentQuery + AvailabilityService)
  [ ] Fix: CalendarConfigService — replace raw DB query with BusinessHourModel
  [ ] Fix: Api/Appointments index() — use AppointmentQueryService
  [ ] Fix: Provider scoping (RISK-06)

PHASE 3 — New API Endpoints for Calendar Rendering
────────────────────────────────────────────────────────
  [ ] Add: GET /api/calendar/month?year=&month=  → MonthViewService response
  [ ] Add: GET /api/calendar/week?date=          → WeekViewService response
  [ ] Add: GET /api/calendar/day?date=           → DayViewService response
  [ ] These return pre-computed render models (top/height/overlap data)

PHASE 4 — New JS Calendar (build alongside, don't delete old)
────────────────────────────────────────────────────────
  [ ] Create: resources/js/modules/calendar-v2/ (new directory)
  [ ] Calendar renders from API responses only
  [ ] NO scheduling logic in JS — pure render layer
  [ ] Remove: scheduling-utils.js
  [ ] Remove: slot-engine.js generateSlots/computeDayAvailability
  [ ] Keep: slot-engine.js renderAvailabilityBadge (rendering only, fed from API)

PHASE 5 — Swap Views
────────────────────────────────────────────────────────
  [ ] Replace: app/Views/appointments/index.php with new view
  [ ] Remove: old scheduler-*.js modules once new ones verified
  [ ] Remove: CalendarConfigService FullCalendar legacy keys

PHASE 6 — Cleanup
────────────────────────────────────────────────────────
  [ ] Remove: WebSchedulr Calendar Architecture (Month + Day + Week).md from root
  [ ] Move: to docs/architecture/
  [ ] Run full test suite
  [ ] Verify all notification paths still functional
```

---

## RECOMMENDED EXECUTION ORDER

1. **Fix DB + settings (Phase 0)** — migrate day_of_week, add missing settings keys. No UI impact.
2. **Remove Scheduler.php + legacy routes** (Phase 1). Zero risk.
3. **Build service layer** (Phase 2) — new services alongside existing code. No deletions yet.
4. **Add /api/calendar/* endpoints** (Phase 3) — additive, no breaking changes.
5. **Build new JS calendar in parallel directory** (Phase 4) — both exist simultaneously.
6. **Feature-flag switchover** — swap the view template, verify, then remove old.
7. **Delete old JS modules** — only after new calendar is verified in production.

---

## STRUCTURAL CHANGES REQUIRED

### New Service Files to Create
```
app/Services/Calendar/
    CalendarRangeService.php   ← 42-day grid, week range, day normalize
    MonthViewService.php       ← combines Range + Query + Availability
    DayViewService.php         ← time grid + block positioning + overlap
    WeekViewService.php        ← loops DayViewService × 7

app/Services/Appointment/
    AppointmentQueryService.php     ← extract from Api/Appointments::index()
    AppointmentFormatterService.php ← response shaping
```

### Existing Files to Refactor (not remove)
```
app/Controllers/Api/Appointments.php    ← slim down to ~300 lines
app/Services/CalendarConfigService.php  ← remove FullCalendar keys, fix raw query
```

### Files to Remove (after phase swap)
```
resources/js/utils/scheduling-utils.js
resources/js/modules/scheduler/slot-engine.js  (or gut generateSlots/computeDayAvailability)
resources/js/modules/scheduler/scheduler-month-view.js
resources/js/modules/scheduler/scheduler-week-view.js
resources/js/modules/scheduler/scheduler-day-view.js
resources/js/modules/scheduler/scheduler-core.js
app/Controllers/Scheduler.php   ← already safe to remove now
```

### DB Migrations Required Before Rebuild
```
M001_fix_provider_schedules_day_of_week.php  — standardize to integer
M002_appointments_customer_id_not_null.php   — enforce NOT NULL
M003_add_booking_resolution_settings.php     — add missing setting keys
M004_add_service_buffer_columns.php          — optional but recommended
```

---

## FINAL ASSESSMENT

The system is **safe to rebuild** with the phased approach above. The primary risks are:

1. The JS scheduling engine must be removed **last** (not first), only after the API delivers pre-computed render models
2. The `day_of_week` inconsistency is a silent bug that will cause wrong-hour loading — fix before any service refactoring
3. The timezone storage policy must be decided and documented before building new calendar services
4. Provider scoping gap is a security issue independent of the calendar rebuild — fix in Phase 2

The notification chain, public booking flow, and role enforcement are all correct and do not need modification during the calendar rebuild.

---

*Audit completed: 2026-02-25 | Author: Copilot | Branch: calendar-refactor*

---

## IMPLEMENTATION TICK LIST

Tracks the resolution of every finding from this audit. Updated as work progresses on branch `calendar-refactor`.

### Phase 0 — Database Migrations

- [x] **A2** `customer_id` NOT NULL enforced — migration `2026-02-26-000100_EnforceCustomerIdNotNull.php`
- [x] **M003** Missing settings keys added — migration `2026-02-26-000200_AddCalendarSchedulingSettings.php`
  - `booking.time_resolution`, `booking.cancellation_window`, `booking.reschedule_window`
  - `calendar.default_view`, `calendar.day_start`, `calendar.day_end`, `general.timezone_storage`
- [x] **D1** Per-service buffer columns — migration `2026-02-26-000300_AddServiceBufferColumns.php`
  - Adds `buffer_before INT NULL` and `buffer_after INT NULL` to `xs_services`
- [x] **Migrations applied** — `php spark migrate -n App` (all 3 ran successfully 2026-02-26)

### Phase 1 — Remove Legacy Dead Code

- [x] **RISK-02** `app/Controllers/Scheduler.php` deleted (sunset 2026-03-01 was past)
- [x] **RISK-02** Legacy routes removed from `app/Config/Routes.php`:
  - `GET /scheduler/*`, `GET /book` (308 redirects)
  - `GET /api/slots`, `POST /api/book` (Scheduler::slots / Scheduler::book)

### Phase 2 — New Service Layer

- [x] **B1 / RISK-05** `day_of_week` dual-format gap resolved — `CalendarRangeService::normalizeDayOfWeek()` bridges ENUM string ↔ INT
- [x] `app/Services/Calendar/CalendarRangeService.php` created
  - `generateMonthGrid()`, `generateWeekRange()`, `generateDaySlots()`, `normalizeDayOfWeek()`, `getMonthBounds()`
  - Reads `localization.timezone` + `localization.first_day` from settings
- [x] **RISK-01 (partial)** `app/Services/Appointment/AppointmentQueryService.php` created
  - `getForCalendar()`, `getForRange()`, `getGroupedByDate()`, `getGroupedByDateAndProvider()`
- [x] **RISK-06** Provider scoping gap fixed — `AppointmentQueryService::applyProviderScope()` enforces `provider_id` when `user_role = 'provider'`
- [x] **RISK-01 (partial)** `app/Services/Appointment/AppointmentFormatterService.php` created
  - Canonical snake_case output shape including `buffer_before` / `buffer_after`
- [x] **RISK-07** `CalendarConfigService::getBusinessHoursForCalendar()` refactored — raw `$db->table('business_hours')` (missing `xs_` prefix bug) replaced with `BusinessHourModel`; now accepts optional `$providerId`
- [x] **RISK-07** `CalendarConfigService::getJavaScriptConfig()` cleaned — FullCalendar-specific keys (`headerToolbar`, `navLinks`, `editable`, `selectable`, `selectMirror`, `dayMaxEvents`) removed
- [x] **RISK-01 (complete)** `Api/Appointments::index()` refactored — inline builder + `array_map` replaced with `AppointmentQueryService::getForCalendar()` + `AppointmentFormatterService::formatManyForCalendar()`
- [x] **RISK-06 (API entry)** Session-based `user_role` + `scope_to_user_id` passed into `AppointmentQueryService` from controller

### Phase 3 — View Services

- [x] `app/Services/Calendar/DayViewService.php` created
  - `build(date, filters)` → complete day render model (time grid + appointments injected per slot)
  - Sweep-line overlap layout (`_colIndex`, `_colCount`, `_widthPct`, `_leftPct`) for CSS multi-column display
  - Reads `calendar.day_start`, `calendar.day_end`, `booking.time_resolution` from settings
- [x] `app/Services/Calendar/WeekViewService.php` created
  - `build(date, filters)` → complete week render model (7 days × time grid + appointments per day)
  - Single DB query for the whole week via `AppointmentQueryService::getGroupedByDate()`
  - Human-readable `weekLabel` (e.g. "Feb 23 – Mar 1, 2026")
- [x] `app/Services/Calendar/MonthViewService.php` created
  - `build(year, month, filters)` → 42-cell grid (6×7) with `hasMore` + `moreCount` overflow flags
  - Single DB query for the entire grid range (including leading/trailing padding days)

### Phase 4 — New API Endpoints

- [x] `app/Controllers/Api/CalendarController.php` created
  - `GET /api/calendar/day?date=YYYY-MM-DD`
  - `GET /api/calendar/week?date=YYYY-MM-DD`
  - `GET /api/calendar/month?year=YYYY&month=M`  (also accepts `?date=`)
  - Applies session-based role scoping (RISK-06) via `current_user_role()` + `scope_to_user_id`
  - Graceful error handling with fallback to `serverError()`
- [x] Routes registered in `app/Config/Routes.php` under `api` group with `auth` filter

### Phase 5 — Frontend (SchedulerCore server mode)

- [x] `SchedulerCore` updated with `mode = 'server'` support
  - `this.calendarModel = null` state field added
  - `loadCalendarModel()` method: fetches `/api/calendar/{view}?...`, populates `this.calendarModel`, syncs `this.appointments` from model so client-side views continue to work
  - `loadData()` wrapper: routes to `loadCalendarModel()` (server) or `loadAppointments()` (client)
  - `changeView()` and `navigateToDate()` updated to use `loadData()` (respects mode setting)
  - `_performRender()` passes `calendarModel` to all views (views can use pre-computed grid when available)
  - Activation: pass `mode: 'server'` to `new SchedulerCore(id, { mode: 'server' })` in app.js

### Phase 6 — Cleanup (Partially Complete)

- [x] `generateSlotsWithAvailability()` in `scheduling-utils.js` — marked `@deprecated Phase 6` with pointer to `/api/calendar/*`
- [x] `generateSlots()` in `slot-engine.js` — marked `@deprecated Phase 6` with server-mode note
- [ ] **Full removal of `generateSlots()` from `slot-engine.js`** (requires all views to consume `calendarModel.days[].dayGrid.slots`)
- [ ] **Full removal of `generateSlotsWithAvailability()`** from `scheduling-utils.js` (after above)
- [ ] FullCalendar packages — already absent from `package.json` ✅

### Build Validation

- [x] `php spark migrate -n App` — apply all 3 Phase 0 migrations (2026-02-26)
- [x] `npm run build` — Vite 6 production build passes, 258 modules, no errors
- [x] `npm run build` — Phase 3+5 build passes, main.js 272.78 kB (+1.28 kB for server mode)
- [ ] Manual smoke test — calendar loads, events render, provider scope enforced
- [ ] Activate server mode: `new SchedulerCore(id, { mode: 'server' })` in app.js → verify `/api/calendar/week` response
- [ ] Commit branch `calendar-refactor`

---

*Tick list added: 2026-02-26 | Status: Phase 3 + Phase 4 + Phase 5 complete; Phase 6 deprecations done, full removal pending*
