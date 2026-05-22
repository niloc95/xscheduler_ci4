# WebScheduler — Engineering Skills

This folder contains the WebScheduler CI4 engineering contract split into 10 focused **skills**. Each skill is a self-contained `SKILL.md` covering one domain. Replace one ~2,000-line context document with targeted skills that load only what's needed for the task at hand.

## Skill Map

| Skill | When to use |
|---|---|
| **rules** | Before any code change — checklists, quality gates, pre-merge ripgrep checks, forbidden patterns |
| **architecture** | Designing services, deciding where logic belongs, business-context resolver, canonical service catalog |
| **auth-rbac** | Login, sessions, role checks, route filters, auth hardening, customer/user/staff/provider scoping |
| **api-contract** | API endpoints, response envelopes, public guardrails, SPA form JSON contract |
| **frontend** | Vite entry points, SPA lifecycle, dark mode FOUC mechanism, avatar system, layout contract |
| **ui-ux** | Icon rendering (Material Symbols, font loading), `xs-card`/`xs-btn` tokens, delivery mode UI, dark mode CSS, animation conventions |
| **scheduling** | Appointments, business hours, availability, calendar, slot generation |
| **notifications** | Queue, templates, reminders, dispatch, SMS/email/WhatsApp, `MailerService`, `{session_info}` placeholder |
| **database** | Schema, 22-table catalog, relationships, timezone integrity, migrations |
| **public-booking** | `/booking/*` routes, `/r/{ref}`, public APIs, policy enforcement, custom field semantics, standalone page asset contract |
| **setup** | Setup wizard routes, view/layout wiring (`components/setup-layout.php`), JS entry points, DB connection testing |
| **operations** | Spark commands, testing, known debt, hardening log, next-up queue |

## Cross-Reference Pattern

Each skill owns its contracts and cross-references the others where relevant. Owner sections are NOT duplicated — if you change a contract, update the owner skill first, then update reference reminders in other skills.

Owner-section map (high-level):

| Contract | Owner |
|---|---|
| Avatar system | `frontend` |
| Dark mode FOUC prevention (inline script, xs-no-transition) | `frontend` |
| RBAC / sessions | `auth-rbac` |
| API envelope | `api-contract` |
| SPA lifecycle | `frontend` |
| Icon system (Material Symbols, font loading) | `ui-ux` |
| Design system components (`xs-card`, `xs-btn`, `xs-actions-container`) | `ui-ux` |
| Delivery mode UI (`DELIVERY_MODE_META`, icons, pill buttons) | `ui-ux` |
| Dark mode CSS rules (selectors, `color-scheme`) | `ui-ux` |
| Scheduler mutations | `scheduling` |
| Booking pipeline | `scheduling` |
| Business hours architecture | `scheduling` |
| Status → notification event | `scheduling` |
| Notification queue / dispatch | `notifications` |
| Email transport (`MailerService`) | `notifications` |
| Delivery mode & session info placeholders (`{session_info}`) | `notifications` |
| Business context resolver | `architecture` |
| Schema + timezone integrity | `database` |
| Migration base requirement | `database` |
| Public hash/token URL safety | `public-booking` |
| Custom field required semantics | `public-booking` |
| Public booking standalone page asset contract | `public-booking` |
| Setup wizard structure & asset wiring | `setup` |
| Pre-merge ripgrep checks | `rules` |

## Recommended Triggering Pattern

- **Any code change:** start with `rules` (always), then load the domain skill(s) for the area you're touching.
- **Cross-domain work** (e.g. adding a public booking that fires a notification): load `public-booking` + `notifications` + `rules`.
- **Schema or migration work:** load `database` + `rules`.
- **PR review:** load `rules` (it has the pre-merge ripgrep checks).

## Recommended Loading Pattern

| Situation | Load |
|---|---|
| Any icon or UI component | `ui-ux` + `rules` |
| Dark mode or FOUC issue | `frontend` + `ui-ux` + `rules` |
| Public booking SPA | `public-booking` + `ui-ux` + `rules` |
| Notification templates / placeholders | `notifications` + `rules` |
| Setup wizard | `setup` + `rules` |

## Source

Consolidated from `Agent_Context_v2.md` (v3.0, 2026-05-12). Expanded with `ui-ux` and `setup` skills (2026-05-22). When the canonical contract changes, update the **owner skill** first, then sync references.
