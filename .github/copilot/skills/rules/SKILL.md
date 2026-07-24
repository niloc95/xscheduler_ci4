---
name: webscheduler-rules
description: Mandatory pre-change rules, checklists, quality gates, and forbidden patterns for the WebScheduler CI4 codebase. Use this skill BEFORE making ANY code change to WebScheduler — including refactors, bug fixes, new features, migrations, or view edits. Triggers on phrases like "fix", "change", "refactor", "add", "update", "modify", "implement", "build", or any code edit request in the WebScheduler / Frontend Dev project. Also use when reviewing a PR or running a pre-merge audit.
---

# WebScheduler — Rules & Quality Gates

This is the first skill to consult on any code change. It encodes the non-negotiable rules. For deeper context on a specific domain, load the matching skill (architecture, frontend, scheduling, notifications, database, etc.) after this one.

## Rule #1 — Full Codebase Audit Directive

When auditing, cover all of:

1. **Code Quality and Redundancy** — eliminate duplicate code, dead code (unused vars/functions/imports/files), orphaned components/views/routes. Migrate inline CSS to Tailwind/SCSS.
2. **Architecture Mapping** — map Controllers ↔ Views ↔ Helpers ↔ Services ↔ Assets. Identify disconnected components, architectural violations, tight coupling.
3. **Refactoring Opportunities** — service-layer extraction (e.g. `MailerService`, `BookingService`), reusable UI components, helper consolidation, JS modularization.
4. **Performance Optimization** — unused/duplicate JS/CSS, inefficient Vite bundling, large dependencies.
5. **Security and Validation** — input validation, output escaping, CSRF consistency, secure file/external input handling.
6. **Deployment Integrity** — only required files deployed; no dev/debug artifacts in production; secure env config.
7. **Actionable Output** — file-level recommendations with concrete solutions, not vague suggestions.
8. **Scheduling and Availability Audit** — see "Scheduling-Specific Audit Rules" below.

### Scheduling-Specific Audit Rules

When auditing scheduling code, verify:

- Every `xs_business_hours` query includes a `provider_id` filter — **no unscoped reads**.
- Global business bounds read from `xs_settings` (keys `business.work_start`, `business.work_end`), not per-provider tables.
- `SettingModel::getByKeys()` used (the non-existent `getValue()` is a bug).
- `AvailabilityService::constrainToBusinessHours()` called for every slot-generation path.
- `TimezoneService::businessTimezone()` is the source for the business timezone — never hardcoded `'UTC'`.

## ⚠️ Rule #2 — No Assumptions

Do not assume code is correct. Always verify usage before keeping anything. If uncertain → trace usage or flag it.

**Query Scope Rule:** Every query against a per-entity table (`xs_business_hours`, `xs_provider_schedules`, `xs_locations`, `xs_blocked_times`) MUST include an entity-scoping `WHERE` clause. An unfiltered query on these tables returns a random row from whichever entity was inserted first — never a "global" value. Verify every `WHERE` clause before keeping code.

**API Contract Rule:** `SettingModel` exposes `getByKeys(array)`, `getByPrefix(string)`, `getAllAsMap()`, and `upsert()`. **There is no `getValue()` method.** Always use `getByKeys(['key'])` to read a single setting.

## 🧠 Rule #3 — Before You Change Code (Mandatory Checklist)

Before making any code changes, validate the following:

