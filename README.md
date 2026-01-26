# Aegir Provision D11

This directory contains the Drush 13 extension that implements the Provision backend for Drupal 10+ platforms (PHP 8.3+). It is designed to be installed as a Composer package and invoked by the Aegir frontend (aegir-hosting) via Drush commands or used standalone.

## Key Features
- **Drush 13 commands**: `provision-*` commands for all hosting operations
- **Context system**: Immutable contexts stored as Drush YAML aliases in `~/.drush/sites/`
- **Service architecture**: Apache HTTP + SSL, MySQL 8.0+, Drupal settings management
- **Modern PHP**: PHP 8.3+ with strict types, attributes, dependency injection
- **Composer-based**: PSR-4 autoloading, Symfony components, proper dependency management
- **Platform support**: Composer-based Drupal 11+ codebases with `/web`, `/docroot`, or `/html` layouts

## Quick Start

```bash
# Install via Composer
composer require argopecten/aegir-provision

# Create server context
drush provision-save @server_master --type=server \
  --data='{"aegir_root":"/var/aegir","web_group":"www-data"}'

# Create platform
drush provision-save @platform_d11 --type=platform \
  --data='{"root":"/var/aegir/platforms/drupal-11","server":"server_master"}'

# Create and install site
drush provision-save @example.com --type=site \
  --data='{"uri":"example.com","platform":"platform_d11","db_server":"server_master"}'
drush provision-install @example.com
```

## Documentation

- **[Home.md](doc/Home.md)** - User-friendly overview and quick start guide
- **[provision-d11.md](doc/provision-d11.md)** - Complete system architecture and technical details
- **[provision-d7.md](doc/provision-d7.md)** - Legacy Drupal 7 reference

## Installation

```bash
composer require argopecten/aegir-provision
```

The `drush.services.yml` file registers the command classes automatically when this package is installed.

## Architecture Overview

```
src/
├── Commands/ProvisionCommands.php  # Drush 13 command definitions
├── Core/                           # Context, Filesystem, ProcessRunner
├── Provision/ProvisionManager.php  # Central orchestrator
├── Service/                        # Apache, MySQL, SSL, Drupal services
└── Config/TemplateRenderer.php     # Template engine

resources/templates/                # Apache vhosts, Drupal settings
```

## Requirements

- PHP 8.3+ with extensions: `json`, `pdo`, `pdo_mysql`
- Drush 13.6+
- Apache 2.4+ or compatible web server
- MySQL 8.0+ or MariaDB 10.6+
- Ubuntu 24.04 LTS (recommended)

## Links

- **Homepage**: https://aegir.hu/
- **Source**: https://github.com/argopecten/aegir-provision
- **Issues**: https://github.com/argopecten/aegir-provision/issues

## License

GPL-2.0-or-later
