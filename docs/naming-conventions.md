# Naming Conventions

## JavaScript
- `camelCase`: variables, functions, method names.
- `PascalCase`: class names and constructor-based view modules.
- Avoid mixed casing and ambiguous abbreviations.

## CSS / Tailwind
- Tailwind utility classes preferred.
- If custom classes are needed, use `kebab-case`.

## Files and Modules
- Module files use `kebab-case`.
- Class-based files can still be in `kebab-case` with exported `PascalCase` symbols.

## Database and API Payloads
- Keep DB field names as schema-defined snake_case.
- API contract fields remain stable and explicit.

## Refactor Enforcement
- New shared logic must be extracted into helper modules when duplicated in 2+ views.
- Remove dead code and stale comments when behavior changes.
