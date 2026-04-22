# Sample Scheduling Dataset

This project now ships with a dedicated seeder for generating a realistic scheduling sandbox that matches the new dashboard/stat requirements.

## What the seeder creates

- **Providers & staff** – 5 colour-coded providers plus 20 staff (2 assigned to each provider, 10 extra unassigned) with hashed credentials and active roles.
- **Categories & services** – 10 curated categories and 20 active services (4 per provider) covering wellness, cardio, dermatology, physio, etc.
- **Schedules & business hours** – Monday–Friday working hours (08:00–17:00) with lunch breaks, mirrored across `xs_provider_schedules` and `xs_business_hours`.
- **Public holidays** – South African holidays between December 2025 and April 2026 stored in `blocked_times` and respected by downstream availability.
- **Customers** – 120 reusable customers with South African phone numbers for appointment assignments.
- **Appointments** – For the next 6 months every provider receives 5 appointments per working day (Mon–Fri), with randomised services/statuses and hashes so dashboard stats, filters, and availability logic have dense data to process.

All sample records use the domains `@sample.local` (users) and `@samplepatients.local` (customers) to keep them easy to identify during clean-up.

## Usage

1. Ensure your `.env` points to the database you want to fill (never run on production).
2. Run the seeder:

```bash
php spark db:seed SchedulingSampleDataSeeder
```

The command runs inside a transaction:

- Existing sample data (matching the sample domains or the predefined category/service names) is purged before re-insertion so the dataset stays deterministic.
- Global public-holiday blocks are created only when missing, but the seeder always treats those dates as unavailable when generating appointments.

## Notes

- The generated appointments respect provider schedules, business hours, public holidays, and lunch breaks to exercise the availability logic.
- Passwords for generated users/staff are hashed from `SamplePass!23`. Update or remove the sample accounts before exposing a non-dev environment.
- Because thousands of appointments are inserted (≈3 250), running the seeder on very slow hardware/MySQL instances can take a few seconds.
- Rerunning the seeder is idempotent for the sample dataset; it won’t touch real data that uses different emails/service names.
