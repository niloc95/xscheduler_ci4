# 📘 xScheduler CI4 Documentation

**Welcome to the xScheduler CI4 documentation hub.**

This documentation provides comprehensive guides for installation, development, configuration, deployment, and system architecture.

---

## 🚀 Quick Start

**New to the project?** Start here:

1. **[Installation & Setup](./development/setup-workflow.md)** - Get started with installation and configuration
2. **[System Requirements](./REQUIREMENTS.md)** - Technical requirements and specifications
3. **[Architecture Overview](./architecture/mastercontext.md)** - Complete system architecture
4. **[Scheduling System](./SCHEDULING_SYSTEM.md)** - Core scheduling functionality

---


## 📚 Documentation Categories

### 🏗️ Architecture & System Design

Core system architecture, design patterns, and technical overview.

- **[System Architecture (Master Context)](./architecture/mastercontext.md)** - Complete technical overview and system design
- **[Role-Based Access Control](./architecture/ROLE_BASED_SYSTEM.md)** - User roles, permissions, and access matrix
- **[Multi-Tenant SaaS Architecture](./architecture/MULTI-TENANT-SAAS-ARCHITECTURE.md)** - Multi-tenant design and implementation
- **[User-Customer Split Design](./architecture/USER_CUSTOMER_SPLIT.md)** - User and customer data model
- **[Calendar UI Architecture](./architecture/CALENDAR_UI_ARCHITECTURE.md)** - Calendar system architecture
- **[Material Dashboard Guide](./architecture/MATERIAL_DASHBOARD_GUIDE.md)** - Dashboard design and implementation

### 💻 Development Guides

Development guides, implementation details, and feature documentation.

- **[Setup & Installation](./development/setup-workflow.md)** - Project setup, database migrations, environment configuration
- **[Calendar Implementation](./development/calendar_implementation.md)** - Calendar system with FullCalendar integration
- **[Provider System Guide](./development/provider_system_guide.md)** - Provider setup, staff assignments, service bindings
- **[Project Phases](./development/phase-1-api.md)** - Development milestones and deliverables
- **[Dynamic Customer Fields](./development/dynamic-customer-fields.md)** - Custom customer field configuration
- **[Schedule Views](./development/schedule-view.md)** - Schedule view implementation
- **[Provider UX](./development/provider-ux.md)** - Provider user experience design
- **[Sample Data](./development/SAMPLE_DATA.md)** - Test data and seeding

### ⚙️ Configuration & Settings

Environment configuration, application settings, and localization.

- **[Environment Configuration](./configuration/ENV-CONFIGURATION-GUIDE.md)** - .env setup and environment variables
- **[Settings Implementation](./configuration/SETTINGS_IMPLEMENTATION_VERIFIED.md)** - Application settings and configuration
- **[Localization & i18n](./configuration/LOCALIZATION_SETTINGS_UPDATE.md)** - Multi-language and regional settings
- **[Contact Fields Configuration](./configuration/SETTINGS_CONTACT_FIELDS.md)** - Contact field customization
- **[Setup Completion](./configuration/SETUP_COMPLETION_REPORT.md)** - Setup wizard completion tracking
- **[Setup-Driven Config](./configuration/SETUP-DRIVEN-ENV-CONFIG.md)** - Configuration through setup wizard

### 🚀 Deployment & Production

Production deployment, server configuration, and hosting setup.

- **[Production Setup Guide](./deployment/PRODUCTION_FIX_GUIDE.md)** - Production deployment and hardening
- **[MySQL Connection Setup](./deployment/MYSQL-TEST-CONNECTION-FIX.md)** - Database connection troubleshooting
- **[URL Auto-Detection](./deployment/PRODUCTION-URL-AUTO-DETECTION.md)** - Automatic URL configuration
- **[Public Booking Deployment](./deployment/PUBLIC_BOOKING.md)** - Public booking page setup
- **[ZIP Deployment](./deployment/ZIP-DEPLOYMENT-SUMMARY.md)** - Package deployment method
- **[Merge Summary](./deployment/MERGE_SUMMARY.md)** - Branch merge documentation

### 🛡️ Security & Compliance

Security implementation, best practices, and compliance standards.

- **[Security Implementation](./security/SECURITY_IMPLEMENTATION_GUIDE.md)** - Security best practices and implementation
- **[Security Status](./security/SECURITY_STATUS.md)** - Current security measures and features
- **[HIPAA Compliance](./compliance/HIPAA-COMPLIANCE-ASSESSMENT.md)** - Healthcare compliance assessment

### 🗄️ Database

Database schema, migrations, and data management.

- **[Database Schema](./database/database-schema.md)** - Complete database structure and relationships
- **[Backup & Maintenance](./database/backup-and-maintenance.md)** - Database backup procedures and maintenance
- **[DB Prefix Best Practices](./database/DB_PREFIX_BEST_PRACTICES.md)** - Database naming conventions

