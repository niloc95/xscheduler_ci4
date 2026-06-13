---
name: webscheduler-scheduling
description: WebScheduler appointments and scheduling contract — booking pipeline, status → notification event mapping, scheduler refresh and mutation semantics, business hours architecture (global vs per-provider), availability and slot generation pipeline, calendar architecture and data flow, server vs non-server mode loading. Use whenever you're touching appointments, the scheduler, availability checks, business hours, the calendar grid, slot generation, day/week/month views, blocked times, or anything that reads `xs_appointments`, `xs_business_hours`, `xs_blocked_times`, or `xs_provider_schedules`. Triggers on phrases like "appointment", "booking", "schedule", "scheduler", "calendar", "availability", "slot", "business hours", "working hours", "blocked time", "DayView", "WeekView", "AvailabilityService", "AppointmentBookingService", or any work involving when/where/how an appointment is created or displayed.
---

# WebScheduler — Appointments & Scheduling Contract

## 1. Single Sources of Truth

- `AvailabilityService`
- `AppointmentBookingService`
- `Appointment\AppointmentQueryService`
- `Appointment\AppointmentStatus`

## 2. Booking Pipeline (Mandatory)

1. Validate service/provider context
2. Resolve timezone boundaries
3. Validate business hours
4. Validate availability and conflicts
5. Resolve/create customer
6. Persist appointment
7. **Enqueue** notifications (do not dispatch inline — see `notifications` skill)

## 3. Canonical Appointment Detail Access

All appointment detail views are accessed via the **scheduler modal only**.

**Entry point:** `/appointments?open={hash}`

**Deep-link compatibility:** Legacy `/appointments/view/{hash}` endpoint persists as a compatibility redirect to `/appointments?open={hash}` to prevent breaking old links, bookmarks, and notification emails.

**Modal implementation:** `AppointmentDetailsModal` in `resources/js/modules/scheduler/appointment-details-modal.js`

**Routes affected:**
- Dashboard "Today's Schedule" card links to `/appointments?open={ref}`
- Customer management history "View Details" links to `/appointments?open={ref}`
- Notification emails contain `/appointments?open={ref}` links

## 4. Status → Notification Event Mapping (Owner Section)

Canonical source: `AppointmentStatus::notificationEvent()`

| Status | Event |
| --- | --- |
| `pending` | `appointment_pending` |
| `confirmed` | `appointment_confirmed` |
| `completed` | `appointment_confirmed` |
| `cancelled` | `appointment_cancelled` |
| `no_show` | `appointment_no_show` |
| `rescheduled` | `appointment_rescheduled` |

The `notifications` skill references this mapping but does not re-document it.

## 5. Scheduler Refresh Semantics (Critical)

**The scheduler is not real-time by default.**

- No WebSocket/SSE sync channel for appointment data
- No periodic polling for appointment model refresh
- Day view timer updates **now-line only**

## 6. Mode-Aware Data Loading Contract

`SchedulerCore` loading methods:

- **`loadData()`** — canonical mutation-safe refresh entry
  - **server mode:** `calendarModel` from `/api/calendar/{view}`
  - **non-server mode:** flat appointments from `/api/appointments`
- **`loadAppointments()`** — flat appointment refresh only (not `calendarModel`)

**Rule:** Mutations that can move slots must refresh through `loadData()` in server mode. Do not replace `loadData()` with `loadAppointments()` in server-mode mutation paths — caught by the variable-collision quality gate.

## 7. Canonical Mutation Pipeline

Use `appointment-mutation-coordinator` for scheduler-side mutations.

Expected behavior:

- Inject CSRF
- Execute mutation request
- In scheduler context, reload via `loadData()` and re-render
- Dispatch `appointment:changed` event
- Coordinator owns toast and loading state

## 8. Business Hours Architecture (Owner Section)

### 8.1 Two Distinct Concepts — Do Not Conflate

| Concept | Source | Scope | Purpose |
| --- | --- | --- | --- |
| **Global business hours** | `xs_settings` keys `business.work_start`, `business.work_end` | System-wide outer bounds | The widest window any appointment can be booked in |
| **Provider schedule** | `xs_business_hours` rows (always per `provider_id` + `weekday`) | Per-provider | The specific hours a given provider is available |

**Critical:** `xs_business_hours` has **NO global-only rows**. Every row always has a `provider_id`. Querying this table without a `provider_id` filter returns an arbitrary provider's row — not a global business hour. This exact mistake caused all providers' slots to start at 10:00 (root cause fixed in commit `34a74a0`).

### 8.2 Canonical Service Responsibilities

