# Deployment Packaging and Release Guide

This guide documents the deployment and release behavior implemented in the current codebase.

## Scope

This document covers:

- Frontend production builds
- Deployment package generation
- Automated release creation
- CI deployment artifact generation

## Command Reference

The following commands are defined in package.json:

| Command | Implementation | Result |
| --- | --- | --- |
| npm run build | vite build | Builds production frontend assets to public/build |
| npm run package | npm run build && node scripts/package.js | Rebuilds assets and creates deployment directory plus ZIP archives |
| npm run package:local | node scripts/package.js | Creates deployment directory plus ZIP archives without rebuilding assets |
| npm run package:archive | npm run build && node scripts/package.js --archive-logs | Same as package, plus log/debugbar archival before cleanup |
| npm run release | node scripts/release.js patch | Full release flow with patch bump |
| npm run release:minor | node scripts/release.js minor | Full release flow with minor bump |
| npm run release:major | node scripts/release.js major | Full release flow with major bump |
| npm run release:beta | node scripts/release.js --beta | Full prerelease flow |
| npm run release:rc | node scripts/release.js --rc | Full prerelease flow |
| npm run release:dry | node scripts/release.js --dry-run | No-write simulation of release flow |
| npm run changelog:preview | node scripts/release.js --sync-changelog --dry-run | Preview generated Unreleased changelog content |
| npm run changelog:sync | node scripts/release.js --sync-changelog | Regenerates Unreleased changelog section |

## Packaging Implementation

Packaging is implemented in scripts/package.js.

### Versioning and output names

- Increments .deploy-version on every package run
- Builds deployment directory at webschedulr-deploy
- Creates two ZIP files at project root:
  - webschedulr-deploy-v{deployVersion}.zip
  - webschedulr-deploy.zip (copied from the versioned ZIP for compatibility)

### Source content copied into deployment directory

The script copies these sources:

- app
- writable
- vendor
- public
- spark
- preload.php
- .env.example
- vendor/codeigniter4/framework/system to system

### Cleanup and exclusion rules

For app:

- Excludes app/Views/test

For writable:

- Removes setup_completed.flag and setup_complete.flag
- Removes writable/database/*.db
- Removes writable/logs/*.log
- Removes writable/debugbar/*.json
- Removes writable/cache/*
- Removes writable/session/*
- Removes writable/backups/*
- Removes writable/exports/*
- Removes writable/upload-debug.log
- Recreates clean runtime directories and adds .gitkeep files in:
  - logs
  - debugbar
  - cache
  - session
  - uploads
  - backups
  - exports

### Package-time configuration mutations

The script mutates configuration files inside webschedulr-deploy only:

- app/Config/Paths.php:
  - systemDirectory set to ../../system
  - appDirectory set to ..
  - writableDirectory set to ../../writable
  - testsDirectory set to ../../tests
  - viewDirectory set to ../Views

- app/Config/App.php:
  - baseURL set to empty string
  - indexPage set to empty string
  - inserts constructor-based URL auto-detection if constructor is missing

- app/Config/Encryption.php:
  - replaces empty key with a hardcoded hex2bin key value

### Environment file handling

- Removes any copied .env first
- Verifies .env.example exists
- Copies .env.example to .env in deployment output
- If .env.example is missing, writes a fallback .env template

### .htaccess and deployment docs generated in package

The script writes:

- public/.htaccess
- root .htaccess
- DEPLOY-README.md
- QUICK-DEPLOY.md

### Validation and ZIP metadata

Before archiving, the script validates required files in the deployment directory:

- public/index.php
- app/Config/App.php
- app/Config/Paths.php
- app/Controllers/Setup.php
- app/Views/setup.php
- system/Boot.php
- writable
- .env
- public/.htaccess

ZIP also includes DEPLOYMENT-INFO.txt with:

- deployment package version
- creation timestamp
- current git branch
- current short commit hash

## Release Script Implementation

Release flow is implemented in scripts/release.js.

### Preconditions

By default, release requires:

- current branch is main
- clean git working tree

Both checks can be bypassed with --force.

### Default patch release flow

For npm run release:

1. Pulls latest changes from origin/main (best-effort)
2. Computes new semver version and tag
3. Generates changelog content from merged PR metadata (GitHub API token) or falls back to commit subjects
4. Prompts for interactive confirmation
5. Runs npm run build unless --skip-build is set
6. Runs node scripts/package.js unless --skip-package is set
7. Updates package.json version
8. Updates docs/changelog.md:
   - creates release section for new version
   - resets Unreleased section to placeholder
   - updates compare links
9. Commits package.json and docs/changelog.md
10. Creates annotated git tag v{version}
11. Pushes main and tag

### Supported release options

- --version=x.y.z for explicit version
- --beta, --rc, --alpha for prerelease bumps
- --dry-run for no-write simulation
- --skip-build to skip frontend build
- --skip-package to skip local package creation
- --sync-changelog to update only docs/changelog.md Unreleased section

## GitHub Actions: Release Workflow

Release workflow is defined in .github/workflows/release.yml.

### Triggers

- Push tag matching v*.*.*
- Manual dispatch with tag input

### Behavior

1. Sets up Node 18 and PHP 8.1
2. Installs dependencies with npm ci and composer install --no-dev
3. Runs npm run build
4. Runs npm run package:local
5. Renames webschedulr-deploy.zip to webschedulr-{tag}-deploy.zip
6. Creates GitHub Release and uploads:
   - webschedulr-{tag}-deploy.zip
   - docs/deployment/MYSQL-TEST-CONNECTION-FIX.md
   - docs/deployment/PRODUCTION-URL-AUTO-DETECTION.md

## GitHub Actions: CI Deployment Package Job

CI artifact packaging is defined in .github/workflows/ci-cd.yml job create-deployment-package.

### Behavior

1. Runs on main and env-setup-config-build branches
2. Installs dependencies and runs npm run build
3. Runs npm run package
4. Validates webschedulr-deploy.zip existence, expected directory entries, and minimum size
5. Uploads:
   - webschedulr-deploy.zip plus deployment-info.md
   - webschedulr-deploy directory artifact

## Operational Notes

- The deployment package is generated for MySQL/MariaDB setup flow.
- setup completion flags are removed from package output so setup can run on fresh deployment.
- Packaging intentionally creates clean runtime directories rather than shipping runtime state.
- Release script depends on local git state and interactive confirmation for non-dry runs.

## Recommended Usage

### Local deployment package test

~~~bash
npm run package
~~~

### Local package with archived logs

~~~bash
npm run package:archive
~~~

### Standard patch release

~~~bash
npm run release
~~~

### Release dry run

~~~bash
npm run release:dry
~~~
