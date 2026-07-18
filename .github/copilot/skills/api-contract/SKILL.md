---
name: webscheduler-api-contract
description: WebScheduler API contracts — response envelopes, versioning, base controller responsibilities, public endpoint guardrails, and the SPA form JSON response contract. Use whenever you're adding, modifying, or reviewing API endpoints, controllers that return JSON, response shapes, error handling, or the JSON returned to SPA-intercepted form submits. Triggers on phrases like "API", "endpoint", "JSON response", "envelope", "BaseApiController", "/api/v1", "SPA form", "redirect after save", "AJAX", or any work in `app/Controllers/Api/`.
---

# WebScheduler — API Contract

## Versioning and Surface

- **Versioned endpoints** under `/api/v1/`
- **Operational endpoints** under `/api/`

## Base API Controller Responsibilities

`app/Controllers/Api/BaseApiController.php` enforces:

- `requireAuth()`
- `hasRole()`
- `requireRole()`
- success / error envelope helpers

All API controllers should extend it and use its envelope helpers — do not hand-roll JSON responses.

## Response Envelopes

### Success

```json
{ "data": {}, "meta": {} }
```

- `data` carries the resource payload
- `meta` carries pagination, view metadata, generated_at timestamps, etc.

### Error

```json
{ "error": { "message": "", "code": "", "details": {} } }
```

- `message` — human-readable
- `code` — machine-readable stable identifier
- `details` — optional structured info (field errors, etc.)

## Public Endpoint Guardrails

Public endpoints (under `/api/v1/public/`) must keep:

- Hash/token protections
- Rate limiting
- Provider/service scoping
- **No numeric customer IDs in public URLs** — use `xs_customers.hash` or `xs_appointments.public_token` only

See the `public-booking` skill for the full set of public route security rules.

## SPA Form JSON Contract

For SPA-intercepted form posts:

```json
{
  "success": true,
  "message": "Saved",
  "redirect": "https://app/path",
  "errors": {}
}
```

**Critical rule:** If the success destination equals the current page, **include `redirect` so SPA navigation can force-reload**. Without `redirect`, `spa.js` cannot reliably refresh the current view after a mutation.

**Optional error key `helpLink`** (added 2026-07-18): error responses may include `"helpLink": { "url": "...", "label": "...", "field": "email" }`. `spa.js` renders it as an internal link below the named field (or at the top of the form when `field` is omitted/not found) and clears it on the next submit. Used by the user-create duplicate-email 409/422 to offer "Edit the existing user instead".

This is the most commonly missed contract — verify it on every controller action that returns to the same page after save. Note: `formSuccess()` (see below) enforces the `redirect` key automatically for any controller using `FormResponseTrait`.

## Form Surface — FormResponseTrait

Form controllers (non-API, SPA-intercepted form POSTs) use `App\Traits\FormResponseTrait` instead of hand-rolling `setJSON()` calls:

```php
use App\Traits\FormResponseTrait;

// Success → HTTP 200, { success: true, message, redirect, ...extra }
$this->formSuccess(string $redirect, string $message, array $extra = [])

// Error → HTTP $status (default 422), { success: false, message, errors }
$this->formError(string $message, array $errors = [], int $status = 422)
```

Applied to: `Services`, `ServiceCategories`, `CustomerManagement`.

This is the form-surface equivalent of `BaseApiController` envelope helpers. Do **not** call `$this->response->setStatusCode(...)->setJSON(...)` in any controller that already uses this trait.

## Server-Side View Model APIs (Calendar)

The calendar surface uses pre-computed server-side render models via `CalendarController`:

| Endpoint | Service | View |
| --- | --- | --- |
| `GET /api/calendar/day?date=YYYY-MM-DD` | `DayViewService` | Day view |
| `GET /api/calendar/week?date=YYYY-MM-DD` | `WeekViewService` | Week view |
| `GET /api/calendar/month?year=&month=` | `MonthViewService` | Month view |

Common query params: `provider_id`, `service_id`, `location_id`, `status`.

Response envelope: `{ "data": { ...viewModel... }, "meta": { "view", "date", "generated_at" } }`

See `scheduling` skill for the full calendar data flow.

## Calendar Role Scoping (Automatic)

- **Providers:** `CalendarController` scopes to their own appointments only regardless of query params.
- **Admins/Staff:** see all appointments; can filter by `provider_id` query param.
- Do not rely on `provider_id` query param as the sole authorization mechanism — role-based scoping is enforced server-side.

## Integrations Hub API

All under `/api/v1/integrations/`, filter: `api_auth`. Controller: `App\Controllers\Api\V1\Integrations`.

| Method | Path | Action |
| --- | --- | --- |
| `GET` | `/api/v1/integrations` | Returns public integration status for all channels |
| `POST` | `/api/v1/integrations/save` | Save credentials for one channel (body: `{ channel, ...fields }`) |
| `POST` | `/api/v1/integrations/test` | Test connection for one channel (body: `{ channel }`) |
| `POST` | `/api/v1/integrations/disconnect` | Remove integration row for one channel (body: `{ channel }`) |

Valid channels: `webhook`, `google_calendar`, `stripe`, `zoom`, `jitsi`, `payfast`.

All responses use the standard `{ data, meta }` envelope. Errors use `{ error: { message } }`. The controller delegates entirely to `IntegrationSettingsService` — no business logic in the controller.

**Google Calendar OAuth routes** (admin only, not API-versioned):
- `GET /oauth/google/authorize` — redirect to Google OAuth consent
- `GET /oauth/google/callback` — exchange code, store tokens, redirect to `/settings#integrations`

## Public APIs Inventory

- `GET /api/v1/public/services`
- `GET /api/v1/public/providers`
- `GET /api/v1/public/availability`
- `GET /api/v1/providers/slug/{segment}/services` — fetch services by provider slug (used by public booking flow)
- `POST` booking submit route

See the `public-booking` skill for full public route contract.

## Shared Frontend Fetch Contract

`resources/js/core/api.js` `apiRequest()` returns `{ response, payload }`.

- For `application/json` responses, `payload` is **already parsed JSON**. Consumers must not assume string methods such as `.match()` are available unless they first confirm `typeof payload === 'string'`.
- Text or HTML responses may still return string payloads.
- Shared frontend helpers such as `extractJSON()` must accept already-parsed objects so search surfaces remain compatible with the shared fetch layer.

See the `frontend` skill for full SPA/fetch lifecycle.
