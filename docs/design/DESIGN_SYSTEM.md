# WebScheduler Design System

Last Updated: 2026-05-09
Status: Active
Applies to: App-facing views in app/Views (excluding framework error templates and email templates)

## Purpose

This document is the implementation-aligned source of truth for visual conventions across WebScheduler.

## Token and Theme Sources

- Core custom properties: resources/scss/abstracts/_custom-properties.scss
- Tailwind token bridge: tailwind.config.js
- Main stylesheet entry: resources/scss/app-consolidated.scss

## Colors

Tailwind extends semantic and brand colors in tailwind.config.js.

Use semantic utilities first:

- bg-background, text-on-background
- bg-primary, text-on-primary
- bg-secondary, text-on-secondary
- bg-surface-0 to bg-surface-3
- border-outline and border-outline-variant
- text-on-surface and text-on-surface-variant

Status/domain palettes also exist:

- appointment.confirmed, appointment.pending, appointment.cancelled, appointment.completed, appointment.rescheduled
- priority.low, priority.medium, priority.high, priority.critical

Rule:

- Do not hardcode hex values in app-facing templates.

## Typography

Primary font stack is configured via Tailwind as Inter, system-ui, sans-serif.

Use utility scale consistently:

- Page title: text-lg to text-2xl with font-bold
- Section title: text-lg or text-xl with font-semibold
- Body: text-sm or text-base
- Meta labels and hints: text-xs or text-sm with reduced contrast classes

## Spacing and Layout

Use Tailwind spacing utilities and established layout primitives in layouts/app.php and shared SCSS.

Recommended spacing rhythm:

- Compact controls and inline groups: gap-2 to gap-3
- Form and card internals: p-4 to p-6
- Section stacking: space-y-4 to space-y-6

## Canonical Shared Components

Primary reusable components in app/Views/components:

- button.php
- input.php
- select.php
- status_badge.php
- card.php
- dark-mode-toggle.php
- unified-sidebar.php

Rule:

- Prefer these components over repeated custom markup when the pattern already exists.

## Dark Mode Contract

Dark mode manager: resources/js/dark-mode.js

Runtime behavior:

- Canonical state key: localStorage xs-theme
- Canonical DOM state: html[data-theme="dark" or "light"]
- Compatibility class also toggled: html.dark

Tailwind dark variant selector is configured as [data-theme="dark"] in tailwind.config.js.

Rule:

- New styles must work when data-theme changes.

## Material Icons Contract

Material Symbols are loaded via Google Fonts in active layouts.

Supported usage patterns:

- Preferred: span with class material-symbols-outlined and icon text name
- Alternate utility system: .material-icon and .icon helpers from resources/scss/utilities/_icons.scss

Do not reference non-existent layout files for icon setup.

## Anti-Patterns

- Inline style attributes in app-facing views
- Hardcoded color values in templates when semantic utility exists
- Duplicating component markup that already exists as shared component
- Creating new dark-mode mechanisms outside dark-mode.js
- Referencing stale component paths that do not exist in app/Views/components

## Contribution Checklist

For UI changes:

- Use existing shared components where applicable
- Use semantic/tailwind tokenized color utilities
- Verify light and dark theme behavior
- Keep sizing/spacing on standard utility scale

## Related Docs

- docs/design/material-3-design-system.md
- docs/design/MATERIAL_ICONS_USAGE.md
- docs/dark-mode/DARK_MODE_IMPLEMENTATION.md
