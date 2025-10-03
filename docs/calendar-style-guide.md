# Calendar Visual Style Guide

## Color Palette Reference

### Status Colors (Priority 1)

```css
Confirmed (Emerald)
Light: bg-emerald-100 text-emerald-700 border-emerald-200
Dark:  bg-emerald-900/40 text-emerald-200 border-emerald-700
Use:   Confirmed appointments

Cancelled (Rose)
Light: bg-rose-100 text-rose-700 border-rose-200
Dark:  bg-rose-900/40 text-rose-200 border-rose-700
Use:   Cancelled appointments

Booked/Pending (Amber)
Light: bg-amber-100 text-amber-700 border-amber-200
Dark:  bg-amber-900/40 text-amber-200 border-amber-200
Use:   Pending or newly booked appointments

Completed (Slate)
Light: bg-slate-200 text-slate-700 border-slate-300
Dark:  bg-slate-800/60 text-slate-200 border-slate-700
Use:   Completed appointments
```

### Service Colors (Priority 2)

```css
Color 0: Cyan      - bg-cyan-100 text-cyan-800 border-cyan-200
Color 1: Fuchsia   - bg-fuchsia-100 text-fuchsia-800 border-fuchsia-200
Color 2: Lime      - bg-lime-100 text-lime-800 border-lime-200
Color 3: Indigo    - bg-indigo-100 text-indigo-800 border-indigo-200
Color 4: Orange    - bg-orange-100 text-orange-800 border-orange-200
Color 5: Pink      - bg-pink-100 text-pink-800 border-pink-200
Color 6: Teal      - bg-teal-100 text-teal-800 border-teal-200
Color 7: Violet    - bg-violet-100 text-violet-800 border-violet-200
```

### Provider Colors (Priority 3)

Same palette as services, but with +3 offset for variety.

---

## Typography Scale

### Desktop (≥768px)

```css
Client Name:       font-bold text-base leading-snug
Service Name:      text-sm font-semibold opacity-90
Provider Name:     text-xs font-medium opacity-75
Time:              text-xs font-semibold opacity-90
Status Badge:      text-[10px] font-bold uppercase tracking-wide
Title:             font-bold text-sm
```

### Mobile (<768px)

```css
Client Name:       font-bold text-sm
Service Name:      text-xs font-semibold
Provider Name:     text-xs font-medium
Time:              text-xs font-semibold
Status Badge:      text-[10px] font-bold uppercase
Title:             font-bold text-xs
```

---

## Spacing System

### Event Pill Padding

```css
Desktop:   px-4 py-3 (16px horizontal, 12px vertical)
Mobile:    px-2 py-2 (8px horizontal, 8px vertical)
```

### Internal Gaps

```css
Desktop:   gap-2 (8px between elements)
Mobile:    gap-1 (4px between elements)
```

### Event Margins

```css
Vertical:     mb-2 (8px below each event)
Horizontal:   2px left/right margin
```

### Grid Cell Padding

```css
Desktop:   p-3 (12px all sides)
Mobile:    p-2 (8px all sides)
```

---

## Component Anatomy

### Event Pill Structure

```html
<div class="fc-xs-pill [color-classes]">
  
  <!-- Header Row -->
  <div class="flex items-start justify-between gap-2 mb-2">
    <div class="flex items-center gap-2 flex-1">
      <span class="fc-event-status-dot"></span>
      <span class="font-bold text-sm">Title</span>
    </div>
    <span class="fc-event-time-text">9:00 AM</span>
  </div>
  
  <!-- Client Name -->
  <div class="fc-event-client">John Doe</div>
  
  <!-- Service Name -->
  <div class="fc-event-service">Haircut & Style</div>
  
  <!-- Provider Name -->
  <div class="fc-event-provider">with Sarah Johnson</div>
  
  <!-- Status Badge -->
  <div class="fc-event-status-badge mt-2">
    <span class="h-1.5 w-1.5 rounded-full"></span>
    <span>CONFIRMED</span>
  </div>
  
</div>
```

