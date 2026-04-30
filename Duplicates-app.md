# Code Quality Audit: app.js

Excavated 7 instances of duplication, dead code, and architectural inconsistencies

## Summary Table

| # | Issue | Lines | Type | Impact |
|---|-------|-------|------|--------|
| 1 | Duplicate `getAppRelativePathname()` | 134-148 | Duplication | Code exists identically in spa.js |
| 2 | Redundant `refreshAppointmentStats` export pattern | 166, 189 | Duplication | Imported, passed to bindAppLifecycleEvents, then re-exported to window |
| 3 | `getAppRelativePathname()` called multiple times | 111, 211 | Inefficiency | Called twice in same conditional chain |
| 4 | Window assignment with null check pattern | 40-44 | Style | Redundant fallback for built-in function definition |
| 5 | Mixed export + window assignment pattern | 68-75 | Architecture | Chart functions exported AND assigned to window (3 functions) |
| 6 | Conditional window.initProviderPicker assignment | 70 | Dead code | Assigned with `||` fallback but always imported |
| 7 | Unused `resetSchedulerInitAttempts` callback | 167-169 | Dead code | Passed to bindAppLifecycleEvents but never called; state reset happens at line 219 |

## Detailed Findings

### 1. DUPLICATE `getAppRelativePathname()` — Lines 134–148

