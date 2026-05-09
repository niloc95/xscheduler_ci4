# Material 3 Design System (Scheduler)

## Scope

This document covers scheduler-facing Material 3 style contracts currently reflected in frontend code.

## Surface Model

Surface classes are bridged in tailwind.config.js:

- bg-surface-0: base/page level
- bg-surface-1: container level
- bg-surface-2: elevated card level
- bg-surface-3: hover/interaction surface

Complementary semantic classes are available for MD3 tokens:

- bg-background, text-on-background
- text-on-surface, text-on-surface-variant
- border-outline, border-outline-variant

## Theme Contract

Dark mode selector contract is attribute-based:

- html[data-theme="dark"]

Tailwind dark variant is configured to this selector.

Compatibility note:

- runtime also toggles html.dark for legacy compatibility paths

## Scheduler State Colors

Scheduler-specific semantic color groups exist in tailwind.config.js:

- appointment.confirmed
- appointment.pending
- appointment.cancelled
- appointment.completed
- appointment.rescheduled

Priority groups:

- priority.low
- priority.medium
- priority.high
- priority.critical

Rule:

- prefer semantic state classes or scheduler color helper modules over hardcoded values.

## Spacing and Density

- use Tailwind spacing scale
- use consistent rhythm across scheduler controls, cards, and slot blocks
- avoid arbitrary spacing unless tied to a documented layout constraint

## Typography

Primary scheduler typography follows global stack:

- Inter, system-ui, sans-serif

Use utility hierarchy:

- strong emphasis for core appointment identity fields
- subdued contrast for metadata and secondary lines

## Interaction and Accessibility

- preserve hover/focus affordances on appointment and slot interactions
- keep keyboard navigation states visible
- maintain sufficient text/background contrast in light and dark themes
