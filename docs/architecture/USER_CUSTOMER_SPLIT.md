## User & Customer Separation (Refactor Spec)

### Overview
System (staff) accounts now reside in the `users` table (roles: admin, provider, receptionist). External booking clients reside in a new `customers` table. Appointments link `customer_id` -> `customers.id` and `provider_id` -> `users.id`.

### Schema Changes
Added: `customers` table.
Updated: `users` (added `status`, `last_login`, updated role enum) & `appointments` (added `customer_id`, removed `user_id`).

### Migration Order
1. `2025-09-16-000001_CreateCustomersTable`
2. `2025-09-16-000002_UpdateUsersAndAppointmentsSplit`

### API Segmentation
| Layer | Purpose | Base Path |
|-------|---------|-----------|
| Public Booking API | Customer profile & appointment booking | `/api/public/v1` |
| Admin API | Manage users, appointments, services | `/api/admin/v1` |

### New Endpoints (Stubs)
- POST `/api/public/v1/customers`
- PUT `/api/public/v1/customers/{id}`
- POST `/api/public/v1/appointments`
- PUT `/api/public/v1/appointments/{id}`
- POST `/api/public/v1/appointments/{id}/cancel`
- GET `/api/admin/v1/users`
- POST `/api/admin/v1/users`
- GET `/api/admin/v1/appointments`

### Outstanding Tasks (Next Iteration)
- Add authentication/authorization middleware to new route groups.
- Implement provider/service browsing endpoints for public API.
- Migrate legacy controllers to rely on `customer_id` instead of `user_id`.
- Update frontend (scheduler) to send `customer_id` when booking.
- Add OTP / lightweight auth flow for customers.
- Adjust analytics to pull customer metrics from `customers`.

### ERD (Simplified)
```
users (id PK) --< appointments >-- customers (id PK)
appointments -- services (id PK)
```

### Rollback Strategy
Rollback migration attempts to restore prior schema (best-effort) but original customer user rows cannot be perfectly reconstructed.
