# Scheduler Component Structure

## Current Structure
- `resources/js/modules/scheduler/calendar-grid-shared.js`
- `resources/js/modules/scheduler/scheduler-day-view.js`
- `resources/js/modules/scheduler/day-view-components.js`
- `resources/js/modules/scheduler/scheduler-week-view.js`
- `resources/js/modules/scheduler/week-view-components.js`
- `resources/js/modules/scheduler/scheduler-month-view.js`
- `resources/js/modules/scheduler/month-view-components.js`
- `resources/js/modules/scheduler/availability-panel-shared.js`

## Logical Components
- SchedulerHeader (`renderSchedulerHeader` in `day-view-components.js`)
- AppointmentCard (`renderAppointmentCard` in `day-view-components.js` using shared color semantics)
- ProviderPanel (`renderProviderPanel` in `day-view-components.js`)
- TimeColumn (`renderTimeColumn` in `day-view-components.js`)
- AvailabilityBlock (`renderAvailabilityBlock` in `day-view-components.js`, slot content from shared availability renderer)
- WeekHeader (`renderWeekHeader` in `week-view-components.js`)
- WeekLeftPanel (`renderWeekLeftPanel` in `week-view-components.js`)
- WeekRightPanel (`renderWeekRightPanel` in `week-view-components.js`)
- WeekSlotEngineHeader (`renderWeekSlotEngineHeader` in `week-view-components.js`)
- AppointmentSummaryCard (`renderAppointmentSummaryCard` in `week-view-components.js`)
- MonthShell (`renderMonthShell` in `month-view-components.js`)
- MonthAppointmentBlock (`renderMonthAppointmentBlock` in `month-view-components.js`)
- MonthModelAppointmentChip (`renderMonthModelAppointmentChip` in `month-view-components.js`)

## Shared Utilities
- `calendar-grid-shared.js` now centralizes:
  - month-grid day generation (42-cell calendars)
  - 6x7 week matrix generation for month fallback rendering
  - rotated weekday labels (initials/short names)
  - appointment density counting by date

- `availability-panel-shared.js` now centralizes:
  - context gating (`provider + service` readiness)
  - debug payload rendering (dev-only)
  - slot list rendering and booking link generation

## Future Extraction Targets
- date navigation controls (day/week/month wrappers)
- week/month appointment card HTML fragments with variant props
- sticky header/grid wrappers for Week and Month
