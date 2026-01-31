# Aegir Provision Documentation

**Modern Drush 13 backend automation for Drupal 11+ hosting management**

Aegir Provision is a command-line tool that automates hosting operations for Drupal 11 sites. Built with PHP 8.3+ and Drush 13.7+, it provides the backend automation layer for the Aegir hosting system.

---

## Documentation Structure

```
doc/
├── Home.md                    # This file - main documentation index
├── provision-d11.md           # Complete D11 architecture and implementation
├── provision-d7.md            # D7 reference (for migration/historical context)
├── roadmap.md                 # Project status and future plans
│
└── guides/                    # User and developer guides
    ├── quickstart.md          # Installation and first steps
    ├── concepts.md            # Core concepts and architecture
    ├── architecture.md        # Component map and data flow diagrams
    ├── extension-system.md    # Creating extensions and event subscribers
    └── api-reference.md       # Core classes and services
```

---

## 📚 Documentation Index

### Getting Started

- **[Quick Start Guide](guides/quickstart.md)** - Installation and first steps
- **[Core Concepts](guides/concepts.md)** - Understanding contexts and workflows

### Technical Documentation

- **[Architecture Overview](guides/architecture.md)** - Component map and data flow
- **[API Reference](guides/api-reference.md)** - Core classes and services
- **[D11 Architecture](provision-d11.md)** - Complete technical documentation for Drupal 11 implementation
- **[D7 Reference](provision-d7.md)** - Historical documentation for Drupal 7 version (for migration reference)

### Extension Development

- **[Extension System](guides/extension-system.md)** - Creating extensions with the event system

### Project Management

- **[Roadmap](roadmap.md)** - Project status, priorities, and future plans

---

## 🚀 Quick Example

```bash
# Create server context
drush provision-save @server_master --type=server \
  --data='{"aegir_root":"/var/aegir","web_group":"www-data"}'

# Create platform context
drush provision-save @platform_d11 --type=platform \
  --data='{"root":"/var/aegir/platforms/drupal-11","server":"server_master"}'

# Create and install a site
drush provision-save @example.com --type=site \
  --data='{"uri":"example.com","platform":"platform_d11","db_server":"server_master"}'
drush provision-install @example.com
```

---

## 📦 Key Information

### Requirements

- **PHP**: 8.3+ with extensions: `json`, `pdo`, `pdo_mysql`
- **Drush**: 13.7+
- **Symfony**: 7.0+ components
- **MySQL/MariaDB**: 8.0+ (via mysql CLI client)
- **Apache**: 2.4+ with PHP-FPM
- **Operating System**: Ubuntu 24.04 LTS (recommended)

### Installation

```bash
composer require argopecten/aegir-provision
```

### Core Features

- **Context System** - Three immutable types: Server, Platform, Site
- **Server Management** - Apache, MySQL, SSL configuration
- **Site Operations** - Install, migrate, clone, backup, restore
- **Extension System** - Event-based hooks for custom logic
- **Modern Architecture** - PHP 8.3+, Drush 13.7+, Symfony 7.0+

---

## 🔧 Available Commands

### Context Management
- `provision-save` - Save or update context data
- `provision-verify` - Verify server, platform, or site configuration
- `provision-delete` - Delete a context

### Site Operations
- `provision-install` - Install a new Drupal site
- `provision-import` - Import an existing site
- `provision-backup` - Create site backup
- `provision-restore` - Restore from backup
- `provision-deploy` - Deploy a backup to a site

### Site Lifecycle
- `provision-migrate` - Migrate to different platform
- `provision-clone` - Clone site to new context
- `provision-enable` / `provision-disable` - Enable/disable site
- `provision-lock` / `provision-unlock` - Lock/unlock site
- `provision-login-reset` - Reset admin login

---

## � Contributing to Documentation

### Writing Style

- **Be concise** - Get to the point quickly
- **Use examples** - Show, don't just tell
- **Link liberally** - Cross-reference related topics
- **Keep current** - Update docs when code changes

### File Organization

- **User guides** go in `guides/`
- **Architecture docs** stay at root level (`provision-d11.md`, `provision-d7.md`)
- **Project management** at root level (`roadmap.md`)
- **Always update Home.md** when adding new documents

### Markdown Standards

- Use ATX-style headers (`#`, `##`, `###`)
- Include horizontal rules (`---`) between major sections
- Use fenced code blocks with language hints
- Format file paths as code: `src/ProvisionManager.php`
- Link to other docs using relative paths

---

## 🔗 External Links

- **Homepage**: https://aegir.hu/
- **GitHub Repository**: https://github.com/argopecten/aegir-provision
- **Issue Tracker**: https://github.com/argopecten/aegir-provision/issues
- **Drush 13 Documentation**: https://www.drush.org/13.x/
- **For AI Coding Agents**: See `.github/AI-INSTRUCTIONS.md` for detailed development guidelines

---

## 📄 License

GPL-2.0-or-later

---

## 🤝 Contributing

Contributions welcome! See the [roadmap](roadmap.md) for current priorities and future plans.

**Development Resources**:
- `.github/AI-INSTRUCTIONS.md` - Detailed instructions for AI coding agents
- `doc/provision-d11.md` - Complete architecture and implementation details
- `doc/guides/` - User guides and developer references
