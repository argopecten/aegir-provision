# Architecture Overview

Understanding how Aegir Provision components work together.

---

## Component Map

```
┌─────────────────────────────────────────────────────────────────┐
│                      Aegir Provision D11                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  17 Drush Commands  (src/Drush/Commands/)                      │
│  ProvisionAutowireTrait + ProvisionServiceRegistry             │
│      ↓ (inject)                                                │
│  ProvisionManager ←────────────────────┐                       │
│      ↓ (orchestrates)                  │                       │
│  ┌──────────────────────────────────┴──────────────────────┐  │
│  │ 11 Specialized Managers                                  │  │
│  │  (Verification, Installation, BackupRestore, Migration,  │  │
│  │   Clone, Delete, Lock, Database, Cron, ContextLoader,    │  │
│  │   PathResolver)                                          │  │
│  └──────────────┬──────────────────────────────────────────┘  │
│                  ↓                                              │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Services                                                  │  │
│  │  • ApacheService   (HttpServiceInterface)                 │  │
│  │  • MySqlService    (DbServiceInterface)                   │  │
│  │  • SslManager      (SslServiceInterface)                  │  │
│  │  • SystemCronService (CronServiceInterface)               │  │
│  │  • SettingsWriter  (Drupal settings.php)                  │  │
│  │  • ServiceRegistry (pluggable implementations)            │  │
│  └────┬─────────────────────────────────────────────────────┘  │
│       ↓ (uses)                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Core                                                      │  │
│  │  • Context / ContextRepository / AliasStore               │  │
│  │  • Filesystem, ProcessRunner, ConfigPaths, PlatformRoot   │  │
│  │  • TemplateRenderer (PHP templates, MD5 caching)          │  │
│  │  • Value Objects: DatabaseCredentials, ServerPaths,        │  │
│  │    ApacheVhostConfig, CronJobConfig                       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  EventDispatcher (51 lifecycle events)                          │
│      → VALIDATE → BEFORE → [Operation] → AFTER → ROLLBACK     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## The Context System

Aegir Provision operates on a **context-based** architecture. Contexts represent servers, platforms, and sites.

### Context Class

`Context` (`src/Core/Context.php`) is a `final` class.

- **Identity is immutable**: `name()` and `type()` are set at construction — no setters exist.
- **Properties are mutable**: `get($key)`, `set($key, $value)`, `all()`, `toArray()`.
- Some properties (e.g., `root`, `server`) are fixed by convention once set.

### Three Context Types

#### 1. Server Context

Represents an infrastructure node providing services (Apache, MySQL, SSL, Cron).

**Key properties**: `aegir_root`, `remote_host`, `script_user`, `http_service_type`, `db_service_type`, `http_port`, `context_type`

```yaml
# drush/sites/aegir/server_master.site.yml
server_master:
  provision:
    aegir_root: /home/aegir
    remote_host: aegir
    script_user: aegir
    http_service_type: apache
    http_port: 80
    context_type: server
  host: aegir
  user: aegir
```

#### 2. Platform Context

Represents a Drupal codebase. `PlatformRoot` auto-detects docroot layout (`/web`, `/docroot`, `/html`, or bare).

**Key properties**: `root`, `server`, `web_server`, `context_type`

```yaml
# drush/sites/aegir/platform_drupal11.site.yml
platform_drupal11:
  provision:
    context_type: platform
    server: server_master
    root: /var/aegir/platforms/drupal-11
    web_server: server_master
  root: /var/aegir/platforms/drupal-11/web
```

#### 3. Site Context

Represents an installed Drupal site.

**Key properties**: `platform`, `db_server`, `uri`, `db_name`, `db_user`, `db_passwd` (note: not `db_password`), `profile`, `language`, `context_type`

```yaml
# drush/sites/aegir/example.com.site.yml
example.com:
  provision:
    context_type: site
    platform: platform_drupal11
    db_server: server_aegir_db
    uri: example.com
    db_name: example_com
    db_user: example_com_user
    db_passwd: generated_password
    profile: standard
    language: en
  root: /var/aegir/platforms/drupal-11/web
  uri: example.com
