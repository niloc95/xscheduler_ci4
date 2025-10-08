# FullCalendar v6 Integration Guide

## Overview

WebSchedulr uses **FullCalendar v6.1.15** (`@fullcalendar/core` + `@fullcalendar/daygrid`) for its inline appointment calendar display. The implementation is fully integrated with Tailwind CSS and supports light/dark mode theming.

## Architecture

### Dependencies
- `@fullcalendar/core` v6.1.15 - Core calendar engine
- `@fullcalendar/daygrid` v6.1.15 - Month/day grid view plugin

### Key Files
| File | Purpose |
|------|---------|
| `resources/js/app.js` | Calendar initialization and event handlers |
| `resources/scss/app-consolidated.scss` | Tailwind-based styling for calendar elements |
| `app/Views/appointments/index.php` | Calendar container and custom toolbar markup |

## Implementation Details

### 1. Calendar Container

The calendar is rendered in a dedicated container with a data attribute for initial date configuration:

```html
<div
    id="appointments-inline-calendar"
    class="w-full mb-6"
    data-initial-date="<?= esc($selectedDate ?? date('Y-m-d')) ?>"
></div>
```

### 2. Custom Toolbar Controls

Instead of FullCalendar's default toolbar, we use custom Tailwind-styled buttons:

```html
<div class="mt-6 flex flex-wrap items-center justify-center gap-3" data-calendar-toolbar>
    <div class="flex items-center gap-2">
        <button type="button" data-calendar-action="prev"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:bg-gray-100 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
            <span class="material-symbols-outlined">chevron_left</span>
        </button>
        <h3 id="appointments-inline-calendar-title"
            class="min-w-[10rem] pt-4 text-center text-lg font-semibold text-gray-900 dark:text-gray-100">
            <!-- Auto-populated via JS -->
        </h3>
        <button type="button" data-calendar-action="next"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm transition-colors hover:bg-gray-100 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
            <span class="material-symbols-outlined">chevron_right</span>
        </button>
    </div>
</div>
```

**Unified "Today" Button:**
The "Today" filter button in the dashboard header doubles as a calendar control:

```html
<button type="button" data-calendar-action="today" 
    class="px-4 py-2 rounded-lg font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
    Today
</button>
```

### 3. JavaScript Initialization

**Calendar Setup:**

```javascript
const calendar = new Calendar(calendarEl, {
    plugins: [dayGridPlugin],
    initialView: 'dayGridMonth',
    height: 'auto',
    nowIndicator: true,
    selectable: false,
    dayMaxEvents: true,
    headerToolbar: false, // Custom toolbar used instead
    initialDate: initialDate instanceof Date && !Number.isNaN(initialDate.valueOf()) ? initialDate : undefined,
    dayCellDidMount: (arg) => {
        // Custom Tailwind styling for day cells
        const dayNumberEl = arg.el.querySelector('.fc-daygrid-day-number');
        if (!dayNumberEl) return;

        // Base styling
        dayNumberEl.classList.add('font-medium', 'text-gray-700', 'dark:text-gray-200');

        // Today highlight
        if (arg.date.toDateString() === today.toDateString()) {
            dayNumberEl.classList.add('bg-blue-600', 'text-white', 'dark:bg-blue-500');
        }
    },
});
```

**Event Delegation for Controls:**

```javascript
const handleCalendarAction = (event) => {
    const actionTarget = event.target.closest('[data-calendar-action]');
    if (!actionTarget) return;

    const action = actionTarget.getAttribute('data-calendar-action');
    
    if (action === 'prev') {
        event.preventDefault();
        calendar.prev();
    } else if (action === 'next') {
        event.preventDefault();
        calendar.next();
    } else if (action === 'today') {
        event.preventDefault();
        calendar.today();
    }
};

document.addEventListener('click', handleCalendarAction);
```

**Title Synchronization:**

```javascript
const updateCalendarTitle = () => {
    if (titleEl) {
        titleEl.textContent = calendar.view.title;
    }
};

calendar.on('datesSet', updateCalendarTitle);
calendar.render();
updateCalendarTitle();
```

## Styling & Theming

### Tailwind Integration

All calendar elements use Tailwind utility classes for consistency:

