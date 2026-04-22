# Development Documentation

This directory contains developer guides, standards, and reference documentation for WebSchedulr.

## 📚 Documentation Index

### Layout System
- [**Unified Layout System**](./UNIFIED_LAYOUT_SYSTEM.md) — Full API reference: layout variants, cards, page headers, buttons, forms, grids, typography
- [**Layout Quick Reference**](./LAYOUT_QUICK_REFERENCE.md) — Copy-paste snippets and class lookups for daily development work

### Codebase Reference
- [**Quick Reference**](./QUICK_REFERENCE.md) — Where to find things: controllers, models, views, common workflows
- [**Provider System Guide**](./provider_system_guide.md) — Provider colour system, staff assignment, service-provider binding, DB schema

### Standards & Templates
- [**File Header Template**](./FILE_HEADER_TEMPLATE.md) — Standardised header comments for PHP, JS, View, and SCSS files
- [**File Naming Convention**](./file-naming-convention.md) — Lowercase hyphen naming rules for all docs and files

### Features
- [**Dynamic Customer Fields**](./dynamic-customer-fields.md) — How `BookingSettingsService` drives conditional customer form fields from booking settings
- [**Sample Data**](./SAMPLE_DATA.md) — `SchedulingSampleDataSeeder`: what it creates and how to run it

## 🚀 Quick Start

### Building a New View?

1. **Choose layout**: Standard (`layouts/app`) or Dashboard (`layouts/dashboard`)
2. **Copy pattern** from [Layout Quick Reference](./LAYOUT_QUICK_REFERENCE.md)
3. **Use components**: `page-header`, `card`
4. **Use CSS classes**: `.xs-btn`, `.xs-form-input`, etc.
5. **No custom HTML/CSS** — reuse system components

### Migrating Existing View?

Follow the checklist in [Layout Quick Reference](./LAYOUT_QUICK_REFERENCE.md) — test responsive + dark mode after each view.

### Need Component Details?

See [Unified Layout System](./UNIFIED_LAYOUT_SYSTEM.md) for full API documentation.

---

## 🎯 Core Principles

1. **Single Source of Truth** — All layout/spacing/typography comes from the unified system. No custom styles per page.
2. **Component-First** — Use `page-header` and `card` PHP components, not custom HTML.
3. **Layout Variants** — Choose `standard` or `dashboard`. Don't override spacing per page.
4. **Semantic CSS Classes** — Use `.xs-btn-primary`, not raw Tailwind utilities on buttons/cards.
5. **No Inline Spacing** — Layout and components handle all section/card spacing automatically.

