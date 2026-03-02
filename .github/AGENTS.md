# AI Agent Guide — aegir-provision (Backend)

> **Repository**: Drush 13 infrastructure automation extension
> **Local Path**: `vendor/argopecten/aegir-provision/`
> **GitHub**: https://github.com/argopecten/aegir-provision

## You Are Here

This is the **Backend Component** — a standalone Drush command package (NOT a Drupal module). It cannot use Drupal APIs, entities, hooks, or database abstraction. Commands run outside Drupal bootstrap.

**This component handles**:
- ✅ 19 Drush commands (`provision:save`, `provision:install`, etc.)
- ✅ Context system (Server, Platform, Site YAML aliases)
- ✅ Service layer (Apache, MySQL, SSL, Cron)
- ✅ Infrastructure automation (vhosts, databases, filesystem, settings.php)
- ❌ Drupal entities (→ aegir-hosting)
- ❌ Task queue and UI (→ aegir-hosting)
- ❌ Theme/presentation (→ aegir-eldir)

## Architecture

```
Drush Commands (19)
    ↓
ProvisionManager (facade)
    ↓
11 Specialized Managers
    ↓
ServiceRegistry → 4 Interfaces → 5 Implementations
    ↓
Infrastructure (Apache, MySQL, filesystem, crontab)
```

### Source Tree

```
src/
├── ProvisionManager.php                  # Facade — delegates to managers
├── Config/
│   └── TemplateRenderer.php              # Priority-based template engine with caching
├── Core/
│   ├── Context.php                       # Mutable data bag (name, type, key-value)
│   ├── ContextRepository.php             # Load/save/delete via AliasStore
│   ├── ContextType.php                   # Constants: SERVER, PLATFORM, SITE
│   ├── AliasStore.php                    # Read/write YAML files on disk
│   ├── ConfigPaths.php                   # Path resolution (root, config, ssl, backups)
│   ├── Filesystem.php                    # ensureDir, writeFile, remove, symlink
│   ├── PlatformRoot.php                  # Auto-detect docroot (web/, docroot/, html/)
│   ├── ProcessRunner.php                 # Run shell commands via Symfony\Process
│   └── ValueObject/
│       ├── ApacheVhostConfig.php         # final readonly
│       ├── CronJobConfig.php             # final readonly
│       ├── DatabaseCredentials.php       # final readonly
│       └── ServerPaths.php               # final readonly
├── Drush/
│   ├── ProvisionServiceRegistry.php      # Static register() wires League Container
│   └── Commands/                         # 17 command classes (one per file)
│       ├── ProvisionAutowireTrait.php    # Bootstraps Provision services into Drush container
│       ├── ProvisionSaveCommands.php
│       ├── ProvisionVerifyCommands.php
│       ├── ProvisionInstallCommands.php
│       ├── ProvisionDeleteCommands.php
│       ├── ProvisionBackupCommands.php
│       ├── ProvisionRestoreCommands.php
│       ├── ProvisionDeployCommands.php
│       ├── ProvisionMigrateCommands.php
│       ├── ProvisionCloneCommands.php
│       ├── ProvisionEnableCommands.php
│       ├── ProvisionDisableCommands.php
│       ├── ProvisionLockCommands.php
│       ├── ProvisionUnlockCommands.php
│       ├── ProvisionLoginResetCommands.php
│       ├── ProvisionImportCommands.php
│       ├── ProvisionCronCommands.php
│       └── BackendParseCommands.php
├── Event/
│   ├── ProvisionEvent.php                # Abstract base
│   ├── ProvisionEvents.php               # 80 event name constants
│   └── VerifyEvent, InstallEvent, DeleteEvent, BackupEvent,
│       RestoreEvent, DeployEvent, MigrateEvent, CloneEvent, CronEvent
├── Manager/
│   ├── ContextLoader.php                 # Load related contexts
│   ├── PathResolver.php                  # Docroot, site path, server paths
│   ├── DatabaseManager.php               # DB credentials, grants
│   ├── VerificationManager.php           # Verify server/platform/site
│   ├── InstallationManager.php           # Install, enable, disable, loginReset
│   ├── DeleteManager.php                 # Delete context + optional files/db
│   ├── BackupRestoreManager.php          # tar.gz + SQL backup/restore/deploy
│   ├── CloneManager.php                  # Clone site (db + files + settings.php)
│   ├── MigrationManager.php              # Migrate site between platforms
│   ├── LockManager.php                   # .aegir.lock files
│   └── CronManager.php                   # crontab entries via CronServiceInterface
└── Service/
    ├── ServiceRegistry.php               # Pluggable registry with lazy factories
    ├── HttpServiceInterface.php          # ensureServerLayout, enableSite, disableSite, removeSite, reload
    ├── DbServiceInterface.php            # ensureDatabase, ensureUser, grant, dropDatabase, dump, import
    ├── SslServiceInterface.php           # resolve
    ├── CronServiceInterface.php          # addCron, deleteCron, hasCron, listCron, reload
    ├── Http/ApacheService.php            # Vhost dirs: pre.d, post.d, platform.d, vhost.d, vhost_ssl.d, disabled.d
    ├── Db/MySqlService.php               # PDO-based, connection caching, mysqldump/import
    ├── Ssl/SslManager.php                # Priority: Let's Encrypt → Cloudflare → self-signed
    ├── Cron/SystemCronService.php        # crontab with # AEGIR {id} markers
    └── Drupal/SettingsWriter.php         # Generate settings.php from template
```

