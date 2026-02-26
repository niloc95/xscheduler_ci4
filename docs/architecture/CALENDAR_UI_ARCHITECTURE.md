# Calendar UI Architecture

## Overview

This document outlines the complete calendar UI architecture for WebSchedulr, including Month, Week, and Day views based on the prototype designs.

## Design System

### Brand Colors

| Context | Light Mode | Dark Mode |
|---------|------------|-----------|
| Background | `#FDF7E3` (warm cream) | `#1F2937` |
| Surface | `#FFFFFF` | `#1F2937` / `slate-900` |
| Today Highlight | `#003049` (ocean blue) | Primary container |
| Weekend Shading | `#F7EFE0` | `slate-800/50` |
| Separator | `gray-200` | `slate-700/800` |

### Provider Colors

Provider colors are stored in `xs_users.color` and used consistently across all views:
- Blue: `#3B82F6`
- Emerald: `#10B981`
- Amber: `#F59E0B`
- Rose: `#F43F5E`
- Purple: `#8B5CF6`

### Status Colors (via appointment-colors.js)

| Status | Light Mode | Dark Mode |
|--------|------------|-----------|
| Confirmed | Green | Green with transparency |
| Pending | Amber | Amber with transparency |
| Cancelled | Red | Red with transparency |
| Rescheduled | Blue | Blue with transparency |

---

## View Components

### 1. Month View (`scheduler-month-view.js`)

**Layout:**
- 7×5 or 7×6 CSS Grid
- Day cells with provider-colored appointment chips
- "+X more" overflow indicator with modal

**Features:**
- ✅ Clean, high-density grid
- ✅ Day number (top-right)
- ✅ Up to 3 appointment chips + "+X more"
- ✅ Weekend shading
- ✅ Today highlight
- ✅ Tap-to-create (future enhancement)
- ✅ Provider color-coded appointments

**Cell Structure:**
```html
<div class="day-cell min-h-[130px] rounded-2xl border bg-white/warm p-3">
  <div class="day-header flex justify-between">
    <span class="weekday">Wed</span>
    <span class="day-number">24</span>
  </div>
  <div class="appointments space-y-1">
    <div class="appointment-chip provider-blue">Dr. Smith • 10:30a</div>
    <div class="appointment-chip provider-emerald">Consult • 1:00p</div>
    <button class="more-button">+3 more</button>
  </div>
</div>
```

### 2. Week View (`scheduler-week-view.js`)

**Layout:**
- Provider sidebar (left 240px)
- Time column (80px)
- 7-column day grid
- Horizontal time slots with positioned appointment blocks

**Features:**
- ✅ Provider list with show/hide toggles
- ✅ Time slots based on business hours
- ✅ Rounded appointment cards with provider color stripe
- ✅ Status icons
- ✅ "Now" line indicator
- ✅ Modes: Week per provider / Combined grid

**Structure:**
```html
<div class="week-view flex min-h-screen">
  <aside class="provider-sidebar w-60">...</aside>
  <main class="flex-1 grid grid-cols-[80px_1fr]">
    <div class="time-column">...</div>
    <div class="day-grid grid-cols-7">
      <!-- Day columns with appointments -->
    </div>
  </main>
</div>
```

### 3. Day View (`scheduler-day-view.js`)

**Layout:**
- Provider sidebar (left)
- Timeline column (80px)
- Multi-provider columns (scrollable)

**Features:**
- ✅ Vertical timeline
- ✅ Sticky provider headers
- ✅ Side-by-side provider columns
- ✅ "Now" line across all providers
- ✅ Appointment cards with icons, notes, duration
- ✅ Add Appointment button at each hour slot

**Structure:**
```html
<div class="day-view flex min-h-screen">
  <aside class="provider-list w-64">...</aside>
  <main class="flex-1 grid grid-cols-[80px_1fr]">
    <div class="timeline">...</div>
    <div class="provider-columns grid-cols-3">
      <!-- Provider columns with appointments -->
    </div>
  </main>
</div>
```

---

## Component Architecture

