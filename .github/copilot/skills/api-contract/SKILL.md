---
name: webscheduler-api-contract
description: WebScheduler API contracts — response envelopes, versioning, base controller responsibilities, public endpoint guardrails, and the SPA form JSON response contract. Use whenever you're adding, modifying, or reviewing API endpoints, controllers that return JSON, response shapes, error handling, or the JSON returned to SPA-intercepted form submits. Triggers on phrases like "API", "endpoint", "JSON response", "envelope", "BaseApiController", "/api/v1", "SPA form", "redirect after save", "AJAX", or any work in `app/Controllers/Api/`.
---

# WebScheduler — API Contract

## Versioning and Surface

- **`/api/v1/…` is the canonical, documented path.** Everything externally callable must be reachable there.
- **`/api/…` (unversioned) is an undocumented alias.** The SPA still calls these paths; keep them working, never advertise them.
- Both registrations come from the single `$xsApiAuthenticatedRoutes` tuple array in `app/Config/Routes.php`. **Add new authenticated endpoints to that array** — do not hand-register a route in one group only, or the two surfaces drift.
- A cross-origin `OPTIONS` matches the `api/(:any)` preflight catch-all route, because CI4 registers routes per-verb and would otherwise 404 during routing before any filter runs. Do not remove it.

## Authentication (Owner Section)

Two mechanisms, one identity. See `auth-rbac` for the session/role model itself.

| Caller | Mechanism | Filter |
| --- | --- | --- |
| SPA / same-origin UI | session cookie (`isLoggedIn`) | `api_auth` |
| External client | `Authorization: Bearer xsk_<prefix>_<secret>` | `api_auth` |

### Filter usage

```php
['filter' => 'api_auth']                 // any authenticated caller
['filter' => 'api_auth:admin,provider']  // plus a role requirement
```

Use `api_auth` — **not** `auth` or `role:` — on any route intended to be externally callable. `auth` and `role:` are session-only and will 401 a valid token.

### Precedence rule (do not regress)

**If an `Authorization` header is present it decides the request.** A bad token returns 401 even when the caller also holds a valid session cookie. The filter must never short-circuit on the session before reading the header — that let a browser session silently mask an invalid token.

### Token model

- Tokens live in `xs_api_keys`, one row per key, **bound to an `xs_users` row** (`user_id`).
- `ApiAuthFilter` loads the bound user and its authoritative `xs_user_roles` and populates `App\Services\ApiIdentity` with **the same user array shape `Auth::login()` writes to the session**. That is why `requireRole()`, provider scoping and `current_business_id()` need no token-specific branches.
- Only `key_prefix` (indexed lookup handle) and `password_hash()` of the secret are stored. Plaintext is shown once at creation.
- Revocation (`revoked_at`) and expiry (`expires_at`) take effect on the next request. A key bound to a non-`active` user is rejected.
- `scopes` is a JSON array; **NULL means "inherit the bound user's role permissions"** and satisfies every `hasScope()` check. There is no shared secret anywhere in the repo.
- Rate limit: 120 requests per key per minute → 429 with `Retry-After`.

Issue and manage keys with `php spark api:key create|list|revoke` (see `operations`).

### Reading the acting identity

In API controllers use `$this->currentUser()` / `requireAuth()` / `requireRole()` — already identity-aware.

Anywhere else (services, non-API controllers reachable from an API route) use the `permissions` helper, **never `session()->get('user_id')` directly**:

| Instead of | Use |
| --- | --- |
| `session()->get('user_id')` | `current_user_id()` |
| `session()->get('user')` | `current_identity_user()` |
| inline `admin > provider > staff` loop | `resolve_active_role($roles, $fallback)` |

A direct session read on a token-reachable route silently drops the actor: provider scoping degrades to unscoped, and audit rows get a null `user_id`.

### CORS

`allowedOrigins` is empty by default (same-origin only) and is populated from the `api.allowedOrigins` env var. Only an allow-listed origin is echoed back; an unknown origin gets **no** `Access-Control-Allow-Origin` header. **Wildcard origins are not supported** — `/api/*` is CSRF-exempt, so a permissive origin plus cookie auth would be a live CSRF hole.

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

## Resource CRUD Surface (external clients)

Full CRUD resources, each registered in `$xsApiAuthenticatedRoutes` (Routes.php)
so both `/api/v1/…` and the unversioned `/api/…` alias exist behind `api_auth`.
Controllers are thin and delegate to existing services — **no business logic in
the controller** (see `architecture`). Writes are role-gated via the filter
argument (`api_auth:admin,provider` etc.), not hand-rolled in the action.

| Resource | Controller | Delegates to | Write roles |
| --- | --- | --- | --- |
| Services | `Api\V1\Services` | `ServiceMutationService` | admin, provider |
| Categories | `Api\V1\Categories` | `ServiceMutationService::*Category` | admin, provider |
| Customers | `Api\V1\Customers` | `CustomerService`, `CustomerDeletionService` | admin, provider, staff |
| Providers (writes) | `Api\V1\Providers` | `UserManagementMutationService` | create/update: admin, provider · delete(deactivate): admin |
| Business hours | `Api\V1\BusinessHours` | `Settings\SettingsApiService` | admin |

