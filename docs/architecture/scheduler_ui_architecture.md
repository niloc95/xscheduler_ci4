# Scheduler UI Architecture

**Location:** `resources/js/modules/scheduler/`  
**Total modules:** 31 files (26 root + 5 in `stats/`)

For the server-side data flow, calendar API endpoints, and datetime parsing contract, see `Agent_Context_v2.md §8.9`. This document covers the frontend module structure only.

---

## Module Inventory

### Orchestrator

#### `scheduler-core.js` (1,066 lines)
Central state manager and lifecycle coordinator. Every view, manager, and utility registers with or is owned by core.

**Owns:**
- Single source of truth: `appointments`, `providers`, `visibleProviders`, active filters
- View instances: `this.views.today`, `.day`, `.week`, `.month`
- Managers: `settingsManager`, `rightPanel`, `dragDropManager`, `appointmentDetailsModal`
- `calendarModel` — pre-computed server-side model (when server mode active)

**Key methods:**
- `init()` — async bootstrap: loads settings, providers, data
- `loadData()` — canonical mutation-safe refresh entry (server mode → `/api/calendar/{view}`; non-server → `/api/appointments`)
- `render()` — debounced (100ms) render dispatcher
- `changeView(viewName)` — switches active view
- `navigateToDate/Today/Next/Prev()` — date navigation
- `setFilters({status, providerId, serviceId, locationId})` — filter update + re-render
- `toggleProvider(providerId)` — show/hide a provider column
- `getFilteredAppointments()` — filtered subset of loaded appointments
- `destroy()` — cleanup

**Dual-mode loading:**
- **Server mode** (default): `GET /api/calendar/{view}?date=...` → pre-computed `calendarModel` with overlap layout, working hours, slot injection
- **Non-server mode**: `GET /api/appointments?start=...&end=...` → flat array; client resolves layout

**Notable:** Auto-opens appointment from URL query param `?open={hash}` or hash `#appointment-{id}` on init.

---

### View Modules

#### `scheduler-day-view.js` (929 lines)
Per-provider vertical columns. One column per visible provider, side-by-side.

- Grid: 100px/hour (vs 60px in week view)
- Adaptive appointment cards — 5 content tiers based on rendered pixel height:
  - `< 35px`: time + customer name + status dot
  - `< 65px`: + service icon
  - `< 100px`: larger layout
  - `< 150px`: status pill
  - `≥ 150px`: avatar, provider name, service, location, notes
- Overlap resolution: consumes `_widthPct`/`_leftPct`/`_colIndex` from server model; falls back to client cluster algorithm
- Now-line updates every 60 seconds, spans all provider columns
- Non-working day overlay from `calendarModel.providerColumns[].workingHours.isActive`
- Inline style injection uses `data-style` JSON attributes (CSP compliance)

Config constants in `scheduler-day-view-config.js` (3 lines): pixel-per-hour and min appointment height.

#### `scheduler-week-view.js` (363 lines)
7-day chip grid. Each day cell shows max 2–4 appointment chips plus a "+N more" button.

- Right panel (powered by `right-panel.js`) lists all appointments for the selected day, grouped by provider in a collapsible accordion
- Selecting a different day refreshes the right panel without changing the loaded week
- Server model provides 7 pre-built day objects; falls back to client grouping if unavailable

Sub-components in `week-view-components.js` (137 lines) and `week-view-day-grid.js` (97 lines).

#### `scheduler-month-view.js` (849 lines)
Traditional month grid, 42 cells (6×7).

- 2 visible appointment chips per cell + expand button
- Provider dots + availability bar at bottom of each cell (5-provider limit)
- Separate "daily provider appointments" section renders outside the calendar into `#daily-provider-appointments`
- Day cell click navigates to day view via core
- Availability heuristic: `(working providers × 8h × 2 slots/h) - booked` — estimate only
- Blocked periods render as a red badge, sourced from `config.blockedPeriods`

Sub-components in `month-view-components.js` (49 lines) and `month-daily-overview.js` (171 lines).

#### `scheduler-today-view.js` (245 lines)
Operational daily summary. Not a time grid — a compact list view.

- Hero stats: Total / Upcoming / Completed / Free Slots
- Upcoming appointments (full opacity) and completed appointments (65% opacity) in separate sections
- Free slot estimation: `8h × 2 slots/h × visibleProviders - booked` — display only
- Appointment card click opens `appointment-details-modal`
- No server-side model dependency; reads directly from core's appointment list

