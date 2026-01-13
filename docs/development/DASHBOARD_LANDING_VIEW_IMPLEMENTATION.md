# Dashboard Landing View - Implementation Plan

**Status**: In Development  
**Priority**: Core / Blocking  
**Target**: xScheduler CI4 v1.0.0  
**Last Updated**: January 2026

---

## 1. Executive Summary

The Dashboard Landing View is the post-login entry point for all users (Owner/Admin, Provider, Staff). It must provide real-time operational awareness and fast access to common actions within 5 seconds, while enforcing strict role- and scope-based permissions.

**Key Principle**: Dashboard functionality is identical across roles; only data visibility and action scope differ.

---

## 2. Architecture Overview

### 2.1 Permission Model

```
┌─────────────────────────────────────────────┐
│         Role-Based Permission Model         │
├─────────────────────────────────────────────┤
│                                             │
│  Owner (Admin)                              │
│  ├─ Full system access                      │
│  ├─ Global business configuration           │
│  └─ No scope restrictions                   │
│                                             │
│  Provider                                   │
│  ├─ Same functional capabilities as Owner   │
│  ├─ Data filtered to own scope only         │
│  │  (provider_id = authenticated user)      │
│  └─ Cannot modify global settings           │
│                                             │
│  Staff                                      │
│  ├─ Permissions inherit from Owner          │
│  ├─ Permissions are a subset only           │
│  ├─ Cannot exceed Owner permissions         │
│  └─ Operates under Owner-defined rules      │
│                                             │
└─────────────────────────────────────────────┘
```

### 2.2 Core Principle

**Authorization is enforced server-side. UI visibility does NOT equal authorization.**

Every API call and mutation validates:
- User role
- Provider/staff scope
- Owner-defined constraints

---

## 3. Implementation Breakdown

### 3.1 Phase 1: Core Controller & Services (Week 1)

#### DashboardService.php

**Purpose**: Centralized business logic for dashboard data aggregation

**Responsibilities**:
- Fetch role-scoped data
- Enforce authorization rules
- Calculate metrics server-side
- Cache-friendly query design

**Key Methods**:

```php
namespace App\Services;

class DashboardService {
    
    /**
     * Get dashboard context for authenticated user
     * @param int $userId
     * @param string $userRole
     * @param ?int $providerId (for providers/staff)
     * @return array Scoped dashboard data
     */
    public function getDashboardContext(int $userId, string $userRole, ?int $providerId = null): array
    
    /**
     * Get today's metrics (server-side calculation)
     * @return array ['total', 'upcoming', 'pending', 'cancelled']
     */
    public function getTodayMetrics(?int $providerId = null): array
    
    /**
     * Get today's schedule snapshot (business hours only)
     * @return array Grouped by provider with appointment blocks
     */
    public function getTodaySchedule(?int $providerId = null): array
    
    /**
     * Get actionable alerts for user
     * @return array ['type' => string, 'message' => string, 'action' => string]
     */
    public function getAlerts(?int $providerId = null): array
    
    /**
     * Get upcoming appointments (next 7 days, max 10)
     * @return array
     */
    public function getUpcomingAppointments(?int $providerId = null): array
    
    /**
     * Get provider availability for today
     * @return array ['provider_id' => [...states...]]
     */
    public function getProviderAvailability(?int $providerId = null): array
    
    /**
     * Get booking system operational status (Owner only)
     * @return array
     */
    public function getBookingStatus(): array
}
```

#### AuthorizationService Updates

**New Methods**:

```php
/**
 * Check if user can view dashboard component
 */
public function canViewDashboardMetrics(int $userId, string $role): bool

/**
 * Check if user can access provider schedule
 */
public function canViewProviderSchedule(int $userId, ?int $targetProviderId): bool

/**
 * Check if user can create appointment from dashboard
 */
public function canCreateAppointmentFromDashboard(int $userId, string $role): bool

/**
 * Get provider scope for user
 * Returns: null (Owner/unrestricted), int (Provider ID), array (Staff permissions)
 */
public function getProviderScope(int $userId, string $role): mixed
```

### 3.2 Phase 2: Controller Update (Week 1)

#### Dashboard.php Controller Refactor

