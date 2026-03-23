# Contributing to xScheduler

Thank you for your interest in contributing to **xScheduler**! We appreciate your help in making this project better.

## 📋 Quick Links

- **🐞 Report a Bug** → [Create Bug Report](https://github.com/niloc95/xscheduler_ci4/issues/new/choose)
- **✨ Request a Feature** → [Create Feature Request](https://github.com/niloc95/xscheduler_ci4/issues/new/choose)
- **💬 Ask Questions** → [GitHub Discussions](https://github.com/niloc95/xscheduler_ci4/discussions)
- **📚 Documentation** → [docs/readme.md](./readme.md)

---

## 🐞 Reporting Bugs

**All bug reports must be submitted via [GitHub Issues](https://github.com/niloc95/xscheduler_ci4/issues/new/choose) using the Bug Report template.**

Before submitting:
1. Search existing issues to avoid duplicates
2. Check the documentation in `/docs` folder
3. Review [requirements.md](./requirements.md)

Include in your bug report:
- Environment (Localhost, VPS, Shared Hosting)
- PHP version and CodeIgniter 4 version
- Clear steps to reproduce
- Expected vs actual behavior
- Error logs from `writable/logs/` or browser console
- Screenshots if applicable

---

## ✨ Requesting Features

**All feature requests must be submitted via [GitHub Issues](https://github.com/niloc95/xscheduler_ci4/issues/new/choose) using the Feature Request template.**

Include:
- Problem description
- Proposed solution
- Use case / user story
- Alternative approaches (optional)

---

## 💬 Asking Questions

**Questions should go to [GitHub Discussions](https://github.com/niloc95/xscheduler_ci4/discussions), NOT Issues.**

Use Discussions for:
- General questions ("How do I...?")
- Installation and setup help
- Ideas that aren't fully formed
- Community feedback

**Do NOT use Issues for support requests.**

---

## 💻 Contributing Code

This repository is maintained on a **personal account** and uses a **PR-only** workflow. Most contributors will contribute via **forks**.

### Workflow

1. **Fork the repository**
   ```bash
   git clone https://github.com/niloc95/xscheduler_ci4.git
   cd xscheduler_ci4
   ```

2. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b bugfix/issue-number-description
   ```

3. **Make your changes**
   - Follow coding standards (PSR-12 for PHP)
   - Add PHPDoc comments to new classes/methods
   - Test thoroughly

4. **Commit with clear messages**
   ```bash
   git commit -m "Add SMS notification support"
   git commit -m "Fix appointment modal location field"
   ```

5. **Push and create Pull Request**
   ```bash
   git push origin feature/your-feature-name
   ```

### Local Setup

**Requirements:**
- PHP 8.1+ 
- MySQL 5.7+ or MariaDB 10.3+
- Composer
- Node.js 16+ and npm
- CodeIgniter 4.6.1

**Installation:**

```bash
# Install dependencies
composer install
npm install

# Configure environment
cp env .env
# Edit .env with your database credentials

# Run migrations
php spark migrate

# Build assets
npm run build
# or for development with hot reload
npm run dev

# Start development server
php spark serve
```

Visit `http://localhost:8080`

---

## 🔄 Pull Request Requirements

Before submitting a PR:

- [ ] Code follows PSR-12 standards
- [ ] PHPDoc comments added to new methods/classes
- [ ] Tested on localhost
- [ ] CI must pass (GitHub Actions)
- [ ] No merge conflicts with main branch
- [ ] Keep changes focused and small
- [ ] Avoid debug logs (`console.log`, `var_dump`)
- [ ] Reference related issue(s) in PR description
- [ ] **Design System compliance** (if UI changes - see below)

### 🎨 Design System Requirements (UI Changes Only)

If your PR modifies views, components, or styles, ensure:

- [ ] **No inline `style=` attributes** in app-facing views (emails/errors exempt)
- [ ] **No hardcoded colors** - use semantic classes (`bg-primary`, `text-on-surface`) or components
- [ ] **Use existing components** - check `app/Views/components/` before creating custom markup
  - Button: Use `view('components/button')` instead of `<button>` tags
  - Input: Use `view('components/input')` instead of `<input>` tags
  - Select: Use `view('components/select')` instead of `<select>` tags
  - Status Badge: Use `view('components/status_badge')` for statuses
- [ ] **Include dark mode support** - add `dark:*` classes for all color/background utilities
- [ ] **Follow typography scale** - use Tailwind `text-*` classes, not custom font sizes
- [ ] **Use Tailwind spacing** - use standard spacing scale (e.g., `mt-4`, `gap-6`), avoid arbitrary values
- [ ] **Test in dark mode** - toggle theme and verify visual correctness
- [ ] **Build passes** - run `npm run build` with no errors

**📚 Design System Reference:** See [design/design_system.md](./design/design_system.md) for complete guidelines, component usage, and anti-patterns.

---

## 📝 Coding Standards

### PHP
- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/)
- Use type hints for parameters and return types
- Add PHPDoc blocks to all classes, methods, properties

**Example:**
```php
/**
 * Create a new appointment
 *
 * @param array $data Appointment data
 * @return int Appointment ID
 * @throws ValidationException
 */
public function createAppointment(array $data): int
{
    // Implementation
}
```

### File Naming
- Controllers: `PascalCase.php` (e.g., `AppointmentController.php`)
- Models: `PascalCase.php` (e.g., `AppointmentModel.php`)
- Views: `kebab-case.php` (e.g., `appointment-form.php`)
- Documentation files: `lowercase_with_underscores.md` (e.g., `security_policy.md`)
- Documentation folders: `lowercase_with_underscores` when multiple words are needed

### JavaScript
- Use ES6+ syntax
- Add JSDoc comments for complex functions
- Keep functions small and focused

---

## 🏷️ Issue Labels

| Label | Description |
|-------|-------------|
| `bug` | Something isn't working |
| `enhancement` | New feature or request |
| `documentation` | Improvements to docs |
| `question` | Further information requested |
| `needs-info` | Waiting for more information |
| `confirmed` | Bug confirmed and ready to fix |
| `in-progress` | Currently being worked on |
| `priority: high` | Critical issue |
| `priority: medium` | Important but not critical |
| `priority: low` | Nice to have |

---

## 🔒 Security Issues

**Do NOT open public issues for security vulnerabilities.**

See [security_policy.md](./security_policy.md) for responsible disclosure.

---

## � Versioning & Releases

xScheduler follows [Semantic Versioning 2.0.0](https://semver.org/):

### Version Format: `MAJOR.MINOR.PATCH`

- **MAJOR** (X.0.0) - Incompatible API changes or breaking changes
- **MINOR** (0.X.0) - New features, backwards-compatible
- **PATCH** (0.0.X) - Bug fixes, backwards-compatible

### Release Types

- **Stable**: `v1.0.0` - Production ready
- **Release Candidate**: `v1.0.0-rc.1` - Final testing
- **Beta**: `v1.0.0-beta.1` - Feature complete, testing
- **Alpha**: `v1.0.0-alpha.1` - Early development

### Changelog

All changes are tracked in [changelog.md](./changelog.md). When contributing:

1. Add your changes to the `[Unreleased]` section
2. Use these categories:
   - **Added** - New features
   - **Changed** - Changes in existing functionality
   - **Deprecated** - Soon-to-be removed features
   - **Removed** - Removed features
   - **Fixed** - Bug fixes
   - **Security** - Security improvements

### Release Process

For maintainers creating releases, see [deployment/releasing.md](./deployment/releasing.md) for the complete release guide.

---

## 📞 Getting Help

- **Questions?** → [GitHub Discussions](https://github.com/niloc95/xscheduler_ci4/discussions)
- **Bug?** → [GitHub Issues](https://github.com/niloc95/xscheduler_ci4/issues/new/choose)
- **Feature idea?** → [GitHub Issues](https://github.com/niloc95/xscheduler_ci4/issues/new/choose)
- **Releases** → [GitHub Releases](https://github.com/niloc95/xscheduler_ci4/releases)

---

**Thank you for contributing to xScheduler! 🎉**