| Service / Method | Responsibility |
| --- | --- |
| `BusinessHoursService::getBusinessHoursForDay($weekday)` | Returns global hours from `xs_settings`. `$weekday` kept for interface compatibility; global hours apply uniformly to all weekdays. |
| `BusinessHoursService::getWeeklyHours()` | Returns Mon–Fri (weekdays 1–5) keyed by weekday, sourced from `xs_settings`. |
| `BusinessHoursService::validateAppointmentTime($start, $end)` | Validates an appointment falls within global hours. Returns `['valid'=>bool,'reason'=>?string,'hours'=>?array]`. |
| `AvailabilityService::constrainToBusinessHours($date, $providerHours)` | Narrows a provider's hours to the global bounds. Uses `SettingModel::getByKeys(['business.work_start','business.work_end'])`. Returns `null` if the window collapses to zero. |
| `BusinessHourModel` | Queries `xs_business_hours` filtered by `provider_id` + `weekday`. Correct place to read individual provider schedules. **Never use this without a `provider_id` filter.** |

### 8.3 Global Business Hours Setting Keys

| Key | Meaning | Example |
| --- | --- | --- |
| `business.work_start` | Global open time | `'06:00'` |
| `business.work_end` | Global close time | `'17:00'` |

Read via: `SettingModel::getByKeys(['business.work_start', 'business.work_end'])`.

`SettingModel` has **no `getValue()` method**. Use `getByKeys()` for all reads (including single keys).

### 8.4 ProviderWorkingHoursTrait — Active Debt

`app/Services/Calendar/ProviderWorkingHoursTrait::getBusinessHours()` falls back to calling `$settings->getValue('booking.day_start', '08:00')`. `SettingModel` has no `getValue()` method and this will throw `BadMethodCallException` at runtime when `$this->timeGrid` is not available.

**The fix** is to replace the fallback with `SettingModel::getByKeys(['booking.day_start', 'booking.day_end'])`. This path is hit for the "placeholder provider" (`provider_id = 0`) case.

## 9. Availability and Slot Generation Pipeline

The server-side slot-generation pipeline operates in this sequence:

1. **Resolve provider schedule** — Query `xs_business_hours` with `provider_id` + `weekday` filter to get the provider's start/end and breaks for the requested day.
2. **Constrain to global bounds** — `AvailabilityService::constrainToBusinessHours()` reads `business.work_start` / `business.work_end` from `xs_settings` and narrows the provider window. If the provider window falls entirely outside global hours the day returns no slots.
3. **Remove blocked times** — Query `xs_blocked_times` where `provider_id` matches and the blocked interval overlaps the working window.
4. **Remove booked appointments** — Query `xs_appointments` for confirmed/pending appointments within the window for that provider.
5. **Generate time slots** — Divide remaining free time into increments from `booking.slot_duration` setting.
6. **Return slots** — Each slot is a UTC-anchored datetime.

### 9.1 Key Variables Required Before Calling Availability

| Variable | Source | Notes |
| --- | --- | --- |
| `$providerId` | `xs_users.id` | Required; `0` is only a UI placeholder |
| `$date` | Request input | ISO date `YYYY-MM-DD` |
| `$serviceId` | `xs_services.id` | Determines slot duration |
| `$timezone` | `localization.timezone` from `xs_settings` | `?string`, `null` resolves via `TimezoneService::businessTimezone()` |
| `business.work_start` / `business.work_end` | `xs_settings` | Global outer bounds |
| Provider's `xs_business_hours` rows | `provider_id` + `weekday` | Per-provider schedule |
| `xs_blocked_times` rows | Provider + date range | Provider non-availability intervals |
| `booking.slot_duration` | `xs_settings` | Slot increment in minutes |

### 9.2 AvailabilityService Contract

- `isSlotAvailable()` `$timezone` parameter is `?string` with `null` resolving via `TimezoneService::businessTimezone()`.
- Always pass the correct business/booking timezone explicitly; do not rely on the default.

## 10. Calendar Architecture and Data Flow (Owner Section)

### 10.1 Server-Side View Model API

| Endpoint | Service | View |
| --- | --- | --- |
| `GET /api/calendar/day?date=YYYY-MM-DD` | `DayViewService` | Day view |
| `GET /api/calendar/week?date=YYYY-MM-DD` | `WeekViewService` | Week view |
| `GET /api/calendar/month?year=&month=` | `MonthViewService` | Month view |

Common query params: `provider_id`, `service_id`, `location_id`, `status`.

Response envelope: `{ "data": { ...viewModel... }, "meta": { "view", "date", "generated_at" } }`

