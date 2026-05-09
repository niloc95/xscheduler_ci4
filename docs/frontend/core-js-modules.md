# Core JS Modules

**Location:** `resources/js/core/`

These four modules form the shared foundation that every other JS module imports from. They handle transport, CSRF, datetime utilities, and DOM lifecycle. Nothing in this directory is view-specific.

---

## `core/api.js` — Shared Fetch Transport

### Purpose

Single fetch wrapper used by all AJAX calls in the app. Returns both the raw `Response` and a pre-parsed `payload` so consumers can inspect status codes and envelope data without re-parsing.

### Export

```js
import { apiRequest } from '../../core/api.js';

const { response, payload } = await apiRequest(endpoint, options);
```

### Signature

```js
apiRequest(endpoint: string, options?: ApiRequestOptions): Promise<{ response: Response, payload: any }>
```

### Options

| Option | Type | Default | Description |
|---|---|---|---|
| `method` | `string` | `'GET'` | HTTP method |
| `body` | `object \| FormData \| null` | `null` | Request body. Plain objects are auto-serialized to JSON. `FormData` is passed through unchanged. |
| `headers` | `object` | `{}` | Additional headers merged after defaults |
| `authContext` | `'authenticated' \| 'public'` | `'authenticated'` | Controls which CSRF token source is used (see `csrf.js`) |
| `credentials` | `string` | `'same-origin'` | Fetch credentials mode |
| `includeRequestedWith` | `boolean` | `true` | Adds `X-Requested-With: XMLHttpRequest` |
| `rotateCsrf` | `boolean` | `true` | Calls `rotateCsrfFromResponse()` after each request |

### Payload Resolution

| Response `Content-Type` | `payload` value |
|---|---|
| `application/json` | Parsed JSON object/array |
| `text/*` | String |
| Anything else | Attempts `response.json()`, falls back to `null` |

`payload` is **already parsed** — consumers must not call `.json()` or `.match()` on it unless they first confirm `typeof payload === 'string'`.

### Body Serialization

- If `body` is a plain object (not `FormData`): serialized to JSON, `Content-Type: application/json` added automatically.
- If `body` is `FormData`: sent as-is, no `Content-Type` override.

### CSRF Injection

On every request, `buildCsrfHeader(authContext)` is called automatically. The resulting header (e.g. `X-CSRF-TOKEN: <token>`) is merged into request headers. See `core/csrf.js` for token resolution.

### CSRF Rotation

If `rotateCsrf: true` (default), `rotateCsrfFromResponse()` runs after the fetch completes. This updates the in-page CSRF token from the server's `X-CSRF-TOKEN` response header, keeping subsequent requests valid without a page reload.

---

## `core/csrf.js` — CSRF Token Management

### Purpose

Centralizes CSRF token read/write for both authenticated app surfaces and the public booking flow. Two contexts are supported: `'authenticated'` and `'public'`.

### Exports

#### `getCsrfHeaderName(): string`

Returns the CSRF header name from `<meta name="csrf-header">`. Defaults to `'X-CSRF-TOKEN'` if the meta tag is absent.

#### `readCsrfToken(authContext = 'authenticated'): string | null`

Reads the current CSRF token for a given context.

| Context | Token source |
|---|---|
| `'authenticated'` | `<meta name="csrf-token">` content, then `window.__CSRF_TOKEN__` |
| `'public'` | `dataset.csrfValue` on `[data-booking-root]` or `#public-booking-root` or `document.body` |

#### `buildCsrfHeader(authContext = 'authenticated'): object`

Returns `{ [headerName]: token }` ready to spread into a `headers` object. Returns `{}` if no token is available.

#### `getFormCsrfContext(form: HTMLFormElement): CsrfContext`

Reads CSRF state from a form element. Returns:

```js
{
  headerName: string,   // header name from meta tag
  tokenName: string,    // input name (e.g. 'csrf_test_name')
  tokenValue: string,   // current token value
  input: HTMLElement,   // the CSRF hidden input, or first named input as fallback
}
```

Looks for `input[name="csrf_test_name"]` or `input[name^="csrf_"]`.

#### `syncCsrfIntoForm(form: HTMLFormElement): CsrfContext`

Writes the current CSRF token into the form's CSRF input. Returns the context object. Use before programmatic form submission to ensure the hidden field is current.

#### `rotateCsrfFromResponse(response, authContext = 'authenticated', form = null)`

Reads `X-CSRF-TOKEN` from the response headers and propagates the new token:

- `'authenticated'`: updates `<meta name="csrf-token">`, `window.__CSRF_TOKEN__`, all `input[type="hidden"][name*="csrf"]` in the document, and optionally the supplied `form`'s CSRF input.
- `'public'`: updates `dataset.csrfValue` on the public booking root element.

Called automatically by `apiRequest()` after every response when `rotateCsrf: true`.

---

## `core/datetime.js` — Browser Timezone Utilities

### Purpose

Provides browser-side timezone detection and date formatting helpers. These values are **hints only** — the server always uses `localization.timezone` from `xs_settings` as the authoritative timezone. Do not use these for scheduling logic.

### Exports

#### `getBrowserTimezone(): string`

Returns the browser's IANA timezone string (e.g. `'America/New_York'`) via `Intl.DateTimeFormat`. Falls back to `'UTC'` if unavailable.

#### `getTimezoneOffsetForTimezone(timezone?: string): number`

Returns the current UTC offset for the given timezone in **minutes** (positive = west of UTC). Defaults to the browser's timezone. Falls back to `new Date().getTimezoneOffset()` on error.

#### `getBrowserTimezoneHeaders(): object`

Returns two headers suitable for attaching to API requests as browser hints:

```js
{
  'X-Client-Timezone': 'America/New_York',
  'X-Client-Offset': '-300'
}
```

The server reads these as hints but does not treat them as authoritative. The scheduler and notification pipeline always resolve timezone from `window.appTimezone` (set from `/api/v1/settings/localization`).

#### `toIsoDate(value?: Date | string): string`

Converts a `Date` object or date string to `'YYYY-MM-DD'` ISO format. Defaults to today.

---

## `core/lifecycle.js` — DOM Lifecycle

### Purpose

A single safe wrapper for DOMContentLoaded. Avoids the common bug where a handler bound after the document is already parsed is never called.

### Export

#### `onDomReady(callback: () => void): void`

Runs `callback` when the DOM is ready. If the document is already past `'loading'` state, runs immediately (synchronously). Uses `{ once: true }` to prevent duplicate execution.

```js
import { onDomReady } from '../../core/lifecycle.js';

onDomReady(() => {
  // safe to query DOM here
});
```

For app surfaces that use SPA navigation, prefer `xsRegisterViewInit()` (exposed from `app.js`) over `onDomReady`, because `onDomReady` only fires once per full page load and will not re-run on `spa:navigated` events.

---

## Import Conventions

These modules export named functions only — no default exports.

```js
import { apiRequest } from '../../core/api.js';
import { buildCsrfHeader, syncCsrfIntoForm } from '../../core/csrf.js';
import { getBrowserTimezone, getBrowserTimezoneHeaders } from '../../core/datetime.js';
import { onDomReady } from '../../core/lifecycle.js';
```

Relative paths vary by the importing module's depth. Adjust accordingly.

---

## Related

- `resources/js/spa.js` — SPA navigation layer; consumes `csrf.js` for form submission
- `resources/js/modules/scheduler/settings-manager.js` — sets `window.appTimezone` from `/api/v1/settings/localization`
- `Agent_Context_v2.md §6.6` — Shared Fetch Contract (authoritative contract on `apiRequest` payload behaviour)
