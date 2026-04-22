# Scheduler Module

Custom appointment scheduler built with Luxon and modern JavaScript.

## Modules

- **scheduler-core.js** — Main orchestrator; initializes, loads data, and manages views.
- **scheduler-month-view.js** — Month grid with appointment blocks.
- **scheduler-week-view.js** — Week grid with hourly time slots.
- **scheduler-day-view.js** — Single-day view with detailed time slots.
- **scheduler-drag-drop.js** — Drag-and-drop support for rescheduling.
- **appointment-details-modal.js** — Quick view/status modal for appointments.
- **appointment-colors.js** — Status and provider color helpers.
- **settings-manager.js** — Centralized settings (localization, booking rules, business hours).
- **time-slots.js** — Shared utility for generating hourly time slots and formatting.
- **logger.js** — Lightweight debug logger with gating.

## Logger Usage

By default, `console.debug` and `console.info` calls are **gated** by the debug flag. To enable verbose logging:

**In browser console:**
```javascript
window.__SCHEDULER_DEBUG__ = true;
```

**Via localStorage (persistent):**
```javascript
localStorage.setItem('scheduler:debug', '1');
```

Then reload the page. All `logger.debug()` and `logger.info()` calls will appear. Errors and warnings always display.

To disable:
```javascript
window.__SCHEDULER_DEBUG__ = false;
// or
localStorage.removeItem('scheduler:debug');
```

## Shared Utilities

### time-slots.js

Provides `generateTimeSlots(businessHours, timeFormat)` to generate hourly slots between start/end times, formatted according to '12h' or '24h'.

Used by day and week views to avoid duplicate implementations.

## Structure

- **init()** → loads settings, providers, appointments
- **render()** → orchestrates view modules (month/week/day)
- **loadAppointments(start, end)** → fetches appointment data
- **getFilteredAppointments()** → applies provider visibility filters
- **handleAppointmentClick(appointment)** → opens details modal

## API Endpoints

- `GET /api/appointments?start=YYYY-MM-DD&end=YYYY-MM-DD` — appointment list
- `GET /api/providers?includeColors=true` — providers with colors
- `GET /api/v1/settings/calendarConfig` — calendar config
- `GET /api/v1/settings/localization` — localization settings
- `GET /api/v1/settings/booking` — booking rules
- `GET /api/v1/settings/business-hours` — business hours

## Notes

- All dates use **Luxon's DateTime** with timezone awareness.
- Appointment times from API are UTC ISO (e.g., `2025-11-13T11:30:00Z`).
- Frontend converts to configured timezone (e.g., `Africa/Johannesburg`).
- Logger default is **off** (minimal noise); enable for debugging.
