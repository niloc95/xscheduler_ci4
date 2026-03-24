# xScheduler CI4 documentation

This directory is the canonical home for repository-authored documentation.

## Start here

1. [requirements.md](./requirements.md) for environment and platform requirements.
2. [configuration/env_configuration_guide.md](./configuration/env_configuration_guide.md) for environment setup.
3. [../Agent_Context.md](../Agent_Context.md) for active engineering context and architecture guardrails.
4. [deployment/releasing.md](./deployment/releasing.md) for release and packaging workflow.

## Documentation map

### Core

- [requirements.md](./requirements.md)
- [changelog.md](./changelog.md)
- [contributing.md](./contributing.md)
- [security_policy.md](./security_policy.md)

### Architecture

- [architecture/scheduler_ui_architecture.md](./architecture/scheduler_ui_architecture.md)
- [architecture/provider_service_catalog_contract.md](./architecture/provider_service_catalog_contract.md)
- [architecture/calendar_engine_api_reference.md](./architecture/calendar_engine_api_reference.md)
- [architecture/calendar_engine_implementation_summary.md](./architecture/calendar_engine_implementation_summary.md)
- [architecture/unified_calendar_engine.md](./architecture/unified_calendar_engine.md)

### Scheduler

- [scheduler/calendar_engine_quick_start.md](./scheduler/calendar_engine_quick_start.md)
- [scheduler/day_view_architecture.md](./scheduler/day_view_architecture.md)
- [scheduler/scheduler_module_readme.md](./scheduler/scheduler_module_readme.md)
- [scheduler/phase1_audit_findings.md](./scheduler/phase1_audit_findings.md)
- [scheduler/phase_summary.md](./scheduler/phase_summary.md)
- [scheduler/validation_report.md](./scheduler/validation_report.md)

### Configuration and security

- [configuration/env_configuration_guide.md](./configuration/env_configuration_guide.md)
- [security/security_implementation_guide.md](./security/security_implementation_guide.md)
- [security/hash_based_url_implementation.md](./security/hash_based_url_implementation.md)
- [security/encryption_status_assessment.md](./security/encryption_status_assessment.md)
- [security/security_status.md](./security/security_status.md)

### Deployment and release

- [deployment/deploy_bundle_readme.md](./deployment/deploy_bundle_readme.md)
- [deployment/quick_deploy.md](./deployment/quick_deploy.md)
- [deployment/flexible_deployment_audit.md](./deployment/flexible_deployment_audit.md)
- [deployment/packaging_and_release_guide.md](./deployment/packaging_and_release_guide.md)
- [deployment/quick_release_guide.md](./deployment/quick_release_guide.md)
- [deployment/releasing.md](./deployment/releasing.md)

### Design and frontend

- [design/design_system.md](./design/design_system.md)
- [design/design_system_roadmap.md](./design/design_system_roadmap.md)
- [design/audit_summary.md](./design/audit_summary.md)
- [technical/css_consolidation_guide.md](./technical/css_consolidation_guide.md)

### Testing and operational docs

- [testing/test_runner_guide.md](./testing/test_runner_guide.md)
- [testing/calendar_settings_sync_test.md](./testing/calendar_settings_sync_test.md)
- [testing/calendar_time_format_test_script.md](./testing/calendar_time_format_test_script.md)
- [development/github_repository_setup_guide.md](./development/github_repository_setup_guide.md)
- [development/github_actions_workflows.md](./development/github_actions_workflows.md)

### Audits and historical records

- [audits/agent_context_remedy_action_plan.md](./audits/agent_context_remedy_action_plan.md)
- [audits/remediation_plan.md](./audits/remediation_plan.md)
- [audits/scheduler_ui_audit_2026_03_02.md](./audits/scheduler_ui_audit_2026_03_02.md)
- [audits/audit_session_v93.md](./audits/audit_session_v93.md)
- [audits/audit_session_v96.md](./audits/audit_session_v96.md)
- [audits/audit_spa_initialization_2026_02_18.md](./audits/audit_spa_initialization_2026_02_18.md)

## Standards

- Repository-authored documentation lives in `/docs`.
- The only top-level repository markdown entrypoint is `README.md`.
- Filenames use lowercase with underscores.
- GitHub-required metadata files may still exist under `.github/` when platform behavior depends on them.

## Audit summary

- Root-level authored docs were centralized into `/docs`.
- Legacy uppercase, mixed-case, spaced, and hyphenated documentation filenames were normalized.
- One-off setup completion notes were deleted after review because they duplicated information better represented in the active guides.
- The docs hub was rewritten to reflect the actual current tree instead of stale or missing files.
