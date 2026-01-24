# Aegir Provision

**Modern Drush 13 backend automation for Drupal 11+ hosting management**

Aegir Provision is a command-line tool that automates hosting operations for Drupal 11 sites. Built with PHP 8.3+ and Drush 13, it provides the backend automation for the Aegir hosting system.

## 🚀 Quick Start

```bash
# Install via Composer
composer require argopecten/aegir-provision

# Verify installation
drush provision-verify @server_master
```

## 📚 Documentation

- **[System Architecture (provision-d11.md)](provision-d11.md)** - Complete technical documentation for the Drupal 11 implementation
- **[Legacy D7 Reference (provision-d7.md)](provision-d7.md)** - Historical documentation for Drupal 7 version

## ✨ Key Features

- **Server Management**: Configure Apache, MySQL, SSL certificates
- **Platform Management**: Drupal codebase verification and configuration
- **Site Operations**: Install, migrate, clone, backup, restore
- **Modern Architecture**: PHP 8.3+, Drush 13, Symfony 7.0+ components
- **Composer-based**: PSR-4 autoloading, proper dependency management
- **Template System**: Flexible configuration generation for Apache, Drupal settings

## 📦 Requirements

- PHP 8.3+ with extensions: `json`, `pdo`, `pdo_mysql`
- Drush 13.6+
- Symfony 7.0+ components
- MySQL/MariaDB (via mysql CLI client)
- Apache web server

## 🔧 Available Commands

### Context Management
- `provision-save` - Save or update context data
- `provision-verify` - Verify server, platform, or site configuration
- `provision-delete` - Delete a context

### Site Operations
- `provision-install` - Install a new Drupal site
- `provision-import` - Import an existing site into Aegir
- `provision-backup` - Create site backup
- `provision-restore` - Restore site from backup
- `provision-deploy` - Deploy a backup to a site

### Site Lifecycle
- `provision-migrate` - Migrate site to different platform
- `provision-clone` - Clone site to new context
- `provision-enable` - Enable a site
- `provision-disable` - Disable a site
- `provision-lock` / `provision-unlock` - Lock/unlock site

### Utilities
- `provision-login-reset` - Reset admin login for a site

## 🏗️ Architecture

```
src/
├── Commands/          # Drush command definitions (PHP 8 attributes)
├── Core/              # Context, Filesystem, ProcessRunner, AliasStore
├── Provision/         # ProvisionManager orchestration
├── Service/           # Apache, MySQL, SSL, Drupal services
└── Config/            # Template rendering engine

resources/
└── templates/         # Apache vhost, Drupal settings templates
    ├── apache/
    └── drupal/
```

## 🔗 Links

- **Homepage**: https://aegir.hu/
- **Source Code**: https://github.com/argopecten/aegir-provision
- **Issue Tracker**: https://github.com/argopecten/aegir-provision/issues
- **Documentation Wiki**: https://github.com/argopecten/aegir-provision/wiki

## 📝 License

GPL-2.0-or-later

## 🤝 Contributing

Contributions are welcome! This is a modern implementation of Aegir Provision for Drupal 11+, built from the ground up with PHP 8.3+ and Drush 13.

---

**Note**: This is the Drupal 11 implementation. Legacy Drupal 7 source code is not present in this repository.
