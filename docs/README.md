# WebScheduler CI4 — Documentation

**Navigate:** See [INDEX.md](INDEX.md) for the full table of contents organized by domain.

**Engineering contract:** See [../Agent_Context_v2.md](../Agent_Context_v2.md) for architecture rules, API contracts, scheduling behavior, database schema, and all non-negotiable patterns.

---

## Quick Links

| Need | Document |
|---|---|
| Architecture overview | [architecture/](architecture/) |
| Role / auth system | [architecture/ROLE_BASED_SYSTEM.md](architecture/ROLE_BASED_SYSTEM.md) |
| Calendar engine API | [architecture/CALENDAR_ENGINE_API_REFERENCE.md](architecture/CALENDAR_ENGINE_API_REFERENCE.md) |
| Scheduler JS modules | [architecture/scheduler_ui_architecture.md](architecture/scheduler_ui_architecture.md) |
| Spark CLI commands | [technical/spark-commands.md](technical/spark-commands.md) |
| Deployment | [deployment/RELEASING.md](deployment/RELEASING.md) |
| Settings system | [configuration/settings-architecture.md](configuration/settings-architecture.md) |
| Test runner | [testing/test_runner_guide.md](testing/test_runner_guide.md) |
| Security policy | [security/security_policy.md](security/security_policy.md) |

---

## Folder Structure

```
docs/
├── architecture/     — System contracts, RBAC, calendar engine, scheduler modules (7 files)
├── aws-lightsail/    — Hosting reference
├── configuration/    — Environment config, settings architecture, setup wizard
├── dark-mode/        — Dark mode implementation
├── database/         — DB backup, prefix conventions
├── deployment/       — Release guides, production troubleshooting
├── design/           — Design system, color palette, UI standards
├── development/      — Developer guides, naming conventions, provider/staff system
├── features/         — Feature docs: scheduling, notifications, avatar, dashboard, locations
├── frontend/         — Frontend guides: core JS modules, calendar sync, view controls
├── scheduler/        — Scheduler component docs, conflict service, troubleshooting
├── security/         — Security policy, hash URLs, implementation guide
├── technical/        — OpenAPI spec, spark commands, CSS guide
├── testing/          — Test runner, calendar testing guides
├── ui-ux/            — UI overview
└── _archive/         — Historical records (fix logs, audit reports, phase summaries)
```