**Current State**:
- Single `index()` method
- No role-based filtering
- Mock data fallback
- No permission checks

**Updated Structure**:

```php
namespace App\Controllers;

class Dashboard extends BaseController {
    protected DashboardService $dashboardService;
    protected AuthorizationService $authService;
    
    public function __construct() {
        $this->dashboardService = new DashboardService();
        $this->authService = new AuthorizationService();
    }
    
    /**
     * Main dashboard landing view
     * POST-login entry point for all roles
     */
    public function index() {
        // Authorization check
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }
        
        $userId = session()->get('user_id');
        $role = current_user_role();
        $providerId = session()->get('provider_id') ?? null;
        
        // Get scoped dashboard data
        $context = $this->dashboardService->getDashboardContext($userId, $role, $providerId);
        
        // Get metrics, schedule, alerts, etc.
        $data = [
            'context' => $context,
            'metrics' => $this->dashboardService->getTodayMetrics($providerId),
            'schedule' => $this->dashboardService->getTodaySchedule($providerId),
            'alerts' => $this->dashboardService->getAlerts($providerId),
            'upcoming' => $this->dashboardService->getUpcomingAppointments($providerId),
            'availability' => $this->dashboardService->getProviderAvailability($providerId),
            'bookingStatus' => $role === 'admin' ? $this->dashboardService->getBookingStatus() : null,
        ];
        
        return view('dashboard/landing', $data);
    }
    
    /**
     * API endpoint for real-time metric updates
     * GET /dashboard/api/metrics
     */
    public function apiMetrics() {
        // Authorization & JSON response
    }
    
    /**
     * API endpoint for alerts
     * GET /dashboard/api/alerts
     */
    public function apiAlerts() {
        // Authorization & JSON response
    }
}
```

### 3.3 Phase 3: View Implementation (Week 2)

#### New View: `app/Views/dashboard/landing.php`

**Structure**:

```php
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('dashboard_content') ?>

<!-- 1. Global Context Header -->
<div class="dashboard-header">
    <?= view('dashboard/components/context-header', ['context' => $context]) ?>
</div>

<!-- 2. Today Overview Metrics -->
<div class="metrics-grid">
    <?= view('dashboard/components/metric-cards', ['metrics' => $metrics, 'role' => $role]) ?>
</div>

<!-- 3. Alerts Panel -->
<?php if (!empty($alerts)): ?>
<div class="alerts-panel">
    <?= view('dashboard/components/alerts', ['alerts' => $alerts]) ?>
</div>
<?php endif; ?>

<!-- 4. Quick Actions -->
<div class="quick-actions">
    <?= view('dashboard/components/quick-actions', ['role' => $role]) ?>
</div>

<!-- 5. Today's Schedule Snapshot -->
<div class="schedule-snapshot">
    <?= view('dashboard/components/schedule-snapshot', ['schedule' => $schedule, 'role' => $role]) ?>
</div>

<!-- 6. Upcoming Appointments -->
<div class="upcoming-appointments">
    <?= view('dashboard/components/upcoming-list', ['upcoming' => $upcoming]) ?>
</div>

<!-- 7. Booking Status (Owner only) -->
<?php if ($role === 'admin'): ?>
<div class="booking-status">
    <?= view('dashboard/components/booking-status', ['status' => $bookingStatus]) ?>
</div>
<?php endif; ?>

<!-- 8. Provider Availability -->
<div class="availability-snapshot">
    <?= view('dashboard/components/availability-snapshot', ['availability' => $availability]) ?>
</div>

<?= $this->endSection() ?>
```

#### Component Views

**1. context-header.php**
```php
<div class="flex justify-between items-center p-6 bg-white dark:bg-gray-800">
    <div>
        <h1><?= esc($context['business_name']) ?></h1>
        <p class="text-sm text-gray-500">
            <?= $context['current_date'] ?> • <?= $context['timezone'] ?>
        </p>
    </div>
    <div>
        <p class="text-right">
            <strong><?= esc($context['user_name']) ?></strong><br>
            <span class="badge badge-<?= $role ?>"><?= ucfirst($role) ?></span>
        </p>
    </div>
</div>
```

