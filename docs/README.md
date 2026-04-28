# WebScheduler CI4 Documentation

This directory is the canonical home for repository-authored documentation.

## Start Here

1. [INDEX.md](./INDEX.md) for the current source-of-truth navigation and documentation health summary.
2. [REQUIREMENTS.md](./REQUIREMENTS.md) for environment and platform requirements.
3. [configuration/ENV-CONFIGURATION-GUIDE.md](./configuration/ENV-CONFIGURATION-GUIDE.md) for environment setup.
4. [architecture/mastercontext.md](./architecture/mastercontext.md) for live architecture and engineering guardrails.
5. [deployment/RELEASING.md](./deployment/RELEASING.md) for release and packaging workflow.

## Canonical Structure

### Core entry points

- [INDEX.md](./INDEX.md)
- [README.md](./README.md)
- [REQUIREMENTS.md](./REQUIREMENTS.md)
- [changelog.md](./changelog.md)
- [contributing.md](./contributing.md)
- [security/security_policy.md](./security/security_policy.md)

### Domain folders

- [architecture/](./architecture/)
- [configuration/](./configuration/)
- [deployment/](./deployment/)
- [design/](./design/)
- [features/](./features/)
- [frontend/](./frontend/)
- [database/](./database/)
- [security/](./security/)
- [testing/](./testing/)
- [technical/](./technical/)
- [scheduler/](./scheduler/)
- [compliance/](./compliance/)
- [dark-mode/](./dark-mode/)
- [user-management/](./user-management/)

### Archive

- [_archive/](./_archive/)
- [_archive/README_ARCHIVE.md](./_archive/README_ARCHIVE.md)
- [old_Archived.md](./old_Archived.md)

## Documentation Rules

- Keep one canonical document per topic.
- Prefer domain folders over root-level duplicates.
- Move deprecated, historical, or superseded documents into [`_archive/`](./_archive/).
- Use [`INDEX.md`](./INDEX.md) as the top-level navigation hub.

## Current Cleanup Direction

- Root-level duplicates are being reduced in favor of domain-folder canonical copies.
- Historical phase reports and superseded guides are being archived with `OLD_` prefixes.
- Calendar, scheduling, and design-system documentation is being consolidated around fewer canonical docs.
