# 📝 File Naming Convention & Documentation Standards

**Last Updated:** October 24, 2025  
**Status:** ACTIVE STANDARD  
**Applies To:** All Markdown files in /docs

---

## Naming Convention Rules

### 1. Filename Format

**Rule:** `purpose_or_module_descriptor.md`

**Format:**
- ✅ Lowercase letters only
- ✅ Hyphens to separate words (not underscores)
- ✅ Descriptive and concise (max 50 characters)
- ✅ No spaces, special characters, or numbers (except dates)
- ✅ Purpose-driven naming

**Examples:**

| ❌ INCORRECT | ✅ CORRECT | Reason |
|-------------|-----------|--------|
| Calendar_UI_Improvements.md | calendar-ui-improvements.md | Lowercase, hyphens |
| PROVIDER_SERVICE_BINDING_FIX.md | provider-service-binding.md | No caps, shorter |
| notes 2.md | project-notes-201024.md | Date format, clear purpose |
| BugFix.md | bug-fixes-archive.md | No camelCase, descriptive |
| API DOCS.md | api-reference.md | No spaces, lowercase |
| DB_Config.md | database-configuration.md | Full words, lowercase |

---

## Directory Structure

### Root Level (/docs/)

Only high-level index files:

```
/docs/
├── README.md                          → Main documentation index
├── FILE_NAMING_CONVENTION.md          → This document
└── DOCUMENT_AUDIT_SUMMARY.md          → Audit trail
```

### Organized Subfolders

**Recommended structure:**

```
/docs/
├── development/                       → Dev guides, setup, architecture
│   ├── calendar-implementation.md
│   ├── provider-system-guide.md
│   ├── project-phases.md
│   ├── setup-guide.md
│   ├── features-guide.md
│   ├── architecture-overview.md
│   └── api-reference.md
│
├── ui-ux/                            → Design, styling, UI components
│   ├── calendar-ui-overview.md
│   ├── calendar-ui-quickref.md
│   ├── design-system.md
│   └── color-palette.md
│
├── database/                         → Database schema, migrations
│   ├── database-schema.md
│   ├── backup-and-maintenance.md
│   └── query-optimization.md
│
├── configuration/                    → Config files and setup
│   ├── environment-configuration.md
│   ├── settings-guide.md
│   └── localization.md
│
├── deployment/                       → Production & deployment
│   ├── production-setup.md
│   ├── mysql-connection-setup.md
│   ├── url-configuration.md
│   └── deployment-checklist.md
│
├── security/                         → Security & compliance
│   ├── security-implementation.md
│   ├── security-status.md
│   └── encryption-guide.md
│
├── technical/                        → Technical guides & fixes
│   ├── spa-settings-fix.md
│   ├── icon-display-fix.md
│   ├── commands-reference.md
│   └── troubleshooting.md
│
├── testing/                          → Test plans & verification
│   ├── test-plan.md
│   ├── test-results.md
│   └── quality-assurance.md
│
├── architecture/                     → Architecture docs (existing)
│   ├── mastercontext.md
│   ├── role-based-system.md
│   └── multi-tenant-architecture.md
│
└── archive/                          → Deprecated/historical docs
    ├── bug-fixes.md                  (Consolidated historical fixes)
    ├── troubleshooting-guides.md     (Resolved issues)
    └── legacy-documentation.md       (Deprecated features)
```

---

## File Naming Examples by Category

### Development Documentation

| Purpose | Filename | Location |
|---------|----------|----------|
| Calendar system | `calendar-implementation.md` | development/ |
| Provider system | `provider-system-guide.md` | development/ |
| Project phases | `project-phases.md` | development/ |
| Setup guide | `setup-guide.md` | development/ |
| API reference | `api-reference.md` | development/ |
| Architecture | `architecture-overview.md` | development/ |

### UI/UX Documentation

| Purpose | Filename | Location |
|---------|----------|----------|
| Calendar UI | `calendar-ui-overview.md` | ui-ux/ |
| Quick reference | `calendar-ui-quickref.md` | ui-ux/ |
| Design system | `design-system.md` | ui-ux/ |
| Color palette | `color-palette.md` | ui-ux/ |

### Database Documentation

| Purpose | Filename | Location |
|---------|----------|----------|
| Database schema | `database-schema.md` | database/ |
| Backup & maintenance | `backup-and-maintenance.md` | database/ |
| Query optimization | `query-optimization.md` | database/ |