**2. metric-cards.php**
```php
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- Total Appointments -->
    <a href="<?= base_url('/appointments?filter=today') ?>" class="metric-card">
        <div class="metric-icon">📅</div>
        <div class="metric-content">
            <p class="metric-label">Today's Appointments</p>
            <p class="metric-value"><?= $metrics['total'] ?></p>
        </div>
    </a>
    
    <!-- Upcoming (next 4 hours) -->
    <a href="<?= base_url('/appointments?filter=upcoming') ?>" class="metric-card">
        <div class="metric-icon">⏰</div>
        <div class="metric-content">
            <p class="metric-label">Upcoming</p>
            <p class="metric-value"><?= $metrics['upcoming'] ?></p>
        </div>
    </a>
    
    <!-- Pending -->
    <a href="<?= base_url('/appointments?filter=pending') ?>" class="metric-card">
        <div class="metric-icon">⏳</div>
        <div class="metric-content">
            <p class="metric-label">Pending Confirmation</p>
            <p class="metric-value"><?= $metrics['pending'] ?></p>
        </div>
    </a>
    
    <!-- Cancelled/Rescheduled -->
    <a href="<?= base_url('/appointments?filter=cancelled') ?>" class="metric-card">
        <div class="metric-icon">❌</div>
        <div class="metric-content">
            <p class="metric-label">Cancelled Today</p>
            <p class="metric-value"><?= $metrics['cancelled'] ?></p>
        </div>
    </a>
</div>
```

**3. alerts.php**
```php
<div class="alerts-container">
    <h3>⚠️ Attention Required</h3>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert alert-<?= $alert['severity'] ?>">
            <div class="alert-content">
                <p><?= $alert['message'] ?></p>
            </div>
            <a href="<?= $alert['action_url'] ?>" class="alert-action">
                <?= $alert['action_label'] ?>
            </a>
        </div>
    <?php endforeach; ?>
</div>
```

**4. quick-actions.php**
```php
<div class="quick-actions-grid">
    <a href="<?= base_url('/appointments/create') ?>" class="action-button">
        <span class="icon">➕</span> Create Appointment
    </a>
    
    <?php if ($role === 'provider' || $role === 'staff'): ?>
        <a href="<?= base_url('/appointments#working-hours') ?>" class="action-button">
            <span class="icon">⏱️</span> Manage Hours
        </a>
    <?php endif; ?>
    
    <a href="<?= base_url('/public/booking') ?>" class="action-button">
        <span class="icon">📊</span> View Booking Page
    </a>
    
    <?php if ($role === 'admin'): ?>
        <a href="<?= base_url('/user-management') ?>" class="action-button">
            <span class="icon">👤</span> Add Provider
        </a>
        <a href="<?= base_url('/services') ?>" class="action-button">
            <span class="icon">⚙️</span> Manage Services
        </a>
    <?php endif; ?>
</div>
```

**5. schedule-snapshot.php**
```php
<div class="schedule-snapshot">
    <h3>Today's Schedule</h3>
    <div class="timeline">
        <!-- Grouped by provider -->
        <?php foreach ($schedule as $provider => $appointments): ?>
            <div class="provider-timeline">
                <h4><?= esc($provider) ?></h4>
                <div class="timeline-events">
                    <?php foreach ($appointments as $appt): ?>
                        <div class="timeline-event status-<?= $appt['status'] ?>">
                            <span class="time"><?= $appt['start_time'] ?></span>
                            <span class="customer"><?= esc($appt['customer_name']) ?></span>
                            <span class="status-badge"><?= ucfirst($appt['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
```

**6. upcoming-list.php**
```php
<div class="upcoming-appointments">
    <h3>Upcoming (Next 7 Days)</h3>
    <table class="upcoming-table">
        <tbody>
            <?php foreach (array_slice($upcoming, 0, 10) as $appt): ?>
                <tr class="appointment-row">
                    <td><?= date('M d', strtotime($appt['date'])) ?></td>
                    <td><?= $appt['time'] ?></td>
                    <td><?= esc($appt['customer']) ?></td>
                    <td><?= esc($appt['provider']) ?></td>
                    <td><span class="status-badge status-<?= $appt['status'] ?>"><?= ucfirst($appt['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="<?= base_url('/appointments') ?>" class="cta-button">View Full Calendar →</a>
</div>
```