- [ ] Does this logic belong in a service instead of a controller/view?
- [ ] Am I using UTC semantics for persisted datetimes?
- [ ] Am I reading timezone/localization from settings-backed services?
- [ ] Am I using canonical appointment fields and IDs?
- [ ] Am I preserving API response contracts?
- [ ] Am I reusing queue and booking pipelines?
- [ ] Am I respecting route filters and role boundaries?
- [ ] If the route is meant to be externally callable, does it use `api_auth` (not session-only `auth`/`role:`), and is it registered in `$xsApiAuthenticatedRoutes` so both `/api/v1/…` and the unversioned alias exist?
- [ ] Am I reading the acting user via `current_user_id()` / `current_identity_user()` rather than `session()->get('user_id')` in code an API token can reach?
- [ ] Am I keeping SPA initialization conventions?
- [ ] If the controller action redirects back to the current page, does the JSON response include a `redirect` key so `spa.js` can use `forceReload`?
- [ ] If adding/changing a setting consumed outside the settings panel (sidebar, `<head>`, a JS singleton), did I wire its live-sync and dispatch `settingsSaved` per `frontend` §14 (Settings Live-Sync Contract)? A setting that only shows after a manual reload is a regression.
- [ ] Am I using `xsRegisterViewInit` instead of a bare `DOMContentLoaded` handler?
- [ ] If writing a migration, does it extend `MigrationBase`?
- [ ] Did I avoid dead-code surfaces and stale symbols?
- [ ] Have I verified consistency with `spa.js` and `app.js` patterns?
- [ ] Am I querying `xs_business_hours` with a `provider_id` filter? (All rows are per-provider; no global-only rows exist. An unfiltered query returns the first-inserted provider's hours — not a system-wide default.)
- [ ] Am I reading global time bounds (`business.work_start`, `business.work_end`) from `xs_settings` via `SettingModel::getByKeys()`, not from `xs_business_hours`?

## 🎯 Rule #4 — Dashboard Layout and Scroll UX Contract

Dashboard panels must use semantic, responsive scroll containers and avoid hardcoded height utilities in view templates.

1. **No inline hard caps in templates**
   - Do not use one-off utility caps such as `max-h-[400px]`, `h-[...px]`, or fixed pixel scroll wrappers directly in view markup for primary dashboard panels.
   - Define semantic classes in SCSS (e.g. `dashboard-schedule-scroll`, `provider-card-slots`) and keep sizing behavior centralized.

2. **Viewport-aware sizing**
   - Use `clamp()`/viewport-aware sizing for desktop dashboards so panel bodies scale across common screen sizes (1366×768 through 2560×1440) without clipping critical controls.
   - On tablet/mobile (`<=1023px`), default to natural height flow unless there is a clear performance reason for nested scroll.

3. **Nested scroll rules**
   - Nested scroll is allowed only for long data lists, not for filter/control groups.
   - Filters and primary actions must remain fully visible without requiring inner scrolling.

4. **UX consistency**
   - Keep provider and schedule panel behaviors consistent: if one pane is internally scrollable on desktop, the other should use an equivalent semantic strategy.
   - Preserve accessibility and keyboard navigation; avoid scroll traps (`overscroll-behavior: contain` where needed).

## 🧪 Rule #5 — Provider Assignment Integrity Audit (Operational)

Before shipping any dashboard/provider-card changes that touch service, location, or availability filtering, run the canonical assignment audit:

```bash
php spark audit:provider-assignments
```

Minimum checks:

1. **Provider → Services mapping** — Each provider card source only contains services assigned via `providers_services` mapping.
2. **Provider → Locations mapping** — Location options are provider-scoped (no cross-provider location leakage).
3. **Service → Providers mapping** — Sensitive/owner-specific services are assigned only to expected providers.

**Release gate:** If audit reports unexpected cross-provider assignment or missing critical assignments, treat as blocking until resolved or explicitly approved.

## Mandatory Quality Gates

### Contract Ownership Matrix

Each high-risk contract has one **owner section** — make full-text changes only in the owner. In other sections, keep only short references. Refer to the architecture skill for the full ownership matrix.

### Variable and Function Collision Gate

1. **Role variables** — Use `roles` for authoritative arrays. Use `role` only as compatibility fallback.
2. **Provider scope keys** — `provider_id`: single provider context. `provider_ids`: array filter context only.
3. **Scheduler loaders** — Use `loadData()` for canonical mutation-safe reload behavior. Do not replace with `loadAppointments()` in server-mode mutation paths.
4. **Notification IDs** — `id`: queue row primary key. `idempotency_key`: dedup semantics. `correlation_id`: trace correlation across related attempts/logs.

### Mandatory Pre-Merge Checks

Run these `rg` checks before merge. Any result must be reviewed and justified or fixed.

```bash
# 1) Detect accidental single-role auth checks
rg "\$user\['role'\]\s*===|\$currentUser\['role'\]\s*==" app/ resources/

# 2) Detect direct provider-role SQL assumptions
rg "role\s*=\s*'provider'|WHERE\s+role\s*=\s*'provider'" app/

# 3) Detect direct session user overwrites without merge
rg "session\(\)->set\('user',\s*\[" app/

# 3b) Detect direct session identity reads in API-reachable code. These silently
#     drop the actor on Bearer-token requests: provider scoping degrades to
#     unscoped and audit rows get a null user_id. Use current_user_id() /
#     current_identity_user() instead. (Session-only controllers are the
#     legitimate hits — verify the route's filter is 'auth'/'role:', not 'api_auth'.)
rg "session\(\)->get\('user_id'\)|session\(\)->get\('user'\)" app/Controllers/Api app/Services

# 4) Detect inline style regressions in views
rg "style=\"|<style>" app/Views resources/js

# 5) Detect deprecated appointment linkage usage
rg "appointments\.user_id|\buser_id\b" app/ resources/

# 6) Detect unfiltered xs_business_hours queries (must always have provider_id filter)
rg "table\('business_hours'\)|from.*xs_business_hours" app/

# 7) Detect SettingModel::getValue() calls (method does not exist; use getByKeys instead)
rg "->getValue\(" app/Services app/Controllers app/Models

# 8) Detect settings keys with wrong casing — all xs_settings keys are lowercase.dotted.
#    getByKeys() matches keys EXACTLY, so 'Localization.currency' silently returns the
#    default forever. This exact bug hardcoded the whole app to ZAR.
rg "setting\('[A-Z]" app/

# 9) Detect hardcoded currency symbols / money formatting outside currency_helper.
#    Views must call format_currency(); a literal-symbol fallback is a latent ZAR bug.
#    (The localization.php currency <option> values are the one legitimate hit.)
rg "'R'\s*\.\s*number_format|number_format\(.*\)\s*\.\s*'|function_exists\('format_currency'\)" app/Views resources/js

# 10) Detect hardcoded business timezones in executable code. Resolve via
#     TimezoneService::businessTimezone() / LocalizationSettingsService::getTimezone().
#     Docblock examples and the localization.php <option> list are legitimate hits,
#     so filter out comment lines:
rg -n "Africa/Johannesburg|America/New_York|Europe/London" app/Services app/Controllers resources/js \
  | grep -vE ":[0-9]+:\s*(\*|//)"
```

### Orphan Section Gate (Docs)

A doc section passes only if it contains either substantive contract content **or** an explicit pointer to the owner section and rationale. No placeholder-only or TODO-only sections.

### Duplication Gate for Docs

- Update only the contract owner section for full text changes.
- In other sections, keep only short references.
- Preserve one-source wording for critical contracts.

## Forbidden Patterns (Condensed)

- Business logic in controllers
- Scheduling rules implemented only in views/JS
- Migrations extending framework `Migration` instead of `MigrationBase`
- Numeric customer IDs in public URLs
- UTC/local time mixing in persisted datetimes
- New authorization logic based on `role` alone
- Shared/hardcoded API secrets in config or `.env` committed to git — API auth is per-user rows in `xs_api_keys` only
- Wildcard `Access-Control-Allow-Origin` on `/api/*` (the surface is CSRF-exempt)
- Session-only filters (`auth`, `role:`) on routes intended for external API clients
- Mid-session session-user overwrite without `array_merge`
- Inline style attributes in app-facing templates
- Querying `xs_business_hours` without a `provider_id` filter
- Calling `SettingModel::getValue()` (does not exist)
- Hardcoded currency symbols or `number_format()` money rendering in views — use `format_currency()`
- Settings keys with non-lowercase segments (`setting('Localization.currency')`)
- Gateway-specific currency config that diverges from `localization.currency`
- Inline notification dispatch in HTTP request path (must queue + defer to cron)

## When to Load Other Skills

After this skill, also load:

| If you're touching… | Load |
|---|---|
| A service/controller/model boundary | `architecture` |
| Auth, sessions, route filters | `auth-rbac` |
| An API endpoint | `api-contract` |
| Views, SPA, Vite, styling | `frontend` |
| Appointments, scheduler, availability | `scheduling` |
| Notifications, queues, reminders, email | `notifications` |
| Schema, queries, migrations, timezone | `database` |
| `/booking`, `/r/{ref}`, public APIs | `public-booking` |
| Spark commands, testing, debt log | `operations` |