### Configuration Documentation

| Purpose | Filename | Location |
|---------|----------|----------|
| Environment setup | `environment-configuration.md` | configuration/ |
| Settings guide | `settings-guide.md` | configuration/ |
| Localization | `localization-settings.md` | configuration/ |

### Deployment Documentation

| Purpose | Filename | Location |
|---------|----------|----------|
| Production setup | `production-setup.md` | deployment/ |
| Database connection | `mysql-connection-setup.md` | deployment/ |
| URL configuration | `url-configuration.md` | deployment/ |
| Deployment checklist | `deployment-checklist.md` | deployment/ |

### Security Documentation

| Purpose | Filename | Location |
|---------|----------|----------|
| Implementation guide | `security-implementation.md` | security/ |
| Security status | `security-status.md` | security/ |
| Encryption guide | `encryption-guide.md` | security/ |

### Technical Documentation

| Purpose | Filename | Location |
|---------|----------|----------|
| Bug fix | `spa-settings-fix.md` | technical/ |
| Icon display fix | `icon-display-fix.md` | technical/ |
| Commands | `commands-reference.md` | technical/ |
| Troubleshooting | `troubleshooting.md` | technical/ |

### Testing Documentation

| Purpose | Filename | Location |
|---------|----------|----------|
| Test plan | `test-plan.md` | testing/ |
| Test results | `test-results.md` | testing/ |
| QA | `quality-assurance.md` | testing/ |

### Archive Documentation

| Purpose | Filename | Location |
|---------|----------|----------|
| Bug fixes (historical) | `bug-fixes.md` | archive/ |
| Troubleshooting (resolved) | `troubleshooting-guides.md` | archive/ |
| Legacy docs | `legacy-documentation.md` | archive/ |

---

## File Header Standards

### Markdown File Header Template

```markdown
# Document Title

**Last Updated:** October 24, 2025  
**Status:** Active | Draft | Deprecated | Archived  
**Applies To:** Components/modules affected  
**Related Docs:** Links to related documentation  

---

## Overview/Executive Summary

[Brief introduction - 2-3 sentences]

---

## Table of Contents

[Auto-generated TOC if > 5 sections]

---

## Section 1

Content...

---

## Related Documentation

- [Related Doc 1](../folder/related-doc.md)
- [Related Doc 2](../folder/related-doc.md)

---

**Last Updated:** October 24, 2025  
**Status:** [Status]  
**Next Review:** [Date if applicable]
```

### Metadata Fields

| Field | Purpose | Example |
|-------|---------|---------|
| **Last Updated** | Version tracking | October 24, 2025 |
| **Status** | Document state | Active, Draft, Deprecated, Archived |
| **Applies To** | Scope | Calendar module, UI/UX, Backend |
| **Related Docs** | Cross-references | Links to related docs |

---

## Status Definitions

### Document Statuses

| Status | Meaning | Action |
|--------|---------|--------|
| ✅ **Active** | Current, maintained, referenced | Use in development |
| 📝 **Draft** | Work in progress, incomplete | Review before use |
| ⏳ **In Review** | Pending approval | Don't reference yet |
| ⚠️ **Deprecated** | Outdated but preserved | Use archive version instead |
| 🗃️ **Archived** | Historical, reference only | Link from archive/ |

---

## Linking Convention

### Internal Link Format

```markdown
[Link Text](../relative/path/filename.md)
```

**Examples:**

```markdown
# Valid Links
- [Calendar Implementation](../development/calendar-implementation.md)
- [Provider System Guide](../development/provider-system-guide.md)
- [Bug Fixes Archive](../archive/bug-fixes.md)
- [API Reference](../development/api-reference.md)

# Invalid Links
❌ [Calendar](./CALENDAR_IMPLEMENTATION.md)
❌ [Setup Guide](setup_guide.md)
❌ [Docs](../../docs/readme.md)
```

### Anchor Links (Table of Contents)

```markdown
## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Architecture Overview](#architecture-overview)
3. [Implementation Details](#implementation-details)

---

## Executive Summary

[Content]

---

## Architecture Overview

[Content]

---

## Implementation Details

[Content]
```

---

## Consolidation Guidelines

### When to Consolidate Files

✅ **Consolidate if:**
- Files cover the same topic (e.g., multiple calendar docs)
- One file is >70% duplicate of another
- Both files are not standalone references
- Consolidation improves discoverability
- Content is naturally related (provider + staff system)