### Context System

`Context` is a single `final` class with **immutable identity** (`name()`, `alias()`, `type()` — set at construction, no setters) and **mutable properties** (`get($key, $default)`, `set($key, $value)`, `all()`, `toArray()`). Some properties (e.g., `root`, `server`) are fixed by convention once set. No typed subclasses exist.

**Storage**: YAML files in `drush/sites/aegir/`, managed by `AliasStore`. Format uses `provision:` block for Aegir metadata + standard Drush keys at root level.

### Event Lifecycle

Every operation follows: **VALIDATE → BEFORE → execute → AFTER** (or **ROLLBACK** on exception).

14 operations × up to 4 phases = 80 event constants in `ProvisionEvents`. Operations: install, verify, backup, restore, migrate, clone, delete, deploy, enable, disable, lock, unlock, cron_add, cron_delete.

### Service Registry

4 pluggable service types with lazy factory instantiation:

| Type | Default | Interface | Implementation |
|------|---------|-----------|----------------|
| `http` | `apache` | `HttpServiceInterface` | `ApacheService` |
| `db` | `mysql` | `DbServiceInterface` | `MySqlService` |
| `ssl` | `default` | `SslServiceInterface` | `SslManager` |
| `cron` | `system` | `CronServiceInterface` | `SystemCronService` |

## Development Rules

1. **All classes `final`** — value objects `final readonly`
2. **No Drupal APIs** — this package operates without Drupal bootstrap
3. **Idempotent commands** — safe to run multiple times
4. **Colon command names** — `provision:save` not `provision-save`
5. **Extend `DrushCommands`** with `ProvisionAutowireTrait`
6. **PSR-4 autodiscovery** from `src/Drush/Commands/`, namespace `Aegir\Provision\Drush\Commands`
7. **No `drush.services.yml`** — services registered procedurally in `ProvisionServiceRegistry::register()`
8. **Breaking changes allowed**, no update hooks, modern PHP 8.3+ only

## Known Issues

- **`LockManager.php`** instantiates abstract `ProvisionEvent` directly — PHP Fatal Error at runtime. Needs concrete `LockEvent`/`UnlockEvent` classes.
- **0% test coverage** — no `tests/` directory, no PHPUnit config. Critical gap.
- **Namespace mismatch**: Docs reference `Drush\Commands\provision` namespace but actual code uses `Aegir\Provision\Drush\Commands`

## Statistics

| Metric | Count |
|--------|-------|
| PHP source files | 47 |
| Classes | 42 |
| Interfaces | 4 |
| Traits | 1 (`ProvisionAutowireTrait`) |
| Abstract classes | 1 (`ProvisionEvent`) |
| Value objects | 4 |
| Drush commands | 19 |
| Event constants | 80 |
| Managers | 11 |
| Service implementations | 5 |
| Template files | 3 (vhost, vhost_ssl, settings.php) |
| Test files | **0** |

## Related Components

| Component | Path | When to reference |
|-----------|------|-------------------|
| Hosting (Frontend) | `web/modules/contrib/aegir-hosting/` | Entity operations, forms, ContextRegistry |
| Eldir (Theme) | `web/themes/contrib/aegir-eldir/` | UI rendering |
| Main repo | `.github/AGENTS.md` | Cross-component architecture |

## Drush 13 Reference

- **Commands**: https://www.drush.org/13.x/commands/#creating-custom-drush-commands
- **Dependency Injection**: https://www.drush.org/13.x/dependency-injection/
- **Site Aliases**: https://www.drush.org/13.x/site-aliases/
- **Bootstrap Levels**: https://www.drush.org/13.x/bootstrap/
