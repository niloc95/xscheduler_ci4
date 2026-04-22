# Availability Rendering Logic

## Source of Truth
Availability is API-driven. Client-side code does not generate candidate slots.

## Request Rules
- Required: `provider_id`, `service_id`, `date`
- Optional: `location_id`
- Timezone sent as explicit `UTC` for scheduler slot panels.

## Server Behavior
- Availability endpoint validates provider/service/date.
- Location context behavior:
  - If active locations exist and no `location_id` is passed, fallback selects primary active location, otherwise first active location.

## Client Rendering Pipeline
1. Build context from active filters + visible provider IDs.
2. Guard early if provider/service/date missing.
3. Fetch `/api/availability/slots`.
4. Render slot rows with booking links.
5. Render debug payload (only when debug mode enabled).

## Shared Helper Module
`availability-panel-shared.js`
- `buildAvailabilityContext`
- `isAvailabilityDebugMode`
- `renderAvailabilityDebugPayload`
- `renderAvailabilitySlotList`

## Validation Constraints
- No inline styles.
- No duplicated slot rendering logic between Day/Week/Month.
- Use dynamic color attributes + existing utilities for tone/state display.
