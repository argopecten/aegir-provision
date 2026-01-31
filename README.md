# Aegir Provision

**Modern Drush 13 backend automation for Drupal 11+ hosting management**

Aegir Provision is a standalone Drush extension that automates hosting operations for Drupal 11 sites. Built with PHP 8.3+ and Drush 13.7+, it provides the backend automation layer for the Aegir hosting system.

## ✨ Key Features

- **Context System** - Immutable server/platform/site contexts stored as Drush YAML aliases
- **Modern Architecture** - PHP 8.3+, Drush 13.7+ (Symfony Console), Symfony 7.0+ components
- **Service Orchestration** - Apache, MySQL, SSL, Drupal settings management
- **Extension System** - Event-based hooks for custom validation and integrations
- **Automated Operations** - Install, migrate, clone, backup, restore sites

## 🚀 Quick Start

```bash
# Install via Composer
composer require argopecten/aegir-provision

# Create server context
drush provision-save @server_master --type=server \
  --data='{"aegir_root":"/var/aegir","web_group":"www-data"}'

# Create platform and site
drush provision-save @platform_d11 --type=platform \
  --data='{"root":"/var/aegir/platforms/drupal-11","server":"server_master"}'
drush provision-save @example.com --type=site \
  --data='{"uri":"example.com","platform":"platform_d11","db_server":"server_master"}'

# Install the site
drush provision-install @example.com
```

## 📚 Documentation

**Start here**: [doc/Home.md](doc/Home.md) - Main documentation index

### Getting Started
- [Quick Start Guide](doc/guides/quickstart.md) - Installation and first steps
- [Core Concepts](doc/guides/concepts.md) - Understanding contexts and workflows

### Technical Documentation
- [D11 Architecture](doc/provision-d11.md) - Complete implementation details
- [Extension System](doc/guides/extension-system.md) - Creating extensions
- [API Reference](doc/guides/api-reference.md) - Core classes and services

### Project Info
- [Roadmap](doc/roadmap.md) - Project status and future plans
- [D7 Reference](doc/provision-d7.md) - Legacy version (migration reference)

## 📦 Requirements

- **PHP**: 8.3+ with extensions: `json`, `pdo`, `pdo_mysql`
- **Drush**: 13.7+
- **Symfony**: 7.0+ components
- **MySQL/MariaDB**: 8.0+
- **Apache**: 2.4+ with PHP-FPM
- **OS**: Ubuntu 24.04 LTS (recommended)

## 🔧 Available Commands

### Context Management
- `provision-save` - Save or update context
- `provision-verify` - Verify configuration
- `provision-delete` - Delete context

### Site Operations
- `provision-install` - Install new site
- `provision-backup` / `provision-restore` - Backup and restore
- `provision-migrate` - Migrate to different platform
- `provision-clone` - Clone site
- `provision-enable` / `provision-disable` - Enable/disable site

See [documentation](doc/Home.md) for complete command reference.

## 🏗️ Architecture

```
src/
├── Drush/Commands/           # Auto-discovered command classes
├── Core/                     # Context, Filesystem, ProcessRunner
├── ProvisionManager.php      # Central orchestrator
├── Service/                  # Apache, MySQL, SSL, Drupal services
├── Event/                    # Event system for extensions
└── Config/                   # Template renderer

resources/templates/          # Apache vhosts, Drupal settings
```

See [provision-d11.md](doc/provision-d11.md) for architecture deep-dive.

## 🔗 Links

- **Homepage**: https://aegir.hu/
- **GitHub**: https://github.com/argopecten/aegir-provision
- **Issues**: https://github.com/argopecten/aegir-provision/issues
- **Drush 13 Docs**: https://www.drush.org/13.x/

## 📄 License

GPL-2.0-or-later

## 🤝 Contributing

Contributions welcome! See [roadmap](doc/roadmap.md) for current priorities.

For AI coding agents, see `.github/AI-INSTRUCTIONS.md` for detailed development guidelines.