**7. booking-status.php (Owner Only)**
```php
<div class="booking-status-card">
    <h3>Booking System Status</h3>
    <ul>
        <li>
            <span class="status-indicator <?= $status['booking_enabled'] ? 'enabled' : 'disabled' ?>"></span>
            Booking Page: <?= $status['booking_enabled'] ? 'Active' : 'Disabled' ?>
        </li>
        <li>
            <span class="status-indicator <?= $status['confirmation_enabled'] ? 'enabled' : 'disabled' ?>"></span>
            Manual Confirmation: <?= $status['confirmation_enabled'] ? 'Enabled' : 'Disabled' ?>
        </li>
        <li>
            <span class="status-indicator <?= $status['email_enabled'] ? 'enabled' : 'disabled' ?>"></span>
            Email Notifications: <?= $status['email_enabled'] ? 'Active' : 'Inactive' ?>
        </li>
        <li>
            <span class="status-indicator <?= $status['whatsapp_enabled'] ? 'enabled' : 'disabled' ?>"></span>
            WhatsApp Notifications: <?= $status['whatsapp_enabled'] ? 'Active' : 'Inactive' ?>
        </li>
    </ul>
    <a href="<?= base_url('/settings') ?>" class="edit-button">Configure</a>
</div>
```

**8. availability-snapshot.php**
```php
<div class="availability-snapshot">
    <h3>Provider Availability Today</h3>
    <div class="availability-grid">
        <?php foreach ($availability as $provider): ?>
            <div class="provider-card">
                <h4><?= esc($provider['name']) ?></h4>
                <div class="availability-status">
                    <span class="status-indicator status-<?= $provider['status'] ?>"></span>
                    <p><?= ucfirst($provider['status']) ?></p>
                </div>
                <?php if ($provider['next_slot']): ?>
                    <p class="next-slot">Next available: <?= $provider['next_slot'] ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
```

### 3.4 Phase 4: Database Queries (Week 1)

#### Optimized Query Methods in Models

**AppointmentModel.php**:

```php
/**
 * Get appointments for today (business hours only)
 */
public function getTodayAppointments(?int $providerId = null): array

/**
 * Get upcoming appointments (next 4 hours from now)
 */
public function getUpcomingAppointments(int $hours = 4, ?int $providerId = null): array

/**
 * Get appointments for time window (next 7 days)
 */
public function getUpcoming7Days(?int $providerId = null, int $limit = 10): array

/**
 * Get schedule grouped by provider for today
 */
public function getTodayScheduleByProvider(?int $providerId = null): array
```

**UserModel.php**:

```php
/**
 * Get provider availability status for today
 */
public function getTodayAvailability(?int $providerId = null): array

/**
 * Get next available slot for provider
 */
public function getNextAvailableSlot(int $providerId): ?string
```

### 3.5 Phase 5: Authorization Layer (Week 2)

#### Authorization Middleware

```php
namespace App\Filters;

class DashboardAuthFilter implements FilterInterface {
    public function before(RequestInterface $request, $arguments = null) {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }
        
        // Check dashboard access permission
        $role = current_user_role();
        if (!in_array($role, ['admin', 'provider', 'staff'])) {
            return redirect()->to('/auth/login')->with('error', 'Insufficient permissions');
        }
    }
}
```

#### Server-Side Data Filtering

Every dashboard query enforces:

```php
// For providers: only their own data
if ($role === 'provider') {
    $data = $data->where('provider_id', $providerId);
}

// For staff: only data they're permitted to see
if ($role === 'staff') {
    $permissions = get_staff_permissions($userId);
    $allowedProviderIds = $permissions['visible_providers'];
    $data = $data->whereIn('provider_id', $allowedProviderIds);
}

// For admin: unrestricted
// No additional filtering needed
```

---

## 4. Data Models

### 4.1 Dashboard Context

```php
$context = [
    'business_name' => string,
    'current_date' => string,      // Localized format
    'timezone' => string,
    'user_name' => string,
    'user_role' => string,
    'user_id' => int,
    'provider_id' => ?int,         // null for admin
];
```

### 4.2 Today's Metrics