---

### Panel and Modal

#### `right-panel.js` (864 lines)
Provider availability cards with real-time slot grid. Displayed as a sidebar or overlay depending on view.

- Shows each visible provider's status (working/on-break/off), next available slot, and today's slot grid
- Slot grid allows direct booking from the scheduler without opening the full appointment form
- Communicates with core to trigger appointment create flows

#### `appointment-details-modal.js` (763 lines)
Quick-view modal for appointment details and status changes. Entry point: `/appointments?open={hash}`.

- Renders: customer name, service, time, duration, location, notes, status
- Status action buttons: Pending → Confirmed → Completed, Cancel
- Full editing redirects to `/appointments/edit/{hash}` (separate page)
- Mutations go through `appointmentMutationCoordinator`, which owns toast and loading state
- Integrates with `PATCH /api/appointments/:id/status`

#### `scheduler-drag-drop.js` (318 lines)
Drag-and-drop rescheduling.

- Attaches to `.appointment-block`, `.inline-appointment`, `.appointment-card` elements
- Visual feedback: ring + background on drag-over
- Drop resolution: maps pixel position to target date/time slot
- On drop: `PATCH /api/appointments/:id/reschedule`
- On error: reverts visual state

---

### Configuration and Settings

#### `settings-manager.js` (566 lines)
Centralized configuration loader. Bootstraps `window.appTimezone` and all locale/calendar settings from the backend.

**Loads from:**
- `/api/v1/settings/localization` → timezone, time format, date format, first day of week
- `/api/v1/settings/booking` → slot duration, booking policies
- `/api/v1/settings/business-hours` → global business hours

**Key methods:**
- `init()` — loads all settings; sets `window.appTimezone`
- `getTimezone()` / `getTimeFormat()` / `getFirstDayOfWeek()` — accessors
- `isWorkingDay(dateTime)` — checks against provider schedule
- `isCacheValid()` / `refresh()` — 5-minute TTL cache

Falls back to browser timezone if API fails.

#### `calendar-model-url.js` (16 lines)
Single exported function:

```js
buildCalendarModelUrl(baseUrl, view, currentDate, activeFilters)
```

Constructs `/api/calendar/{day|week|month}?date=...&provider_id=...&service_id=...&location_id=...&status=...`

Extracted from `scheduler-core.js` during decomposition (§17.3 item 15).

---

### Color and Visual Identity

#### `appointment-colors.js` (303 lines)
Single source of truth for provider colors and status colors.

- `getProviderColor(providerId)` — reads `xs_users.color`; falls back to a rotating palette of 8 hex values
- `getProviderInitials(provider)` — delegates to `getAvatarInitials()` from `utils/avatar.js`
- `STATUS_COLORS` map — `{pending, confirmed, completed, cancelled, no_show}` with light/dark mode hex values and Tailwind class names
- Used by all four views and the stats system

#### `appointment-chip.js` (14 lines)
Renders a single compact appointment chip for month view cells. Accepts appointment data, returns HTML string.

---

### Stats System (`stats/`)

Architecture: **Car Analogy** — Engine (computation) + Sensors (definitions) + Dashboard (UI) + Body Specs (view config). Zero stats logic in view files.

#### `stats/stats-engine.js` (279 lines) — Engine
Pure computation; no DOM, no config reading.

- `getStatsForView(appointments, viewType, currentDate)` — main entry point
- `getStatsForRange(appointments, startDate, endDate)` — generic range calculator
- `calculateDateRange(viewType, date)` — resolves date boundaries per view type
- `compareStats(prev, next)` — delta calculation for trend arrows

Computes: `byStatus`, `byProvider`, `byHour`, `upcoming/past/inProgress`, `completionRate`, `cancellationRate`.

#### `stats/stats-definitions.js` (275 lines) — Sensors
Single source of truth for status definitions.

- `STATUS_DEFINITIONS` — `{pending, confirmed, completed, cancelled, no_show}` each with label, shortLabel, icon, priority, isActive, light/dark colors, hex
- `STAT_TYPES` — definitions for total, upcoming, inProgress, activeCount, completionRate, cancellationRate
- `getStatusDef(key)`, `getActiveStatuses()`, `getStatusesByPriority()`, `getStatusHex/Classes()`

