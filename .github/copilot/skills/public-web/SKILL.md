---
name: webscheduler-public-web
description: WebScheduler's public web surfaces that live in THIS app repo — the developer API portal (in-app /developers + the standalone docs-site build) and the "List on our directory" handoff to the external directory SaaS. Use when touching the developer docs portal, the OpenAPI spec wiring, the redoc bundle, or the directory handoff (Config\Directory, DirectoryHandoffService). NOTE: the public marketing website and the directory listing app were moved OUT of this repo to the separate `niloc95/marketing` repo. Triggers on "developer portal", "API docs", "redoc", "openapi", "docs-site", "docs:build", "list on our directory", "directory handoff", "prefill", "DirectoryHandoff".
---

# WebScheduler — Public Web (developer portal + directory handoff)

Two public-facing concerns live in this app repo. The **marketing website** and the
**directory listing SaaS** do **not** — they were moved to the separate
**`niloc95/marketing`** repo (marketing site `marketing-site/`, directory CI4 app,
`site:*` build scripts). Do not re-add them here.

## 1. Developer API portal (stays here)

- **In-app portal:** `app/Controllers/DeveloperDocs.php` serves `/developers`,
  `/developers/getting-started`, `/developers/openapi.yaml`. Views in
  `app/Views/developers/`; runtime `resources/js/developers.js` (vendored Redoc at
  `resources/redoc/redoc.standalone.js`). Spec source of truth:
  `docs/technical/openapi.yml` (drift-guarded; shipped by `scripts/package.js`).
- **Standalone portal build:** `scripts/build-docs-site.js` (`npm run docs:build`)
  assembles `docs-site/` + the vendored redoc + the spec into `dist/developer/` for
  upload to `webscheduler.co.za/developer`. CI: `.github/workflows/developer-docs.yml`
  builds it on changes to `docs-site/**` or the build script.
- The marketing site (other repo) links to this portal at absolute `/developer/`.

## 2. "List on our directory" handoff (stays here)

Lets a logged-in admin/provider publish to the external directory SaaS without
re-typing. Owner files:
- `Config\Directory` — `handoffUrl`, `handoffSecret` (shared HMAC), `enabled`; env-mapped.
- `App\Services\DirectoryHandoffService` — assembles basic business details from
  `general.*` settings + acting user + primary `xs_locations`, base64+HMAC-signs them.
- `App\Controllers\DirectoryHandoff` (`GET /directory/list`, `role:admin,provider`) —
  renders an auto-POST interstitial; adds the directory origin to CSP `form-action` for
  that response only.
- Entry point: the "WebScheduler Directory" card in
  `app/Views/settings/tabs/integrations.php`.

The payload is convenience only — the SaaS treats it as unverified and gates
publication behind email verification. The HMAC is tamper-evidence, not auth (real API
auth remains per-user `xs_api_keys` — see `api-contract`).

## 3. Where the moved pieces went

`niloc95/marketing` (a CI4 + MySQL app) now holds: the static marketing website
(`marketing-site/`, built with `site:build`), and the **directory listing service**
(browse/search/profile, email-verified signup, the prefill **consumer** that verifies
the handoff HMAC, and an admin panel). If you change the handoff payload shape or the
shared-secret contract here, update `DirectoryPrefillService` in that repo to match.

## Cross-references

- API envelope + the OpenAPI contract behind the portal → [[webscheduler-api-contract]]
- Settings reads used by the handoff (`general.*`) → [[webscheduler-architecture]] (§8A)
- Route filters / roles on `directory/list` → [[webscheduler-auth-rbac]]
