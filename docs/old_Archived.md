# Legacy Archive Notes

This file is retained only as a transitional pointer while the documentation tree is being rationalized.

## Canonical Locations

- Use [INDEX.md](./INDEX.md) as the primary documentation navigation hub.
- Use [_archive/](./_archive/) as the canonical archive root.
- Use [_archive/README_ARCHIVE.md](./_archive/README_ARCHIVE.md) for archive rules and grouping.

## What Belongs In Archive

- Deprecated or superseded implementation guides
- Historical phase reports and completion summaries
- One-off diagnostics that are no longer source-of-truth
- Exact duplicate root copies replaced by canonical subfolder docs

## What Should Stay Active

- Architecture contracts in [architecture/mastercontext.md](./architecture/mastercontext.md)
- Canonical domain docs in `architecture/`, `configuration/`, `deployment/`, `design/`, `features/`, `frontend/`, `database/`, `security/`, `testing/`, and `technical/`
- The current navigation hub in [INDEX.md](./INDEX.md)

## Migration Note

Older archive references that previously pointed at mixed root-level files should now be interpreted through the new canonical structure:

1. Check [INDEX.md](./INDEX.md) for the active source-of-truth path.
2. Check [_archive/](./_archive/) for historical material.
3. Prefer canonical subfolder docs over root-level duplicates.
