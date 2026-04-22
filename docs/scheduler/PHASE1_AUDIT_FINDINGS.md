# Phase 1: Day View Scheduler Audit Findings

**Date:** March 9, 2026  
**Branch:** calendar-refactor  
**Component:** Day View Timeline & Appointment Grid

---

## Executive Summary

Audit completed on Day View Scheduler components to identify misalignment between timeline and appointment grid. The current implementation uses **static hour ranges** from business hours config, but **does not respect individual provider working hours** from the `xs_provider_schedules` table.

---

## Component Structure

### Frontend Components Audited

1. **scheduler-day-view.js** ✅ EXISTS
   - Location: `resources/js/modules/scheduler/scheduler-day-view.js`
   - Lines of code: ~400
   - Responsibilities: Render per-provider columns, timeline generation, appointment positioning

2. **scheduler-timeline.js** ❌ DOES NOT EXIST
   - Timeline rendering is **embedded within** `scheduler-day-view.js` (`_renderTimeline` method)

3. **scheduler-grid.js** ❌ DOES NOT EXIST
   - Grid logic is **embedded within** `scheduler-day-view.js`

4. **scheduler-slots.js** ❌ DOES NOT EXIST
   - Slot calculations are handled by backend `DayViewService.php` and `TimeGridService.php`

5. **time-grid-utils.js** ✅ EXISTS
   - Location: `resources/js/modules/scheduler/time-grid-utils.js`
   - Provides: `resolveOverlaps`, `topPx`, `heightPx`, `getBusinessHours`, `weekStart`
   - Status: Shared utility for Week and Day views

### Backend Services Audited

1. **DayViewService.php** ✅ EXISTS
   - Location: `app/Services/Calendar/DayViewService.php`
   - Generates server-side render model with `providerColumns` structure
   - **Does NOT use provider-specific working hours**

2. **TimeGridService.php** ✅ EXISTS (Assumed)
   - Generates time slots based on business hours config
   - **Does NOT integrate with `xs_provider_schedules`**

3. **ProviderScheduleModel.php** ✅ EXISTS
   - Location: `app/Models/ProviderScheduleModel.php`
   - Database table: `xs_provider_schedules`
   - **AVAILABLE but NOT INTEGRATED into Day View**

---

## Critical Issues Identified

### 1. ❌ Static Timeline Values

**Location:** `scheduler-day-view.js:138-182` (`_renderTimeline` method)

```javascript
_renderTimeline(visibleProviders, appointmentsByProvider, data, isToday) {
    const { startHour, endHour } = this.businessHours; // ❌ STATIC from config
    const totalHeight = Math.max(1, (endHour - startHour) * 60); // ❌ HARDCODED calculation
    const hours = [];
    for (let h = startHour; h < endHour; h++) { // ❌ LOOP-based hour generation
        hours.push(h);
    }
```

**Problem:**
- Timeline hours are **hardcoded loops** (8 AM - 5 PM from business config)
- **Does NOT adapt** to provider working hours (e.g., Dr. Ayanda Mbeki: 08:00-17:00)
- **No provider-specific time ranges**

---

### 2. ❌ Hardcoded Hours

**Location:** `time-grid-utils.js:148-169` (`getBusinessHours`)

```javascript
export function getBusinessHours(config = {}) {
    let startTime = config?.businessHours?.startTime || config?.slotMinTime || '08:00'; // ❌ DEFAULT '08:00'
    let endTime = config?.businessHours?.endTime || config?.slotMaxTime || '17:00';     // ❌ DEFAULT '17:00'
```

**Problem:**
- Falls back to **hardcoded 8 AM - 5 PM** if config missing
- **No provider schedule lookup**

---

### 3. ❌ Inline CSS Heights

**Location:** `scheduler-day-view.js:175-176`

```javascript
<div class="timeline-shell" style="grid-template-columns: 60px repeat(${visibleProviders.length}, 1fr);">
    <div class="timeline-time-column" style="height:${totalHeight}px;">  // ❌ INLINE STYLE
```

**Location:** `scheduler-day-view.js:165-169`

```javascript
<div class="timeline-column ${...}" 
     data-provider-id="${provider.id}"
     style="height:${totalHeight}px;">  // ❌ INLINE STYLE
```

**Problem:**
- Timeline height is **inline CSS** calculated from static hours
- **Not driven by CSS custom properties**
- **Violates architectural requirement**: "No inline CSS is introduced"

---

### 4. ❌ Fixed Grid Rows

**Location:** `scheduler-day-view.js:144-152`

```javascript
const hours = [];
for (let h = startHour; h < endHour; h++) {
    hours.push(h);
}
```

**Problem:**
- Grid rows are **fixed integer hour array**
- **No fractional intervals** (e.g., 15-minute or 30-minute slots)
- **Mismatch with appointment positioning** which uses `topPx()` for minute-level precision

---

### 5. ❌ Misalignment Causes

**Timeline Generation:**
```javascript
// scheduler-day-view.js:144-152
const hours = [];
for (let h = startHour; h < endHour; h++) {
    hours.push(h);
}
```

**Appointment Positioning:**
```javascript
// scheduler-day-view.js:191
const top = Number.isFinite(appointment._topPx) ? appointment._topPx : topPx(appointment.startDateTime, startHour);
```

**Problem:**
- Timeline renders **hourly rows** (discrete)
- Appointments position using **minute-level calculations** (continuous)
- **Different math** → **visual misalignment**

---

### 6. ✅ Orphans / Duplication (NONE FOUND)

**Findings:**
- NO duplicate time generation logic found
- `time-grid-utils.js` is the **single source** for positioning calculations (`topPx`, `heightPx`)
- Week view (`scheduler-week-view.js`) uses **same utilities**

