# Avatar System

The avatar system has a single source of truth on each side of the stack. All avatar rendering — profile images with initials fallback — goes through these helpers exclusively.

---

## PHP Side — `app/Helpers/app_helper.php`

Load with `helper('app')` (auto-loaded by `BaseController`).

### Functions

#### `avatar_display_name(array $entity): string`

Derives a display name from an entity array. Prefers `$entity['name']`. Falls back to `first_name` + `last_name` concatenation. Returns an empty string if both are absent.

```php
avatar_display_name(['name' => 'Dr. Jane Smith']);         // 'Dr. Jane Smith'
avatar_display_name(['first_name' => 'Jane', 'last_name' => 'Smith']); // 'Jane Smith'
avatar_display_name([]);                                   // ''
```

---

#### `avatar_initials(?string $name, string $default = 'U'): string`

Derives 1–2 letter uppercase initials from a display name.

**Normalization pipeline:**
1. Strip leading titles: `Dr.`, `Mr.`, `Mrs.`, `Ms.`, `Prof.`, `Rev.` (case-insensitive)
2. Strip trailing credentials/suffixes (loop until stable): `MD`, `PhD`, `DDS`, `DO`, `RN`, `NP`, `PA`, `DVM`, `Jr.`, `Sr.`, `II`, `III`, `IV`
3. Split on whitespace:
   - Multi-word → first letter of first word + first letter of last word, uppercased
   - Single-word → first 2 characters, uppercased
4. Empty after normalization → returns `$default`

```php
avatar_initials('Jane Smith');          // 'JS'
avatar_initials('Dr. Jane Smith MD');   // 'JS'
avatar_initials('Jane');                // 'JA'
avatar_initials(null);                  // 'U'
avatar_initials('', 'C');              // 'C'
```

Uses `mb_strtoupper` / `mb_substr` when available (UTF-8 safe).

---

#### `avatar_profile_image_url(array $entity, string $imageField = 'profile_image'): ?string`

Resolves a profile image URL from an entity. Returns `null` if no usable image path is found.

**Resolution order:**
1. Read `$entity[$imageField]`. If empty and `$imageField === 'profile_image'`, query `UserModel::find($entity['id'])` for `profile_image` (static per-request cache).
2. If still empty → return `null`

**Path mapping:**
| Stored path prefix | File location | URL |
|---|---|---|
| `assets/...` | `FCPATH/assets/...` | `base_url('assets/...')` |
| `uploads/...` | `WRITEPATH/uploads/...` | `base_url('writable/uploads/...')` |
| bare filename | `WRITEPATH/uploads/profile_images/{filename}` | `base_url('uploads/profile_images/{filename}')` |

Returns `null` if the resolved filesystem path does not exist.

---

#### `avatar_data(array $entity, string $default = 'U', string $imageField = 'profile_image'): array`

Convenience wrapper that returns all three avatar values in one call.

```php
$avatar = avatar_data($user);
// Returns:
[
    'name'      => string,   // display name from avatar_display_name()
    'image_url' => ?string,  // from avatar_profile_image_url(), null if no image
    'initials'  => string,   // from avatar_initials(), using $default
]
```

Use this in views for image-first rendering with initials fallback:

```php
<?php $avatar = avatar_data($user); ?>
<?php if ($avatar['image_url']): ?>
    <img src="<?= esc($avatar['image_url']) ?>" alt="<?= esc($avatar['name']) ?>">
<?php else: ?>
    <span class="avatar-initials"><?= esc($avatar['initials']) ?></span>
<?php endif; ?>
```

---

## JavaScript Side — `resources/js/utils/avatar.js`

ESM module. Import directly in other modules.

### Exports

#### `getDisplayName(entity = {}, fallback = ''): string`

Prefers `entity.name`. Falls back to `entity.first_name + ' ' + entity.last_name`. Returns `fallback` when both are absent or entity is not an object.

```js
import { getDisplayName } from '../../utils/avatar.js';

getDisplayName({ name: 'Jane Smith' });                    // 'Jane Smith'
getDisplayName({ first_name: 'Jane', last_name: 'Smith' }); // 'Jane Smith'
getDisplayName({}, 'Unknown');                             // 'Unknown'
```

#### `getAvatarInitials(name, options = {}): string`

Applies the same normalization rules as PHP `avatar_initials()`:
- Strip leading titles (`Dr.`, `Mr.`, `Mrs.`, `Ms.`, `Prof.`, `Rev.`)
- Strip trailing credentials/suffixes (loop)
- Multi-word → first + last initial, uppercased
- Single-word → first 2 chars, uppercased

```js
import { getAvatarInitials } from '../../utils/avatar.js';

getAvatarInitials('Dr. Jane Smith MD');           // 'JS'
getAvatarInitials('Jane');                        // 'JA'
getAvatarInitials('', { defaultInitial: 'C' });   // 'C'
```

| Option | Type | Default | Description |
|---|---|---|---|
| `defaultInitial` | `string` | `'U'` | Returned when name is empty after normalization |

### Global window functions

For inline `<script>` blocks in PHP views (which cannot use ESM imports), `app.js` exposes globals:

```js
window.xsGetAvatarInitials(name, defaultInitial)
window.xsGetDisplayName(entity, fallback)
```

---

## Default Initials by Context

| Surface | Default |
|---|---|
| User / staff / header | `'U'` |
| Customer | `'C'` |
| Staff assignment widget | `'S'` |
| Provider assignment widget | `'P'` |
| Scheduler provider chip | `'?'` |

---

## Covered Surfaces

| Surface | Implementation |
|---|---|
| `app/Views/layouts/app.php` header | `avatar_data($user)` |
| `app/Views/user-management/index.php` PHP rows | `avatar_data($user)` |
| `app/Views/user-management/index.php` JS rows | `window.xsGetAvatarInitials` |
| `app/Views/customer-management/index.php` | `avatar_data($customer, 'C')` |
| `app/Views/customer-management/history.php` | `avatar_data($customer, 'C')` |
| `app/Views/appointments/form.php` | `'C'` default initials for customer placeholder |
| `app/Views/user-management/components/provider-staff.php` | PHP + JS `renderStaff()` |
| `app/Views/user-management/components/staff-providers.php` | PHP + JS `renderProviders()` |
| `modules/scheduler/appointment-colors.js` | `getAvatarInitials` via `getProviderInitials()` |
| `modules/customer-management/customer-search.js` | `getAvatarInitials` |
| `modules/appointments/appointments-form.js` | `getAvatarInitials` |

---

## Do Not Duplicate

Do not reimplement initials logic in any view, controller, or JS module. Always call the shared helpers. A one-letter initial in a completed surface is a regression.

---

## Test Coverage

| Test | Coverage |
|---|---|
| `tests/unit/Helpers/AvatarHelperTest.php` | PHP helper parity — 3 tests, 12 assertions |
| `tests/frontend/avatar-utils.test.js` | JS `getAvatarInitials` and `getDisplayName` — 6 tests |

---

## Related

- `app/Services/ProfilePageService.php` — `buildProfileImageUrl()` and `buildProfileInitials()` delegate to these helpers
- `Agent_Context_v2.md §6.8` — Avatar System Contract (architecture reference)
