# Calendar Rebuild Status

**Date:** 2026-02-27
**Branch:** calendar-refactor

## What Is Done

- Added server-side calendar rebuild feature flag (`calendar.rebuild_enabled`).
- Introduced `TimeGridService` to centralize time-grid generation.
- Refactored `DayViewService` to use `TimeGridService` and accept preformatted appointments.
- Refactored `WeekViewService` to loop `DayViewService` (no duplicate grid logic).
- Standardized blocked times to UTC (`start_at`/`end_at`) and aligned conflict/availability queries.
- Removed client-side scheduling engines and switched availability panels to API-driven slots.

## What Is Still Pending

1. Centralize booking pipeline so Admin + Public booking go through a single service path.
2. Normalize provider schedule weekday representation (enum strings vs integer weekdays).
3. Harden appointment schema per audit (customer_id not null, confirm indexes aligned to `start_at`).

## Rebuild Flag

- Toggle: `calendar.rebuild_enabled` in `xs_settings`.
- When `false`, `/api/calendar/*` returns 503 to prevent inconsistent UI during rebuild.

## Notes

- Month/Day/Week view models are server generated under `/api/calendar/*`.
- JS scheduler still contains client-side availability logic; removal is required to reach 100% alignment with IMP.md.