```

### Context Storage

Contexts are Drush YAML site aliases stored in `drush/sites/aegir/`. The `AliasStore` class handles I/O.

```
drush/sites/aegir/
├── server_master.site.yml
├── server_aegir_db.site.yml
├── platform_drupal11.site.yml
├── example.com.site.yml
└── staging.example.com.site.yml
```

All provision-specific data lives under a `provision:` block; standard Drush keys (`root`, `uri`, `host`, `user`) sit at root level.

**Always use** `provision:save` to update contexts — don't edit YAML files directly.

### Context Hierarchy

```
Server (@server_master)
  ├── Platform (@platform_drupal11)     via 'server', 'web_server'
  │     ├── Site (@example.com)          via 'platform' + 'db_server'
  │     └── Site (@staging.example.com)
  └── Platform (@platform_d11_dev)
        └── Site (@dev.example.com)
```

Cross-references use plain names in YAML (e.g., `server_master`); code uses `@` prefix notation.

```php
$site = $contexts->load('example.com');
$platform = $contexts->load($site->get('platform'));
$server = $contexts->load($platform->get('server'));
```

---

## Package Hierarchy

### Layer 1: Foundation (Core, Config)

- `Context`, `ContextRepository`, `AliasStore` — data layer
- `Filesystem` — safe file operations (Symfony wrapper)
- `ProcessRunner` — command execution (Symfony Process)
- `ConfigPaths`, `PlatformRoot` — path utilities
- `TemplateRenderer` — PHP template engine with MD5 caching
- Value Objects: `DatabaseCredentials`, `ServerPaths`, `ApacheVhostConfig`, `CronJobConfig`

### Layer 2: Services

- `ApacheService` → `HttpServiceInterface` — vhost management, reload
- `MySqlService` → `DbServiceInterface` — database CRUD via PDO
- `SslManager` → `SslServiceInterface` — certificate resolution
- `SystemCronService` → `CronServiceInterface` — crontab management
- `SettingsWriter` — Drupal settings.php generation
- `ServiceRegistry` — pluggable implementations per type

### Layer 3: Orchestration

- `ProvisionManager` → 11 specialized managers
- Symfony `EventDispatcher` → 51 lifecycle events, 9 event classes

### Layer 4: Interface

- 17 Drush command classes (`src/Drush/Commands/`)
- `ProvisionServiceRegistry` — dependency registration
- `ProvisionAutowireTrait` — manager access for commands

---

## Data Flow

### Operation Flow (Example: provision:install)

```
1. ProvisionInstallCommand receives site context_name
   ↓
2. Load contexts: site → platform → server
   ↓
3. ProvisionManager delegates to InstallationManager
   ↓
4. Dispatch VALIDATE_INSTALL event
   ↓ (subscribers can throw to abort)
5. Dispatch BEFORE_INSTALL event
   ↓ (subscribers can modify event data)
6. Execute:
   • MySqlService.ensureDatabase() + grant()
   • SettingsWriter.write()
   • ApacheService.createVhost()
   • ProcessRunner.run(['drush', 'site:install', ...])
   • ApacheService.reloadService()
   ↓
7. Dispatch AFTER_INSTALL event
   ↓
8. Return success

If exception at step 6:
   ↓
