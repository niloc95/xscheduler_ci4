# Calendar Prototype – Phase 3 State & Store Plan

_Last updated: 2025-11-21_

## 1. Objectives

- Introduce a dedicated calendar state layer that can drive the new Month/Week/Day views without touching the legacy appointment UI.
- Keep data modeling, hydration, and derived selectors encapsulated so the UI swap can happen incrementally.
- Define explicit integration points with existing controllers/routes to ensure zero regressions while the new experience is feature-flagged.

## 2. Canonical Data Model

The state tree lives under a single namespace (e.g. `calendarState`). Suggested TypeScript-style signatures shown for clarity.

```ts
interface CalendarState {
  meta: {
    activeView: 'month' | 'week' | 'day';
    rangeStart: string; // ISO date string (inclusive)
    rangeEnd: string;   // ISO date string (exclusive)
    nowTimestamp: number;
    timezone: string;   // Olson ID, e.g. "America/New_York"
    hydrationSource: 'bootstrap' | 'lazy-load';
    lastUpdatedAt: number;
    featureFlags: Record<string, boolean>;
  };
  providers: Record<ProviderId, Provider>;
  appointments: Record<AppointmentId, Appointment>;
  providerOrder: ProviderId[];          // matches provider rail ordering
  filters: {
    providerIds: Set<ProviderId>;
    visitTypes: Set<string>;
    locations: Set<string>;
    showCanceled: boolean;
  };
  derived: {
    monthMatrix: MonthMatrixCell[][];   // cached after selector run
    weekColumns: WeekColumn[];
    dayTimeline: DayColumn[];
  };
}

interface Provider {
  id: string;
  displayName: string;
  colorToken: string;      // e.g. "blue-500"
  location: string;
  specialty?: string;
  avatarUrl?: string;
  orderingWeight: number;
  isActive: boolean;
}

interface Appointment {
  id: string;
  providerId: ProviderId;
  patientName: string;
  visitType: string;
  status: 'confirmed' | 'pending' | 'canceled' | 'draft';
  start: string;          // ISO datetime
  end: string;            // ISO datetime
  durationMinutes: number;
  room?: string;
  colorOverride?: string; // e.g. hex for special blocks
  metadata: Record<string, unknown>;
}

interface MonthMatrixCell {
  date: string;
  isCurrentMonth: boolean;
  isToday: boolean;
  isWeekend: boolean;
  appointmentChips: Array<{ providerId: string; label: string; timeLabel: string }>;
  overflowCount: number;
}

interface WeekColumn {
  date: string;
  appointments: AppointmentBlock[];
}

interface DayColumn {
  providerId: string;
  providerName: string;
  colorToken: string;
  blocks: AppointmentBlock[];
}

interface AppointmentBlock {
  appointmentId: string;
  startOffsetMinutes: number; // from day start (e.g. 0 = 00:00)
  heightMinutes: number;
  densityLane: number;        // index for overlapping layout
  summary: string;
  subtext: string;
  chipColor: string;
}
```

### Modeling Considerations

- **Range boundaries**: month view always expands to a 6-row matrix (42 days). Week/day views align to the same `rangeStart`/`rangeEnd` to simplify caching and hydration.
- **Provider filters**: instead of mutating the appointment list, we keep the master list intact and expose derived selectors that honor the `filters.providerIds` set. This makes toggling providers instantaneous and avoids re-fetch.
- **Derived caches**: `monthMatrix`, `weekColumns`, and `dayTimeline` are memoized outputs. We store them under `state.derived` to make hydration debug easier. A hash of inputs (range + providers + filters) can guard recomputation.

## 3. Store & Hydration Flow

### Module Layout

```
resources/js/modules/calendar/state/
  ├── index.js             // store creation + exports
  ├── selectors.js         // memoized derived data (month, week, day)
  ├── actions.js           // pure reducers for navigation + filters
  ├── adapters.js          // map API payloads → canonical model
  └── fixtures/            // mock payloads for local dev
```

### Store Implementation

