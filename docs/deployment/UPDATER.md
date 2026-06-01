# In-App Updater

Browser-based update system that requires no CLI, SSH, or `exec()`. An admin uploads a deployment ZIP through Settings → System Update; the app backs itself up, swaps files, and runs migrations without touching the server shell.

---

## npm Command Reference

| Command | What it does |
|---------|-------------|
| `npm run build` | Vite asset compilation only — outputs to `public/build/`. No ZIP, no version bump, no git. Use during development to rebuild JS/CSS after changes. |
| `npm run package:local` | Creates the deployment ZIP from current source without rebuilding Vite assets. Reads `package.json` for the version, writes `version.json` to both `webschedulr-deploy/` and the project root, archives with `archive.glob` (canonical paths, no `./` prefix). Output: `webschedulr-deploy.zip`. Use when assets are already built and you only want to repackage. |
| `npm run package` | `npm run build` + `npm run package:local`. Full rebuild from scratch. |
| `npm run release:patch` | Bumps `package.json` patch version → builds assets → builds ZIP (stamped with the **new** version) → commits `package.json + version.json + changelog` → creates git tag → pushes branch and tag to GitHub → triggers the GitHub Actions release workflow which builds the official `webschedulr-vX.X.X-deploy.zip` and publishes it on GitHub Releases. |
| `npm run release:minor` | Same as `release:patch` but bumps the minor version. |
| `npm run release:major` | Same as `release:patch` but bumps the major version. |

### Key ordering rule

The release script bumps `package.json` **before** running `node scripts/package.js`, so the local ZIP and `version.json` are always stamped with the **new** version number (not the previous one). This was a known bug fixed in v2.0.8 — do not reorder those steps in `scripts/release.js`.

---

## How the In-App Updater Works

### Services (`app/Services/Updater/`)

| Service | Responsibility |
|---------|---------------|
| `UpdaterValidatorService` | Opens the uploaded ZIP; finds `version.json` at root (or `./version.json`); rejects source-code archives, legacy ZIPs without `version.json`, downgrades, and `min_version` violations. |
| `UpdaterBackupService` | Pure-PHP DB dump (SHOW TABLES → SELECT * → INSERT SQL file) + shadow ZIP of `app/` and `public/`. Prunes to 3 backup sets in `writable/backups/`. |
| `UpdaterFileService` | Entry-by-entry extraction with path-traversal rejection. Preserve list: `.env`, `writable/`, `public/assets/settings/`, `public/assets/providers/`. |
| `UpdaterMigrationService` | Runs CI4 migrations using a non-shared DB connection (mirrors `Setup.php:444`). |
| `UpdaterService` | Orchestrator. Fail-closed state gate: tracks `$enteredMaintenance` and `$filesMutated` so the site is never left in a broken state on partial failure. |

### Update flow

```
Upload ZIP  →  [ValidatorService]  →  session: updater_pending_zip
                    ↓ pass
Execute     →  [BackupService]     →  writable/backups/pre-update-{ts}.sql
                                   →  writable/backups/pre-update-{ts}-appfiles.zip
            →  [MaintenanceFilter] →  enable (503 for non-admins)
            →  [FileService]       →  extract ZIP to ROOTPATH (preserving .env etc.)
            →  [MigrationService]  →  php spark migrate equivalent
            →  [SettingModel]      →  update system.installed_version → new version
            →  [MaintenanceFilter] →  disable
```

If extraction or migration fails, `UpdaterService` clears maintenance mode and returns an error. The backup remains intact for rollback.

### Controller (`app/Controllers/Admin/Updater.php`)

Thin coordinator extending `BaseApiController`.

| Route | Method | Returns |
|-------|--------|---------|
| `POST /admin/updater/upload` | `upload()` | Multipart → redirect with flashdata |
| `POST /admin/updater/execute` | `execute()` | JSON `{success, version, redirect}` |
| `POST /admin/updater/rollback` | `rollback()` | JSON |
| `POST /admin/updater/maintenance` | `toggleMaintenance()` | JSON |

The upload form uses `data-no-spa="true"` (full-page POST). Execute/rollback/maintenance are AJAX JSON calls via `core/api.js`.

---

## version.json

Every deployment ZIP must have `version.json` at the ZIP root (not nested in a subdirectory).

```json
{
  "version": "2.0.15",
  "build": "20260601120000",
  "min_version": "1.0.0",
  "requires_migration": true,
  "released_at": "2026-06-01T12:00:00.000Z"
}
```

