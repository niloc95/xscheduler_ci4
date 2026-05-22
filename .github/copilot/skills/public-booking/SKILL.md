---
name: webscheduler-public-booking
description: WebScheduler public booking — public routes, public APIs, security rules, policy/legal contract, and custom field required semantics. Use this skill for any work on the public-facing `/booking` flow, `/my-appointments/{hash}`, the `/r/{reference}` short link, public booking APIs (`/api/v1/public/...`), or the public booking JS module. Covers all public routes, the public API surface, security rules (no numeric customer IDs in URLs, hash/token only, CSRF on submit, provider+service-scoped slot queries), policy enforcement (`business.reschedule`, `business.cancel` server-side; `business.future_limit` is public-channel-only), the `legalPolicies` object structure and rendering rules, the `/booking/legal` page contract, the duplicate-policy-block prohibition, and the **Custom Field Required Semantics** contract (required custom fields are satisfied by stored values — strict on initial booking, relaxed on later edits/reschedule, with three surfaces: customer edit form, admin appointment edit form, public booking manage). Trigger on any mention of "public booking", "/booking", "/my-appointments", "/r/", "public route", "legal policy", "reschedule policy", "cancel policy", "future limit", "custom field", "required field", or when editing `PublicBookingService`, `BookingLinkService`, `BookingSettingsService`, `resources/js/public-booking.js`, or `app/Views/public-site/`.
---

# WebScheduler — Public Booking

## Public Routes

- `GET /booking`
- `GET /booking/{serviceSlug}`
- `GET /booking/{serviceSlug}/{providerSlug}`
- `GET /booking/legal`
- `GET /my-appointments/{hash}`
- `GET /r/{reference}` — managed appointment reference short-link; resolves to `/my-appointments/{hash}` or `/booking`; built by `BookingLinkService::manageReferenceUrl()`

## Public APIs

- `GET /api/v1/public/services`
- `GET /api/v1/public/providers`
- `GET /api/v1/public/availability`
- `GET /api/v1/providers/slug/{segment}/services` — fetch services by provider slug (used by public booking flow)
- `POST` booking submit route

## Public Security Rules

- **Never expose numeric customer IDs in URLs**
- Use hash/token references only
- Enforce CSRF on form submit
- Keep slot queries scoped by provider AND service

---

## Public Booking Policy and Legal Rules

### Server-Side Enforcement

- `business.reschedule` and `business.cancel` are enforced **server-side** in `PublicBookingService`. The SPA may only reflect eligibility via `can_reschedule` / `can_cancel` flags and policy summaries.
- `business.future_limit` is a **public-channel-only** booking guard. Public booking calendar queries and public booking submission MUST respect it. Admin/staff/API appointment creation does NOT inherit this limit by default.

### Public Booking Page Context

Page context must expose:
- `reschedulePolicy`
- `cancelPolicy`
- `futureLimitDays`
- `legalPolicies` object for UI rendering

### `legalPolicies` Object

Currently carries:
- `cancellationPolicy`
- `reschedulingPolicy`
- `termsUrl`
- `privacyUrl`
- `legalPageUrl`

### UI Rendering Rules

- The public booking UI MUST reuse the existing in-form policy surface (`renderSchedulingTips()` in `resources/js/public-booking.js`) instead of adding a detached sidebar/right panel **unless explicitly requested**.
- Short legal summaries belong in the booking flow; long-form legal text belongs on `/booking/legal`.
- The "Full legal policies" link from the booking flow opens `/booking/legal` in a new tab.
- The legal page itself MUST provide an in-page navigation affordance back to `/booking`.
- **Avoid duplicate policy copy blocks in the form.** If rules/legal are shown in `renderSchedulingTips()`, do NOT render a second standalone policy card with overlapping content.
- Any legal URL shown from booking context (`termsUrl`, `privacyUrl`) MUST open with `target="_blank" rel="noopener"`.

---

## Custom Field Required Semantics (Apr 2026)

### Rule: Required Fields Are Satisfied by Stored Values

**Canonical contract:** A custom field marked as `required` in booking settings is only required if the linked customer does NOT already have a non-empty value stored in `xs_customers.custom_fields`.

### When Required Enforcement Applies

- **Initial booking/create:** Enforced strictly; all required custom fields must be provided.
- **Later edits/reschedule:** Relaxed; customer may update a single field without re-entering others if they already have stored values.

### Implementation Seams

- `CustomerCustomFieldService::hasStoredValue($storedValues, $fieldKey)` → returns bool; true if field exists in decoded JSON and value is non-empty
- `BookingSettingsService::getValidationRulesForUpdate($customerId, $existingCustomFieldValues)` → accepts optional stored values; relaxes required rules when stored values exist

### Three Surfaces Apply This Rule