- **Store primitive**: start with a lightweight observable (e.g. `nanoevents`, `zustand`-style vanilla) rather than bringing Redux. We only need `getState`, `setState`, and `subscribe` for the prototype.
- **Actions**:
  - `hydrate(payload, meta)` – run payload through adapters, merge into state, update `meta.hydrationSource`.
  - `setActiveView(view)` – updates `meta.activeView` and triggers selectors.
  - `navigate(offset)` – accepts `{ months?: number, weeks?: number, days?: number }`, shifts `rangeStart`/`rangeEnd`, triggers background fetch if data missing.
  - `toggleProvider(providerId)` – updates `filters.providerIds` set.
  - `setFilter(key, value)` – generic for visit types, locations, status toggles.
- **Selectors** (memoized via simple cache key of `[activeView, rangeStart, filtersHash]`):
  - `selectMonthMatrix(state)` – returns 6×7 grid with chips/overflow counts.
  - `selectWeekColumns(state)` – array of 7 columns, each with prepared appointment blocks.
  - `selectDayTimeline(state)` – provider columns ordered by `providerOrder` with slot metrics.
  - `selectNowMarker(state)` – computes pixel offset/time label for the “Now” indicator.

### Hydration Flow

1. **Bootstrap**
   - Controller injects JSON payload (existing appointments API) into the Blade view under `window.__calendarBootstrap`. Payload includes providers, appointments covering at least the initial visible range, timezone, and user preferences.
   - Store initializes using `hydrate(bootstrapPayload, { source: 'bootstrap' })` before the React/Vue/vanilla view mounts.

2. **Lazy Range Fetch**
   - When navigation moves outside hydrated range, dispatch `requestRange(rangeStart, rangeEnd)` which hits a new API route (e.g. `GET /api/calendar/range?start=...&end=...`).
   - Responses pass through `adapters.js` → `hydrate` with `source: 'lazy-load'`. New appointments merge into `state.appointments` keyed by ID.

3. **Debounced Filter Persistence**
   - Filter changes update state immediately; a `saveFilters` job persists to localStorage or the user profile endpoint asynchronously.

4. **Error Handling**
   - Store exposes `meta.error` and `meta.isFetching` so UI can show skeletons/spinners without blocking other interactions.

### View Consumption

- Shell view subscribes to `meta.activeView`, `meta.rangeStart`, `meta.rangeEnd`, and selectors so the Month/Week/Day partials can be swapped without re-rendering the provider rail.
- Each prototype file will eventually import the selectors and render using the derived structures instead of hard-coded chips.

## 4. Integration Touchpoints

| Area | Approach | Notes |
| --- | --- | --- |
| **Backend routes** | Add `CalendarPrototypeController::range` returning JSON payloads. Keep behind `config('calendar.prototype_enabled')` flag. | Do not modify `Appointments` controller responses yet. |
| **Blade layout** | Inject store script + bootstrap payload only when prototype flag is true. Continue rendering legacy calendar otherwise. | Allows QA to toggle new UI per environment. |
| **Assets** | Bundle store + future components via Vite entry `resources/js/calendar-prototype.js`. The Tailwind sandbox CSS remains separate until we formalize components. | Prevents global CSS bleed. |
| **Telemetry** | Log store hydration + navigation events under a new channel (`calendar_prototype`). | Helps audit performance before broader rollout. |
| **Fallback** | Maintain ability to destroy the store and re-render legacy UI instantly. A simple feature flag + guard in Blade should suffice. | Critical for safe rollout. |

## 5. Next Steps

1. Scaffold `resources/js/modules/calendar/state` with the files outlined above.
2. Build adapters + selectors with unit tests (can live under `tests/unit/calendar/`).
3. Replace static HTML data inside the sandbox views with store-driven renders (Phase 3 UI wiring).
4. Once stable, begin Phase 4 API integration + migration planning.

---
Questions or clarifications? Drop them in `docs/development/CALENDAR_PHASE3_STATE_PLAN.md` via PR comments so we keep the plan versioned.