#### `stats/stats-bar.js` (277 lines) — Dashboard
Reusable UI component. Renders; does not compute.

- `StatsBar` class: `update(stats, viewType, date)` then `render()`
- Renders: header, primary stats, status breakdown pills, secondary stats
- Status pills dispatch `statsbar:filter` CustomEvent — core listens and applies filter

#### `stats/stats-view-configs.js` (185 lines) — Body Specs
View-specific display preferences. No logic — pure config objects.

- `DAY_VIEW_CONFIG` — operational intent; emphasizes upcoming/inProgress
- `WEEK_VIEW_CONFIG` — planning intent; emphasizes total/activeCount + trends
- `MONTH_VIEW_CONFIG` — summary intent; shows all statuses + rates
- Each config declares: `primaryStats`, `statusBreakdown`, `secondaryStats`, `display`
- `getViewConfig(viewType)` — lookup; `getViewTitle(viewType, date)` — formatted title

#### `stats/index.js` — Entry point
Re-exports the stats system components for consumers.

---

### Utilities and Shared Helpers

| File | Lines | Purpose |
|---|---|---|
| `time-grid-utils.js` | 70 | `getBusinessHours(config)` — extracts startHour/endHour from config or calendarModel; `weekStart(date, firstDayOfWeek)` — ISO date week math; `isAppointmentVisible(appt)` — boolean guard |
| `calendar-grid-shared.js` | 58 | `buildMonthGridDays/Weeks()`, `getRotatedWeekdayInitials/ShortNames()`, `buildAppointmentCountsByDate()` — shared between Day mini-calendar, Week date picker, and Month grid |
| `availability-panel-shared.js` | 63 | `buildAvailabilityContext()`, `renderAvailabilityDebugPayload()`, `renderAvailabilitySlotList()` — used by all three views; prevents duplication of panel logic |
| `availability-slots.js` | 87 | Slot panel rendering: slot rows, slot status indicators, click-to-book wiring |
| `month-daily-overview.js` | 171 | Daily section renderer for `#daily-provider-appointments` DOM target |
| `date-nav-label.js` | 49 | Formats navigation label strings per view type using localization settings |
| `logger.js` | 32 | Debug logging utility; respects `window.schedulerDebug` flag |
| `scheduler-debug.js` | 14 | Factory that creates a scoped logger instance for a given module name |
| `constants.js` | 1 | Shared constant(s) |
| `scheduler-ui.js` | 147 | Legacy UI wrapper; preserved for backward compatibility with older view mount patterns |

---

## Architectural Patterns

### Orchestration
`SchedulerCore` is the single hub. Views do not communicate with each other — they receive data from core and call back to core for mutations, navigation, and filter changes.

### Dual-Mode Data Loading
See `Agent_Context_v2.md §8.9.3` for the authoritative description. Summary:
- **Server mode** (default): pre-computed `calendarModel` from `/api/calendar/{view}` includes overlap positions, working hours, slot injection
- **Non-server mode**: flat `appointments` from `/api/appointments`; client resolves layout

Mutations must refresh via `loadData()` in server mode (not `loadAppointments()`).

### Appointment Normalization
Core normalizes appointment field names before passing to views. Supports: `start`/`end`, `start_at`/`end_at`, `start_datetime`/`end_datetime`, `startDateTime`/`endDateTime`. Views always receive normalized objects.

### Stats Decoupling
Views have zero stats computation. Core calls `statsEngine.getStatsForView()`, then passes the result to `statsBar.update()`. Stats bar dispatches filter events that core intercepts — views remain unaware of stats.

### Rendering
All render calls are debounced (100ms) through core to prevent thrashing during rapid filter changes or resize events.

### Inline Style Injection
Day and week views use `data-style` JSON attributes on elements, then a post-render script applies them as `element.style.*`. This avoids inline `style=` attributes which trigger CSP violations.

---

## Governance Rules

- No inline CSS in scheduler view templates.
- Tailwind utility classes for all layout/styling; `data-style` JSON for dynamic values (CSP compliance).
- No hardcoded timezone in local state — UTC is explicit in all API requests; display conversion uses `window.appTimezone` via Luxon.
- New shared behavior must be extracted to a shared helper module rather than copied across views.
- Stats computation belongs in `stats-engine.js` only; views must not duplicate counting logic.
- `loadData()` is the canonical mutation-safe refresh entry; do not replace with `loadAppointments()` in server-mode mutation paths.
