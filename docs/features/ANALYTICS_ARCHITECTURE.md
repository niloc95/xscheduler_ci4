# Analytics Architecture

**Controller:** `app/Controllers/Analytics.php`  
**Routes:** `GET /analytics` (admin + provider access)

---

## Role-Scoping Contract

| Role | `$providerId` | Behaviour |
|---|---|---|
| `admin` (no filter) | `null` | All queries return global totals across all providers |
| `admin` (with `?provider_id=X`) | `X` (int) | All queries scoped to provider X |
| `provider` | `current_user_id()` | All queries locked to own data |

**Canonical provider ID accessor:** `current_user_id()` (reads `session()->get('user_id')`).  
The controller sets `$providerId` at line ~101:
```php
$providerId = $currentRole === 'provider'
    ? (int) session()->get('user_id')
    : $this->sanitizeOptionalInt($this->request->getGet('provider_id'));
```

---

## Stat Card Data Map

| Card | PHP key | Source method |
|---|---|---|
| Total Revenue | `$overview['total_revenue']` | `AppointmentModel::getRevenueForDateRange()` |
| Revenue change % | `$overview['revenue_change']` | Current vs previous period |
| Total Appointments | `$overview['total_appointments']` | `AppointmentModel::getStatsForDateRange()` |
| Appointments change % | `$overview['appointments_change']` | Current vs previous period |
| New Customers | `$overview['new_customers']` | `CustomerModel::getNewCustomers($period, $providerId)` |
| Customers change % | `$overview['customers_change']` | `CustomerModel::getGrowthTrend($providerId)` |
| Avg Booking Value | `$overview['avg_booking_value']` | Derived: `total_revenue ÷ total_appointments` |
| Booking Value change % | `$overview['booking_value_change']` | Current vs previous period avg |
| Customer Retention | `$overview['customer_retention']` | `CustomerModel::getRetentionRate($providerId)` |
| Retention change % | `$overview['retention_change']` | **Always 0** — requires time-bounded retention snapshots |
| Staff Utilization | `$overview['staff_utilization']` | Derived: `appointments ÷ estimated_capacity` |
| Utilization change % | `$overview['utilization_change']` | Current vs previous period utilization |

---

## Provider-Scoping Rules for Each Model

### `CustomerModel` — all methods accept `?int $providerId = null`

**Business rule:** A provider's customer is any `xs_customers` record that has at least one appointment with that provider in `xs_appointments`.

| Method | Scoping when `$providerId` set |
|---|---|
| `getNewCustomers($period, $providerId)` | `WHERE c.id IN (SELECT DISTINCT customer_id FROM xs_appointments WHERE provider_id = X)` |
| `getGrowthTrend($providerId)` | Passes `$providerId` through to `getNewCustomers()` |
| `getNewVsReturning($providerId)` | `INNER JOIN xs_appointments … WHERE a.provider_id = X` on inner subquery |
| `getRetentionRate($providerId)` | Passes through to `getNewVsReturning()` |
| `getLoyaltySegments($providerId)` | `INNER JOIN xs_appointments … WHERE a.provider_id = X` on inner subquery |

When `$providerId = null`, all methods return global (system-wide) data.

### `ServiceModel::getPerformanceMetrics(?int $providerId = null)`

When `$providerId` is set, completion_rate and repeat_booking_rate are scoped to `WHERE provider_id = X` in the appointments subqueries. `avg_duration` comes from the service catalogue and is not provider-scoped.

### `BookingMetricsService` — already correctly scoped

`getByService()`, `getPopularServices()`, `getStatsByStatus()`, `getTotalBookings()` all accept a `$providerScope` parameter and use `BookingMetricsService::providerScopeSqlClause()`.

### `AppointmentModel` — already correctly scoped

All revenue and stats methods (`getRevenueForDateRange`, `getDailyRevenue`, `getMonthlyRevenue`, `getStatsForDateRange`, `getByTimeSlot`, `getAverageBookingValue`) accept `?int $providerId = null` and append `AND a.provider_id = X` when set.

---

## Revenue Comparisons

| Field | Calculation |
|---|---|
| `vs_last_month` | Current window revenue vs previous window |
| `vs_last_quarter` | Last 3 months vs prior 3 months |
| `vs_last_year` | Last 12 months vs prior 12 months |
| `forecast_next_month` | Trailing 3-month average |

---

## Known Limitations

- `retention_change` is always 0 — requires storing historical retention snapshots.
- `customer_satisfaction` is always 0 — no rating system exists.
- `avg_duration` in service performance is not provider-scoped (global service catalogue).
- Staff utilization formula is a simple approximation (appointments ÷ estimated capacity); does not account for variable service durations or actual provider availability.

---

## JavaScript Data Flow

Charts read base64-encoded JSON from `data-*` attributes on `[data-analytics-tabs]`:

| Attribute | PHP source |
|---|---|
| `data-revenue-payload` | `$revenue` |
| `data-detailed-revenue-payload` | `$revenue_data` |
| `data-comparisons-payload` | `$comparisons` |
| `data-customer-payload` | `$customers` |
| `data-appointment-payload` | `$appointments` |
| `data-provider-busy-hours-payload` | `$revenue_data['busy_hours_distribution']` |

All data is PHP-rendered on page load. No AJAX calls are made by the analytics JS module.

---

## Related Files

```
app/Controllers/Analytics.php
app/Models/AppointmentModel.php          ← revenue/stats methods
app/Models/CustomerModel.php             ← customer metric methods
app/Models/ServiceModel.php              ← getPerformanceMetrics()
app/Services/BookingMetricsService.php   ← service/booking count methods
app/Views/analytics/index.php
resources/js/modules/analytics/analytics-charts.js
```
