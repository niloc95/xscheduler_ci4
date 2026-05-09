# Material Icons Usage Guide

## Scope

This guide documents icon usage currently implemented in WebScheduler.

## Font Loading

Material Symbols are loaded via Google Fonts in active layouts, including:

- app/Views/layouts/app.php
- app/Views/layouts/auth.php
- app/Views/layouts/public.php
- app/Views/layouts/setup.php

Outlined is the primary style. Rounded is available where loaded.

## Recommended Pattern

Use Material Symbols directly in markup:

```html
<span class="material-symbols-outlined">schedule</span>
<span class="material-symbols-outlined">person</span>
<span class="material-symbols-outlined">settings</span>
```

This is the dominant pattern in current JS-rendered and PHP-rendered UI.

## Utility Pattern (Supported)

SCSS utility helpers exist in resources/scss/utilities/_icons.scss:

- .material-icon
- .icon with predefined icon aliases
- size classes: xs, sm, md, lg, xl
- weight classes: thin, light, regular, medium, bold
- style classes: rounded, filled

Example:

```html
<span class="material-icon rounded lg">favorite</span>
<span class="icon schedule sm"></span>
```

Use this pattern when it improves consistency in a component. Prefer not to mix patterns within the same UI block.

## Naming

Use official Material Symbols icon names from Google Fonts.

Commonly used names in this codebase include:

- schedule
- event
- person
- group
- search
- edit
- delete
- settings
- notifications
- error
- warning
- check_circle

## Color and Sizing

Apply size and color via existing utility classes:

- text-sm, text-base, text-lg, text-xl, text-2xl, etc.
- semantic/text utilities already used by the view context

Avoid inline styles for icon color/size unless no utility can express the requirement.

## Accessibility

When icons are decorative:

- keep them adjacent to text labels; no extra aria label is needed

When icons are standalone interactive affordances:

- provide accessible labeling on the button/link element via aria-label or visible text

## Do and Do Not

Do:

- use material-symbols-outlined for primary icon rendering
- keep icon naming consistent with Google catalog
- keep icon class usage consistent inside each component

Do not:

- reference non-existent layout paths for font loading
- hardcode SVG icon sets ad hoc in places already standardized on Material Symbols

## Reference

- Google Material Symbols: https://fonts.google.com/icons
- Icon utility definitions: resources/scss/utilities/_icons.scss