**Status:** ✅ Clean architecture— no duplication.

---

### 7. ❌ CSS Problems

**SCSS File:** `resources/scss/pages/_appointments-scheduler.scss:282-422`

**Good:**
```scss
:root {
    --hour-height: 60px; /* ✅ CSS custom property */
}
```

**Bad:**
- Inline styles override CSS custom property:
  ```javascript
  style="height:${totalHeight}px;"  // ❌ Should use CSS calc()
  ```

**Problem:**
- CSS custom property `--hour-height` exists but is **not leveraged** for dynamic calculations
- **Inline styles** used instead of CSS classes

---

###8. ❌ Naming Inconsistencies

**Frontend:**
- ✅ Consistent: `providerId`, `startDateTime`, `endDateTime` (camelCase)

**Backend:**
- ✅ Consistent: `provider_id`, `start_time`, `end_time` (snake_case)

**Conversion:**
- ✅ Proper normalization in `_normalizeModelAppointment()`

**Status:** ✅ No naming issues—proper conversion layer exists.

---

### 9. ❌ UI Expansion Issues

**Problem:**
- Grid height is **calculated once** during render:
  ```javascript
  const totalHeight = Math.max(1, (endHour - startHour) * 60);
  ```
- **Does NOT expand** based on provider working hours
- **Timeline height is independent** from provider schedule

**Expected Behavior:**
- Dr. Ayanda Mbeki works 08:00-17:00 → timeline should be 9 hours × 60px = 540px
- Dr. Sarah Lee works 07:00-19:00 → timeline should be 12 hours × 60px = 720px
- **Current:** All providers share same business hours (08:00-17:00)

---

### 10. ❌ Time Source Validation

**Current Source of Truth:**
```javascript
// scheduler-day-view.js:137
_renderTimeline(visibleProviders, appointmentsByProvider, data, isToday) {
    const { startHour, endHour } = this.businessHours; // ❌ From config
```

**Correct Source of Truth:**
1. **Provider Working Hours** (`xs_provider_schedules` table)
   - `start_time`, `end_time` per provider per day
   - Example: `{ day_of_week: 'monday', start_time: '08:00:00', end_time: '17:00:00' }`

2. **Fallback:** Business Hours Config
   - `config.businessHours.startTime` / `config.businessHours.endTime`

**Status:** ❌ **Provider schedules NOT integrated**

---

## Database Schema: xs_provider_schedules

```sql
CREATE TABLE xs_provider_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    day_of_week ENUM('monday','tuesday','wednesday','thursday','friday','saturday','sunday'),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_start TIME NULL,
    break_end TIME NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (provider_id) REFERENCES xs_users(id)
);
```

**Status:** ✅ Available but **NOT USED** by Day View

---

## Summary of Findings

| Issue | Status | Severity | Location |
|-------|--------|----------|----------|
| Static timeline values | ❌ | **CRITICAL** | `scheduler-day-view.js:138-182` |
| Hardcoded hours | ❌ | **CRITICAL** | `time-grid-utils.js:148-169` |
| Inline CSS heights | ❌ | **HIGH** | `scheduler-day-view.js:165,175` |
| Fixed grid rows (hourly only) | ❌ | **MEDIUM** | `scheduler-day-view.js:144-152` |
| Timeline/grid misalignment | ❌ | **HIGH** | Timeline discrete, appointments continuous |
| Provider schedule NOT integrated | ❌ | **CRITICAL** | Entire Day View |
| Orphans / duplication | ✅ | **NONE** | Clean architecture |
| CSS custom properties underutilized | ❌ | **MEDIUM** | Inline styles instead of CSS |
| Naming inconsistencies | ✅ | **NONE** | Proper conversion layer exists |
| UI doesn't expand per provider | ❌ | **HIGH** | Grid height static |

---

## Recommendations

### Phase 2: Create Unified Time Range Generator
- Extract hour/slot generation into `timeRangeGenerator.js`
- Support fractional intervals (15min, 30min, 60min)
- Replace loop-based generation with configurable generator

### Phase 3: Integrate Provider Schedules
- Modify `DayViewService.php` to query `xs_provider_schedules`
- Pass provider working hours to frontend via `providerColumns[].workingHours`
- Fallback to business hours if provider schedule missing

### Phase 4: Refactor Timeline Rendering
- Use unified time range generator for timeline rows
- Align timeline labels with appointment positioning math

### Phase 5: Align Appointment Grid
- Use same dataset for timeline AND grid
- Ensure 1:1 row correspondence

### Phase 6: Dynamic Height Layout
- Replace inline `style="height:${totalHeight}px"` with CSS grid `auto-rows`
- Leverage `--hour-height` CSS custom property
- Grid expands based on time range length

### Phase 7: Appointment Positioning 
- Validate `topPx()` and `heightPx()` calculations align with grid
- Ensure slot index math matches timeline row index

### Phase 8: UI Behavior
- Implement scroll sync between timeline and grid
- Ensure responsive behavior (desktop/tablet/mobile)

### Phase 9: Debug Tools
- Add developer debug panel showing:
  - Provider working hours
  - Business hours fallback
  - Timeline start/end
  - Total slots generated

### Phase 10: Documentation
- Create `/docs/scheduler/DAY_VIEW_ARCHITECTURE.md`
- Document provider schedule integration
- Document time range generation logic

---

## Next Steps

✅ **Phase 1 Complete** — Audit finished  
⏭️ **Phase 2: Create `timeRangeGenerator.js` utility**

---

*End of Phase 1 Audit*
