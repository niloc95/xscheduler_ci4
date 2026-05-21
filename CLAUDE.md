# WebScheduler CI4 — Claude Code Context

## Engineering Skills

Domain contracts live in `.github/copilot/skills/`. Load the relevant skill(s) before any code change.

| Skill | When to load |
|---|---|
| `rules` | **Always** — checklists, quality gates, pre-merge ripgrep checks, forbidden patterns |
| `architecture` | Designing services, deciding where logic belongs, business-context resolver |
| `auth-rbac` | Login, sessions, role checks, route filters, auth hardening, provider/customer scoping |
| `api-contract` | API endpoints, response envelopes, public guardrails, SPA form JSON contract |
| `frontend` | Anything in `resources/js/`, `resources/scss/`, or `app/Views/` — SPA, Vite, dark mode, avatar, design system |
| `scheduling` | Appointments, business hours, availability, calendar, slot generation |
| `notifications` | Queue, templates, reminders, dispatch, SMS/email/WhatsApp, `MailerService` |
| `database` | Schema, 22-table catalog, relationships, timezone integrity, migrations |
| `public-booking` | `/booking/*` routes, `/r/{ref}`, public APIs, policy enforcement, custom field semantics |
| `operations` | Spark commands, testing, known debt, hardening log, next-up queue |

See `.github/copilot/skills/README.md` for the full trigger map and cross-reference pattern.

### Default loading rule

- **Any code change:** read `rules/SKILL.md` first, then the domain skill(s) for the area being touched.
- **Cross-domain work:** load all relevant domain skills + `rules`.
- **PR review:** `rules/SKILL.md` (contains the pre-merge ripgrep checklist).