❌ **Keep Separate if:**
- Serve different purposes (reference vs guide)
- Each >500 lines (split for readability)
- Different audiences (user vs developer)
- Both are commonly referenced independently
- Technical depth differs significantly

### Consolidation Process

1. **Identify Overlapping Content**
   - Read all related files
   - Note duplicated sections
   - Identify unique content from each

2. **Create Master Document**
   - Merge all unique content
   - Remove duplicates
   - Reorganize by topic (not source)
   - Use consistent heading structure

3. **Archive Original Files**
   - Move to `/docs/archive/`
   - Add deprecation notice at top
   - Preserve for historical reference

4. **Update References**
   - Find all links to old files
   - Update to point to new consolidated file
   - Update README.md index

5. **Commit with Clear Message**
   ```
   chore(docs): consolidate [topic] documentation
   
   - Merged X related files into Y master document
   - Moved originals to archive/
   - Updated cross-references
   - Applied naming convention
   ```

---

## Content Standards

### Markdown Best Practices

1. **Use consistent heading hierarchy**
   ```markdown
   # H1 - Document Title
   ## H2 - Main Sections
   ### H3 - Subsections
   #### H4 - Details
   ```

2. **Use code blocks with language specification**
   ```markdown
   ```php
   // PHP code
   ```

   ```sql
   -- SQL code
   ```

   ```javascript
   // JavaScript code
   ```
   ```

3. **Use tables for structured data**
   ```markdown
   | Column 1 | Column 2 | Column 3 |
   |----------|----------|----------|
   | Data 1   | Data 2   | Data 3   |
   ```

4. **Use lists for enumeration**
   ```markdown
   - Unordered item 1
   - Unordered item 2
   
   1. Ordered item 1
   2. Ordered item 2
   ```

5. **Use blockquotes for important notes**
   ```markdown
   > **Note:** This is important information
   
   > **Warning:** Be careful with this step
   ```

---

## Search & Discovery

### File Naming for Discoverability

**Use clear, descriptive names:**
- ✅ `calendar-implementation.md` (searchable: calendar)
- ❌ `cal_impl.md` (not searchable)

**Include common variations:**
- `provider-system-guide.md` (keywords: provider, staff, assignment)
- `calendar-ui-overview.md` (keywords: calendar, UI, design)

**Folder organization improves discovery:**
- `development/` → for dev-related docs
- `ui-ux/` → for design docs
- `deployment/` → for production docs

---

## Maintenance & Review

### Regular Audits
- **Quarterly:** Review for outdated content
- **Semi-annual:** Check all cross-references
- **Annual:** Full content audit and cleanup

### Deprecation Process
1. Add deprecation notice at top
2. Link to replacement document
3. Move to archive/ after 3 months
4. Update all references

### Version Control
- Use git commit messages to track doc changes
- Include issue number if applicable
- Use clear, descriptive commit messages

---

## Checklist for New Documentation

- [ ] Filename follows convention (lowercase, hyphens)
- [ ] File placed in appropriate folder
- [ ] Header with status and metadata
- [ ] Table of contents (if >5 sections)
- [ ] Related docs section
- [ ] Clear heading hierarchy
- [ ] Code examples with syntax highlighting
- [ ] Internal links checked
- [ ] No duplicate content from other docs
- [ ] GitHub-friendly formatting (no HTML tables)
- [ ] Consistent with project tone
- [ ] Ready for public review

---

## Questions & Clarifications

**Q: Why lowercase and hyphens?**  
A: GitHub renders these best, they're URL-friendly, and consistent with code conventions.

**Q: How deep should folder structure be?**  
A: Max 3 levels (docs → category → specific topic). Keep it navigable.

**Q: Should I use underscores or hyphens?**  
A: Hyphens only. Underscores are harder to read and conflict with PHP/database conventions.

**Q: What about dates in filenames?**  
A: Avoid unless documenting a specific incident (e.g., `2025-10-24-incident.md`).

**Q: Can I use numbers in filenames?**  
A: Only for version numbers or dates (e.g., `api-reference-v2.md`).

---

## Related Standards

- [Document Audit Summary](./DOCUMENT_AUDIT_SUMMARY.md)
- [Main Documentation Index](./README.md)
- Git Commit Standards (see project CONTRIBUTING.md)
- Code Style Guide (see project README.md)

---

**Last Updated:** October 24, 2025  
**Status:** Active ✅  
**Enforced Since:** October 24, 2025

