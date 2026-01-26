# Aegir Provision

**Modern Drush 13 backend automation for Drupal 11+ hosting management**

Aegir Provision is a command-line tool that automates hosting operations for Drupal 11 sites. Built with PHP 8.3+ and Drush 13, it provides the backend automation for the Aegir hosting system.

## 🚀 Quick Start

```bash
# Install via Composer
composer require argopecten/aegir-provision

# Create server context
drush provision-save @server_master --type=server --data='{"aegir_root":"/var/aegir","web_group":"www-data"}'

# Verify installation
drush provision-verify @server_master
```

## 📖 Core Concepts

### Context System

Aegir Provision operates on **three immutable context types** stored as Drush YAML aliases:

1. **Server Context** (`@server_master`) - Infrastructure node with services (http/db)
2. **Platform Context** (`@platform.local`) - Drupal codebase directory
3. **Site Context** (`@example.com`) - Individual Drupal site

Contexts are stored in `~/.drush/sites/` as YAML files:

```yaml
# ~/.drush/sites/example.com.site.yml
type: site
uri: example.com
platform: platform_d11
db_server: server_master
db_name: example_com
db_user: example_com_user
db_passwd: generated_password
root: /var/aegir/platforms/drupal-11/web
ssl_enabled: true
```

### Typical Workflow

```bash
# 1. Create a platform context
drush provision-save @platform_d11 --type=platform \
  --data='{"root":"/var/aegir/platforms/drupal-11","server":"server_master"}'
drush provision-verify @platform_d11

# 2. Create and install a site
drush provision-save @example.com --type=site \
  --data='{"uri":"example.com","platform":"platform_d11","db_server":"server_master"}'
drush provision-install @example.com

# 3. Backup the site
drush provision-backup @example.com

# 4. Migrate to a new platform
drush provision-migrate @example.com @platform_d11_updated
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

### Directory Structure

```
src/
├── Commands/          # Drush command definitions (PHP 8 attributes)
│   └── ProvisionCommands.php
├── Core/              # Core infrastructure
│   ├── Context.php             # Immutable context data structure
│   ├── ContextRepository.php   # Load/save via Drush AliasStore
│   ├── ContextType.php         # Server, Platform, Site types
│   ├── Filesystem.php          # File operations with permissions
│   ├── ProcessRunner.php       # External command execution
│   └── ConfigPaths.php         # Path resolution
├── Provision/         # Orchestration layer
│   └── ProvisionManager.php    # Central task orchestrator
├── Service/           # Service implementations
│   ├── Http/
│   │   └── ApacheService.php   # Apache vhost generation
│   ├── Db/
│   │   └── MySqlService.php    # MySQL management
│   ├── Ssl/
│   │   └── SslManager.php      # SSL certificate management
│   └── Drupal/
│       └── SettingsWriter.php  # settings.php generation
└── Config/
    └── TemplateRenderer.php    # Template engine

resources/
└── templates/         # Configuration templates
    ├── apache/       # vhost.tpl.php, vhost_ssl.tpl.php
    └── drupal/       # settings.php.tpl.php
```

### How It Works

1. **Commands** receive Drush invocations and delegate to ProvisionManager
2. **ProvisionManager** orchestrates services and manages workflows
3. **Services** handle infrastructure (Apache, MySQL, SSL, Drupal)
4. **Context System** stores configuration as immutable data structures
5. **Templates** generate Apache vhosts and Drupal settings files

## 🔗 Links

- **Homepage**: https://aegir.hu/
- **Source Code**: https://github.com/argopecten/aegir-provision
- **Issue Tracker**: https://github.com/argopecten/aegir-provision/issues
- **Documentation Wiki**: https://github.com/argopecten/aegir-provision/wiki

## 📝 License

GPL-2.0-or-later

## 🤝 Contributing

Contributions are welcome! This is a modern implementation of Aegir Provision for Drupal 11+, built from the ground up with PHP 8.3+ and Drush 13.

## ⚠️ Best Practices

### Security
- Never run provision commands as root
- Keep database credentials secure in context files (permissions 0600)
- Use SSL certificates for production sites
- Review generated configs before enabling sites

### Operations
- Always run `provision-verify` after context changes
- Backup sites before migrations or major changes
- Test migrations on staging platforms first
- Monitor Apache/MySQL logs for errors

### Anti-Patterns to Avoid
- ❌ Don't modify context YAML files directly - use `provision-save`
- ❌ Don't hardcode paths - contexts provide path resolution
- ❌ Don't skip verification after making changes
- ❌ Don't ignore file permissions - use Filesystem service

---

**Note**: This is the Drupal 11 implementation. Legacy Drupal 7 source code is not present in this repository.
