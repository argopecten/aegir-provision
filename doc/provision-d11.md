# Aegir Provision — D11 System Architecture

## Scope

This document defines the architecture of the Provision backend for Drupal 11, targeting Drush 13.7+ and PHP 8.3+. It covers the context model, service layer, command surface, event system, and hostmaster integration.

For historical D7 reference, see [provision-d7.md](provision-d7.md).

## Goals

- Automate Drupal 11 hosting lifecycle: install, verify, backup, restore, migrate, clone, enable, disable, lock, unlock, delete
- Operate exclusively on YAML contexts — never access the Drupal database
- Keep services modular via typed interfaces (HTTP, DB, SSL, Cron)
- Extend via Symfony EventDispatcher (51 lifecycle events)
- Target Composer-only Drupal 11 platforms, PHP 8.3+, Drush 13.7+

## Non-Goals

- D7/D8/D9/D10 backward compatibility
- Packaging, make, or download workflows
- Aegir frontend redesign (that is the hosting module's concern)

---

## Component Map

```
┌──────────────────────────────────────────────────────────────┐
│ CLI Interface                                                 │
│ 17 Drush Command Classes (src/Drush/Commands/)               │
│ ProvisionAutowireTrait + ProvisionServiceRegistry            │
└──────────────┬───────────────────────────────────────────────┘
               │
┌──────────────▼───────────────────────────────────────────────┐
│ Orchestration                                                 │
│ ProvisionManager → 11 Specialized Managers                   │
│ Symfony EventDispatcher (51 lifecycle events)                 │
└──────────────┬───────────────────────────────────────────────┘
               │
┌──────────────▼───────────────────────────────────────────────┐
│ Service Layer                                                 │
│ 4 Interfaces: Http, Db, Ssl, Cron                            │
│ Implementations: ApacheService, MySqlService, SslManager,    │
│                  SystemCronService, SettingsWriter            │
│ ServiceRegistry for pluggable implementations                │
└──────────────┬───────────────────────────────────────────────┘
               │
┌──────────────▼───────────────────────────────────────────────┐
│ Core Infrastructure                                           │
│ Context, ContextRepository, AliasStore, ContextType          │
│ Filesystem, ProcessRunner, ConfigPaths, PlatformRoot         │
│ ValueObjects: DatabaseCredentials, ServerPaths,              │
│               ApacheVhostConfig, CronJobConfig               │
│ TemplateRenderer (PHP templates with MD5 caching)            │
└──────────────┬───────────────────────────────────────────────┘
               │
┌──────────────▼───────────────────────────────────────────────┐
│ Runtime Data                                                  │
│ drush/sites/aegir/*.site.yml (YAML context files)            │
│ {config_path}/apache/ (vhost configs)                        │
│ resources/templates/ (PHP config templates)                   │
└──────────────────────────────────────────────────────────────┘
```

## Drush Extension Layout

Provision is a Composer package (`type: drupal-drush`) with this structure:

```
vendor/argopecten/aegir-provision/
├── composer.json
├── src/
│   ├── ProvisionManager.php
│   ├── Config/TemplateRenderer.php
│   ├── Core/            # Context, AliasStore, ValueObjects, etc.
│   ├── Drush/
│   │   ├── ProvisionServiceRegistry.php
│   │   └── Commands/    # 17 command classes + ProvisionAutowireTrait
│   ├── Event/           # ProvisionEvents, 9 event classes
│   ├── Manager/         # 11 specialized managers
│   └── Service/         # 4 interfaces + 5 implementations
└── resources/templates/ # Apache, Drupal settings templates
```

Commands are auto-discovered by Drush 13.7+ (no `drush.services.yml`). `ProvisionServiceRegistry` registers provision services for autowiring. `ProvisionAutowireTrait` provides the `ProvisionManager` to command classes.

---

## Context Model

### Context Class

`Context` (`src/Core/Context.php`) is a `final` class.

**Identity is immutable**: `name()` and `type()` are set at construction — no setters exist.
**Properties are mutable**: `get($key, $default)`, `set($key, $value)`, `all()`, `toArray()`.
Some properties (e.g., `root`, `server`) are fixed by convention once set.

```php
final class Context {
    public function __construct(string $name, string $type, array $data = [])
    public function name(): string      // Stripped of leading @
    public function alias(): string     // '@' . name
    public function type(): string      // 'server', 'platform', 'site'
    public function get(string $key, mixed $default = null): mixed
    public function set(string $key, mixed $value): void
    public function all(): array
    public function toArray(): array
}
```

`ContextType` enum provides `SERVER`, `PLATFORM`, `SITE` constants. No typed subclasses exist — type is a string property.

### Context Storage

Contexts are stored as Drush YAML site aliases in `drush/sites/aegir/{name}.site.yml`. The `AliasStore` class handles I/O. All provision-specific data lives under a `provision:` block; standard Drush keys (`root`, `uri`, `host`, `user`) sit at root level.

### Server Context

Represents a service provider (Apache, MySQL, SSL, Cron).

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

Key properties: `aegir_root`, `remote_host`, `script_user`, `http_service_type`, `db_service_type`, `http_port`, `context_type`

Config directory layout (managed by services):
```
{config_path}/apache/
├── vhost.d/          # Active HTTP vhosts
├── vhost_ssl.d/      # Active HTTPS vhosts
├── disabled.d/       # Disabled site vhosts
└── platform.d/       # Platform-level includes
```

### Platform Context

Represents a Drupal codebase.

```yaml
platform_drupal11:
  provision:
    context_type: platform
    server: server_master
    root: /var/aegir/platforms/drupal-11
    web_server: server_master
  root: /var/aegir/platforms/drupal-11/web
```

Key properties: `root` (platform base, parent of web dir), `server`, `web_server`

`PlatformRoot` auto-detects the docroot layout: `/web`, `/docroot`, `/html`, or bare root.

### Site Context

Represents an installed Drupal site.

```yaml
example.com:
  provision:
    context_type: site
    platform: platform_drupal11
    db_server: server_aegir_db
    uri: example.com
    root: /var/aegir/platforms/drupal-11
    db_name: example_com
    db_user: example_com_user
    db_passwd: generated_password
    profile: standard
    language: en
  root: /var/aegir/platforms/drupal-11/web
  uri: example.com
```

Key properties: `platform`, `db_server`, `uri`, `db_name`, `db_user`, `db_passwd` (note: not `db_password`), `profile`, `language`

### Context Hierarchy

```
Site → Platform (via 'platform') + Server (via 'db_server')
Platform → Server (via 'server', 'web_server')
Server → standalone
```

Cross-references use plain names in YAML (e.g., `server_master`); code uses `@` prefix notation (e.g., `@server_master`).

---

## Service Architecture

### Service Interfaces

| Interface | File | Purpose |
|---|---|---|
| `HttpServiceInterface` | `src/Service/HttpServiceInterface.php` | Web server operations |
| `DbServiceInterface` | `src/Service/DbServiceInterface.php` | Database operations |
| `SslServiceInterface` | `src/Service/SslServiceInterface.php` | SSL certificate management |
| `CronServiceInterface` | `src/Service/CronServiceInterface.php` | Cron job management |

### Implementations

| Class | Interface | Key Operations |
|---|---|---|
| `ApacheService` | HttpServiceInterface | createVhost, removeVhost, enableSite, disableSite, reloadService |
| `MySqlService` | DbServiceInterface | ensureDatabase, ensureUser, grant, dropDatabase, dump, import |
| `SslManager` | SslServiceInterface | Certificate resolution (Let's Encrypt → Cloudflare → self-signed) |
| `SystemCronService` | CronServiceInterface | crontab management with `# AEGIR {id}` markers |
| `SettingsWriter` | (standalone) | Generate settings.php from PHP templates |

### ServiceRegistry

`ServiceRegistry` (`src/Service/ServiceRegistry.php`) manages available implementations. Services are registered by type and name, with a default per type.

```php
$registry->register('http', 'nginx', new NginxService(...));
$registry->setDefault('http', 'nginx');
```

Per-server service type is determined by context properties (`http_service_type`, `db_service_type`).

### MySqlService Security

Uses PDO with prepared statements — SQL injection mitigated. Database credentials are generated per-site. Grants use minimal privileges.

---

## Drush Command Surface

17 command classes in `src/Drush/Commands/`, each extending `DrushCommands` with PHP 8 `#[CLI\Command]` attributes.

| Command | Class | Operation |
|---|---|---|
| `provision:save` | ProvisionSaveCommands | Create/update/delete context YAML |
| `provision:verify` | ProvisionVerifyCommands | Verify server/platform/site configuration |
| `provision:install` | ProvisionInstallCommands | Install Drupal site (DB + settings + vhost) |
| `provision:import` | ProvisionImportCommands | Import existing Drupal site |
| `provision:backup` | ProvisionBackupCommands | Backup site DB + files to tarball |
| `provision:restore` | ProvisionRestoreCommands | Restore from backup tarball |
| `provision:deploy` | ProvisionDeployCommands | Deploy backup to existing site |
| `provision:migrate` | ProvisionMigrateCommands | Migrate site to new platform |
| `provision:clone` | ProvisionCloneCommands | Clone site to new context |
| `provision:enable` | ProvisionEnableCommands | Enable (activate) site |
| `provision:disable` | ProvisionDisableCommands | Disable site |
| `provision:lock` | ProvisionLockCommands | Lock context (maintenance mode) |
| `provision:unlock` | ProvisionUnlockCommands | Unlock context |
| `provision:delete` | ProvisionDeleteCommands | Delete context and resources |
| `provision:login-reset` | ProvisionLoginResetCommands | Admin one-time login link |
| `provision:cron` | ProvisionCronCommands | Manage cron jobs |
| `backend:parse` | BackendParseCommands | Parse Drush backend output |

Commands accept context name **without** `@` prefix. Exit codes follow Symfony Console conventions.

---

## ProvisionManager & Managers

`ProvisionManager` (`src/ProvisionManager.php`) is the central orchestrator. It delegates to 11 specialized managers:

| Manager | Key Methods |
|---|---|
| `VerificationManager` | verifyServer(), verifyPlatform(), verifySite() |
| `InstallationManager` | install(), enable(), disable() |
| `BackupRestoreManager` | backup(), restore(), deploy() |
| `MigrationManager` | migrate() |
| `CloneManager` | cloneSite() |
| `DeleteManager` | delete() |
| `LockManager` | lock(), unlock() |
| `DatabaseManager` | Credential generation, database operations |
| `CronManager` | Cron job lifecycle |
| `ContextLoader` | Context loading from YAML |
| `PathResolver` | Path calculation and validation |

---

## Event System

Symfony EventDispatcher is integrated throughout ProvisionManager and all specialized managers.

### 51 Lifecycle Events

Events are defined as constants in `ProvisionEvents` (`src/Event/ProvisionEvents.php`):

| Phase | Purpose | Operations |
|---|---|---|
| `VALIDATE_*` | Can block/abort before start | 12 standard + 2 cron |
| `BEFORE_*` | Pre-operation hooks | 12 standard + 2 cron |
| `AFTER_*` | Post-operation hooks | 12 standard + 2 cron |
| `ROLLBACK_*` | Fire on exceptions | 7 standard + 4 cron |

**Standard operations** (12): install, verify, backup, restore, deploy, migrate, clone, delete, enable, disable, lock, unlock

**Cron operations** (2): cron-add, cron-delete

**Rollback coverage**: install, verify, backup, restore, deploy, migrate, clone (7 standard) + all 4 cron = 11. Enable/disable/lock/unlock/delete have no rollback.

### Event Classes

All extend abstract `ProvisionEvent`:

| Class | Operations |
|---|---|
| `InstallEvent` | install |
| `VerifyEvent` | verify |
| `BackupEvent` | backup |
| `RestoreEvent` | restore |
| `DeployEvent` | deploy |
| `MigrateEvent` | migrate |
| `CloneEvent` | clone |
| `DeleteEvent` | delete |
| `CronEvent` | cron-add, cron-delete |

### Event Lifecycle per Operation

```
VALIDATE_{OP} → (abort if blocked)
BEFORE_{OP}   → (modify event data)
   ↓ actual operation
AFTER_{OP}    → (post-processing)
   or
ROLLBACK_{OP} → (on exception, if available)
```

---

## Hostmaster Integration

The Aegir Hostmaster frontend (aegir-hosting Drupal module) invokes provision via Drush shell commands:

```
Frontend Entity Save → ContextRegistry → provision:save → YAML written
Frontend Task Queue  → BackendInvoker  → provision:{op} → operation executed
```

The backend never accesses the Drupal database. Communication is:
1. **Frontend → Backend**: Shell execution of `drush provision:{command} {context_name}`
2. **Backend → Frontend**: Exit code + stdout/stderr (captured by BackendInvoker)
3. **Shared state**: YAML alias files in `drush/sites/aegir/`

---

## Template System

`TemplateRenderer` (`src/Config/TemplateRenderer.php`) renders PHP templates from `resources/templates/`:

```
resources/templates/
├── apache/
│   ├── vhost.tpl.php        # HTTP vhost
│   └── vhost_ssl.tpl.php    # HTTPS vhost
└── drupal/
    └── settings.php.tpl.php # Drupal settings.php
```

Features:
- MD5-based caching with mtime invalidation
- Priority-based directory stack (extensions can register higher-priority template dirs)
- Variables passed as PHP array, rendered via `extract()` + `include`

---

## Value Objects

Immutable data containers in `src/Core/ValueObject/`:

| Class | Properties |
|---|---|
| `DatabaseCredentials` | host, port, name, username, password, driver |
| `ServerPaths` | aegirRoot, configPath, backupPath, platformsPath |
| `ApacheVhostConfig` | serverName, documentRoot, port, serverAliases |
| `CronJobConfig` | Cron job definition fields |

---

## Infrastructure Classes

| Class | Purpose |
|---|---|
| `Filesystem` | Symfony Filesystem wrapper with logging |
| `ProcessRunner` | External command execution via Symfony Process |
| `ConfigPaths` | Calculate config paths for server/context |
| `PlatformRoot` | Auto-detect /web, /docroot, /html |
| `ContextRepository` | Load/save/delete contexts via AliasStore |
| `AliasStore` | YAML I/O for drush/sites/aegir/ |

---

## Security Considerations

- **Database credentials**: Generated per-site, minimal privileges, PDO prepared statements
- **File permissions**: 0750 directories, 0640 files, aegir:www-data ownership
- **Command injection**: ProcessRunner uses Symfony Process (array arguments, no shell expansion)
- **SSL**: SslManager with Let's Encrypt, Cloudflare, and self-signed fallback
- **Cron markers**: `# AEGIR {id}` for safe crontab management

---

## Implementation Status

| Component | Status |
|---|---|
| Context system | ✅ Complete |
| All 17 command classes | ✅ Complete |
| 11 specialized managers | ✅ Complete |
| 4 service interfaces | ✅ Complete |
| 5 service implementations | ✅ Complete |
| 51 lifecycle events | ✅ Complete |
| ServiceRegistry | ✅ Complete |
| TemplateRenderer | ✅ Complete |
| Value objects (4) | ✅ Complete |
| PHPUnit tests | ❌ 0% coverage |
| PHP-FPM per-site pools | ❌ Not implemented |
| Context schema validation | ❌ Not implemented |
| Nginx service | ❌ Not implemented (interface exists) |
| Remote server support | ❌ Not implemented |

See [roadmap.md](roadmap.md) for detailed status and priorities.

---

## D7 → D11 Key Changes

| Aspect | D7 | D11 |
|---|---|---|
| Context | `d()` global function, typed subclasses | `Context` final class, no subclasses |
| Hooks | `hook_provision_*` | Symfony EventDispatcher |
| Services | `provision_service_*()` functions | Typed interfaces + ServiceRegistry |
| Commands | `provision-*` (Drush 8 style) | `provision:*` (Drush 13 `#[CLI\Command]`) |
| Config | `drush_get_option()` | Context `get()`/`set()` |
| Templates | Custom `provision_config` class | `TemplateRenderer` with PHP templates |
| Storage | `~/.drush/*.alias.drushrc.php` | `drush/sites/aegir/*.site.yml` |
| Drupal support | D6, D7, (D8 partial) | D11 only |
| PHP | 5.x-7.x | 8.3+ with strict types |

---

**Related**: [Home.md](Home.md) · [roadmap.md](roadmap.md) · [architecture.md](guides/architecture.md) · [provision-d7.md](provision-d7.md)