`scripts/package.js` generates this file from `package.json` and archives it via `archive.glob('**', { cwd: packageDir, dot: true })` — the glob method produces canonical entry names (no `./` prefix) on both macOS and Linux. Earlier approaches using `archive.directory(dir, false)` produced `./`-prefixed entries on Linux that PHP's `ZipArchive::getFromName('version.json')` could not find.

### Validator lookup order

```php
$versionRaw = $zip->getFromName('version.json');           // canonical root
if (!$versionRaw) $versionRaw = $zip->getFromName('./version.json');  // archiver Linux legacy
if (!$versionRaw) {
    $idx = $zip->locateName('version.json', ZipArchive::FL_NODIR);    // detection only
    // if found → source-code archive error, not acceptance
}
```

`FL_NODIR` is used **only to detect** source-code archives (e.g. `xscheduler_ci4-2.0.15.zip` from GitHub's "Source code" button). It is **not** used to accept them — extracting a source archive to the server would corrupt the installation.

---

## GitHub Actions Release Workflow

`release.yml` triggers on tag push (`v*.*.*`) or `workflow_dispatch`.

```
Checkout → npm ci → composer install --no-dev → npm run build
  → npm run package:local
  → rename webschedulr-deploy.zip → webschedulr-v{tag}-deploy.zip
  → Python validation: confirm version.json at root, print version
  → sha256sum sidecar
  → softprops/action-gh-release (requires permissions.contents: write)
  → upload-artifact (365-day retention)
```

The Python validation step uses `zipfile.ZipFile.namelist()` to find exact entry names, then calls `z.read(entry)` to confirm the JSON is readable. This catches both missing `version.json` and malformed content before the release is published.

**Do not download the "Source code" archive from GitHub** (named `xscheduler_ci4-vX.X.X.zip`). Download `webschedulr-vX.X.X-deploy.zip` from the Assets section of the release.

---

## Subdirectory Installs (e.g. Hostinger)

If the app is deployed to a subdirectory (e.g. `https://example.com/mvp/public/`):

1. **`app.baseURL`** in `.env` must match the full public URL including the path:
   ```
   app.baseURL = 'https://example.com/mvp/public/'
   ```

2. **`public/.htaccess`** needs `RewriteBase` for LiteSpeed to resolve rewrites correctly:
   ```apache
   RewriteBase /mvp/public/
   ```

3. **JavaScript API calls** (`core/api.js`) resolve root-relative paths (`/admin/...`) against `window.__BASE_URL__`, which is set from `body[data-base-url]` (PHP's `base_url()`) at page load. This means the updater's AJAX calls will use the correct subdirectory prefix automatically once `app.baseURL` is correct.

   Prior to v2.0.15, `core/api.js` passed endpoints directly to `fetch()` without prepending the base URL, causing 404 on all subdirectory installs.

---

## Rollback

Rollback scope: `app/` + `public/` (restored from shadow ZIP) + DB (restored from SQL dump).

**Not covered:** `vendor/` and `system/`. For framework-level regressions, re-upload the previous release ZIP from GitHub Releases and restore the SQL backup from `writable/backups/` manually via phpMyAdmin.

---

## Maintenance Mode

`writable/maintenance.flag` (JSON file). The global `MaintenanceFilter` runs before every request. Admin sessions bypass it via the `roles` array check. No auto-expiry — must be disabled explicitly or automatically cleared by a successful update or rollback.

---

## Troubleshooting

| Error | Cause | Fix |
|-------|-------|-----|
| "This package does not contain version.json" | Uploading the GitHub source-code archive instead of the deploy ZIP | Download `webschedulr-vX.X.X-deploy.zip`, not "Source code" |
| "This looks like the GitHub source code archive" | Same — newer validator gives a clearer message | Download the correct deploy ZIP |
| "Cannot install vX over installed vX" | Same version already installed | The ZIP version must be higher than `system.installed_version` in DB |
| `404` on execute/maintenance (subdirectory install) | `app.baseURL` wrong or old JS before v2.0.15 | Fix `.env` baseURL; deploy v2.0.15+ |
| Maintenance mode stuck on | Update failed mid-flight | Use the "Clear Maintenance" emergency button in System Update tab, or `rm writable/maintenance.flag` |
| `escapeStr()` error during backup | CI3 method used in `UpdaterBackupService` | Fixed in v2.0.3 — upgrade |
