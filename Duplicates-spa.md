# Code Quality Audit: spa.js

Excavated 8 instances of dead code, duplication, and architectural inconsistencies

## Summary Table

| # | Issue | Lines | Type | Impact |
|---|-------|-------|------|--------|
| 1 | Duplicate `noSpa` check pattern | 171, 288, 468 | Duplication | ~9 LOC repeated 3x |
| 2 | Double `form.method.toUpperCase()` call | 289-290 | Inefficiency | Method called twice per submission |
| 3 | Duplicate error response handling | 318-326 vs 339-347 | Duplication | Similar error dispatch/validation patterns |
| 4 | Similar CustomEvent creation patterns | Multiple | Duplication | `new CustomEvent('xs:flash', {...})` repeated 5x |
| 5 | Duplicate error message fallback pattern | 320, 340, 358 | Duplication | `message \|\| error \|\| fallback` pattern |
| 6 | Repeated validation error clearing | 396-400 | Orphan | Two-step clear (remove elements + remove classes) every time |
| 7 | Missing blank line after initTabsInSpaContent | 147-148 | Format | Code organization |
| 8 | History state check repeated implicitly | 321, 369 | Duplication | `history.pushState({ spa: true }, ...)` pattern |

## Detailed Findings

### 1. DUPLICATE `noSpa` PATTERN — appears 3 times

Lines 171, 288, 468
```js
// Line 171 (shouldIntercept)
if (a.dataset?.noSpa === 'true' || a.classList.contains('no-spa')) return false;

// Line 288 (submitHandler)
if (form.dataset.noSpa === 'true' || form.classList.contains('no-spa')) {
  return;
}

// Line 468 (init click handler)
if (form.dataset.noSpa === 'true' || form.classList.contains('no-spa')) {
  return;
}
```

**Fix**: Extract shared helper function:
```js
const shouldSkipSPA = (el) => el?.dataset?.noSpa === 'true' || el?.classList.contains('no-spa');
```

### 2. DOUBLE METHOD CALL — submitHandler lines 289–290

```js
if (form.method.toUpperCase() !== 'POST' && form.method.toUpperCase() !== 'PUT') return;
```

**Impact**: Method called twice per submission validation
**Fix**: Cache the result
```js
const method = (form.method || 'POST').toUpperCase();
if (method !== 'POST' && method !== 'PUT') return;
```

### 3. DUPLICATE ERROR HANDLING — submitForm

**First error path** (lines 318–326):
```js
if (!res.ok) {
  const errMsg = (data && (data.message || data.error)) || `HTTP ${res.status}`;
  const flashEvent = new CustomEvent('xs:flash', {
    detail: { type: 'error', message: errMsg }
  });
  document.dispatchEvent(flashEvent);
  
  if (data && data.errors && typeof data.errors === 'object') {
    injectValidationErrors(form, data.errors);
  }
  return;
}
```

**Second error path** (lines 339–347):
```js
} else {
  document.dispatchEvent(new CustomEvent('xs:flash', {
    detail: { type: 'error', message: data.message || 'An error occurred' }
  }));
  
  if (data.errors && typeof data.errors === 'object') {
    injectValidationErrors(form, data.errors);
  }
}
```

Both follow identical pattern: extract error message → dispatch flash → inject validation errors

**Fix**: Extract helper `dispatchFormError(message, errors)`

### 4. REPEATED CustomEvent CREATION — 5 instances

Lines: 324, 353, 357, 409, 420
```js
// Pattern repeated 5 times:
new CustomEvent('xs:flash', { detail: { type: 'X', message: 'Y' } })
```

**Fix**: Create helper function:
```js
const dispatchFlash = (type, message, autoClose = true, duration = 5000) => {
  document.dispatchEvent(new CustomEvent('xs:flash', {
    detail: { type, message, autoClose, duration }
  }));
};
```

### 5. DUPLICATE ERROR MESSAGE FALLBACK — appears 3 times

Lines 320, 340, 358
```js
// Line 320
const errMsg = (data && (data.message || data.error)) || `HTTP ${res.status}`;

// Line 340
data.message || 'An error occurred'

// Line 358
'Form submission failed: ' + e.message
```

Similar fallback/default pattern with slight variations

### 6. REPEATED VALIDATION ERROR CLEARING — injectValidationErrors

Lines 396–400 always run the same clear sequence:
```js
form.querySelectorAll('.spa-validation-error').forEach(el => el.remove());
form.querySelectorAll('.border-red-500').forEach(el => {
  el.classList.remove('border-red-500', 'dark:border-red-400');
});
```

This two-step clear runs every time `injectValidationErrors` is called. Could be optimized by tracking which field inputs have been marked, or using a more efficient query.

**Potential fix**: Track marked fields or use a single data attribute instead of multiple queries.

### 7. MISSING BLANK LINE — formatting

Line 147-148: `initTabsInSpaContent` closing brace immediately followed by `sameOrigin` without blank line separator. Minor code organization issue.

### 8. IMPLICIT HISTORY STATE PATTERN DUPLICATION

The `{ spa: true }` marker is used in multiple places:
- Line 321: `history.pushState({ spa: true }, '', url)`
- Line 369: `history.pushState({ spa: true }, '', url)`
- Line 395: `history.replaceState({ spa: true }, '', window.location.href)`

Could extract a helper like:
```js
const pushSpaState = (url) => history.pushState({ spa: true }, '', url);
```

## Recommendations (Priority Order)

1. **Extract `shouldSkipSPA(el)` helper** — Eliminates 3 duplicate checks
2. **Extract `dispatchFlash(type, message)` helper** — Consolidates 5 CustomEvent creations
3. **Consolidate error handling** — Merge non-ok and error-data response paths
4. **Cache `form.method` result** — Eliminates redundant toUpperCase() calls
5. **Extract error message builder** — Standardize fallback patterns
6. **Consider clearing optimization** — Track marked fields instead of repeated querySelectorAll
7. **Add blank line** — Minor formatting after initTabsInSpaContent

## Build Impact
✅ No breaking changes — all suggestions are pure refactoring
✅ No functional behavior changes
✅ Code clarity and maintainability improvement only