`viewModel.businessHours` (`{ startTime, endTime }`) is sourced from `ProviderWorkingHoursTrait::getBusinessHours()` using `booking.day_start` / `booking.day_end` settings — these control the **calendar grid display window**, not booking availability.

### 10.2 Client-Side Scheduler Modules

| File | Role |
| --- | --- |
| `scheduler-core.js` | Orchestrator. Owns `this.calendarModel`, `this.appointments`, `loadData()`, `loadCalendarModel()`, `loadAppointments()`. |
| `scheduler-day-view.js` | Day column rendering. Derives `this.businessHours` via `_resolveBusinessHours(config, calendarModel)`. |
| `scheduler-week-view.js` | Week grid rendering. |
| `scheduler-month-view.js` | Month grid rendering. |
| `time-grid-utils.js` | `getBusinessHours(config)` — extracts `startHour`/`endHour` from config or `calendarModel.businessHours`. Returns `{ startHour, endHour, startTime, endTime, hoursPerDay }`. |
| `settings-manager.js` | Bootstraps `window.appTimezone` from `/api/v1/settings/localization`. |
| `appointment-details-modal.js` | Renders the appointment detail modal. |

### 10.3 Data Load Paths

**Server mode (default):**

`loadData()` → `loadCalendarModel()` → `GET /api/calendar/{view}` → `calendarModel` set → appointments synced from `calendarModel.appointments` → `render(calendarModel)` → `DayView._resolveBusinessHours()` uses `calendarModel.businessHours`.

**Non-server mode (fallback):**

`loadData()` → `loadAppointments()` → `GET /api/appointments` → flat array → `render(null)` → `DayView._resolveBusinessHours()` falls back to `config.businessHours` or `config.slotMinTime`/`config.slotMaxTime`, then hardcoded `'08:00'`/`'17:00'`.

### 10.4 Calendar Grid Hours vs Booking Availability

These are **two separate concerns**:

| Concern | Source | Where used |
| --- | --- | --- |
| Calendar grid display window | `booking.day_start` / `booking.day_end` in `xs_settings` | `ProviderWorkingHoursTrait`, passed as `viewModel.businessHours` to JS |
| Booking availability bounds | `business.work_start` / `business.work_end` in `xs_settings` | `BusinessHoursService`, `AvailabilityService::constrainToBusinessHours()` |
| Provider working hours | `xs_business_hours` (per `provider_id` + `weekday`) | `AvailabilityService` slot generation, `BusinessHourModel` |

### 10.5 Role Scoping (Automatic)

- **Providers:** `CalendarController` scopes to their own appointments only regardless of query params.
- **Admins/Staff:** see all appointments; can filter by `provider_id` query param.
- Do not rely on `provider_id` query param as the sole authorization mechanism — role-based scoping is enforced server-side.

### 10.6 Datetime Parsing on the Client

All scheduler views parse API datetimes as UTC via Luxon:

```js
DateTime.fromISO(val, { zone: 'utc' }).setZone(window.appTimezone)
```

`window.appTimezone` is set by `SettingsManager` from `/api/v1/settings/localization`. Never parse appointment datetimes as local time.

## 11. Provider Assignment Integrity (Operational)

Before shipping any dashboard/provider-card changes that touch service, location, or availability filtering, run:

```bash
php spark audit:provider-assignments
```

See `rules` skill (Rule #5) for full criteria.

## 12. Pre-Merge Scheduling Grep Checks

```bash
# Detect unfiltered xs_business_hours queries (must always have provider_id filter)
rg "table\('business_hours'\)|from.*xs_business_hours" app/

# Detect SettingModel::getValue() calls (method does not exist; use getByKeys instead)
rg "->getValue\(" app/Services app/Controllers app/Models

# Detect deprecated appointment linkage usage
rg "appointments\.user_id|\buser_id\b" app/ resources/
```

Any result must be reviewed and justified or fixed.

## 13. Cross-Skill References

- Status → event mapping consumers → `notifications` skill
- Public booking surfaces the same pipeline → `public-booking` skill
- Schema details for all `xs_*` tables → `database` skill
- Timezone conversion rules → `database` skill (§ Timezone Integrity)
- `xs_appointments.delivery_mode` (onsite/online_zoom/online_jitsi) is aggregated for the Analytics
  Overview tab via `AppointmentModel::getDeliveryModeStats()` (mirrors `getStatusStats()`), called
  from `Analytics::getAppointmentAnalytics()`. Chart/icon conventions → `ui-ux` skill §5.5.
