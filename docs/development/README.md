# Layout System Documentation

This directory contains comprehensive documentation for the XScheduler Unified Layout System.

## 📚 Documentation Index

### 1. [**Unified Layout System**](./UNIFIED_LAYOUT_SYSTEM.md) 
**For**: All developers  
**Purpose**: Complete reference documentation  
**Contents**:
- Layout variants (standard vs dashboard)
- All component APIs (cards, page headers, buttons, forms, grids)
- Typography scale
- Spacing rules
- CSS class reference
- CSS variables
- Migration checklist

👉 **Start here** for comprehensive understanding of the system.

---

### 2. [**Migration Example**](./MIGRATION_EXAMPLE.md)
**For**: Developers refactoring existing views  
**Purpose**: Step-by-step migration guide with before/after comparison  
**Contents**:
- Side-by-side code comparison (customer management index)
- Detailed explanations of each change
- Benefits of each change
- Time estimates
- Testing checklist
- Common pitfalls

👉 **Use this** when migrating an existing view to the new system.

---

### 3. [**Layout Quick Reference**](./LAYOUT_QUICK_REFERENCE.md)
**For**: Daily development work  
**Purpose**: Quick copy-paste snippets and lookups  
**Contents**:
- Component usage snippets
- CSS class reference
- Common patterns (CRUD, forms, dashboards)
- Rules to remember (DO/DON'T)
- Migration checklist

👉 **Keep this open** while building views for quick reference.

---

### 4. [**Implementation Summary**](./UNIFIED_SYSTEM_SUMMARY.md)
**For**: Project managers, leads, new team members  
**Purpose**: High-level overview of what was built and why  
**Contents**:
- What was created (SCSS, PHP components, docs)
- Build verification results
- Current migration status
- Next steps
- Success criteria
- Support resources

👉 **Read this** to understand the project scope and status.

---

## 🚀 Quick Start

### Building a New View?

1. **Choose layout**: Standard (`layouts/app`) or Dashboard (`layouts/dashboard`)
2. **Copy pattern** from [Quick Reference](./LAYOUT_QUICK_REFERENCE.md)
3. **Use components**: `page-header`, `card`
4. **Use CSS classes**: `.xs-btn`, `.xs-form-input`, etc.
5. **No custom HTML/CSS** - reuse system components

### Migrating Existing View?

1. **Read** [Migration Example](./MIGRATION_EXAMPLE.md) first
2. **Follow checklist** in Quick Reference
3. **Test** on mobile, tablet, desktop, dark mode
4. **Time estimate**: ~45 minutes per view

### Need Component Details?

**Check** [Unified Layout System](./UNIFIED_LAYOUT_SYSTEM.md) for full API documentation.

---

## 🎯 Core Principles

### 1. Single Source of Truth
All layout, spacing, typography, and component styles come from the unified system. NO custom styles per page.

### 2. Component-First
Use PHP components (`page-header`, `card`) instead of writing HTML. Components ensure consistency.

### 3. Layout Variants, Not Customization
Choose `standard` or `dashboard` layout variant. Don't customize spacing/width per page.

### 4. Semantic CSS Classes
Use `.xs-btn-primary` not `.px-4 .py-2 .bg-blue-600`. Utility classes are for Tailwind, not custom buttons.

### 5. No Inline Spacing
Layout and components handle spacing. Don't add `mt-6`, `mb-4` to cards/sections.

---

## 📦 File Structure

```
resources/scss/
├── layout/
│   ├── _app-layout.scss               # Base app structure
│   └── _unified-content-system.scss   # 🆕 Cards, buttons, forms, grids
└── app-consolidated.scss              # Main entry point

app/Views/
├── components/
│   ├── card.php                       # 🆕 Unified card component
│   └── page-header.php                # 🆕 Page header component
└── layouts/
    ├── app.php                        # ✏️ Updated with layout variants
    └── dashboard.php                  # ✏️ Declares dashboard variant

docs/development/
├── UNIFIED_LAYOUT_SYSTEM.md           # 🆕 Full documentation
├── MIGRATION_EXAMPLE.md               # 🆕 Migration guide
├── LAYOUT_QUICK_REFERENCE.md          # 🆕 Quick reference
├── UNIFIED_SYSTEM_SUMMARY.md          # 🆕 Implementation summary
└── README.md                          # 🆕 This file
```

**Legend**: 🆕 New | ✏️ Modified

---

## ✅ Build Status

**Last Build**: Successful ✅  
**CSS Output**: `public/build/assets/style.css` (176.77 KB)  
**Compiled Classes**: `.xs-card`, `.xs-btn`, `.xs-form-*`, `.xs-grid-*`, etc.

**Build Command**:
```bash
npm run build
```

---

## 📈 Migration Progress

| Category | Files | Status |
|----------|-------|--------|
| System Infrastructure | 7 files | ✅ Complete |
| Documentation | 4 files | ✅ Complete |
| Customer Management | 3 views | ⏳ Not started |
| User Management | 3 views | ⏳ Not started |
| Services | 2 views | ⏳ Not started |
| Settings/Help/Notifications | 3 views | ⏳ Not started |
| Dashboard/Profile | 2 views | ⏳ Not started |

**Target**: Complete migration in 6 weeks

---

## 🆘 Need Help?

### Documentation
1. **Full API**: [Unified Layout System](./UNIFIED_LAYOUT_SYSTEM.md)
2. **Migration**: [Migration Example](./MIGRATION_EXAMPLE.md)
3. **Quick Lookup**: [Quick Reference](./LAYOUT_QUICK_REFERENCE.md)
4. **Overview**: [Implementation Summary](./UNIFIED_SYSTEM_SUMMARY.md)

### Code Examples
- **Components**: `app/Views/components/`
- **SCSS**: `resources/scss/layout/_unified-content-system.scss`
- **Layouts**: `app/Views/layouts/`

### Common Questions

**Q: Which layout should I use?**  
A: Standard (`layouts/app`) for CRUD/settings, Dashboard (`layouts/dashboard`) for stats/analytics.

**Q: Can I customize card styling?**  
A: No. Use the `card` component with variants (`default`, `stat`, `chart`) and body classes (`compact`, `spacious`).

**Q: Can I add custom spacing?**  
A: No. Layout variants handle spacing automatically. For forms, use `.xs-form-group`.

**Q: How do I make buttons smaller?**  
A: Use size variants: `.xs-btn-sm`, `.xs-btn-lg`.

**Q: Can I use Tailwind utility classes?**  
A: For layout/structure yes, but NOT for buttons, cards, typography, or spacing between sections.

---

## 🔄 Workflow

### New Feature
1. Choose layout variant
2. Use `page-header` component
3. Use `card` component for content
4. Use `.xs-btn` for actions
5. Use `.xs-form-*` for forms
6. Test responsive + dark mode

### Bug Fix in View
1. Check if view uses unified system
2. If not, consider migrating while fixing
3. Don't add custom CSS - extend system if needed

### Code Review
1. Verify uses components, not custom HTML
2. No inline spacing on cards/sections
3. Correct layout variant chosen
4. Buttons use `.xs-btn` variants
5. Forms use `.xs-form-*` classes

---

## 🎓 Training Resources

### For New Team Members
1. Read [Implementation Summary](./UNIFIED_SYSTEM_SUMMARY.md) - 15 min
2. Read [Quick Reference](./LAYOUT_QUICK_REFERENCE.md) - 30 min
3. Build one simple view using examples - 1 hour
4. Review [Full Documentation](./UNIFIED_LAYOUT_SYSTEM.md) - 1 hour

**Total**: ~3 hours to full proficiency

### For Experienced Developers
1. Skim [Quick Reference](./LAYOUT_QUICK_REFERENCE.md) - 10 min
2. Reference [Full Documentation](./UNIFIED_LAYOUT_SYSTEM.md) as needed
3. Copy patterns from examples

---

## 📊 Success Metrics

### Code Quality
- [ ] No custom card HTML in views
- [ ] No custom button styles
- [ ] No inline spacing on sections
- [ ] Consistent typography hierarchy
- [ ] All forms use `.xs-form-*`

### User Experience
- [ ] Consistent UI across all pages
- [ ] Responsive on mobile/tablet/desktop
- [ ] Dark mode works everywhere
- [ ] Clear visual hierarchy
- [ ] Proper focus states

### Developer Experience
- [ ] Faster view development
- [ ] Less decision fatigue
- [ ] Easier maintenance
- [ ] Clear documentation
- [ ] Reusable patterns

---

**Maintained By**: XScheduler Development Team  
**Last Updated**: 2024  
**Status**: ✅ System Ready - Begin Migration