```
resources/js/modules/scheduler/
├── scheduler-core.js           # Main orchestrator
├── scheduler-month-view.js     # Month view renderer
├── scheduler-week-view.js      # Week view renderer
├── scheduler-day-view.js       # Day view renderer
├── scheduler-drag-drop.js      # Drag-and-drop manager
├── scheduler-ui.js             # UI helpers (DOM construction)
├── settings-manager.js         # User settings handler
├── appointment-details-modal.js # Appointment details modal
├── appointment-colors.js       # Color utilities
├── slot-engine.js              # Slot rendering engine (client-side until API rebuild)
├── constants.js                # Shared constants
├── date-nav-label.js           # Date navigation label helpers
├── logger.js                   # Logging utility
└── stats/                      # Stats barrel module
```

---

## CSS Architecture

### Tailwind Extensions (tailwind.config.js)

```javascript
module.exports = {
  theme: {
    extend: {
      colors: {
        'brand-cream': '#FDF7E3',
        'brand-warm': '#F7EFE0',
        'brand-ocean': '#003049',
      }
    }
  }
}
```

### Custom CSS (scheduler.css)

```css
/* Month View */
.month-grid { @apply grid grid-cols-7 gap-2; }
.day-cell { @apply min-h-[130px] rounded-2xl p-3; }
.day-cell.today { @apply border-2 border-brand-ocean; }
.day-cell.weekend { @apply bg-brand-warm dark:bg-slate-800/50; }

/* Week View */
.week-view .time-slot { @apply h-16 border-b border-gray-200; }
.week-view .appointment-block { @apply rounded-2xl p-3 shadow-sm; }

/* Day View */
.day-view .provider-column { @apply rounded-3xl border border-dashed; }
.day-view .now-line { @apply bg-rose-500 h-0.5; }
```

---

## State Management

### SchedulerCore State

```javascript
{
  currentDate: DateTime,      // Current focused date
  currentView: 'month',       // 'month' | 'week' | 'day'
  appointments: [],           // Loaded appointments
  providers: [],              // Provider list
  visibleProviders: Set,      // Toggled provider IDs
  statusFilter: null,         // Status filter
  calendarConfig: {
    slotMinTime: '08:00',
    slotMaxTime: '17:00',
    firstDayOfWeek: 0
  }
}
```

### Data Flow

```
┌─────────────────────────────────────────────────────────┐
│                    Backend APIs                          │
│  /api/appointments  /api/providers  /api/v1/settings    │
└─────────────────────┬───────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────┐
│                  SchedulerCore                           │
│  - loadAppointments()                                    │
│  - loadProviders()                                       │
│  - getFilteredAppointments()                            │
└─────────────────────┬───────────────────────────────────┘
                      │
        ┌─────────────┼─────────────┐
        ▼             ▼             ▼
┌───────────┐  ┌───────────┐  ┌───────────┐
│ MonthView │  │ WeekView  │  │ DayView   │
│ .render() │  │ .render() │  │ .render() │
└───────────┘  └───────────┘  └───────────┘
```

---

## Performance Optimizations

### P0-3: Future-Only Loading

```javascript
// scheduler-core.js
const params = new URLSearchParams({ start, end });
if (this.options.futureOnly !== false) {
  params.append('futureOnly', '1');
  params.append('lookAheadDays', '90');
}
```

### Template Cloning

```javascript
// Use <template> for repeated structures
const template = document.getElementById('day-cell-template');
const cell = template.content.cloneNode(true);
```

### Virtualized Scrolling (Future)

For 6+ months of data, implement virtual scrolling to render only visible cells.

---

## Interaction Patterns

### Quick Actions (Hover/Right-click)

- **Confirm** - Mark appointment as confirmed
- **Cancel** - Cancel with reason
- **Reschedule** - Open reschedule modal
- **Mark Arrived** - Check-in customer

### Drag-and-Drop

- Week/Day views support appointment dragging
- Visual feedback with ghost element
- API call to update appointment time

---

## Accessibility

- ARIA labels on all interactive elements
- Keyboard navigation for time slot selection
- Focus management in modals
- High contrast mode support

### 🔄 Pending Improvements
1. Implement template cloning for performance
2. Add heatmap mode
3. Add provider load balancing visualization
4. Implement density compression