| Element | Light Mode | Dark Mode |
|---------|-----------|-----------|
| Container | `bg-white border-gray-200` | `bg-gray-800 border-gray-700` |
| Header cells | `bg-gray-50 text-gray-700` | `bg-gray-900 text-gray-200` |
| Day cells | `bg-white border-gray-200` | `bg-gray-800 border-gray-700` |
| Day numbers | `text-gray-700` | `text-gray-200` |
| Today highlight | `bg-blue-600 text-white` | `bg-blue-500 text-white` |
| Hover state | `bg-blue-50` | `bg-blue-900/20` |

### SCSS Overrides

Located in `resources/scss/app-consolidated.scss`:

```scss
#appointments-inline-calendar {
  .fc {
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
  }
  
  .fc-daygrid-day-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    font-weight: 600;
    border-radius: 9999px;
    transition: all 0.2s ease;
  }
}

html.dark #appointments-inline-calendar {
  .fc {
    background-color: #1f2937;
    border-color: #374151;
  }
}
```

## Event Integration (Future Enhancement)

To add appointment events to the calendar:

### 1. Create Event Feed Endpoint

```php
// app/Controllers/Appointments.php
public function feed()
{
    $events = $this->appointmentModel
        ->select('id, title, start_date, end_date, status')
        ->findAll();

    $formatted = array_map(function($event) {
        return [
            'id' => $event['id'],
            'title' => $event['title'],
            'start' => $event['start_date'],
            'end' => $event['end_date'],
            'backgroundColor' => $this->getStatusColor($event['status']),
            'borderColor' => $this->getStatusColor($event['status']),
        ];
    }, $events);

    return $this->response->setJSON($formatted);
}
```

### 2. Update Calendar Config

```javascript
const calendar = new Calendar(calendarEl, {
    // ...existing config
    events: '/appointments/feed',
    eventClick: (info) => {
        window.location.href = `/appointments/view/${info.event.id}`;
    },
});
```

### 3. Add Event Color Mapping

```javascript
const getEventClasses = (status) => {
    const colors = {
        'confirmed': 'bg-green-500 border-green-600',
        'pending': 'bg-amber-500 border-amber-600',
        'completed': 'bg-blue-500 border-blue-600',
        'cancelled': 'bg-red-500 border-red-600',
    };
    return colors[status] || 'bg-gray-500 border-gray-600';
};
```

## Responsive Design

### Breakpoint Behavior

| Screen Size | Layout |
|-------------|--------|
| `< 640px` (sm) | Stats and filters stack vertically, calendar full width |
| `640px - 1024px` (md-lg) | Stats left, filters right, calendar full width below |
| `≥ 1024px` (xl+) | Stats left, filters/actions right column, calendar centered below |

### Mobile Optimization

- Touch-friendly day cells (min 2.25rem size)
- Simplified hover states on mobile
- Full-width calendar container
- Stacked navigation controls on small screens

## Maintenance & Troubleshooting

### Common Issues

**Calendar not rendering:**
- Verify `#appointments-inline-calendar` element exists
- Check browser console for JS errors
- Ensure FullCalendar dependencies are installed (`npm list @fullcalendar/core`)

**Dark mode not working:**
- Confirm `html.dark` class is toggled correctly
- Check that SCSS dark mode selectors are compiled
- Rebuild assets: `npm run build`

**Title not updating:**
- Verify `#appointments-inline-calendar-title` element exists
- Check `datesSet` event listener is attached
- Ensure `updateCalendarTitle()` is called after `render()`

### Debugging Tips

```javascript
// Log calendar state
console.log('Calendar view:', calendar.view.title);
console.log('Current date:', calendar.getDate());

// Force refresh
calendar.refetchEvents();
calendar.render();
```

## Future Enhancements

- [ ] Add week/day view toggles
- [ ] Implement drag-and-drop appointment rescheduling
- [ ] Add appointment creation via date click
- [ ] Integrate real-time updates (WebSockets/Pusher)
- [ ] Add appointment filtering by provider/service
- [ ] Implement recurring appointment visualization

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-10-08 | Initial FullCalendar v6 integration with Tailwind theming |
| 1.1.0 | 2025-10-08 | Added custom toolbar controls and unified "Today" button |
| 1.2.0 | 2025-10-08 | Enhanced dark mode support and responsive layout |

---

**Last Updated:** October 8, 2025  
**Maintained By:** WebSchedulr Development Team