```php
$metrics = [
    'total' => int,       // Total appointments today
    'upcoming' => int,    // Next 4 hours
    'pending' => int,     // Awaiting confirmation
    'cancelled' => int,   // Cancelled/rescheduled today
];
```

### 4.3 Alert Structure

```php
$alert = [
    'type' => string,           // 'confirmation_pending', 'no_hours', etc.
    'severity' => string,       // 'warning', 'error', 'info'
    'message' => string,        // Human-readable
    'action_label' => string,   // Button text
    'action_url' => string,     // Where to navigate
];
```

---

## 5. Performance Requirements

### 5.1 Query Optimization

- **Indexes Required**:
  - `xs_appointments(provider_id, appointment_date, appointment_time)`
  - `xs_appointments(status, created_at)`
  - `xs_users(role, is_active, provider_id)`

- **Query Limits**:
  - Metrics: Single cached query per role
  - Schedule: One query per provider group
  - Alerts: Single query with eager loading
  - Upcoming: Single query with LIMIT 10

### 5.2 Caching Strategy

```php
// Cache for 5 minutes
$metrics = cache()->remember("dashboard_metrics_{$providerId}", 300, function() {
    return $this->dashboardService->getTodayMetrics($providerId);
});

// Cache invalidation on appointment create/update/cancel
// Triggered via database events or middleware
```

### 5.3 Page Load Targets

- **First Contentful Paint (FCP)**: < 1s
- **Total Page Load**: < 2s
- **API Response (metrics refresh)**: < 500ms
- **No blocking API calls on initial load**

---

## 6. Mobile Responsive Design

### 6.1 Breakpoints

- **Mobile (< 640px)**: Single column, read-only
- **Tablet (640px - 1024px)**: Two columns, limited actions
- **Desktop (> 1024px)**: Full layout with all features

### 6.2 Mobile Constraints

- Schedule snapshot: Horizontal scroll or collapsed view
- Upcoming list: Compressed display with expand option
- Quick actions: Hamburger menu or simplified button set
- Alerts: Stacked vertical layout

---

## 7. Testing Strategy

### 7.1 Unit Tests

```php
// Test DashboardService
public function test_admin_sees_all_appointments()
public function test_provider_sees_only_own_appointments()
public function test_staff_respects_owner_permissions()
public function test_metrics_calculated_correctly()
public function test_alerts_generated_for_pending_confirmations()
```

### 7.2 Integration Tests

```php
public function test_dashboard_loads_under_1_second()
public function test_role_based_data_filtering()
public function test_no_unauthorized_data_exposure()
public function test_quick_actions_navigate_correctly()
```

---

## 8. Implementation Timeline

| Week | Phase | Deliverable |
|------|-------|-------------|
| 1 | Core Services | DashboardService, AuthorizationService updates |
| 1 | Controller | Dashboard.php refactor, API endpoints |
| 1 | Database | Query optimization, indexes |
| 2 | Views | dashboard/landing.php + components |
| 2 | Mobile | Responsive design, mobile testing |
| 2 | Testing | Unit & integration tests |
| 3 | Polish | Performance optimization, documentation |

---

## 9. Acceptance Criteria

- [ ] Dashboard renders for all roles (Admin, Provider, Staff)
- [ ] Provider experience mirrors Admin within scope
- [ ] Staff permissions never exceed Owner permissions
- [ ] No unauthorized data exposure
- [ ] All quick actions route correctly
- [ ] Page loads under 1 second on standard hosting
- [ ] No blocking API calls
- [ ] Mobile responsive
- [ ] All tests pass
- [ ] Documentation complete

---

## 10. Related Files

- **Controllers**: `app/Controllers/Dashboard.php`, `app/Controllers/Api/Dashboard.php`
- **Services**: `app/Services/DashboardService.php`, `app/Services/AuthorizationService.php`
- **Views**: `app/Views/dashboard/*`
- **Models**: Updates to `AppointmentModel.php`, `UserModel.php`
- **Tests**: `tests/unit/DashboardServiceTest.php`, `tests/integration/DashboardTest.php`
- **Database**: Migration for new indexes
- **Routes**: `app/Config/Routes.php` (dashboard routes)

---

**Version**: 1.0  
**Status**: Ready for Implementation  
**Next Step**: Begin Phase 1 (Week 1) - Core Services
