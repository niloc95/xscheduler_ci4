# Material 3 Design System (Scheduler)

## Surface Model
Material 3-inspired surfaces are represented with Tailwind tokens:
- `bg-surface-0`: page/base
- `bg-surface-1`: panel/container
- `bg-surface-2`: card/elevated blocks
- `hover:bg-surface-3`: interactive hover state

Defined in `tailwind.config.js` under `theme.extend.colors.surface`.

## Spacing and Density
- Use Tailwind spacing scale only.
- Prefer 8pt rhythm (`p-2`, `p-4`, `gap-2`, `gap-4`, etc.).
- Avoid arbitrary spacing values unless required by existing layout constraints.

## State Colors
- Use semantic color helpers (`appointment-colors.js`) and dynamic color attributes (`data-bg-color`, `data-border-left-color`).
- Avoid inline color styles.

## Typography
- Existing font stack: `Inter`, `system-ui`, `sans-serif`.
- View titles: medium/high emphasis.
- Metadata and hints: subdued text utility classes.

## Accessibility
- Keep contrast on all surfaces in light/dark mode.
- Preserve keyboard and pointer affordances for interactive controls.