---

## Sizing Reference

### Minimum Heights

```css
Month View (Day Grid):      60px
Week/Day View (Time Grid):  80px
Mobile:                     50px
```

### Calendar Grid Heights

```css
Day Cell (Month View):
  Desktop: min-h-[140px]
  Mobile:  min-h-[100px]
```

### Border Widths

```css
Event Pills:    border-2 (2px)
Grid Lines:     border-0 (removed for clean look)
```

---

## Interactive States

### Default State

```css
shadow-sm
opacity-100
scale-100
```

### Hover State

```css
shadow-lg
scale-[1.02]
transition: all 0.2s ease-in-out
```

### Focus State (for accessibility)

```css
outline: 2px solid primary-500
outline-offset: 2px
```

---

## Status Dot Reference

### Sizes

```css
Header Dot:    h-2.5 w-2.5 (10px)
Badge Dot:     h-1.5 w-1.5 (6px)
```

### Shadow Effect

```css
box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3)
```

### Colors

```css
Confirmed:  bg-emerald-500 dark:bg-emerald-300
Cancelled:  bg-rose-500 dark:bg-rose-300
Booked:     bg-amber-500 dark:bg-amber-300
Pending:    bg-amber-500 dark:bg-amber-300
Completed:  bg-slate-400 dark:bg-slate-300
Default:    bg-blue-500 dark:bg-blue-300
```

---

## Accessibility Considerations

### Contrast Ratios

All color combinations meet WCAG AA standards:
- Text on light backgrounds: ≥4.5:1
- Text on dark backgrounds: ≥4.5:1
- Border contrast: ≥3:1

### Font Weights

- Regular: 400 (default)
- Medium: 500 (provider names)
- Semibold: 600 (services, time)
- Bold: 700 (client names, titles, status)

### Interactive Elements

- All clickable events have visible hover states
- Tooltips provide full context for screen readers
- Keyboard navigation supported via FullCalendar

---

## Dark Mode Adaptations

### Background Adjustments

```css
Light Mode: bg-white
Dark Mode:  dark:bg-gray-900
```

### Color Opacity

```css
Light Mode: Full opacity
Dark Mode:  40% background opacity for better contrast
            (e.g., bg-emerald-900/40)
```

### Border Adjustments

```css
Light Mode: border-[color]-200
Dark Mode:  dark:border-[color]-700
```

---

## Responsive Breakpoints

### Mobile (<768px)

- Reduced padding
- Smaller typography
- Tighter spacing
- Touch-friendly targets (min 44x44px)

### Tablet (768px - 1024px)

- Standard desktop styling
- Optimized for portrait/landscape

### Desktop (≥1024px)

- Full spacing and typography
- Enhanced hover effects
- Maximum readability

---

## CSS Class Reference

### Custom Classes Added

```css
.fc-xs-pill                  - Main event container
.fc-event-details           - Content wrapper
.fc-event-status-dot        - Status indicator
.fc-event-time-text         - Time display
.fc-event-client            - Client name
.fc-event-service           - Service name
.fc-event-provider          - Provider name
.fc-event-status-badge      - Status badge container
```

### Utility Combinations

```css
Flex Layout:     flex flex-col / flex items-center
Spacing:         gap-1 gap-2 mb-2 mt-2 p-3 px-4 py-3
Typography:      text-xs text-sm text-base font-bold font-semibold
Colors:          opacity-75 opacity-90
Effects:         rounded-full rounded-xl shadow-sm shadow-lg
Transitions:     transition-all duration-200
```

---

## Animation Timing

```css
Default Transition:  200ms ease-in-out
Hover Scale:         0.2s ease-in-out
Shadow Transition:   0.2s ease-in-out
```

---

## Browser Support

✅ Chrome 90+
✅ Firefox 88+
✅ Safari 14+
✅ Edge 90+
✅ Mobile Safari 14+
✅ Chrome Android 90+

All modern browsers with CSS Grid and Flexbox support.