This function exists identically in [spa.js](spa.js#L57-L75):

**app.js (lines 134–148)**:
```js
function getAppRelativePathname() {
    try {
        const currentPath = window.location.pathname;
        const basePath = new URL(getBaseUrl(), window.location.origin).pathname.replace(/\/+$/, '');

        if (!basePath || basePath === '/') {
            return currentPath;
        }

        if (currentPath === basePath) {
            return '/';
        }

        if (currentPath.startsWith(`${basePath}/`)) {
            return currentPath.slice(basePath.length);
        }

        return currentPath;
    } catch {
        return window.location.pathname;
    }
}
```

**spa.js equivalent** (lines 57–75):
```js
const getAppRelativePath = (url) => {
    const pathname = normalizePathname(url);
    const appBasePath = getAppBasePath();
    // ... similar logic
};
```

**Fix**: Extract to shared utility module (`utils/app-paths.js`), import in both:
```js
// app-paths.js
export const getAppRelativePathname = () => { /* implementation */ };

// app.js & spa.js
import { getAppRelativePathname } from './utils/app-paths.js';
```

### 2. REDUNDANT `refreshAppointmentStats` PATTERN — Lines 166, 189

**Line 23** (import):
```js
import { ..., refreshAppointmentStats } from './modules/filters/status-filters.js';
```

**Line 166** (passed to bindAppLifecycleEvents):
```js
bindAppLifecycleEvents({
    documentRef: document,
    initializeComponents,
    refreshAppointmentStats,
    // ...
});
```

**Line 189** (re-exported to window):
```js
window.refreshAppointmentStats = refreshAppointmentStats;
```

**Issue**: Function is imported, passed to a handler, then assigned to window. The re-export (line 189) is redundant because:
- `bindAppLifecycleEvents` already stores it for internal use
- Re-exporting to window suggests it's part of the public API, but it's only used internally

**Fix**: Remove line 189, let `bindAppLifecycleEvents` manage the exported reference internally.

### 3. DOUBLE `getAppRelativePathname()` CALL — Line 211

```js
if (getAppRelativePathname().includes('/appointments') && schedulerInitAttempts < MAX_SCHEDULER_INIT_ATTEMPTS) {
```

This function call happens every `200ms` retry (line 213). The result doesn't change between retries within the same navigation.

**Fix**: Cache the result before the retry loop:
```js
const relativePath = getAppRelativePathname();
if (relativePath.includes('/appointments') && schedulerInitAttempts < MAX_SCHEDULER_INIT_ATTEMPTS) {
```

### 4. WINDOW ASSIGNMENT WITH NULL CHECK — Lines 40–44

```js
window.xsEscapeHtml = window.xsEscapeHtml || function(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
};
```

**Issue**: Assigns a function definition to window as a fallback, but:
- This will ALWAYS define it (never truly "fallback") because `window.xsEscapeHtml` is initially undefined
- Should just be a straightforward assignment or extracted to utils
- Similar built-in utility definitions elsewhere use plain assignment

**Fix**: Simplify to direct assignment:
```js
window.xsEscapeHtml = function(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
};
```

Or extract to utils module.

### 5. MIXED EXPORT + WINDOW ASSIGNMENT — Lines 68–75

```js
// Lines 45–47: Import then assign to window
import { initTimeSlotsUI } from './modules/appointments/time-slots-ui.js';
window.initTimeSlotsUI = initTimeSlotsUI;

// Lines 52–54: Export to window (analytics)
window.initRevenueTrendChart = initRevenueTrendChart;
window.initTimeSlotChart = initTimeSlotChart;
window.initServiceDistributionChart = initServiceDistributionChart;

// Lines 68–75: Also ES6 export
export { 
    initRevenueTrendChart, 
    initTimeSlotChart, 
    initServiceDistributionChart 
};
```

**Issue**: Functions are both ES6-exported AND assigned to window. This is inconsistent:
- If they're part of module API → use ES6 export only
- If they're for backward compatibility with legacy code → assign to window and comment why
- Doing both is confusing

**Fix**: Choose one approach and document. For backward compat, keep window assignments and remove ES6 export (or vice versa).

### 6. REDUNDANT FALLBACK WINDOW ASSIGNMENT — Line 70

```js
window.initProviderPicker = window.initProviderPicker || initProviderPicker;
```

**Issue**: Uses `||` fallback pattern but `initProviderPicker` is always imported at line 36. This is never a "fallback" — it always overwrites.

**Fix**: Use direct assignment:
```js
window.initProviderPicker = initProviderPicker;
```

### 7. UNUSED `resetSchedulerInitAttempts` CALLBACK — Lines 167–169

```js
bindAppLifecycleEvents({
    // ...
    resetSchedulerInitAttempts: () => {
        schedulerInitAttempts = 0;
    },
    // ...
});
```

**Issue**: This callback is passed to `bindAppLifecycleEvents()` but never called. The actual reset happens:
- **Line 219**: `schedulerInitAttempts = 0;` in `initScheduler()` when container is found

The passed callback (line 167–169) is **dead code** — it exists but has no effect. The reset happens via direct assignment instead.

**Fix**: Remove the unused callback from `bindAppLifecycleEvents()` call. Keep the direct reset at line 219.

## Additional Observations

### CustomEvent Pattern (Not Duplicated Here, but Related)
Line 249:
```js
window.dispatchEvent(new CustomEvent('scheduler:ready', { detail: { scheduler } }));
```

This is a custom event dispatch. While not duplicated in app.js, it matches the pattern I consolidated in spa.js with `dispatchFlash()` helper.

### Scheduler Initialization Retry Logic
Lines 207–215: The scheduler retry logic with `setTimout(..., 200)` is reasonable for SPA navigation timing but could benefit from a configurable delay and more robust path checking.

## Recommendations (Priority Order)

1. **Extract shared path utility** — Create `utils/app-paths.js` to eliminate duplicate `getAppRelativePathname()` logic
2. **Remove redundant `refreshAppointmentStats` export** — Line 189 is unnecessary; `bindAppLifecycleEvents` manages it
3. **Remove unused `resetSchedulerInitAttempts` callback** — Dead code at lines 167–169
4. **Fix double `getAppRelativePathname()` call** — Cache result before retry loop (line 211)
5. **Fix redundant fallback assignments** — Lines 40–44, 70 should use direct assignment
6. **Clarify export strategy** — Choose ES6 export OR window assignment, not both (lines 68–75)
7. **Add comments** — Document why window assignments exist (backward compatibility)

## Build Impact
✅ No breaking changes — all suggestions are pure refactoring
✅ No functional behavior changes
✅ Code clarity and maintainability improvement only
✅ Reduced code duplication across modules
