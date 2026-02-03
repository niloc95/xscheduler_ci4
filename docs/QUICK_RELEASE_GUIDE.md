# Quick Release Guide

Fast reference for creating xScheduler releases.

## 🚀 Quick Steps

### 1. Pre-Release Checks ✅

```bash
# Ensure main is up to date
git checkout main
git pull origin main

# Run tests
composer test
npm run lint

# Build assets
npm run build
```

### 2. Update Version 📝

Edit these files:

- `CHANGELOG.md` - Add new version section
- `app/Config/Constants.php` - Update APP_VERSION

```bash
# Commit version bump
git add CHANGELOG.md app/Config/Constants.php
git commit -m "Bump version to v1.1.0"
git push origin main
```

### 3. Create & Push Tag 🏷️

```bash
# Create tag
git tag -a v1.1.0 -m "Release v1.1.0"

# Push tag (triggers release workflow)
git push origin v1.1.0
```

### 4. Monitor Release 👀

Watch GitHub Actions: https://github.com/niloc95/xscheduler_ci4/actions

---

## Version Formats

| Type | Format | Example |
|------|--------|---------|
| **Stable** | `vMAJOR.MINOR.PATCH` | `v1.0.0` |
| **RC** | `vMAJOR.MINOR.PATCH-rc.N` | `v1.0.0-rc.1` |
| **Beta** | `vMAJOR.MINOR.PATCH-beta.N` | `v1.0.0-beta.1` |
| **Alpha** | `vMAJOR.MINOR.PATCH-alpha.N` | `v1.0.0-alpha.1` |

---

## Semantic Versioning

| Change | Bump | Example |
|--------|------|---------|
| Breaking changes | **MAJOR** | 1.0.0 → 2.0.0 |
| New features | **MINOR** | 1.0.0 → 1.1.0 |
| Bug fixes | **PATCH** | 1.0.0 → 1.0.1 |

---

## Hotfix Release

For critical bugs:

```bash
# Create hotfix branch from tag
git checkout -b hotfix/v1.0.1 v1.0.0

# Make fix
git commit -m "Fix critical bug"

# Merge to main
git checkout main
git merge hotfix/v1.0.1

# Tag and push
git tag -a v1.0.1 -m "Hotfix: Critical bug fix"
git push origin main v1.0.1
```

---

## Release Checklist

- [ ] All tests passing
- [ ] CHANGELOG.md updated
- [ ] Version bumped in Constants.php
- [ ] Documentation updated
- [ ] No open critical bugs
- [ ] Tested on staging
- [ ] Tag created and pushed

---

## Troubleshooting

### Workflow Failed
- Check GitHub Actions logs
- Verify tag format is `v*.*.*`
- Ensure tests pass

### Wrong Version Tagged
```bash
# Delete local tag
git tag -d v1.0.0

# Delete remote tag
git push origin :refs/tags/v1.0.0

# Create correct tag
git tag -a v1.0.1 -m "Release v1.0.1"
git push origin v1.0.1
```

---

## Resources

- **Full Guide**: [docs/RELEASING.md](RELEASING.md)
- **Releases**: https://github.com/niloc95/xscheduler_ci4/releases
- **Changelog**: [CHANGELOG.md](../CHANGELOG.md)
- **Workflow**: [.github/workflows/release.yml](../.github/workflows/release.yml)

---

**Need Help?** See [RELEASING.md](RELEASING.md) for detailed instructions.
