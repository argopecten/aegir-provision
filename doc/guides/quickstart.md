# Quick Start Guide

This guide will help you get Aegir Provision up and running in minutes.

---

## Prerequisites

Before you begin, ensure you have:

- **PHP 8.3+** with required extensions
- **Drush 13.7+** installed globally or in your project
- **MySQL/MariaDB 8.0+** running
- **Apache 2.4+** with PHP-FPM configured
- **Sudo access** for server configuration
- **Ubuntu 24.04 LTS** (recommended, other Linux distributions may work)

---

## Installation

### 1. Install via Composer

```bash
# Install in your global Drush environment
composer global require argopecten/aegir-provision

# Or install in a specific project
cd /path/to/your/project
composer require argopecten/aegir-provision
```

### 2. Verify Installation

```bash
drush list provision
```

You should see a list of provision commands.

---

## Initial Setup

### Step 1: Create Server Context

The server context represents your infrastructure node:

```bash
drush provision-save @server_master --type=server --data='{
  "aegir_root": "/var/aegir",
  "web_group": "www-data",
  "http_port": 80,
  "http_ssl_port": 443,
  "http_restart_cmd": "sudo systemctl reload apache2"
}'
```

### Step 2: Verify Server

```bash
drush provision-verify @server_master
```

This will:
- Check directory permissions
- Verify Apache configuration paths
- Ensure MySQL connectivity

---

## Create Your First Site

### Step 1: Create Platform Context

A platform is a Drupal codebase:

```bash
drush provision-save @platform_d11 --type=platform --data='{
  "root": "/var/aegir/platforms/drupal-11",
  "server": "server_master"
}'
```

**Note**: Ensure the Drupal codebase exists at this location:

```bash
# Example: Install Drupal 11 at the platform location
mkdir -p /var/aegir/platforms
cd /var/aegir/platforms
composer create-project drupal/recommended-project:^11 drupal-11
```

### Step 2: Verify Platform

```bash
drush provision-verify @platform_d11
```

This will:
- Detect the Drupal version and docroot
- Generate platform-level Apache configuration
- Create necessary directories

### Step 3: Create Site Context

```bash
drush provision-save @example.local --type=site --data='{
  "uri": "example.local",
  "platform": "platform_d11",
  "db_server": "server_master",
  "db_name": "example_local",
  "db_user": "example_user",
  "db_passwd": "secure_password_here"
}'
```

### Step 4: Install the Site

```bash
drush provision-install @example.local
```

This will:
1. Create the MySQL database and user
2. Generate `settings.php`
3. Create Apache vhost configuration
4. Run `drush site:install`
5. Enable the site (activate vhost)
6. Reload Apache

---

## Access Your Site

### Add Local DNS Entry

For local development, add an entry to `/etc/hosts`:

```bash
sudo nano /etc/hosts
```

Add:
```
127.0.0.1  example.local
```

### Visit in Browser

Navigate to `http://example.local` - you should see your new Drupal site!

---

## Common Operations

### Backup a Site

```bash
drush provision-backup @example.local
```

Backup will be saved to `/var/aegir/backups/example.local/`

### Restore from Backup

```bash
drush provision-restore @example.local /var/aegir/backups/example.local/backup-2026-01-31.tar.gz
```

### Migrate to New Platform

```bash
# Create new platform
drush provision-save @platform_d11_v2 --type=platform --data='{
  "root": "/var/aegir/platforms/drupal-11-updated",
  "server": "server_master"
}'
drush provision-verify @platform_d11_v2

# Migrate site
drush provision-migrate @example.local @platform_d11_v2
```

### Clone a Site

```bash
drush provision-clone @example.local @example-staging.local
```

### Disable/Enable a Site

```bash
# Temporarily disable (removes vhost)
drush provision-disable @example.local

# Re-enable
drush provision-enable @example.local
```

### Delete a Site

```bash
# Delete context, database, and files
drush provision-delete @example.local --delete-db --delete-files

# Or just delete the context (keep database/files)
drush provision-delete @example.local
```

---

## Troubleshooting

### Command Not Found

If `drush provision-*` commands are not found:

```bash
# Verify installation
composer global show argopecten/aegir-provision

# Clear Drush cache
drush cache:clear drush
```

### Permission Errors

Ensure proper permissions:

```bash
sudo chown -R aegir:www-data /var/aegir
sudo chmod -R 775 /var/aegir
```

### Apache Configuration Not Applied

Reload Apache after changes:

```bash
sudo systemctl reload apache2

# Or restart if needed
sudo systemctl restart apache2
```

### MySQL Connection Failed

Check MySQL credentials and connectivity:

```bash
mysql -u root -p
# Then test the database user
mysql -u example_user -p example_local
```

---

## Next Steps

- Read [Core Concepts](concepts.md) to understand the context system in depth
- See [Extension System](extension-system.md) to customize provision behavior
- Review [provision-d11.md](../provision-d11.md) for complete architecture details
- Check the [Roadmap](../roadmap.md) for upcoming features

---

## Getting Help

- **Issue Tracker**: https://github.com/argopecten/aegir-provision/issues
- **Documentation**: https://github.com/argopecten/aegir-provision/wiki
- **Community**: https://aegir.hu/
