# 👥 Provider & Staff Assignment System Guide

**Last Updated:** October 24, 2025  
**Status:** ✅ COMPLETE & TESTED  
**Covers:** Provider setup, staff assignment, color coding, service binding

---

## 📋 Table of Contents

1. [System Overview](#system-overview)
2. [Provider Color System](#provider-color-system)
3. [Staff Assignment Architecture](#staff-assignment-architecture)
4. [Service-Provider Binding](#service-provider-binding)
5. [Provider-First UX](#provider-first-ux)
6. [Database Schema](#database-schema)
7. [API Endpoints](#api-endpoints)
8. [Configuration](#configuration)
9. [Troubleshooting](#troubleshooting)

---

## System Overview

The Provider & Staff system manages:
- **Provider Setup** - Business location/department management
- **Staff Assignment** - Individual staff member allocation to providers
- **Color Coding** - Visual differentiation for calendar display
- **Service Binding** - Linking services to specific providers
- **UX Ordering** - Provider-first selection flow for appointments

### Key Components

```
Provider (Business Location)
  ├── Staff Members (Assigned to Provider)
  ├── Services (Offered by Provider)
  ├── Business Hours (Operating hours)
  ├── Color Code (Visual identifier)
  └── Settings (Specific to this provider)
```

---

## Provider Color System

### Overview
Automatic color assignment system for visual differentiation in calendar views. Each provider is assigned a unique color from a predefined 12-color palette.

### Color Palette

**12 Distinct Material Design Colors:**

| # | Color | Hex | Name | Use Case |
|---|-------|-----|------|----------|
| 1 | 🔵 | `#3B82F6` | Blue | Primary provider |
| 2 | 🟢 | `#10B981` | Green | Secondary provider |
| 3 | 🟠 | `#F59E0B` | Amber | Tertiary provider |
| 4 | 🔴 | `#EF4444` | Red | Urgent/Priority |
| 5 | 🟣 | `#8B5CF6` | Purple | Special service |
| 6 | 💗 | `#EC4899` | Pink | VIP provider |
| 7 | 🔷 | `#06B6D4` | Cyan | Wellness |
| 8 | 🟧 | `#F97316` | Orange | Alternative |
| 9 | 🟩 | `#84CC16` | Lime | New provider |
| 10 | 💙 | `#6366F1` | Indigo | Corporate |
| 11 | 🩵 | `#14B8A6` | Teal | Nature/Health |
| 12 | 🌹 | `#F43F5E` | Rose | Special |

### Database Implementation

**Table:** `xs_users`  
**Column:** `color VARCHAR(10) NULL`

```sql
ALTER TABLE xs_users ADD COLUMN color VARCHAR(10) NULL AFTER profile_image;
```

**Schema:**
```php
'color' => [
    'type' => 'VARCHAR',
    'constraint' => 10,
    'null' => true,
    'comment' => 'Provider color for calendar display (hex code)',
]
```

### Color Assignment Algorithm

```php
class ColorAssignmentService {
    
    protected $colors = [
        '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
        '#8B5CF6', '#EC4899', '#06B6D4', '#F97316',
        '#84CC16', '#6366F1', '#14B8A6', '#F43F5E'
    ];
    
    public function assignColorToProvider($provider) {
        // Find next available color in sequence
        $existingColors = UserModel::pluck('color')->toArray();
        
        foreach ($this->colors as $color) {
            if (!in_array($color, $existingColors)) {
                return $color;  // Assign first available
            }
        }
        
        // If all used, assign randomly
        return $this->colors[array_rand($this->colors)];
    }
    
    public function reassignColors() {
        // Optimize color distribution if needed
        $providers = UserModel::where('role', 'provider')->get();
        
        foreach ($providers as $i => $provider) {
            $provider->color = $this->colors[$i % count($this->colors)];
            $provider->save();
        }
    }
}
```

### Usage in Calendar

**Event Display:**
```javascript
// In appointments-calendar.js
eventContent: function(info) {
    return {
        html: `
            <div class="event-content">
                <span style="background-color: ${info.event.extendedProps.provider_color};" 
                      class="color-indicator"></span>
                <span>${info.event.title}</span>
            </div>
        `
    };
}
```

**Styling:**
```css
.event-content {
    display: flex;
    align-items: center;
    gap: 8px;
}

.color-indicator {
    width: 4px;
    height: 100%;
    border-radius: 2px;
}

/* Provider-specific styling */
.event { border-left: 4px solid var(--provider-color); }
```

---

## Staff Assignment Architecture

### System Design

**Unified Staff Assignment System:**
- One staff member can be assigned to multiple providers
- Each staff-provider relationship is tracked separately
- Assignments can be enabled/disabled without deletion
- History is maintained for audit purposes

### Database Schema

**Table 1: xs_staff**
```sql
CREATE TABLE xs_staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    specialization VARCHAR(255),
    qualifications TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Table 2: xs_staff_assignment**
```sql
CREATE TABLE xs_staff_assignment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    provider_id INT NOT NULL,
    assignment_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_staff_provider (staff_id, provider_id),
    FOREIGN KEY (staff_id) REFERENCES xs_staff(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES xs_providers(id) ON DELETE CASCADE
);
```

**Table 3: xs_staff_hours** (Optional - per-provider hours)
```sql
CREATE TABLE xs_staff_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    day_of_week INT (0-6),
    start_time TIME,
    end_time TIME,
    is_available BOOLEAN,
    created_at TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES xs_staff_assignment(id)
);
```

### API Endpoints

**List Staff for Provider:**
```
GET /api/v1/providers/{provider_id}/staff
Response: [
    {
        "id": 1,
        "name": "John Smith",
        "email": "john@example.com",
        "specialization": "Haircut",
        "is_active": true
    }
]
```

**Assign Staff to Provider:**
```
POST /api/v1/providers/{provider_id}/staff
{
    "staff_id": 1,
    "assignment_date": "2025-10-24",
    "notes": "Part-time assignment"
}
```

**Remove Staff Assignment:**
```
DELETE /api/v1/providers/{provider_id}/staff/{staff_id}
```

---

## Service-Provider Binding

### Overview
Services are linked to specific providers, enabling:
- Provider-specific service availability
- Service-specific pricing (optional)
- Staff specialization matching
- Business hours by service

### Database Implementation

**Table: xs_service_provider** (Join Table)
```sql
CREATE TABLE xs_service_provider (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    provider_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    custom_price DECIMAL(10,2) NULL,
    duration_minutes INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_service_provider (service_id, provider_id),
    FOREIGN KEY (service_id) REFERENCES xs_services(id),
    FOREIGN KEY (provider_id) REFERENCES xs_providers(id)
);
```

### Query Examples

**Get Services for Provider:**
```sql
SELECT s.* FROM xs_services s
JOIN xs_service_provider sp ON s.id = sp.service_id
WHERE sp.provider_id = ? AND sp.is_active = TRUE
ORDER BY s.name;
```

**API Endpoint:**
```
GET /api/v1/services?provider_id=1
Response: [
    {
        "id": 1,
        "name": "Haircut",
        "duration_minutes": 30,
        "custom_price": 25.00
    },
    {
        "id": 2,
        "name": "Color",
        "duration_minutes": 90,
        "custom_price": 75.00
    }
]
```

### PHP Service Class

```php
class ServiceProviderBindingService {
    
    protected $db;
    
    public function getServicesForProvider($provider_id) {
        return $this->db->table('xs_service_provider')
            ->join('xs_services', 'xs_services.id', '=', 'xs_service_provider.service_id')
            ->where('xs_service_provider.provider_id', $provider_id)
            ->where('xs_service_provider.is_active', true)
            ->select('xs_services.*', 'xs_service_provider.custom_price', 'xs_service_provider.duration_minutes')
            ->orderBy('xs_services.name')
            ->get();
    }
    
    public function bindServiceToProvider($service_id, $provider_id, $duration = null, $price = null) {
        return $this->db->table('xs_service_provider')->insert([
            'service_id' => $service_id,
            'provider_id' => $provider_id,
            'duration_minutes' => $duration,
            'custom_price' => $price,
            'is_active' => true,
            'created_at' => now(),
        ]);
    }
    
    public function removeBinding($service_id, $provider_id) {
        return $this->db->table('xs_service_provider')
            ->where('service_id', $service_id)
            ->where('provider_id', $provider_id)
            ->update(['is_active' => false]);
    }
}
```

### Fixes Applied

**Issue:** Services not loading from provider dropdown  
**Root Cause:** Missing xs_ table prefix in queries  
**Fix:** Updated all queries to use `xs_service_provider` table  
**Commit:** 9cd4757

---

## Provider-First UX

### Overview
Optimized dropdown ordering and interaction flow to prioritize provider selection, improving user experience for appointment booking.

### Implementation

**Dropdown Order:**
1. Provider (Primary) - User selects provider first
2. Service (Secondary) - Populated only after provider selected
3. Staff/Resource (Optional) - If applicable

**State Management:**

```javascript
// Before: All dropdowns enabled
<select id="service">
    <option value="">Select Service</option>
    <option value="1">Haircut</option>
</select>

// After: Service disabled until provider selected
<select id="service" disabled="disabled">
    <option value="">Select Service First</option>
</select>
```

**Event Handlers:**

```javascript
document.getElementById('provider').addEventListener('change', function() {
    const providerId = this.value;
    
    if (!providerId) {
        // Disable service dropdown
        document.getElementById('service').disabled = true;
        document.getElementById('service').innerHTML = '<option>Select Service First</option>';
        return;
    }
    
    // Load services for selected provider
    fetch(`/api/v1/services?provider_id=${providerId}`)
        .then(r => r.json())
        .then(services => {
            const select = document.getElementById('service');
            select.innerHTML = '<option value="">Select Service</option>';
            services.data.forEach(service => {
                select.innerHTML += `<option value="${service.id}">${service.name}</option>`;
            });
            select.disabled = false;
        });
});
```

### Benefits
- ✅ Clearer user mental model
- ✅ Reduced dropdown clutter
- ✅ Better form validation
- ✅ Improved mobile UX
- ✅ Faster decision making

---

## Database Schema

### Complete Provider System

```
xs_users (Providers)
├── id (Primary Key)
├── name
├── email
├── phone
├── profile_image
├── color (NEW - for calendar)
└── role = 'provider'

xs_providers (Business Unit)
├── id
├── name
├── address
├── phone
├── email
├── is_active

xs_staff (Staff Members)
├── id
├── name
├── email
├── phone
├── specialization
├── is_active

xs_staff_assignment (Assignment Link)
├── id
├── staff_id → xs_staff
├── provider_id → xs_providers
├── assignment_date
├── is_active

xs_services (Service Types)
├── id
├── name
├── duration_minutes
├── description
├── is_active

xs_service_provider (Binding)
├── id
├── service_id → xs_services
├── provider_id → xs_providers
├── custom_price
├── duration_minutes
├── is_active

xs_appointments (Bookings)
├── id
├── customer_id
├── provider_id
├── service_id
├── staff_id (optional)
├── appointment_date
├── appointment_time
├── end_time
├── status
└── created_at
```

---

## API Endpoints

### Providers
```
GET /api/v1/providers              - List all providers
GET /api/v1/providers/{id}         - Get provider details
GET /api/v1/providers/{id}/staff   - List staff for provider
GET /api/v1/providers/{id}/services - List services for provider
```

### Staff
```
GET /api/v1/staff                  - List all staff
POST /api/v1/staff                 - Create staff member
GET /api/v1/staff/{id}             - Get staff details
PUT /api/v1/staff/{id}             - Update staff
DELETE /api/v1/staff/{id}          - Deactivate staff
```

### Services
```
GET /api/v1/services               - List services
POST /api/v1/services              - Create service
GET /api/v1/services?provider_id=1 - Services by provider
POST /api/v1/services/bind         - Bind service to provider
```

---

## Configuration

### Settings Table
```sql
INSERT INTO xs_settings VALUES
('provider_color_auto_assign', 'true', 'boolean'),
('service_duration_override', 'false', 'boolean'),
('staff_assignment_required', 'false', 'boolean'),
('provider_first_selection', 'true', 'boolean');
```

### Environment Variables
```env
# Provider System Configuration
PROVIDER_ENABLE_COLOR_CODING=true
PROVIDER_ENABLE_STAFF_ASSIGNMENT=true
SERVICE_PROVIDER_CUSTOM_PRICING=false
```

---

## Troubleshooting

### Issue: Services not loading for provider
**Cause:** xs_service_provider table not populated  
**Solution:**
```sql
INSERT INTO xs_service_provider (service_id, provider_id, is_active)
SELECT id, 1, TRUE FROM xs_services;
```

### Issue: Staff not showing for provider
**Cause:** No records in xs_staff_assignment  
**Solution:** Use admin panel to assign staff to providers

### Issue: Color not displaying in calendar
**Cause:** Color field NULL in xs_users  
**Solution:** Run color assignment script

---

## Related Documentation

- [Calendar Implementation Guide](./calendar_implementation.md)
- [API Endpoints Reference](../development/api_endpoints_reference.md)
- [Provider Color System Details](../archive/provider_color_system.md)
- [Staff Assignment Unified System](../archive/staff_assignment_implementation.md)

---

**Last Updated:** October 24, 2025  
**Status:** Production Ready ✅

