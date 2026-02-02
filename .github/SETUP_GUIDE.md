# GitHub Repository Setup Guide

This guide will help you enable and configure GitHub Issues, Discussions, and labels for xScheduler.

## 📋 Prerequisites

- Admin access to the repository: `https://github.com/niloc95/xscheduler_ci4`
- GitHub CLI installed (optional, for automated label creation)

---

## ✅ Step 1: Enable GitHub Issues

GitHub Issues should already be enabled by default, but verify:

1. Go to **Settings** → **General**
2. Scroll to **Features** section
3. Ensure **Issues** checkbox is ✅ **checked**
4. Click **Save changes** if needed

---

## ✅ Step 2: Enable GitHub Discussions

1. Go to **Settings** → **General**
2. Scroll to **Features** section
3. Check the **Discussions** checkbox ✅
4. Click **Save changes**

### Configure Discussion Categories

After enabling Discussions, set up these categories:

1. Go to **Discussions** tab
2. Click **Categories** (pencil icon)
3. Create/modify these categories:

| Category | Description | Format |
|----------|-------------|--------|
| **💬 General** | General questions and discussions | Discussion |
| **❓ Q&A** | Ask questions and get answers | Q&A |
| **💡 Ideas** | Share ideas for new features | Discussion |
| **🛠️ Installation Help** | Get help with setup and installation | Q&A |
| **📢 Announcements** | Project updates and news | Announcement |
| **🎉 Show and Tell** | Share your xScheduler setup | Discussion |

---

## ✅ Step 3: Verify Issue Templates

The issue templates are already created in `.github/ISSUE_TEMPLATE/`:
- ✅ `bug_report.yml` - Bug Report template
- ✅ `feature_request.yml` - Feature Request template
- ✅ `config.yml` - Issue template configuration

These will automatically appear when users create new issues.

**Test it:**
1. Go to **Issues** → **New Issue**
2. You should see both templates as options
3. Links to Discussions and Documentation should appear

---

## ✅ Step 4: Create GitHub Labels

### Option A: Manual Label Creation

Go to **Issues** → **Labels** and create these labels:

#### Priority Labels
| Label | Color | Description |
|-------|-------|-------------|
| `priority: high` | `#d73a4a` (red) | Critical issue requiring immediate attention |
| `priority: medium` | `#fbca04` (yellow) | Important but not critical |
| `priority: low` | `#0e8a16` (green) | Nice to have, low urgency |

#### Status Labels
| Label | Color | Description |
|-------|-------|-------------|
| `needs-info` | `#d876e3` (purple) | Waiting for more information from reporter |
| `confirmed` | `#0e8a16` (green) | Bug confirmed and ready to fix |
| `in-progress` | `#1d76db` (blue) | Currently being worked on |
| `duplicate` | `#cfd3d7` (gray) | Duplicate of another issue |
| `wontfix` | `#ffffff` (white) | Will not be fixed |

#### Type Labels (should already exist)
| Label | Color | Description |
|-------|-------|-------------|
| `bug` | `#d73a4a` (red) | Something isn't working |
| `enhancement` | `#a2eeef` (light blue) | New feature or request |
| `documentation` | `#0075ca` (blue) | Improvements or additions to documentation |
| `question` | `#d876e3` (purple) | Further information is requested |

---

### Option B: Automated Label Creation (GitHub CLI)

If you have GitHub CLI installed, run this script:

```bash
#!/bin/bash

# Navigate to repository
cd /Volumes/Nilo_512GB/projects/xscheduler_ci4

# Create priority labels
gh label create "priority: high" --color d73a4a --description "Critical issue requiring immediate attention"
gh label create "priority: medium" --color fbca04 --description "Important but not critical"
gh label create "priority: low" --color 0e8a16 --description "Nice to have, low urgency"

# Create status labels
gh label create "needs-info" --color d876e3 --description "Waiting for more information from reporter"
gh label create "confirmed" --color 0e8a16 --description "Bug confirmed and ready to fix"
gh label create "in-progress" --color 1d76db --description "Currently being worked on"
gh label create "duplicate" --color cfd3d7 --description "Duplicate of another issue"
gh label create "wontfix" --color ffffff --description "Will not be fixed"

echo "✅ Labels created successfully!"
```

Save this as `setup-labels.sh`, make it executable, and run:

```bash
chmod +x setup-labels.sh
./setup-labels.sh
```

---

## ✅ Step 5: Configure Issue Settings

1. Go to **Settings** → **General**
2. Scroll to **Features** → **Issues**
3. Optional: Enable **Allow users to create issues from code**

---

## ✅ Step 6: Add Issue & PR Templates to Repository

The templates are already created! Just commit and push:

```bash
cd /Volumes/Nilo_512GB/projects/xscheduler_ci4

# Stage all changes
git add .github/ISSUE_TEMPLATE/
git add CONTRIBUTING.md
git add README.md

# Commit
git commit -m "Add GitHub issue templates, discussions setup, and CONTRIBUTING.md

- Add bug report template (YAML)
- Add feature request template (YAML)
- Add issue template config with links to Discussions
- Update CONTRIBUTING.md with comprehensive guidelines
- Add Support & Bug Reporting section to README.md"

# Push to main
git push origin main
```

---

## ✅ Step 7: Test the System

### Test Bug Report
1. Go to **Issues** → **New Issue**
2. Select **🐞 Bug Report**
3. Verify all fields appear correctly
4. Submit a test issue
5. Add labels: `bug`, `needs-info`, `priority: medium`

### Test Feature Request
1. Go to **Issues** → **New Issue**
2. Select **✨ Feature Request**
3. Verify all fields appear correctly
4. Submit a test issue
5. Add labels: `enhancement`, `priority: low`

### Test Discussions
1. Go to **Discussions**
2. Create a test discussion in **💬 General**
3. Verify it appears correctly

### Test Documentation Links
1. Go to **Issues** → **New Issue**
2. Verify links to Discussions and Documentation appear at the top

---

## 📊 Label Usage Guide

### When to Use Each Label

**Priority Labels:**
- `priority: high` - Security issues, data loss, app crashes, blocking bugs
- `priority: medium` - Important bugs, commonly requested features
- `priority: low` - Minor bugs, nice-to-have features, cosmetic issues

**Status Labels:**
- `needs-info` - Reporter needs to provide more details
- `confirmed` - Bug has been verified and is ready to fix
- `in-progress` - Someone is actively working on this
- `duplicate` - Same as another issue (link to original)
- `wontfix` - Out of scope or intentional behavior

**Workflow:**
1. New issue arrives → Auto-labeled `needs-info` (from template)
2. Verify issue → Remove `needs-info`, add `confirmed`
3. Start work → Add `in-progress`
4. Complete work → Close issue, reference PR

---

## 🎯 Best Practices

### For Maintainers

1. **Triage new issues within 48 hours**
   - Add appropriate labels
   - Ask for clarification if needed
   - Close duplicates and link to original

2. **Keep issues organized**
   - Use labels consistently
   - Update status labels as work progresses
   - Close stale issues after 30 days of inactivity

3. **Respond to Discussions**
   - Move support requests from Issues to Discussions
   - Mark helpful answers
   - Archive resolved discussions

4. **Security First**
   - Never discuss security vulnerabilities in public issues
   - Direct reporters to SECURITY.md

### For Contributors

1. **Search before creating**
   - Check existing issues and discussions
   - Avoid duplicates

2. **Use the right channel**
   - Bugs → Issues (Bug Report template)
   - Features → Issues (Feature Request template)
   - Questions → Discussions
   - Security → SECURITY.md

3. **Provide complete information**
   - Fill out all template fields
   - Include error logs and screenshots
   - Test on latest version first

---

## 📞 Support Channels Summary

| Issue Type | Where to Go | Template |
|------------|-------------|----------|
| 🐞 Bug Report | [GitHub Issues](https://github.com/niloc95/xscheduler_ci4/issues/new/choose) | Bug Report |
| ✨ Feature Request | [GitHub Issues](https://github.com/niloc95/xscheduler_ci4/issues/new/choose) | Feature Request |
| ❓ Questions | [GitHub Discussions](https://github.com/niloc95/xscheduler_ci4/discussions) | Q&A Category |
| 💡 Ideas | [GitHub Discussions](https://github.com/niloc95/xscheduler_ci4/discussions) | Ideas Category |
| 🛠️ Installation Help | [GitHub Discussions](https://github.com/niloc95/xscheduler_ci4/discussions) | Installation Help |
| 🔒 Security | [SECURITY.md](https://github.com/niloc95/xscheduler_ci4/security/advisories/new) | Private Report |

---

## ✅ Verification Checklist

After setup, verify:

- [ ] GitHub Issues enabled
- [ ] GitHub Discussions enabled with categories
- [ ] Issue templates appear when creating new issue
- [ ] Links to Discussions appear in issue creation
- [ ] All labels created with correct colors
- [ ] CONTRIBUTING.md updated and pushed
- [ ] README.md has Support & Bug Reporting section
- [ ] Test issue created and labeled successfully
- [ ] Test discussion created successfully

---

## 🎉 Setup Complete!

Your bug reporting and feature request system is now fully configured!

**Next Steps:**
1. Create a pinned issue welcoming contributors
2. Create a pinned discussion explaining how to get help
3. Add repository description and topics (Settings → General)
4. Consider adding a CODE_OF_CONDUCT.md

**Recommended Topics to Add:**
- `appointment-scheduling`
- `codeigniter4`
- `php`
- `booking-system`
- `material-design`
- `tailwindcss`

---

**Questions about this setup?** Create a discussion in the [Installation Help](https://github.com/niloc95/xscheduler_ci4/discussions) category!
