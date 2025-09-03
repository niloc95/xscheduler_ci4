# xScheduler — Requirements Specification

_Last updated: July 2025_

---

## Project Overview

xScheduler is a modern online appointment scheduling system for service-based businesses. It features:

- Customer-facing appointment booking
- Provider calendar and availability management
- Admin panel for configuration and analytics
- Responsive Material Design UI
- Zero-configuration deployment to shared or VPS hosting

**Technology Stack:**

- **Backend:** CodeIgniter 4 (PHP 7.4+)
- **Frontend:** Tailwind CSS + Material Web Components
- **Charts:** Chart.js
- **Database:** MySQL or SQLite
- **Build System:** Vite

---

## User Roles

### Customer

- View services and providers
- Book appointments
- View/cancel appointments
- Receive email notifications

### Provider

- View personal calendar
- Block unavailable time slots
- Receive booking notifications

### Admin

- Manage users
- Manage services
- Block global unavailable times
- Access analytics dashboard

---

## Core Features

### Authentication

- Admin registration during setup
- Provider/admin login
- Password hashing (security best practices)
- CSRF protection

---

### Appointment Scheduling

#### Booking Process

- Customer selects:
  - Service
  - Provider
  - Date & time
- System checks:
  - Provider availability
  - Conflicts with existing bookings
  - Blocked periods
- Appointment saved with:
  - Customer details
  - Provider details
  - Service info
  - Status: booked

#### Appointment Lifecycle

- Booked
- Cancelled
- Completed
- Rescheduled

---

### Provider Calendar

- Month/Week/Day views
- Material Design UI
- Appointment blocks:
  - Service name
  - Customer name
  - Timeslot
- Ability to block time periods:
  - Lunch breaks
  - Holidays
  - Personal time off

---

### Services Management

- Admin can:
  - Add/edit/delete services
  - Define:
    - Name
    - Description
    - Duration
    - Price
- Providers linked to specific services

---

### Notifications

- Email confirmations:
  - New bookings
  - Cancellations
  - Reschedules
- Daily summary emails for providers (future)

---

### Analytics Dashboard

- Data visualizations:
  - Total bookings
  - Provider utilization
  - Peak booking times

---

### System Setup Wizard

- Admin user registration
- Database configuration
- MySQL connection testing
- One-time flag prevents re-running setup

---

## Database Design

### users

| Field         | Type      | Notes                         |
|---------------|-----------|-------------------------------|
| id            | PK        |                               |
| name          | varchar   |                               |
| email         | varchar   | unique                        |
| phone         | varchar   | nullable                      |
| password_hash | varchar   | hashed password               |
| role          | enum      | customer, provider, admin     |
| created_at    | timestamp |                               |

---

### services

| Field        | Type      | Notes         |
|--------------|-----------|---------------|
| id           | PK        |               |
| name         | varchar   |               |
| description  | text      |               |
| duration_min | int       | in minutes    |
| price        | decimal   | nullable      |

---

### providers_services

| Field        | Type      | Notes             |
|--------------|-----------|-------------------|
| provider_id  | FK        | users.id          |
| service_id   | FK        | services.id       |

---

### appointments

| Field        | Type      | Notes                          |
|--------------|-----------|--------------------------------|
| id           | PK        |                                |
| user_id      | FK        | users.id (customer)            |
| provider_id  | FK        | users.id (provider)            |
| service_id   | FK        | services.id                    |
| start_time   | timestamp |                                |
| end_time     | timestamp |                                |
| status       | enum      | booked, cancelled, completed   |
| notes        | text      | nullable                       |
| created_at   | timestamp |                                |

---

### blocked_times

| Field        | Type      | Notes                              |
|--------------|-----------|------------------------------------|
| id           | PK        |                                    |
| provider_id  | FK        | users.id, nullable (for global)    |
| start_time   | timestamp |                                    |
| end_time     | timestamp |                                    |
| reason       | varchar   |                                    |

---

## API Endpoints

### POST /api/appointments

**Create new appointment.**

#### Request JSON

```json
{
  "user_id": 5,
  "provider_id": 2,
  "service_id": 3,
  "start_time": "2025-07-15T10:30:00",
  "end_time": "2025-07-15T11:00:00",
  "notes": "Customer requests extra time."
}
