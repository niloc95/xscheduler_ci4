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

# Test complete packaging workflow
npm run package
```

### 2. Create Release 🎯

```bash
# Standard release (patch version bump)
npm run release

# Minor version (new features)
npm run release:minor

# Major version (breaking changes)
npm run release:major

# Test first (dry run)
npm run release:dry
```

**What happens automatically:**
1. ✅ Builds production assets (`npm run build`)
2. ✅ Creates deployment package (`node scripts/package.js`)
3. ✅ Bumps version in `package.json`
4. ✅ Updates `docs/changelog.md`
5. ✅ Creates git commit and tag
6. ✅ Pushes to GitHub
7. ✅ Triggers GitHub Actions
8. ✅ GitHub Actions creates release with ZIP artifact

### 3. Verify Release 👀

```bash
# Check git tags
git tag -l

# Monitor GitHub Actions
# https://github.com/niloc95/xscheduler_ci4/actions

# View release
# https://github.com/niloc95/xscheduler_ci4/releases
```

---

## 📦 Standalone Commands

| Command | Purpose |
|---------|---------|
| `npm run build` | Build assets only |
| `npm run package` | Build + create deployment ZIP |
| `npm run package:local` | Create ZIP without rebuilding |
| `npm run release` | Complete release workflow |
| `npm run release:dry` | Test release (no changes) |

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
- [ ] `docs/changelog.md` updated
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

- **Full Guide**: [releasing.md](./releasing.md)
- **Releases**: https://github.com/niloc95/xscheduler_ci4/releases
- **Changelog**: [changelog.md](../changelog.md)
- **Workflow**: [.github/workflows/release.yml](../.github/workflows/release.yml)

---

**Need Help?** See [releasing.md](./releasing.md) for detailed instructions.
