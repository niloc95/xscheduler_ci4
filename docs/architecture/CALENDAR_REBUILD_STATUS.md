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
- Centralized booking pipeline for admin/public/API create through `AppointmentBookingService`.
- Normalized provider schedule weekday handling for string/int inputs.

## What Is Still Pending

1. Harden appointment schema per audit (customer_id not null, confirm indexes aligned to `start_at`).

## Rebuild Flag

- Toggle: `calendar.rebuild_enabled` in `xs_settings`.
- When `false`, `/api/calendar/*` returns 503 to prevent inconsistent UI during rebuild.

## Notes

- Month/Day/Week view models are server generated under `/api/calendar/*`.
- Availability UI and drag-drop now rely on API slot checks.
