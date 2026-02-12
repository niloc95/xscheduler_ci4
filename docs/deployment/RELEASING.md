# Releasing xScheduler

This guide explains how to create and manage releases for xScheduler.

## 📋 Table of Contents

- [Release Process](#release-process)
- [Versioning](#versioning)
- [Creating a Release](#creating-a-release)
- [Release Checklist](#release-checklist)
- [Automated Release Workflow](#automated-release-workflow)
- [Manual Release](#manual-release)
- [Post-Release Tasks](#post-release-tasks)

---

## Release Process

xScheduler uses GitHub Releases with automated workflows for consistent, high-quality releases.

### Release Cadence

- **Major Releases** (X.0.0) - Quarterly or when major features complete
- **Minor Releases** (0.X.0) - Monthly or when new features are ready
- **Patch Releases** (0.0.X) - As needed for bug fixes

---

## Versioning

We follow [Semantic Versioning 2.0.0](https://semver.org/):

### Version Format: `MAJOR.MINOR.PATCH`

- **MAJOR** - Incompatible API changes or significant breaking changes
- **MINOR** - New functionality in a backwards-compatible manner
- **PATCH** - Backwards-compatible bug fixes

### Pre-release Versions

- **Alpha**: `v1.0.0-alpha.1` - Early development, unstable
- **Beta**: `v1.0.0-beta.1` - Feature complete, testing phase
- **Release Candidate**: `v1.0.0-rc.1` - Final testing before stable

### Examples

```
v1.0.0        # Stable release
v1.1.0        # Minor feature update
v1.1.1        # Patch/bug fix
v2.0.0        # Major version with breaking changes
v1.2.0-beta.1 # Beta release for testing
v1.2.0-rc.1   # Release candidate
```

---

## Creating a Release

### Prerequisites

- [ ] All planned features/fixes merged to `main`
- [ ] All tests passing on CI
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Version bumped in appropriate files
- [ ] No open critical bugs

### Step 1: Update Version Numbers

Update version in these files:

1. **CHANGELOG.md** - Add new version section
   ```markdown
   ## [1.1.0] - 2026-03-01
   
   ### Added
   - New feature description
   
   ### Fixed
   - Bug fix description
   ```

2. **README.md** - Update version badge if needed

3. **app/Config/Constants.php** - Update APP_VERSION
   ```php
   define('APP_VERSION', '1.1.0');
   ```

### Step 2: Commit Version Changes

```bash
git add CHANGELOG.md README.md app/Config/Constants.php
git commit -m "Bump version to v1.1.0"
git push origin main
```

### Step 3: Create and Push Tag

```bash
# Create annotated tag
git tag -a v1.1.0 -m "Release version 1.1.0"

# Push tag to trigger release workflow
git push origin v1.1.0
```

### Step 4: GitHub Actions Creates Release

The workflow automatically:
1. ✅ Builds production assets
2. ✅ Installs production dependencies
3. ✅ Creates deployment package
4. ✅ Generates changelog from commits
5. ✅ Creates GitHub Release with assets
6. ✅ Marks pre-releases appropriately

### Step 5: Edit Release Notes (Optional)

1. Go to [Releases](https://github.com/niloc95/xscheduler_ci4/releases)
2. Click **Edit** on the new release
3. Add highlights, screenshots, or additional notes
4. Save

---

## Release Checklist

Use this checklist before creating a release:

### Code Quality
- [ ] All tests passing (`composer test`)
- [ ] No linting errors (`npm run lint`)
- [ ] Code reviewed and approved
- [ ] Security scan clean

### Documentation
- [ ] CHANGELOG.md updated with all changes
- [ ] README.md reflects new features
- [ ] API documentation updated (if applicable)
- [ ] Migration guide created (for major versions)

### Testing
- [ ] Manual testing on localhost
- [ ] Testing on staging environment
- [ ] Cross-browser testing completed
- [ ] Mobile responsiveness verified

### Database
- [ ] All migrations tested
- [ ] No pending migrations
- [ ] Rollback tested
- [ ] Seeder data verified

### Dependencies
- [ ] `composer.json` dependencies up to date
- [ ] `package.json` dependencies up to date
- [ ] Security vulnerabilities addressed
- [ ] Breaking changes documented

### Configuration
- [ ] `.env.example` includes new variables
- [ ] Default settings verified
- [ ] Setup wizard tested

### Assets
- [ ] Frontend assets built: `npm run build`
- [ ] CSS compiled correctly
- [ ] JavaScript minified
- [ ] Images optimized

---

## Automated Release Workflow

The GitHub Actions workflow (`.github/workflows/release.yml`) handles:

### Triggers
- Pushing a tag matching `v*.*.*`
- Manual dispatch with tag input

### Steps

1. **Checkout & Setup**
   - Clones repository
   - Sets up PHP 8.1 and Node.js 18

2. **Install Dependencies**
   - `composer install --no-dev --optimize-autoloader`
   - `npm ci`

3. **Build Assets**
   - `npm run build`
   - Compiles and minifies all frontend assets

4. **Create Package**
   - Excludes dev files (tests, node_modules, etc.)
   - Creates optimized deployment zip
   - Generates checksums

5. **Generate Changelog**
   - Extracts commits since last tag
   - Formats changelog with categories
   - Groups by feature/fix/docs

6. **Create Release**
   - Uploads deployment package
   - Attaches checksums
   - Publishes release notes
   - Marks pre-releases

---

## Manual Release

If you need to create a release manually:

### Build Package Locally

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci

# Build assets
npm run build

# Create package
mkdir xscheduler-1.1.0
rsync -av --exclude-from='.github/release-excludes.txt' \
  --exclude='.git' \
  --exclude='node_modules' \
  . xscheduler-1.1.0/

# Create archive
zip -r xscheduler-1.1.0.zip xscheduler-1.1.0/

# Generate checksum
sha256sum xscheduler-1.1.0.zip > checksums.txt
```

### Create Release on GitHub

1. Go to [Releases](https://github.com/niloc95/xscheduler_ci4/releases)
2. Click **Draft a new release**
3. Choose or create a tag (e.g., `v1.1.0`)
4. Set release title: `xScheduler v1.1.0`
5. Add release notes from CHANGELOG.md
6. Upload `xscheduler-1.1.0.zip` and `checksums.txt`
7. Check **Set as pre-release** if alpha/beta/rc
8. Click **Publish release**

---

## Post-Release Tasks

After publishing a release:

### 1. Verify Release

- [ ] Download package and verify checksum
- [ ] Test installation on clean environment
- [ ] Verify all assets load correctly
- [ ] Check that migrations run

### 2. Update Documentation

- [ ] Add release to CHANGELOG.md links
- [ ] Update any version-specific docs
- [ ] Create upgrade guide (for major versions)

### 3. Announce Release

- [ ] Create announcement in GitHub Discussions
- [ ] Update project website (if applicable)
- [ ] Social media announcement (if applicable)
- [ ] Email notification to subscribers (if applicable)

### 4. Monitor

- [ ] Watch for bug reports
- [ ] Monitor GitHub Issues
- [ ] Check analytics/error logs
- [ ] Prepare hotfix if needed

---

## Hotfix Releases

For critical bugs in production:

### Fast-Track Process

1. **Create hotfix branch**
   ```bash
   git checkout -b hotfix/v1.0.1 v1.0.0
   ```

2. **Fix the bug**
   ```bash
   # Make fixes
   git commit -m "Fix critical bug in X"
   ```

3. **Test thoroughly**
   - Verify fix works
   - Ensure no new bugs introduced
   - Test on staging

4. **Merge to main**
   ```bash
   git checkout main
   git merge hotfix/v1.0.1
   git push origin main
   ```

5. **Tag and release**
   ```bash
   git tag -a v1.0.1 -m "Hotfix: Fix critical bug in X"
   git push origin v1.0.1
   ```

6. **Update CHANGELOG.md**
   ```markdown
   ## [1.0.1] - 2026-02-15
   
   ### Fixed
   - Critical bug description
   ```

---

## Release Notes Template

Use this template for release notes:

```markdown
## xScheduler v1.1.0

### 🎉 Highlights

- Brief description of major features
- Key improvements users will notice

### ✨ New Features

- Feature 1 description
- Feature 2 description

### 🐛 Bug Fixes

- Bug fix 1
- Bug fix 2

### 📚 Documentation

- Documentation improvements

### ⚠️ Breaking Changes

- List any breaking changes (for major versions)
- Migration instructions

### 📦 Installation

1. Download `xscheduler-v1.1.0.zip`
2. Extract to your server
3. Run `composer install --no-dev`
4. Configure `.env` file
5. Run `php spark migrate`

### 🔒 Security

**Verify your download:**
```bash
sha256sum xscheduler-v1.1.0.zip
# Should match: [checksum]
```

### 📖 Resources

- [Installation Guide](...)
- [Upgrade Guide](...)
- [Documentation](...)

### 🙏 Contributors

Thanks to everyone who contributed!
```

---

## Troubleshooting

### Release Workflow Failed

1. Check GitHub Actions logs
2. Verify tag format is correct (`v*.*.*`)
3. Ensure all tests pass
4. Check if PHP/Node versions match workflow

### Package Size Too Large

1. Check `.github/release-excludes.txt`
2. Ensure `node_modules` excluded
3. Verify test files excluded
4. Remove unnecessary documentation

### Changelog Not Generating

1. Ensure commits follow conventional format
2. Check if tags exist for comparison
3. Verify `.github/release-config.json` is correct

---

## Best Practices

1. **Test Before Tagging** - Always test on staging first
2. **Clear Commit Messages** - Write descriptive commit messages
3. **Update CHANGELOG** - Keep CHANGELOG.md current
4. **Semantic Versioning** - Follow SemVer strictly
5. **Pre-releases for Testing** - Use beta/rc for user testing
6. **Security First** - Never release with known security issues
7. **Backup First** - Users should backup before upgrading
8. **Document Breaking Changes** - Clearly mark breaking changes

---

## Questions?

- **Release Issues** → [GitHub Issues](https://github.com/niloc95/xscheduler_ci4/issues)
- **General Questions** → [GitHub Discussions](https://github.com/niloc95/xscheduler_ci4/discussions)
- **Security** → See [SECURITY.md](../SECURITY.md)

---

**Last Updated**: February 2, 2026  
**Workflow File**: `.github/workflows/release.yml`  
**Changelog**: `CHANGELOG.md`