1. **Customer edit form** — `CustomerManagement::edit()` computes `$customFieldRequiredState` array; `app/Views/customer-management/edit.php` renders field-by-field required attribute conditionally.
2. **Admin appointment edit form** — `app/Views/appointments/form.php` computes `$isRequiredForEdit = $fieldMeta['required'] && $existingValue === ''` for each field.
3. **Public booking manage** — `PublicBookingService::reschedule()` passes stored customer values to `extractAppointmentCustomFieldValues()`; `resources/js/public-booking.js` only shows required marker when `!existingMasked`.

### Example Workflow

1. **Initial booking:** Customer fills required Medical Aid → value stored in `xs_customers.custom_fields`
2. **Later customer edit:** Medical Aid field NOT marked required (stored value exists) → customer may change address and save without re-entering Medical Aid
3. **Public reschedule:** Medical Aid NOT marked required in form → blank submission accepted, existing value preserved via selective merge

### Edge Case Semantics

- **Sensitive fields on reschedule:** Form input starts empty but shows "Current: ****5201" hint. Blank submission is a no-op (doesn't overwrite stored value).
- **Non-sensitive fields on edit:** Show "Leave blank to remove this value" hint. Blank submission is a no-op (selective merge only takes non-empty values).
- **Explicit clear:** `clear__fieldKey=1` in payload still removes value regardless of blank status (explicit intent wins).

### Test Coverage

- `Tests\Unit\Services\BookingSettingsServiceTest::testGetValidationRulesForUpdateRelaxesRequiredCustomFieldWhenStoredValueExists` → confirms rules relax when stored values present
- `Tests\Unit\PublicBookingServiceTest::testExtractAppointmentCustomFieldValuesRequiresInitialCaptureWhenMissing` → confirms first capture strict
- `Tests\Unit\PublicBookingServiceTest::testExtractAppointmentCustomFieldValuesAllowsBlankWhenStoredValueAlreadyExists` → confirms reschedule relax

---

---

## 9. Standalone Page & Asset Contract (Owner Section)

`app/Views/public/booking.php` is a **fully standalone HTML page** — it does NOT extend `layouts/public.php`. It generates its own complete `<!DOCTYPE html>` document structure.

### Required `<head>` elements (in order)

1. **FOUC prevention inline blocking script** — same pattern as all layouts, but uses `#0f172a` (slate-900) as the dark background to match `dark:bg-slate-900` on `<body>`:
   ```html
   <script>!function(){var t=localStorage.getItem('xs-theme')||(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);document.documentElement.classList.toggle('dark',t==='dark');document.documentElement.style.colorScheme=t;if(t==='dark')document.documentElement.style.backgroundColor='#0f172a';document.documentElement.classList.add('xs-no-transition')}();</script>
   ```
2. **Compiled CSS** — two sources merged:
   - `vite_css('resources/scss/app-consolidated.scss')`
   - `vite_asset('resources/js/public-booking.js')['css']` (JS-extracted CSS)
3. **Material Symbols Outlined font link** — **required**: without it all `<span class="material-symbols-outlined">` render as text literals (`location_on In Person`, `videocam Jitsi Meet`, etc.)
   ```html
   <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
   ```

### Body and root element

- `<body class="bg-slate-50 dark:bg-slate-900">` — uses **slate** palette (not gray) — distinct from the authenticated app
- `<div id="public-booking-root">` — mounting point for the SPA, carries CSRF data attributes
- Honeypot field inside root (hidden from real users, triggers server-side rejection for bots)

### JS entry points (load order)

1. `<script id="public-booking-context" type="application/json">` — server context payload (services, providers, settings, CSRF)
2. `public-booking-bootstrap.js` (type=module) — sets `window.__BASE_URL__` and `window.appBaseUrl` from `body.dataset.baseUrl` before the main SPA loads
3. `public-booking.js` (type=module, defer) — full booking SPA

### FOUC cleanup

`app-layout-init.js` does NOT run on this page. Cleanup is a bare inline double-rAF at end of `<body>`:
```html
<script>requestAnimationFrame(function(){requestAnimationFrame(function(){document.documentElement.classList.remove('xs-no-transition');document.documentElement.style.backgroundColor='';document.documentElement.style.colorScheme=''})});</script>
```

### Rules

- **Any new icon added to booking SPA components:** verify the Google Fonts link is in `booking.php` head — easy to miss since the page bypasses standard layout
- **Dark mode color:** `#0f172a` / `dark:bg-slate-900` — do not change to gray-900 without updating both the inline script and body class
- **Do not add `app-layout-init.js` or the SPA `spa.js` to this page** — it is not an authenticated SPA surface

---

## Cross-References

- Booking pipeline (server-side, shared with admin path) → `scheduling` skill §8.2
- Notification templates rendered for public booking confirmations → `notifications` skill
- `xs_customers`, `xs_appointment_custom_fields` schema → `database` skill
- `xs_customers.email` unique index + `xs_customers.hash` for public routes → `database` skill
- Public endpoint guardrails (no numeric IDs, rate limiting) → `api-contract` skill
- Public booking JS timezone via `context.timezone` (not session) → `database` skill (Timezone Integrity Rules)
- Icon system and delivery mode UI (DELIVERY_MODE_META) → `ui-ux` skill