9. Dispatch ROLLBACK_INSTALL event
10. Re-throw exception
```

---

## Service Communication Patterns

### 1. Value Objects for Type Safety

```php
$credentials = new DatabaseCredentials(
    host: $server->get('db_host', 'localhost'),
    port: (int) $server->get('db_port', 3306),
    name: $dbName,
    username: $dbUser,
    password: $dbPassword
);
// Immutable, validated at construction
```

### 2. Event-Based Extension

```php
class MySubscriber implements EventSubscriberInterface {
    public static function getSubscribedEvents(): array {
        return [ProvisionEvents::AFTER_INSTALL => 'onInstall'];
    }
    public function onInstall(InstallEvent $event): void {
        // Custom post-install logic
    }
}
```

### 3. Template-Based Configuration

```php
$vhost = $this->templates->render('apache/vhost.tpl.php', [
    'uri' => $site->get('uri'),
    'docroot' => $docroot,
]);
$this->filesystem->dumpFile($vhostPath, $vhost);
```

### 4. ServiceRegistry for Pluggable Implementations

```php
$registry->register('http', 'nginx', new NginxService(...));
$registry->setDefault('http', 'nginx');
$httpService = $registry->get('http', $server->get('http_service_type'));
```

---

## Directory Structure

### Project Layout

```
aegir-provision/
├── composer.json
├── src/
│   ├── ProvisionManager.php        # Central orchestrator
│   ├── Config/TemplateRenderer.php
│   ├── Core/                       # Context, AliasStore, ValueObjects
│   ├── Drush/
│   │   ├── ProvisionServiceRegistry.php
│   │   └── Commands/               # 17 command classes
│   ├── Event/                      # ProvisionEvents, 9 event classes
│   ├── Manager/                    # 11 specialized managers
│   └── Service/                    # 4 interfaces + 5 implementations
├── resources/templates/
│   ├── apache/
│   │   ├── vhost.tpl.php
│   │   └── vhost_ssl.tpl.php
│   └── drupal/
│       └── settings.php.tpl.php
├── examples/
└── doc/
```

### Runtime Layout

```
/var/aegir/                         # aegir_root
├── drupal/aegir-2601/              # Hostmaster install
│   ├── drush/sites/aegir/          # Context storage (YAML aliases)
│   └── web/                        # Drupal docroot
├── platforms/                      # Platform codebases
│   └── drupal-11/
│       └── web/sites/example.com/
│           ├── settings.php
│           └── files/
├── backups/                        # Site backups
└── .config/
    ├── apache/
    │   ├── vhost.d/                # Active HTTP vhosts
    │   ├── vhost_ssl.d/            # Active HTTPS vhosts
    │   └── platform.d/             # Platform configs
    └── ssl/
        └── example.com/
            ├── cert.pem
            └── key.pem
```

---

## Error Handling

### Exception Flow

```
VALIDATE event → exception aborts, no changes
BEFORE event   → exception aborts, no changes
Main logic     → exception triggers ROLLBACK, then re-thrown
AFTER event    → exception logged, operation considered successful
ROLLBACK event → exception logged, original exception re-thrown
```

### Rollback Coverage

Operations with `ROLLBACK_*` events: install, verify, backup, restore, deploy, migrate, clone (7 standard) + cron-add, cron-delete (2) = 9 rollback events.

Enable, disable, lock, unlock, delete have no rollback events.

---

## Security

- **Credentials**: `DatabaseCredentials` value object, PDO prepared statements, per-site isolation
- **File permissions**: 0750 dirs, 0640 files, aegir:www-data ownership; settings.php 0440
- **Command injection**: `ProcessRunner` uses Symfony Process array syntax (no shell expansion)
- **SSL**: SslManager with Let's Encrypt → Cloudflare → self-signed fallback
- **Cron**: `# AEGIR {id}` markers for safe crontab management

---

## Workflow Patterns

### Standard Site Deployment

1. `provision:save server_master --type=server ...` (one-time)
2. `provision:save platform_d11 --type=platform ...`
3. `provision:verify platform_d11`
4. `provision:save example.com --type=site ...`
5. `provision:install example.com`
6. `provision:verify example.com`

### Drupal Core Update

1. Deploy new codebase as new platform
2. `provision:verify new_platform`
3. `provision:migrate example.com --target=new_platform`
4. Test, then remove old platform

### Backup/Restore

1. `provision:backup example.com` → timestamped tarball
2. `provision:restore example.com /path/to/backup.tar.gz`
3. Or `provision:deploy` to deploy backup to different site

### Multi-Site on Single Platform

One platform context, multiple site contexts sharing the same codebase. Each site gets its own database, vhost, settings.php, and files directory.

---

## Best Practices

### Context Naming

- Sites: use the domain (`example.com`, `staging.example.com`)
- Servers: `server_{name}` (e.g., `server_master`, `server_web1`)
- Platforms: `platform_{identifier}` (e.g., `platform_d11`, `platform_d11_dev`)

### Testing

- Always `provision:verify` after creating/updating contexts
- Test migrations on staging before production
- Keep backups before major changes
- Validate Apache config: `apache2ctl configtest`

---

## Related Documentation

- [provision-d11.md](../provision-d11.md) — Complete D11 architecture reference
- [API Reference](api-reference.md) — Class and method documentation
- [Extension System](extension-system.md) — Creating extensions and service plugins
- [provision-d7.md](../provision-d7.md) — D7 historical reference
