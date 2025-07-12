# GitHub Actions Configuration for xScheduler
# This file documents the workflow setup and configuration

## Workflow Overview

The xScheduler project uses GitHub Actions for comprehensive CI/CD automation:

### 1. Main CI/CD Pipeline (`ci-cd.yml`)
- **Triggers**: Push to main/env-setup-config-build, PRs to main
- **Jobs**:
  - `build-and-test`: Asset compilation and validation
  - `setup-test`: MySQL connection testing with real database
  - `create-deployment-package`: Production package generation
  - `code-quality`: Security and quality checks
  - `performance-analysis`: Bundle size analysis

### 2. Release Workflow (`release.yml`)
- **Triggers**: Git tags (v*.*.*), manual dispatch
- **Features**: Automated release creation with deployment packages

### 3. Security Scanning (`security.yml`)
- **Triggers**: Weekly schedule, pushes, PRs
- **Features**: NPM audit, PHP security checks, dependency review

### 4. Documentation (`docs.yml`)
- **Triggers**: Documentation file changes
- **Features**: Markdown linting, link validation, index generation

## Artifact Management

### Build Artifacts (7 days retention)
- Frontend build assets
- Performance analysis reports

### Deployment Packages (90 days retention)
- Production-ready ZIP files
- Deployment documentation

### Security Reports (30 days retention)
- Vulnerability scans
- Dependency analysis

## Environment Configuration

### Matrix Strategy
- Node.js: 18 (LTS)
- PHP: 8.1 (recommended for CodeIgniter 4)

### Services
- MySQL 8.0 for database testing
- Automated environment setup

### Caching
- npm dependencies
- Composer packages
- Build artifacts

## Security Features

### Dependency Management
- Automated vulnerability scanning
- License compliance checking
- Outdated package detection

### Code Quality
- File permission validation
- Sensitive file detection
- Security advisory monitoring

### Release Security
- Signed releases
- Artifact verification
- Deployment package validation

## Performance Monitoring

### Asset Analysis
- Bundle size tracking
- Gzip compression analysis
- Performance regression detection

### Build Optimization
- Asset caching
- Incremental builds
- Parallel job execution

## Integration Points

### Pull Request Automation
- Build validation
- Security scanning
- Documentation checks
- Deployment preview

### Release Automation
- Changelog generation
- Asset packaging
- GitHub release creation
- Documentation updates

## Maintenance

### Scheduled Tasks
- Weekly security scans
- Dependency updates
- Documentation validation

### Manual Triggers
- Emergency deployments
- Security patches
- Documentation rebuilds

## Configuration Files

All workflows are stored in `.github/workflows/`:
- `ci-cd.yml` - Main pipeline
- `release.yml` - Release automation
- `security.yml` - Security scanning
- `docs.yml` - Documentation management

## Usage Examples

### Trigger Manual Release
```bash
# Create and push a tag
git tag v1.1.1
git push origin v1.1.1
```

### Manual Workflow Dispatch
- Go to Actions tab in GitHub
- Select workflow
- Click "Run workflow"
- Choose branch and parameters

### View Artifacts
- Go to completed workflow run
- Scroll to "Artifacts" section
- Download deployment packages or reports

## Troubleshooting

### Common Issues
1. **Build failures**: Check Node.js/PHP versions
2. **Test failures**: Verify MySQL service status
3. **Package errors**: Check file permissions
4. **Security alerts**: Review npm audit output

### Debug Steps
1. Check workflow logs
2. Download artifact reports
3. Review configuration files
4. Validate environment setup

---

**Last Updated**: July 2025
**Workflow Version**: 1.0
