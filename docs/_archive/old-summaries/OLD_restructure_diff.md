# restructure_diff

## Scope

- Source: `Agent_Context.md` (1816 lines)
- Output: `Agent_Context_Restructured.md`
- Method: exact source line ranges moved into feature-based sections; sparse feature areas received section skeletons only.

## Line Mapping

| Original Lines | New Section | Source Block |
|---|---|---|
| 1-29 | System Overview & Foundation | Source preamble + What You Are Building / How To Read / Current Refactor Snapshot |
| 30-38 | Backend Architecture | Confirmed Tech Stack -> Backend |
| 39-48 | Frontend Architecture | Confirmed Tech Stack -> Frontend |
| 49-72 | Build & Deployment | Build / Release + Deployment Package |
| 73-78 | System Overview & Foundation | No Framework Drift |
| 79-104 | Backend Architecture | Project Structure -> Backend |
| 105-122 | Frontend Architecture | Project Structure -> Frontend |
| 123-137 | Documentation | Project Structure -> Documentation |
| 138-142 | Backend Architecture | Known Dead Code |
| 143-174 | System Overview & Foundation | Architecture Rules |
| 175-357 | Database Foundation | Database Rules |
| 358-372 | Localization & Timezone | Timezone & Datetime Rules |
| 373-429 | Appointments & Scheduling | Scheduling & Booking Rules |
| 430-464 | Public Booking | Public Booking Flow (top-level rules) |
| 465-482 | Settings | Settings Rules |
| 483-505 | Frontend Architecture | Frontend Rules |
| 506-643 | Frontend Architecture | Frontend JS Architecture (core SPA/app.js/spa.js) |
| 644-746 | Appointments & Scheduling | Scheduler refresh semantics + mutation pipeline + appointment form schema drift |
| 747-768 | Public Booking | public-booking.js bundle |
| 769-808 | API Foundation | API Rules |
| 809-846 | Authentication & Authorization | Roles & Access Rules + RBAC route/service enforcement |
| 847-851 | API Foundation | API Base Controller role enforcement |
| 852-857 | User Management | User Management Context |
| 858-870 | Providers | Provider Schedule JS + API Providers Resource + Provider Schedule Authorization |
| 871-877 | Authentication & Authorization | Route Filters Reference |
| 878-890 | Customers | Staff Scope + Provider Scope Contract |
| 891-1077 | Notifications | Notifications Rules + queue/dispatch/log architecture |
| 1078-1174 | Frontend Architecture | Frontend Architecture: In-Depth |
| 1175-1272 | API Foundation | API Request Flow |
| 1273-1543 | Public Booking | Public Booking Flow: Step-by-Step |
| 1544-1556 | Testing Infrastructure | Testing Rules |
| 1557-1567 | Documentation | Documentation Rules |
| 1568-1663 | Agent Notes & Standards | Known Tech Debt + Debt Prevention Guardrails + Forbidden List |
| 1664-1682 | Development Checklist | Before You Change Code |
| 1683-1697 | Phase Status & Active Work | Suggested Active Phase View |
| 1698-1749 | System Overview & Foundation | Key Files To Know + Final Rule |
| 1750-1783 | User Management | Phase 3: Multi-Role User Management Status |
| 1784-1807 | Agent Notes & Standards | Agent Notes bullets |
| 1808-1816 | Phase Status & Active Work | Last updated + Phase 3/4/5/6 status footer |

## Sparse / Skeleton Sections

- Dashboard
- Calendar & Availability
- Services & Categories
- Analytics
- Profile

These sections were created to match the requested navigation structure, but the current source document does not contain dedicated standalone prose blocks for them. Cross-references were added to point to the exact source-backed sections that currently hold the relevant material.

## Manual Review Notes

- `Key Files To Know` was kept under `System Overview & Foundation` to avoid duplicating file references across multiple feature areas.
- `Roles & Access Rules` was split across `Authentication & Authorization`, `API Foundation`, `User Management`, `Providers`, and `Customers` so each exact source sub-block remains single-homed.
- `Notifications Rules` in the original source also contained an embedded `Frontend Architecture: In-Depth` section and `API Request Flow`; these were moved to `Frontend Architecture` and `API Foundation` respectively.
- `Public Booking Flow` existed in two places in the original source (top-level overview + step-by-step walkthrough). Both remain under `Public Booking` in the restructured output.