### 🎨 Design & UI/UX

Design system, styling, and user interface documentation.

- **[Color Palette](./design/COLOR-PALETTE-ANALYSIS.md)** - Color schemes for light/dark modes
- **[Material Icons Usage](./design/MATERIAL_ICONS_USAGE.md)** - Icon system implementation
- **[Preloaded Availability System](./design/PRELOADED_AVAILABILITY_SYSTEM.md)** - Availability UI design
- **[Dark Mode Implementation](./dark-mode/DARK_MODE_IMPLEMENTATION.md)** - Dark theme support

### 🔧 Technical References

Technical guides, CLI commands, and troubleshooting.

- **[CLI Commands Reference](./technical/command.md)** - PHP Spark commands and utilities
- **[SPA Settings Fix](./technical/SPA_SETTINGS_FIX.md)** - Single-page application configuration
- **[Icon Display Fix](./technical/ICON-DISPLAY-FIX.md)** - Material icons troubleshooting
- **[CSS Cleanup Summary](./technical/CSS_CLEANUP_SUMMARY.md)** - CSS consolidation documentation

### 🔔 Features & Functionality

Feature-specific documentation and implementation guides.

- **[Scheduling System](./SCHEDULING_SYSTEM.md)** - Core scheduling system documentation
- **[Notifications Implementation](./NOTIFICATIONS_IMPLEMENTATION_CHECKLIST.md)** - Notification system setup
- **[Customer Appointment History](./features/CUSTOMER_APPOINTMENT_HISTORY.md)** - Customer history feature
- **[System Requirements](./REQUIREMENTS.md)** - Technical requirements and specifications

### 👥 User Management

User accounts, roles, and customer management.

- User management documentation available in `./user-management/` directory

### 📁 Archive

Historical documentation preserved for reference.

- **[Bug Fixes Archive](./archive/bug-fixes.md)** - Historical bug fixes
- **[Troubleshooting Archive](./archive/troubleshooting-guides.md)** - Historical troubleshooting guides

---

## 📖 Documentation Standards

### File Naming Convention

See [File Naming Convention](./file-naming-convention.md) for documentation standards.

### API Documentation

- **[OpenAPI Specification](./openapi.yml)** - RESTful API documentation

---

## 🆘 Getting Help

- **Repository**: [github.com/niloc95/xscheduler_ci4](https://github.com/niloc95/xscheduler_ci4)
- **Issues**: [GitHub Issues](https://github.com/niloc95/xscheduler_ci4/issues)
- **Email**: info@webschedulr.co.za

---

**Last Updated**: January 2026

## 📋 Documentation Standards

### Naming Convention & Organization
All documentation follows a standardized naming convention and organizational structure. See [File Naming Convention Guide](./file-naming-convention.md) for:
- Filename format rules (lowercase, hyphens)
- Folder structure organization
- Link formatting standards
- Content organization guidelines

### Document Audit & Consolidation
For information about documentation organization and consolidation history, see [Document Audit Summary](./DOCUMENT_AUDIT_SUMMARY.md).

---

## 🔍 Finding Information

### By Topic
- **Calendar Features** → [Calendar Implementation Guide](./development/calendar-implementation.md)
- **Provider Management** → [Provider & Staff System Guide](./development/provider-system-guide.md)
- **UI/UX Design** → [Calendar UI Overview](./ui-ux/calendar-ui-overview.md)
- **Deployment** → [Production Setup Guide](./deployment/PRODUCTION_FIX_GUIDE.md)
- **Security** → [Security Implementation Guide](./security/SECURITY_IMPLEMENTATION_GUIDE.md)
- **Database** → [Master Context Document](./architecture/mastercontext.md)
- **Setup** → [Setup & Installation Guide](./development/setup-workflow.md)

### By Role
- **Developers** → Development + Architecture sections
- **DevOps/SysAdmin** → Deployment + Configuration sections
- **QA Engineers** → Testing + Troubleshooting sections
- **Product Managers** → Features + Architecture overview

---

## 📊 Documentation Statistics

- **Total Documents:** 32+ comprehensive guides
- **Total Pages:** ~50 pages of organized documentation
- **Last Updated:** October 24, 2025
- **Coverage Areas:** 10 major categories
- **Code Examples:** 100+ with syntax highlighting
- **Status:** ✅ Fully Organized & Indexed

---

## 🏆 Quality Standards

All documentation meets these standards:
- ✅ Clear, concise language
- ✅ Proper formatting (markdown)
- ✅ Code examples with syntax highlighting
- ✅ Cross-references and links
- ✅ Table of contents (if applicable)
- ✅ Last updated date provided
- ✅ GitHub preview compatible
- ✅ Organized by purpose

---

**Last Updated:** October 24, 2025  
**Documentation Status:** ✅ Fully Organized & Indexed  
**Branch:** calendar (consolidated)  
**Ready for:** Production deployment and team review
