# Aegir Provision Documentation

**Drush 13 backend automation for Drupal 11 hosting management**

Aegir Provision is the backend component of the Aegir hosting system. It automates infrastructure operations — Apache vhosts, MySQL databases, settings.php generation, SSL certificates, cron jobs, and site lifecycle management — via Drush commands operating on YAML context files.

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.3+ (cli, fpm, pdo, pdo_mysql, json) |
| Drush | 13.7+ |
| Apache | 2.4+ with mod_rewrite, mod_ssl, mod_proxy_fcgi |
| MySQL/MariaDB | 8.0+ |
| Ubuntu | 24.04 LTS |

## Quick Start

### 1. Create Server Context

```bash
drush provision:save server_master --type=server --data='{
  "aegir_root": "/var/aegir",
  "web_group": "www-data",
  "http_service_type": "apache",
  "http_port": 80
}'
drush provision:verify server_master
```

### 2. Create Platform

```bash
drush provision:save platform_d11 --type=platform --data='{
  "root": "/var/aegir/platforms/drupal-11",
  "server": "server_master"
}'
drush provision:verify platform_d11
```

### 3. Create and Install a Site

```bash
drush provision:save example.local --type=site --data='{
  "uri": "example.local",
  "platform": "platform_d11",
  "db_server": "server_master"
}'
drush provision:install example.local
```

This creates the MySQL database, generates settings.php, creates the Apache vhost, runs Drupal site:install, and reloads Apache.

### Common Operations

```bash
drush provision:verify example.local            # Verify/repair config
drush provision:backup example.local             # Backup DB + files
drush provision:restore example.local backup.tar.gz  # Restore
drush provision:migrate example.local platform_v2    # Migrate platform
drush provision:clone example.local staging.local    # Clone site
drush provision:disable example.local            # Disable site
drush provision:enable example.local             # Re-enable site
drush provision:delete example.local             # Delete site
drush provision:login-reset example.local        # Admin login link
```

## Documentation

### Architecture & Concepts

| Document | Content |
|---|---|
| [provision-d11.md](provision-d11.md) | Complete D11 system architecture |
| [guides/architecture.md](guides/architecture.md) | Component map, data flow, extension points |
| [guides/api-reference.md](guides/api-reference.md) | Class reference for all packages |

### Extension Development

| Document | Content |
|---|---|
| [guides/extension-system.md](guides/extension-system.md) | Event subscribers, service plugins, template overrides, Drush command libraries |

### Project Management

| Document | Content |
|---|---|
| [roadmap.md](roadmap.md) | Status, priorities, testing gaps, future work |
| [manual-testing.md](manual-testing.md) | Step-by-step manual test procedures |

### Historical Reference

| Document | Content |
|---|---|
| [provision-d7.md](provision-d7.md) | Legacy D7 architecture (migration reference) |

## Command Reference

| Command | Description |
|---|---|
| `provision:save` | Create/update context |
| `provision:verify` | Verify server/platform/site config |
| `provision:install` | Install a Drupal site |
| `provision:import` | Import existing site |
| `provision:backup` | Backup site (DB + files) |
| `provision:restore` | Restore from backup |
| `provision:deploy` | Deploy backup to site |
| `provision:migrate` | Migrate to different platform |
| `provision:clone` | Clone site to new context |
| `provision:enable` | Enable (activate) site |
| `provision:disable` | Disable (deactivate) site |
| `provision:lock` | Lock context (maintenance) |
| `provision:unlock` | Unlock context |
| `provision:delete` | Delete context and resources |
| `provision:login-reset` | Reset admin login link |
| `provision:cron` | Manage cron jobs |
| `backend:parse` | Parse Drush backend output |

## For AI Coding Agents

See [.github/AGENTS.md](../.github/AGENTS.md) for architecture context and [.github/SKILLS.md](../.github/SKILLS.md) for actionable procedures.

## Parent Project

This package is the backend component of **Aegir Hostmaster**. See the [main project documentation](../../../doc/HOME.md) for the full system overview.