Conventions:

- **camelCase ↔ snake_case bridging** in the controller payload builder
  (`durationMin`→`duration_min`, `categoryId`→`category_id`, `firstName`→`first_name`).
- Uniform service-result arrays (`{success, statusCode, message, errors}` from
  `UserManagementMutationService` / `CustomerDeletionService`) map to envelopes
  via a small `respondFrom…()` helper — do not re-implement per action.
- Business hours: the **global work window** (`business.work_start/END` settings)
  only. Per-provider `xs_business_hours` rows are written via the provider
  `schedule` payload, never here (Rule #2 — those reads stay provider-scoped).
- A private controller helper must not be named `validate()` — it collides with
  `CodeIgniter\Controller::validate()` (must be protected). Use `validatePayload()`.

## OpenAPI Spec + Drift Guard

`docs/technical/openapi.yml` (OpenAPI 3.1) is the source of truth for the
external surface. Security scheme is `http bearer` with `bearerFormat: opaque`
(the `xsk_<prefix>_<secret>` key is **not** a JWT). Reusable `components` mirror
the runtime: `SuccessEnvelope`/`ErrorEnvelope`, shared `page`/`length`/`sort`/`q`
params, `PaginationMeta`, per-resource schemas, and the error-code enum.

`php spark api:spec:validate` (`App\Commands\ApiSpecValidateCommand`) fails when
the spec drifts from the route table — **phantom** (spec path with no real
`/api/v1` route) or **missing** (a route under a documented prefix absent from
the spec). Documented prefixes: appointments, availability, services, providers,
customers, categories, business-hours, locations. It runs in CI (build job) and
must be updated whenever those routes change. The spec rotted silently once;
this guard exists so it can't again.

## Developer Portal (`/developers`)

`App\Controllers\DeveloperDocs` serves a public, self-hosted Redoc portal — the
contract only, never data:

- `GET /developers` — Redoc reference (renders `resources/js/developers.js`,
  which loads the vendored `resources/redoc/redoc.standalone.js` via Vite `?url`;
  no CDN — CSP forbids external script hosts). The action adds `blob:` to
  `child-src` (Redoc's search worker) and **strips the `style-src` nonce** for
  this response.
- `GET /developers/getting-started` — auth/envelope/errors/pagination/rate-limits guide.
- `GET /developers/openapi.yaml` — the spec, served same-origin (`connect-src 'self'`).

Routes are registered outside the setup/auth groups so the portal renders pre-login.

### Public static docs site (`webscheduler.co.za/developer`)

For external developers who don't run the app, the same spec is published as a
**standalone static bundle** — no PHP, no CI4, no CDN, zero external requests:

- Source templates: `docs-site/index.html`, `docs-site/getting-started.html`
  (static ports of the in-app views, all relative paths so they work under a
  `/developer/` subpath). `index.html` loads `./redoc.standalone.js` and inits
  Redoc against `./openapi.yaml` — no CSP nonce concern (static host).
- `npm run docs:build` (`scripts/build-docs-site.js`) assembles `dist/developer/`
  = the templates + vendored `resources/redoc/redoc.standalone.js` +
  `docs/technical/openapi.yml` → `openapi.yaml`. The script fails if the Redoc
  bundle still references `cdn.redoc.ly` (external-request guard).
- CI (`.github/workflows/developer-docs.yml`) rebuilds on changes to the spec /
  redoc bundle / `docs-site/` and uploads a `developer-docs` artifact to download
  and upload to the host's `/developer` path. A direct FTP/S3 deploy step is a
  documented stub (add host secrets to enable).
- Spec `servers` lists a placeholder absolute (`https://your-webscheduler-domain/api/v1`,
  since WebScheduler is self-hosted per instance) plus the relative `/api/v1`
  the in-app portal uses.

**CSP style-src reset (why `index()` clears + re-adds `style-src`):** Redoc
(styled-components) injects `<style>` elements and inline `style="…"` attributes;
a nonce can't cover style attributes, and a browser ignores `'unsafe-inline'`
once a nonce is in `style-src` → styled-components error #17 ("Something went
wrong"). In development, `CodeIgniter::initializeKint()` adds a style nonce on
every request (`CI_DEBUG` only), which broke the page locally. `index()` calls
`$csp->clearDirective('style-src')` then re-adds `config(ContentSecurityPolicy)->styleSrc`
(the pristine, nonce-free list), so the portal renders in dev **and** prod.
`script-src`/`connect-src`/`child-src` stay fully enforced — only the Phase-2
style nonce is dropped, back to the config's Phase-1 `'unsafe-inline'`, on this
public data-free page. Do not reintroduce a style nonce here.

## Shared Frontend Fetch Contract

`resources/js/core/api.js` `apiRequest()` returns `{ response, payload }`.

- For `application/json` responses, `payload` is **already parsed JSON**. Consumers must not assume string methods such as `.match()` are available unless they first confirm `typeof payload === 'string'`.
- Text or HTML responses may still return string payloads.
- Shared frontend helpers such as `extractJSON()` must accept already-parsed objects so search surfaces remain compatible with the shared fetch layer.

See the `frontend` skill for full SPA/fetch lifecycle.
