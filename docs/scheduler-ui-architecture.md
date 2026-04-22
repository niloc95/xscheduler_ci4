# Scheduler UI Architecture

## Scope
This document defines the scheduler UI architecture for Day, Week, and Month views.

## View Responsibilities
- `DayView`: detailed operational view with provider-centric slot visibility.
- `WeekView`: planning grid with density-balanced appointment rendering.
- `MonthView`: high-level overview with day-level density and availability indicators.

## Shared Modules
- `scheduler-core.js`: state orchestration and view switching.
- `scheduler-ui.js`: common shell and view mounting behavior.
- `scheduler-drag-drop.js`: drag/drop interactions.
- `availability-panel-shared.js`: shared availability context + slot/debug rendering helpers.
- `calendar-grid-shared.js`: shared month-grid/day-header/day-count helpers for Day/Week/Month calendar renderers.
- `day-view-components.js`: Day view render components for shell header/cards/provider panel composition.
- `week-view-components.js`: Week view render components for split panels, slot header, and summary cards.
- `month-view-components.js`: Month view render components for shell composition and appointment chip/card blocks.

## Component Boundaries
- Header/date navigation concerns stay in each view shell.
- Appointment visual rendering is extracted to view-local component modules where practical (`day-view-components.js` for Day).
- Availability panel logic is centralized in shared helpers when behavior is identical.

## Phase 1 (Day Foundation)
- Extracted Day view shell sections into reusable render helpers:
  - `renderSchedulerHeader`
  - `renderAppointmentCard`
  - `renderProviderPanel`
  - `renderTimeColumn`
  - `renderAvailabilityBlock`
- Updated Day view layout to use surface tokens (`surface.0` / `surface.1`) for Material-3-aligned layering.
- Preserved existing interaction and API-driven availability behavior.

## Phase 2 (Week Foundation)
- Extracted Week split-view shell sections into reusable render helpers:
  - `renderWeekHeader`
  - `renderWeekLeftPanel`
  - `renderWeekSlotEngineHeader`
  - `renderWeekRightPanel`
  - `renderAppointmentSummaryCard`
  - `renderAppointmentSummaryEmptyState`
- Updated Week shell/panel layering to use `surface` tokens while preserving all event-hook IDs/classes.
- Preserved existing API-driven slot behavior and listener wiring.

## Phase 3 (Month Foundation)
- Extracted Month shell and appointment rendering helpers:
  - `renderMonthShell`
  - `renderMonthEmptyState`
  - `renderMonthAppointmentBlock`
  - `renderMonthModelAppointmentChip`
- Rewired Month render path to compose shell and grid output via helper module.
- Preserved day-cell interaction hooks and API-driven availability slot behavior.

## Phase 4 (Calendar Grid Dedup)
- Extracted duplicated calendar-grid helpers into `calendar-grid-shared.js`:
  - `buildMonthGridDays`
  - `buildMonthGridWeeks`
  - `getRotatedWeekdayInitials`
  - `getRotatedWeekdayShortNames`
  - `buildAppointmentCountsByDate`
- Rewired Day/Week mini calendars and Week slot date picker to use shared day-grid/day-header/count helpers.
- Rewired Month fallback grid generation and day-header short-name generation to shared helpers.

## Refactor Decisions
- Removed duplicated availability panel rendering logic from Day/Week/Month by extracting:
  - context resolution (`buildAvailabilityContext`)
  - debug payload rendering (`renderAvailabilityDebugPayload`)
  - slot list rendering (`renderAvailabilitySlotList`)
- Preserved behavior (API-driven slot source; no client slot computation).

## Governance Rules
- No inline CSS in scheduler view templates.
- Tailwind utility classes for all layout/styling.
- No hardcoded timezone in local state; UTC is explicit in slot API requests.
- New shared behavior must be extracted instead of copied across views.
