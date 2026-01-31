# Aegir Provision (D11) System Architecture Document

## Scope
This SAD defines a Drupal 10+ (Composer-only) oriented architecture for the Provision backend. It reuses the subsystem model described in `architecture/provision-d7.md`, and specifies the compatibility targets for Drupal 10+, Drush 13.7+, and PHP 8.3+.

The document describes functionality, component map, context definitions, hostmaster integration, service types (http/db), SSL behavior, Drupal version support, Drush command surface, and dependencies within a Drupal 11 architecture.

## Goals
- Preserve Provision's core lifecycle behavior (server/platform/site management, config generation, task execution).
- Target **Drupal 10+** Composer-only platforms, **Drush 13.7+**, and **PHP 8.3+** compatibility.
- Ensure supported Drupal 10+ platforms are compatible with PHP 8.3+.
- Maintain the backend/frontend split where Hostmaster drives Provision via Drush commands and aliases.
- Keep services modular (http, db) with explicit configuration and lifecycle hooks.

## Non-goals
- Reintroduce packaging, test, or drush make flows (these were removed).
- Redesign the Aegir hosting frontend or its data model.
- Provide full migration guides; this is an architecture definition only.

## Component map
Provision is organized around contexts and services, with task modules that implement platform/site workflows.

Primary components:
- **Core API and autoload**: `provision.inc`, `provision.service.inc`
- **Context system**: `provision.context.inc`, `Provision/Context.php`, `Provision/Context/server.php`, `Provision/Context/platform.php`, `Provision/Context/site.php`
- **Service layer**: `Provision/Service.php`, `db/Provision/Service/*`, `http/Provision/Service/*`
- **Configuration generation**: `Provision/Config/*`, `http/Provision/Config/*`
- **Platform engine**: `platform/*.provision.inc`, `platform/provision_drupal.drush.inc`
- **Drupal version shims**: `platform/drupal/*.inc`
- **Hostmaster integration**: `install.hostmaster.inc`, `migrate.hostmaster.inc`, `uninstall.hostmaster.inc`
- **Drush command surface**: `provision.drush.inc`, `parse.backend.inc`

## Drush extension layout (vendor)
Provision is delivered as a Composer package that registers Drush commands from `vendor/`, without requiring a Drupal module.

Drush 13.7+ required package layout:
- `composer.json` (type: `drupal-drush`)
- `src/Drush/Commands/` (auto-discovered command classes in the `Aegir\Provision\Drush\Commands` namespace)
- `src/Drush/Commands/Provision*Command.php` (one Symfony Console command per file, using `#[AsCommand]` and `ProvisionAutowireTrait`)
- `src/Drush/Commands/ProvisionAutowireTrait.php` (wraps Drush `AutowireTrait` and registers Provision services)
- `src/Drush/ProvisionServiceRegistry.php` (registers Provision services in the Drush container for autowiring)
- `src/Core/*` (core infrastructure: Context, ContextRepository, ContextType, AliasStore, Filesystem, ProcessRunner, ConfigPaths, PlatformRoot)
- `src/ProvisionManager.php` (main orchestration and task execution)
- `src/Service/*` (service implementations: Db/MySqlService, Http/ApacheService, Drupal/SettingsWriter, Ssl/SslManager)
- `src/Config/TemplateRenderer.php` (template rendering engine)
- `resources/templates/*` (config templates for Apache, Drupal settings, etc.)

Legacy note: `drush.services.yml` is deprecated for Drush 13.7+ and has been removed in favor of command auto-discovery and a small registry to make Provision services available for autowiring.

The implementation uses modern PHP 8.3+ features including strict types, constructor property promotion, readonly properties, and PHP 8 attributes.

## Context model
Contexts are named Drush site aliases representing infrastructure and Drupal objects. The Context class (`Aegir\Provision\Core\Context`) is a simple data structure managed by ContextRepository. Contexts are stored as YAML site alias files in `~/.drush/sites/aegir/*.site.yml`.

### Server context
Responsibilities:
- Stores server identity and filesystem layout (`aegir_root`, `config_path`, `clients_path`, `backup_path`).
- Identifies remote host (`remote_host`), SSH user (`script_user`), and service types (http, db).
- Owns HTTP service configuration directories (managed by ApacheService).

Code references:
- Context class: `src/Core/Context.php` (simple data container with name, type, and key-value data)
- Context repository: `src/Core/ContextRepository.php` (load, save, delete operations)
- Context types: `src/Core/ContextType.php` (constants: SERVER, PLATFORM, SITE)
- Alias storage: `src/Core/AliasStore.php` (reads/writes YAML site aliases in `~/.drush/sites/aegir/`)

Current implementation:
- Server contexts are stored as Drush 13 YAML site aliases with `host` and `user` keys.
- Remote execution is handled by ProcessRunner with optional SSH integration.
- Service configuration layout is created by ApacheService and MySqlService.

### Platform context
Responsibilities:
- Represents a Drupal codebase (root path) and associated webserver.
- Stores platform root, associated server reference, and platform-specific configuration.
- Orchestrates platform verification including directory structure validation.

Code references:
- Platform root detection: `src/Core/PlatformRoot.php` (detects `/web`, `/docroot`, `/html` layouts)
- ProvisionManager platform verify: `src/ProvisionManager.php` (`verifyPlatform()`)

Current implementation:
- Platform contexts store `root` (platform base path), `server` (server context reference).
- Platform verification ensures directory structure and generates Apache vhost configuration.
- Auto-detects Composer-based Drupal 11 layouts with `/web` docroot.

### Site context
Responsibilities:
- Represents a site within a platform (`uri`, `root`, `platform`, `db_server`).
- Stores database credentials and site-specific configuration.
- Generates Drupal settings.php and Apache vhost configuration.

Code references:
- Site verification: `src/ProvisionManager.php` (`verifySite()`)
- Settings generation: `src/Service/Drupal/SettingsWriter.php`
- Apache vhost: `src/Service/Http/ApacheService.php`

Current implementation:
- Site contexts are stored as Drush 13 YAML aliases with `root` and `uri` keys.
- Site verification generates settings.php from templates in `resources/templates/drupal/`.
- Apache vhost config is generated from templates in `resources/templates/apache/`.
- Database credentials are stored in the context and written to settings.php.

## Context option schema (key fields)
The following options are read from Drush context and persisted in alias/config files.

Server context options:
- `remote_host`, `script_user`, `aegir_root`, `master_url`, `admin_email`
- `http_service_type`, `http_port`, `http_ssl_port`, `http_restart_cmd`
- `db_service_type`, `master_db`, `db_grant_all_hosts`, `db_port`
- `web_group`, `backup_path`, `clients_path`, `config_path`

Platform context options:
- `root`, `server`, `web_server`
- `packages` (derived during verify for module/profile data)

Site context options:
- `uri`, `platform`, `db_server`, `profile`, `install_method`
- `aliases`, `redirection`, `client_name`, `drush_aliases`
- `db_type`, `db_host`, `db_port`, `db_user`, `db_passwd`, `db_name` (assigned by DB service)

## Configuration and data stores
Provision writes configuration and state using a modern template-based approach.

Current implementation:
- **Site aliases**: YAML files in `~/.drush/sites/aegir/*.site.yml` (managed by `src/Core/AliasStore.php`)
- **Apache vhosts**: Generated from templates in `resources/templates/apache/` (managed by `src/Service/Http/ApacheService.php`)
- **Drupal settings.php**: Generated from templates in `resources/templates/drupal/` (managed by `src/Service/Drupal/SettingsWriter.php`)
- **Server configuration**: Apache config directories under `${config_path}/apache/` with `pre.d`, `post.d`, `platform.d`, `vhost.d`, `vhost_ssl.d`, `disabled.d` subdirectories

Template system:
- Templates are PHP files with `.tpl.php` extension
- TemplateRenderer (`src/Config/TemplateRenderer.php`) handles variable substitution
- Template paths resolved from package `resources/templates/` directory

Alias storage:
- AliasStore supports multiple search paths via `DRUSH_SITE_ALIAS_PATH` environment variable
- Default path: `~/.drush/sites/aegir/`
- Alias format: `<context-name>.site.yml` with nested structure for Drush 13
- Context data stored in `provision:` key within alias file
- Site aliases include `root` and `uri` for Drupal site bootstrap
- Server aliases include `host` and `user` for SSH operations

Code references:
- Alias storage: `src/Core/AliasStore.php`
- Template rendering: `src/Config/TemplateRenderer.php`
- Config paths: `src/Core/ConfigPaths.php`

## Hostmaster integration
Provision is triggered by the Aegir frontend (Hostmaster) through Drush commands and backend invocations.

Responsibilities:
- Create and update context aliases for server/platform/site.
- Dispatch provisioning tasks (`provision-install`, `provision-verify`, `provision-migrate`, etc.).
- Coordinate with hosting frontend tasks (`hosting-task`, `hosting-setup`, `hosting-resume`), invoked via `provision_backend_invoke()`.

Code references:
- Install flow: `install.hostmaster.inc`
- Migrate flow: `migrate.hostmaster.inc`
- Uninstall flow: `uninstall.hostmaster.inc`
- Backend invoke wrapper: `provision.inc` (`provision_backend_invoke()`)
- Backend output parsing: `parse.backend.inc`
- Hostmaster task calls: `migrate.hostmaster.inc` (uses `hosting-pause`, `hosting-resume`, `hosting-task`)

D11 compatibility notes:
- Drush 13 process invocation and context integration must be verified for `drush_invoke_process()` and backend output parsing.
- Hostmaster modules for Drupal 11 must provide equivalent schema and hooks for site/platform/server management.

## Frontend (aegir-hosting) integration contract
The Drupal 11 frontend modules in `aegir-hosting` provide the task queue, context registry, and UI for dispatching Provision commands. This section documents the runtime contract between frontend and backend.

Frontend modules and responsibilities:
- `hosting`: core services, queue dispatch, backend invoker, context registry, admin UI.
- `hosting_task`: task entity, queue worker, task logs, and Drush command for creating tasks.
- `hosting_platform`, `hosting_site`, `hosting_server`, `hosting_db_server`, `hosting_web_server`, `hosting_package`, `hosting_client`: entity models and feature definitions for domain objects.

Backend invocation contract:
- Backend commands are executed by `hosting.backend_invoker` (`hosting/src/Service/BackendInvoker.php`), which shells out to the configured Drush binary and optional alias from `hosting.settings`.
- Task execution is driven by `hosting_task`:
  - `TaskManager::createTask()` stores tasks with `command = 'provision-' . $task_type` and `context_name` (without `@`).
  - `TaskManager::runTaskId()` calls backend with args `[$context_name]` and options from the task record.
  - The queue worker (`hosting_task` queue) invokes `TaskManager::runTaskId()` and logs stdout/stderr to task logs.
- Exit code handling: any non-zero exit code marks the task as failed. Provision must return a non-zero exit code for failure conditions and write useful output to stdout/stderr.
- Command dispatch: Provision Drush commands should accept a leading `context_name` argument (without `@`) and internally switch to that context when the backend alias is fixed (for example, `drush @server_master provision-verify example.com`).

Context name and alias contract:
- Frontend stores context names in `hosting_context.context_name` without `@` (see `hosting/src/Service/ContextRegistry.php`).
- Backend aliases must match these names (with Drush alias `@<context_name>`). Provision should treat `context_name` as the canonical alias base and accept arguments with or without `@`.
- The frontend creates path aliases `/hosting/c/<context_name>` for entity linking; these are not used by Provision but must stay consistent with backend context naming.

Configuration handshake:
- `hosting.settings` provides `backend.drush_path` and `backend.alias` for backend invocation (`hosting/config/schema/hosting.schema.yml`, `hosting/src/Service/BackendInvoker.php`).
- Provision should be installed as a Drush extension in `vendor/` and be resolvable by the configured Drush binary.

Entity mapping notes (frontend to backend):
- `hosting_server.hostname` maps to Provision `remote_host`.
- `hosting_platform.publish_path` maps to Provision platform `root`.
- `hosting_site.domain` maps to Provision site `uri`.
- `hosting_service_instance` defines service providers (http/db) and ports; Provision must accept the chosen service types and ports via context options.
- `hosting_platform.makefile` and `make_working_copy` are legacy fields; for Composer-only Drupal 10+ platforms, these should be treated as deprecated/ignored by Provision.

Code references (frontend):
- Backend invoker: `hosting/src/Service/BackendInvoker.php`
- Task execution: `hosting_task/src/Service/TaskManager.php`, `hosting_task/src/Plugin/QueueWorker/HostingTaskQueueWorker.php`
- Context registry: `hosting/src/Service/ContextRegistry.php`
- Hosting settings schema: `hosting/config/schema/hosting.schema.yml`
- Drush commands: `hosting/src/Commands/HostingCommands.php`, `hosting_task/src/Commands/HostingTaskCommands.php`

## Frontend -> Backend expectations
The frontend (Hostmaster) must be able to rely on deterministic backend behavior for task execution and status reporting.

Expected by frontend:
- **Stable command names**: `provision-*` commands exist and behave consistently with the task types (`install`, `verify`, `backup`, `restore`, `deploy`, `clone`, `migrate`, `import`, `enable`, `disable`, `lock`, `unlock`, `delete`).
- **Context argument**: backend accepts `context_name` as the first argument (without `@`), even when a fixed backend alias is used to invoke Drush.
- **Exit codes**: non-zero exit codes on failure, zero on success. Failures must not return zero.
- **Output**: stdout/stderr should be meaningful; task logs capture both channels for operator troubleshooting.
- **Idempotent verify**: `provision-verify` should be safe to run repeatedly without destructive effects.
- **Queue safety**: backend commands must tolerate being run from queue workers and should avoid interactive prompts.

## Backend -> Frontend expectations
The backend (Provision) assumes the frontend provides correct context data and dispatch configuration.

Expected by backend:
- **Backend alias availability**: `hosting.settings.backend.alias` (if set) points to a valid Drush alias that loads Provision and resolves contexts.
- **Context registry**: frontend keeps `hosting_context` records consistent with entities and uses normalized context names.
- **Service definitions**: frontend provides server/platform/site entities with required fields (publish path, hostname, domain, service references).
- **Queue dispatch**: `hosting.queue_dispatcher` must trigger `hosting_task` queue runs on schedule (cron or manual dispatch).
- **Task creation**: frontend creates tasks with valid `task_type`, args, and options that match Provision command expectations.

## Service layer
Services provide reusable behaviors for HTTP servers, databases, SSL, and Drupal configuration.

Code references:
- HTTP service: `src/Service/Http/ApacheService.php` (Apache vhost and configuration generation)
- Database service: `src/Service/Db/MySqlService.php` (MySQL database and user management via PDO)
- SSL service: `src/Service/Ssl/SslManager.php` (SSL certificate management)
- Drupal service: `src/Service/Drupal/SettingsWriter.php` (Drupal settings.php generation)

### HTTP service (Apache)
**Current implementation: Apache only** (Nginx, Cluster, Pack services from D7 not implemented)
- Service class: `Aegir\Provision\Service\Http\ApacheService`
- Creates Apache configuration directory structure: `pre.d`, `post.d`, `platform.d`, `vhost.d`, `vhost_ssl.d`, `disabled.d`
- Generates vhost configuration from templates in `resources/templates/apache/`
- Supports SSL via SslManager integration
- Vhost templates: `resources/templates/apache/vhost.tpl.php`, `vhost_ssl.tpl.php`

Apache configuration features:
- Per-server, per-platform, and per-site vhost generation
- SSL/TLS support with separate vhost_ssl templates
- Site enable/disable moves vhosts between active and disabled directories
- Automatic restart/reload commands after configuration changes

**Apache vhost example** (generated from template):
```apache
<VirtualHost *:80>
  ServerName example.com
  DocumentRoot /var/aegir/platforms/drupal-11/web
  
  <Directory /var/aegir/platforms/drupal-11/web>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
  </Directory>
  
  # PHP-FPM integration
  <FilesMatch \.php$>
    SetHandler "proxy:unix:/run/php/php8.3-fpm-example.sock|fcgi://localhost"
  </FilesMatch>
  
  ErrorLog ${APACHE_LOG_DIR}/example.com-error.log
  CustomLog ${APACHE_LOG_DIR}/example.com-access.log combined
</VirtualHost>
```

**Config Output Paths** (relative to `server.config_path`):
```
{config_path}/apache/
├── vhost.d/            # HTTP vhosts (port 80)
│   ├── example.com.conf
│   └── platform_d11.conf
├── vhost_ssl.d/        # HTTPS vhosts (port 443)
│   └── example.com.conf
├── disabled.d/         # Disabled site configs
└── platform.d/         # Platform-level includes
    └── platform_d11.conf
```

### Database service (MySQL/MariaDB)
Current implementation:
- Service class: `Aegir\Provision\Service\Db\MySqlService`
- Executes MySQL commands via ProcessRunner using mysql CLI client
- Uses PHP 8.3+ strict types and modern syntax

Core behaviors:
- `ensureDatabase()`: Creates database if not exists
- `ensureUser()`: Creates and updates MySQL user with password
- `grant()`: Grants all privileges on database to user
- `dropDatabase()`: Drops database if exists
- `dropUser()`: Drops MySQL user
- `dump()`: Creates mysqldump backup (optional gzip compression)
- `import()`: Imports SQL dump file into database

**Example MySQL operations**:
```php
// Database credentials from context
$db_name = 'example_com';
$db_user = 'example_com_user';
$db_passwd = 'generated_password';

// Operations executed via CLI
CREATE DATABASE IF NOT EXISTS `example_com` 
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS 'example_com_user'@'localhost' 
  IDENTIFIED BY 'generated_password';
GRANT ALL PRIVILEGES ON `example_com`.* TO 'example_com_user'@'localhost';
```

Code reference: `src/Service/Db/MySqlService.php`

Features:
- PDO-based connection checking (requires `ext-pdo`, `ext-pdo_mysql`)
- Automatic credential escaping for SQL injection prevention
- Support for remote database servers via server context `remote_host`
- Optional GTID suppression for mysqldump operations
- Backup and restore operations integrated with ProvisionManager

## Provisioning task lifecycle
Provision executes tasks through ProvisionManager, which orchestrates services and manages context state.

Current implementation:
- **ProvisionManager**: `src/ProvisionManager.php` - Main orchestration class
- Task methods: `verify()`, `install()`, `backup()`, `restore()`, `deploy()`, `migrate()`, `cloneSite()`, `enable()`, `disable()`, `lock()`, `unlock()`, `delete()`, `loginReset()`
- Context types handled: server, platform, site
- Service coordination: ApacheService, MySqlService, SettingsWriter, SslManager

Task flow example (site install):
1. Load site context from ContextRepository
2. Resolve platform and server contexts
3. Create database and user via MySqlService
4. Grant database privileges
5. Generate Drupal settings.php via SettingsWriter
6. Configure Apache vhost via ApacheService
7. Run `drush site:install` command
8. Enable site (activate vhost)
9. Save updated context data

Task flow example (site verify):
1. Load site context from ContextRepository
2. Resolve platform and server contexts
3. Ensure directory structure via Filesystem
4. Generate Drupal settings.php via SettingsWriter
5. Generate Apache vhost via ApacheService
6. Restart/reload Apache via ProcessRunner
7. Save updated context data

Key workflows:
- **Verify**: Validate and configure server/platform/site
- **Install**: Create new Drupal site with database and configuration
- **Backup**: Create mysqldump + tarball of site files (stored in `{backup_path}/backups/`)
- **Restore**: Restore site from backup archive
- **Deploy**: Import backup into existing site (replaces database and files)
- **Migrate**: Move site to different platform (updates context and regenerates configs)
- **Clone**: Duplicate site to new context (creates new database and copies files)
- **Enable/Disable**: Activate or deactivate site vhost
- **Lock/Unlock**: Add maintenance mode to site
- **Delete**: Remove site, optionally delete database and files

Code reference: `src/ProvisionManager.php` (773 lines of orchestration logic)

## Filesystem and sync layer
Provision uses modern filesystem abstraction and process execution for consistent operations.

Current implementation:
- **Filesystem class**: `src/Core/Filesystem.php` - Wraps Symfony Filesystem with logging
- **ProcessRunner class**: `src/Core/ProcessRunner.php` - Executes external commands using Symfony Process
- **ConfigPaths class**: `src/Core/ConfigPaths.php` - Calculates configuration paths for servers and contexts
- **PlatformRoot class**: `src/Core/PlatformRoot.php` - Detects Drupal platform root and docroot layouts

Features:
- `ensureDir()`: Create directories with proper permissions
- `writeFile()`: Write files with proper ownership and permissions
- `removeFile()`: Remove files safely
- `copyDir()`: Copy directories recursively
- Process execution with output capture and error handling
- Auto-detection of `/web`, `/docroot`, `/html` Composer-based Drupal layouts

Code references:
- Filesystem: `src/Core/Filesystem.php`
- Process runner: `src/Core/ProcessRunner.php`
- Platform root: `src/Core/PlatformRoot.php`

Modern approach vs legacy:
- Uses Symfony Process component instead of `drush_shell_exec()`
- Uses Symfony Filesystem component instead of manual file operations
- Proper exception handling instead of return codes
- Dependency injection instead of global state

## SSL/TLS support
SSL management is provided through the SslManager service.

Current implementation:
- **SslManager class**: `src/Service/Ssl/SslManager.php`
- Certificate storage in server config directory
- Self-signed certificate generation using `openssl` command
- SSL certificate discovery and validation
- Integration with ApacheService for SSL vhost generation

Features:
- Per-server SSL certificate store
- Apache SSL vhost templates in `resources/templates/apache/vhost_ssl.tpl.php`
- HTTPS redirect support
- Certificate and key file management

**Certificate Paths** (relative to `server.config_path`):
```
{config_path}/ssl/
├── example.com/
│   ├── cert.pem       # Certificate
│   ├── key.pem        # Private key
│   ├── chain.pem      # Certificate chain
│   └── fullchain.pem  # Full certificate chain
```

**SSL Configuration Example**:
```yaml
# Site context with SSL enabled
ssl_enabled: true
ssl_redirect: true
ssl_cert_path: /var/aegir/config/ssl/example.com/cert.pem
ssl_key_path: /var/aegir/config/ssl/example.com/key.pem
```

Code reference: `src/Service/Ssl/SslManager.php`

Modern approach:
- Service-based architecture with dependency injection
- ProcessRunner for openssl command execution
- Template-based SSL vhost configuration
- Proper file permissions and ownership for certificates (mode 0600 for private keys)

## Drush command surface
Provision defines Drush 13.7+ commands as Symfony Console classes in `src/Drush/Commands/`.

Implemented commands:
- `provision-save`: Save or update context data (with `--data`, `--data-file`, `--type`, `--delete` options)
- `provision-verify` (aliases: `pv`, `verify`): Verify server, platform, or site context
- `provision-install`: Install a Drupal site
- `provision-import`: Import an existing site into Aegir
- `provision-backup`: Create a backup of a site (returns backup file path)
- `provision-restore`: Restore a site from backup
- `provision-deploy`: Deploy a backup to a site
- `provision-migrate`: Migrate a site to a different platform
- `provision-clone`: Clone a site to a new site (optionally on different platform)
- `provision-enable`: Enable a site (move Apache vhost to active)
- `provision-disable`: Disable a site (move Apache vhost to disabled)
- `provision-lock`: Lock a site
- `provision-unlock`: Unlock a site
- `provision-delete`: Delete a site context (with `--delete-files`, `--delete-db` options)
- `provision-login-reset`: Reset admin login for a site
- `backend-parse`: Parse backend command output (legacy compatibility)

Command implementation (Drush 13.7+ required):
- All commands are Symfony Console commands using `#[AsCommand]`
- Arguments/options defined in `configure()`, logic in `execute()`
- Commands accept context name (with or without `@` prefix)
- `ProvisionAutowireTrait` enables constructor-based dependency injection and registers Provision services in the Drush container
- ProvisionManager orchestrates all task execution
- Commands integrate with Drush logger for output

### Drush 13.7+ Commandfile Requirements

- **AutowireTrait required**: command classes use `ProvisionAutowireTrait` for DI; Drush does not use Drupal’s container automatically.
- **Site-wide commands only**: commandfiles live under the site’s `drush/Commands` tree or are installed via Composer.
- **No global config discovery**: do not use `drush.commands` configuration to register commands.
- **Valid paths / namespaces** (no `src` in the path):
  - `$PROJECT_ROOT/drush/Commands/ExampleCommands.php` → `Drush\Commands`
  - `$PROJECT_ROOT/drush/Commands/example/ExampleCommands.php` → `Drush\Commands\example`
  - `$PROJECT_ROOT/drush/Commands/contrib/dev_modules/ExampleCommands.php` → `Drush\Commands\dev_modules`

Site-wide command examples:
```text
https://github.com/drush-ops/drush/tree/13.x/examples/Commands
```

Code reference: `src/Drush/Commands/`

## Drush 13.7 integration (REQUIRED)
Drush 13.7 deprecates annotated commands and requires pure Symfony Console commands for new work.

Required features:
- ✅ YAML site alias storage in `~/.drush/sites/aegir/*.site.yml` (managed by AliasStore)
- ✅ PSR-4 autoloading with `Aegir\Provision\` namespace
- ✅ Class-based commands under `src/Drush/Commands` using `#[AsCommand]`
- ✅ One command per class file, with `configure()` + `execute()`
- ✅ `ProvisionAutowireTrait` for constructor-based dependency injection
- ✅ ProcessRunner for external command execution (replaces `drush_shell_exec`)
- ✅ Strict typing and PHP 8.3+ syntax throughout codebase
- ✅ Context management via ContextRepository and AliasStore
- ✅ `drush.services.yml` removed (deprecated; not used under Drush 13.7+)

Architecture (target layout):
- **Commands layer**: `src/Drush/Commands/*` - Symfony Console command classes
- **Core layer**: `src/Core/*` - Context, ContextRepository, AliasStore, Filesystem, ProcessRunner, ConfigPaths, PlatformRoot
- **Provision layer**: `src/ProvisionManager.php` - Main orchestration and task execution
- **Service layer**: `src/Service/*` - ApacheService, MySqlService, SettingsWriter, SslManager
- **Config layer**: `src/Config/TemplateRenderer.php` - Template rendering engine

Composer package example (Drush 13.7+):
```json
{
  "name": "argopecten/aegir-provision",
  "type": "drupal-drush",
  "require": {
    "php": ">=8.3",
    "drush/drush": "^13.7",
    "symfony/process": "^7.0"
  },
  "conflict": {
    "drush/drush": "<13.7"
  },
  "autoload": {
    "psr-4": {
      "Aegir\\Provision\\": "src/"
    }
  },
  "extra": {
    "branch-alias": {
      "dev-main": "11.x-dev"
    }
  }
}
```

## Extension points (REQUIRED)
The current implementation is designed for extensibility through dependency injection and service architecture, but **lacks a formal extension API**. This is a **critical must-have feature** for production use.

**Required extension mechanisms** (high priority):
- **Event/hook system for task lifecycle** - Allow third-party code to inject logic at validation, pre, execute, post, and rollback phases
- **Service plugin system** - Support custom HTTP servers (Nginx, Caddy), database backends (PostgreSQL, MongoDB), and other service implementations
- **Service discovery via Drush service tags** - Auto-discover and register third-party services

**Additional planned mechanisms** (medium priority):
- Custom template directories with override support
- Middleware pattern for command processing
- Extension configuration and dependency management

Current architecture supports extension by:
- Dependency injection in ProvisionManager constructor
- Service interfaces (implicit)
- Template override mechanism in TemplateRenderer
- ProcessRunner abstraction for external commands

Future work:
- Formal plugin API documentation
- Hook system similar to legacy `hook_provision_*()` functions
- Extension examples and templates
- Third-party service integration guides

## Platform/Drupal engine
Provision's platform engine handles Drupal-specific operations and platform management.

Current implementation:
- **PlatformRoot**: `src/Core/PlatformRoot.php` - Detects platform root and docroot location
- **SettingsWriter**: `src/Service/Drupal/SettingsWriter.php` - Generates Drupal settings.php
- **ProvisionManager platform methods**: Platform verify, install, and configuration

Platform detection:
- Supports Composer-based layouts: `/web`, `/docroot`, `/html` as webroot
- Auto-detects Drupal root vs webroot
- Validates Drupal installation presence

Settings generation:
- Template-based settings.php from `resources/templates/drupal/settings.php.tpl.php`
- Database credentials injection
- Drupal 11+ compatibility
- File permissions (0640 for security)
- Site-specific configuration

**Generated settings.php example**:
```php
<?php
/**
 * Aegir-generated settings.php
 * DO NOT EDIT - Changes will be overwritten on next verify
 */

// Database configuration
$databases['default']['default'] = [
  'driver' => 'mysql',
  'database' => 'example_com',
  'username' => 'example_com_user',
  'password' => 'generated_password',
  'host' => 'localhost',
  'port' => 3306,
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

// File paths
$settings['file_public_path'] = 'sites/example.com/files';
$settings['file_private_path'] = '/var/aegir/private/example.com';
$settings['file_temp_path'] = '/tmp';

// Trusted hosts
$settings['trusted_host_patterns'] = [
  '^example\\.com$',
];

// Config sync
$settings['config_sync_directory'] = '../config/sync';

// Aegir integration
$_SERVER['db_type'] = 'mysql';
$_SERVER['db_name'] = 'example_com';
$_SERVER['db_user'] = 'example_com_user';

// Load local settings (not managed by Aegir)
if (file_exists(__DIR__ . '/local.settings.php')) {
  include __DIR__ . '/local.settings.php';
}
```

Platform verification:
- Validates platform root path
- Ensures Apache configuration
- Creates platform vhost includes
- Verifies directory permissions

Code references:
- Platform root: `src/Core/PlatformRoot.php`
- Settings writer: `src/Service/Drupal/SettingsWriter.php`
- Platform tasks: `src/ProvisionManager.php` (`verifyPlatform()`, etc.)

## Dependencies (Drupal 11+ architecture - Drush 13.7+ REQUIREMENT)
Runtime dependencies (from `composer.json`):
- **PHP 8.3+** with extensions: `ext-json`, `ext-pdo`, `ext-pdo_mysql`
- **Drush 13.7+** for command execution and alias management
- **Symfony 7.0+** components: `symfony/process`, `symfony/yaml`, `symfony/filesystem`
- **Composer** for package installation and autoloading
- External tools: `mysql`, `mysqldump`, `openssl`, `rsync` (optional), `ssh` (optional)

Composer package configuration:
```json
{
  "name": "argopecten/aegir-provision",
  "type": "drupal-drush",
  "require": {
    "php": ">=8.3",
    "ext-json": "*",
    "ext-pdo": "*",
    "ext-pdo_mysql": "*",
    "drush/drush": "^13.7",
    "symfony/process": "^7.0",
    "symfony/yaml": "^7.0",
    "symfony/filesystem": "^7.0"
  },
  "conflict": {
    "drush/drush": "<13.7"
  },
  "autoload": {
    "psr-4": {
      "Aegir\\Provision\\": "src/"
    }
  },
  "extra": {
    "branch-alias": {
      "dev-main": "11.x-dev"
    }
  }
}
```

Installation:
- Install via Composer: `composer require argopecten/aegir-provision`
- Drush auto-discovers commands from `src/Drush/Commands` using `#[AsCommand]`
- No Drupal module required - runs as vendor package

Code organization:
- Namespace: `Aegir\\Provision\\`
- All code uses strict types (`declare(strict_types=1);`)
- Modern PHP 8.3+ features: attributes, constructor property promotion, readonly properties

## Implementation status and roadmap

### Completed (✅)
- **Core architecture**: Modern PHP 8.3+ class-based design with strict types
- **Drush 13.7+ command system**: Symfony Console commands with `#[AsCommand]`, `ProvisionAutowireTrait`, and PSR-4 auto-discovery
- **Context management**: Context, ContextRepository, ContextType, AliasStore
- **YAML alias storage**: Drush site aliases in `~/.drush/sites/aegir/`
- **Service architecture**: ApacheService, MySqlService, SettingsWriter, SslManager
- **Infrastructure**: Filesystem, ProcessRunner, ConfigPaths, PlatformRoot
- **Template system**: TemplateRenderer with PHP template support
- **Composer packaging**: PSR-4 autoloading, proper dependencies
- **Command set**: save, verify, install, backup, restore, deploy, migrate, clone, enable, disable, lock, unlock, delete, login-reset
- **Apache support**: Full vhost generation and management
- **MySQL support**: Database and user management, backup/restore via mysqldump
- **SSL/TLS**: Certificate management through SslManager
- **Platform detection**: Auto-detect `/web`, `/docroot`, `/html` Composer layouts

### Critical Requirements (MUST HAVE)

- **Extension/Hook System**: **CRITICAL** - Event system and service plugin architecture for third-party extensions. Without this, D11 cannot support:
  - Custom validation logic (e.g., domain restrictions, quota checks)
  - Post-operation hooks (e.g., remote backup sync, notifications)
  - Alternative service implementations (custom HTTP/DB backends)
  - Site-specific customizations beyond core functionality
  - Extension points: `BeforeInstallEvent`, `AfterBackupEvent`, `ServicePluginInterface`, etc.

### High Priority (Remaining)

- **Testing**: Automated test suite for core functionality (unit + integration)
- **Documentation**: Update all examples to show modern Drush 13.7+ patterns
- **Context schema documentation**: Formal documentation of context data structure for each type

### Optional/Low Priority Features (📋)
- **Nginx support**: Nginx service implementation (Apache-only currently sufficient)
- **Multi-server**: SSH/rsync integration for remote operations (local-only currently)
- **Cluster/Pack services**: Multi-webserver configurations (enterprise feature)
- **Backup compression**: Advanced backup formats and compression options
- **PostgreSQL/MongoDB**: Alternative database backends (MySQL sufficient currently)

### Architecture differences from legacy Provision
Legacy Provision used procedural PHP with hooks (`provision.inc`, `Provision_*` classes). Current implementation uses:
- Modern OOP with strict typing and dependency injection
- Drush command classes instead of `hook_drush_command()`
- YAML aliases instead of PHP `.alias.drushrc.php` files
- Composer PSR-4 autoloading instead of manual includes
- Symfony components for process execution and filesystem operations
- Service classes instead of service plugins
- ProvisionManager orchestration instead of context-based dispatch

## Security and operational considerations
- Avoid running provisioning commands as root (enforced in `provision.drush.inc`).
- Ensure SSH and filesystem permissions are aligned with webserver group and Aegir user.
- SSL certificate management should respect external certificate managers.
- Database credential handling should avoid logging secrets and use Drush options where applicable.

### Best Practices

**Context Management**:
- Always use `provision-save` to modify contexts - never edit YAML files directly
- Run `provision-verify` after making context changes
- Keep context backups before major changes
- Use descriptive context names (e.g., `@platform_d11_2024` not `@platform1`)

**File Permissions**:
```
# Server config directories
0750  aegir:aegir      /var/aegir/config/
0700  aegir:aegir      /var/aegir/config/ssl/

# Platform and site paths
0755  aegir:www-data   /var/aegir/platforms/
0770  aegir:www-data   /var/aegir/platforms/*/sites/*/files/
0640  aegir:www-data   /var/aegir/platforms/*/sites/*/settings.php

# Generated Apache configs
0644  aegir:www-data   /var/aegir/config/apache/vhost.d/*.conf
```

**Operations Workflow**:
1. Test on staging platform before production
2. Always backup before migrations: `drush provision-backup @site`
3. Monitor Apache/MySQL logs during operations
4. Verify configurations before enabling sites
5. Use `--dry-run` when available (future feature)

**Security Hardening**:
- Keep context YAML files secure (permissions 0600)
- Use SSL for all production sites
- Rotate database passwords regularly
- Review generated Apache configs for security headers
- Monitor SSL certificate expiry dates
- Use strong database passwords (generated automatically)

**Anti-Patterns to Avoid**:
- ❌ Don't modify context YAML files directly - use ContextRepository
- ❌ Don't hardcode paths - use ConfigPaths service
- ❌ Don't execute shell commands directly - use ProcessRunner or service methods
- ❌ Don't ignore file permissions - use Filesystem service
- ❌ Don't skip verification after changes
- ❌ Don't run commands as root

**Troubleshooting**:
- Check Apache logs: `/var/log/apache2/{site}-error.log`
- Verify MySQL connectivity: `mysql -u {user} -p`
- Test Apache config: `apache2ctl -t`
- Review generated configs in `{config_path}/apache/`
- Check context data: `drush site:alias @site --full`
- Validate file permissions: `ls -la {platform}/sites/{uri}/`

---

## Refactoring Drupal 7 Commands for Drush 13.7+

### High-Level Refactoring Strategy

The D7 Provision implementation uses procedural Drush 8 patterns with hooks. The D11 implementation must modernize to:

1. **Symfony Console Commands**: Replace `hook_drush_command()` arrays with `#[AsCommand]` attribute classes
2. **Dependency Injection**: Replace global `d()` context access with constructor-injected services
3. **Service Architecture**: Convert `Provision_Service_*` classes to modern PHP 8.3+ service classes
4. **YAML Aliases**: Maintain compatibility with Drush alias system
5. **Process Execution**: Use Symfony Process component instead of `drush_shell_exec()`
6. **Hook System**: Replace Drush module hooks with event system or service callbacks

### Command Refactoring Matrix

This matrix maps D7 commands to D11 implementation requirements. Based on analysis of D7 `/var/aegir/d7/aegir-provision` and D11 current implementation:

| D7 Command | D11 Status | Bootstrap | Refactoring Notes |
|------------|-----------|-----------|-------------------|
| provision-save | ✅ Implemented | DRUSH | Context persistence via ContextRepository/AliasStore. D7 used `Provision_Config_Drushrc_Alias`, D11 uses AliasStore. |
| provision-verify | ✅ Implemented | DRUSH | Multi-context dispatch, service orchestration. D7 invoked hooks, D11 uses ProvisionManager explicit methods. |
| provision-install | ✅ Implemented | DRUPAL_ROOT | Drush site:install integration, db creation. D7 used `provision-install-backend`, D11 uses ProcessRunner. |
| provision-backup | ✅ Implemented | DRUPAL_ROOT | Tarball + mysqldump, returns backup path. Both versions create `{uri}-{timestamp}.tar.gz`. |
| provision-restore | ✅ Implemented | DRUPAL_ROOT | Atomic directory swap, db import. D7 created pre-restore backup, D11 maintains same pattern. |
| provision-deploy | ✅ Implemented | DRUPAL_ROOT | Backup deployment to different context. D7 updated file references with `old_uri`, D11 equivalent. |
| provision-migrate | ✅ Implemented | DRUPAL_ROOT | Platform change, update.php for version changes. D7 used hooks, D11 uses explicit orchestration. |
| provision-clone | ✅ Implemented | DRUPAL_ROOT | New context + db duplication. D7 used backup/deploy, D11 similar pattern. |
| provision-import | ✅ Implemented | DRUPAL_ROOT | Detect existing site, parse settings.php. D7 inspected settings for db creds, D11 maintains compatibility. |
| provision-enable | ✅ Implemented | DRUPAL_ROOT | Move vhost from `disabled.d/` to `vhost.d/`. Same pattern in both versions. |
| provision-disable | ✅ Implemented | DRUPAL_ROOT | Move vhost to `disabled.d/`, show maintenance page. Same pattern in both versions. |
| provision-lock | ✅ Implemented | DRUPAL_ROOT | Set `platform_locked` flag. Simple flag operation in both versions. |
| provision-unlock | ✅ Implemented | DRUPAL_ROOT | Clear `platform_locked` flag. Simple flag operation in both versions. |
| provision-delete | ✅ Implemented | DRUSH | Multi-phase deletion with final backup. D7 verified no dependent contexts, D11 same. |
| provision-login-reset | ✅ Implemented | DRUPAL_ROOT | Invoke `drush user:login`. D7 used `drush_invoke_process`, D11 uses ProcessRunner. |
| provision-backup-delete | 📋 Not implemented | DRUSH | Delete backup file. Simple file removal (low priority/optional). |
| backend-parse | ✅ Implemented | DRUSH | Parse backend output (legacy compat). For frontend integration. |
| hostmaster-install | 📋 Not implemented | DRUSH | Install Aegir hosting system. Orchestrates frontend installation (low priority/optional). |
| hostmaster-migrate | 📋 Not implemented | DRUSH | Migrate Aegir to new platform. Updates hosting system (low priority/optional). |
| hostmaster-uninstall | 📋 Not implemented | DRUPAL_SITE | Uninstall Aegir hosting system. Cleanup and removal (low priority/optional). |

**Status Key**:
- ✅ Implemented: Command exists and works in D11
- ⚠️ Not implemented: Missing or intentionally excluded
- ❌ Broken: Exists but needs fixing

### Context System Refactoring

**D7 Pattern** (procedural with global state):

```php
// D7: Global d() function in provision.context.inc
function & d($name = NULL, $_root_object = FALSE, $allow_creation = TRUE) {
  static $instances = null;
  
  // Load from Drush alias or create new
  if (isset($instances[$name])) {
    return $instances[$name];
  }
  
  $instances[$name] = provision_context_factory($name, $allow_creation);
  $instances[$name]->method_invoke('init');
  return $instances[$name];
}

// D7: Usage - global access
$site = d('@example.com');
$uri = $site->uri;  // Direct property access
$platform = d($site->platform);  // Chained loading
$site->write_alias();  // Save to file

// D7: Context classes
class Provision_Context_server extends Provision_Context {
  function init_server() {
    $this->setProperty('aegir_root');
    $this->load_services();
  }
}
```

**D11 Pattern** (dependency injection):

```php
// D11: No global d() function, explicit loading via ContextRepository
namespace Aegir\Provision\Core;

class ContextRepository {
    public function __construct(
        private readonly AliasStore $store
    ) {}
    
    public function load(string $name): Context {
        // Load from YAML alias
        $data = $this->store->read($name);
        return new Context($name, $data['type'], $data);
    }
    
    public function save(Context $context): void {
        $this->store->write($context->getName(), [
            'type' => $context->getType(),
            ...$context->all(),
        ]);
    }
}

// D11: Usage - explicit injection
class ProvisionInstallCommand extends Command {
    public function __construct(
        private readonly ContextRepository $contexts
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $site_name = $input->getArgument('site');
        
        // Explicit loading (no globals)
        $site = $this->contexts->load($site_name);
        $uri = $site->get('uri');  // Getter method
        $platform = $this->contexts->load($site->get('platform'));
        
        // Save via repository
        $this->contexts->save($site);
        
        return Command::SUCCESS;
    }
}

// D11: Context is simple immutable data structure
class Context {
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private array $data = []
    ) {}
    
    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }
    
    public function has(string $key): bool {
        return isset($this->data[$key]);
    }
    
    // No methods like init_server() - those moved to services
}
```

**Migration Checklist**:
- [x] Replace `d()` global function with `ContextRepository` injection
- [x] Replace direct property access (`$ctx->uri`) with getters (`$ctx->get('uri')`)
- [x] Replace `write_alias()` with `ContextRepository::save()`
- [x] Move service initialization from context classes to ProvisionManager
- [x] Make Context immutable (no `setProperty()`)
- [ ] Document Context data schema for each type

### Service Layer Refactoring

**D7 Service Pattern**:

```php
// D7: db/db.drush.inc - Service registration hook
function db_provision_services() {
  return array('db' => 'mysql');
}

// D7: db/Provision/Service/db/mysql.php - Service class
class Provision_Service_db_mysql extends Provision_Service_db_pdo {
  public $PDO_type = 'mysql';
  
  function create_database() {
    return $this->query("CREATE DATABASE `%s`", d()->creds['db_name']);
  }
  
  function grant_privileges() {
    return $this->query("GRANT ALL ON `%s`.* TO '%s'@'%s'",
      d()->creds['db_name'], d()->creds['db_user'], '%');
  }
  
  // Invoked via context
  function verify() {
    $this->create_database();
    $this->grant_privileges();
  }
}

// D7: Usage via context service subscription
d('@site')->service('db')->verify();

// D7: db/install.provision.inc - Hook implementation
function drush_provision_mysql_pre_provision_install($url) {
  d()->service('db')->create_site_database();
  d()->service('db')->grant_privileges();
}
```

**D11 Service Pattern**:

```php
// D11: src/Service/Db/MySqlService.php - Service class
namespace Aegir\Provision\Service\Db;

use Aegir\Provision\Core\{Context, ProcessRunner};

class MySqlService {
    public function __construct(
        private readonly ProcessRunner $runner
    ) {}
    
    public function ensureDatabase(string $name, string $user, string $passwd): void {
        // Use mysql CLI client instead of PDO
        $this->runner->run([
            'mysql', '-u', 'root', '-e',
            "CREATE DATABASE IF NOT EXISTS `{$name}` 
             CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
        ]);
    }
    
    public function grant(string $db_name, string $db_user): void {
        $this->runner->run([
            'mysql', '-u', 'root', '-e',
            "GRANT ALL PRIVILEGES ON `{$db_name}`.* TO '{$db_user}'@'localhost'"
        ]);
    }
    
    public function dump(string $db_name, string $output_file): void {
        $this->runner->run([
            'mysqldump',
            '--single-transaction',
            '--opt',
            $db_name,
            '--result-file=' . $output_file
        ]);
    }
}

// D11: No service registration hook, explicit injection
// src/ProvisionManager.php - Service orchestration
class ProvisionManager {
    public function __construct(
        private readonly ContextRepository $contexts,
        private readonly MySqlService $db,
        private readonly ApacheService $http
    ) {}
    
    public function install(string $site_name): void {
        $site = $this->contexts->load($site_name);
        
        // Explicit service calls (no hooks)
        $this->db->ensureDatabase(
            $site->get('db_name'),
            $site->get('db_user'),
            $site->get('db_passwd')
        );
        $this->db->grant($site->get('db_name'), $site->get('db_user'));
    }
}
```

**Key Architectural Differences**:

| Aspect | D7 Approach | D11 Approach |
|--------|-------------|--------------|
| Service Discovery | `hook_provision_services()` | Constructor injection |
| Service Access | `d()->service('db')` | `$this->db` property |
| Command Execution | PDO queries | Shell commands via ProcessRunner |
| Hook Invocation | `drush_command_invoke_all()` | Explicit method calls |
| Error Handling | `drush_set_error()` | Exceptions |
| Configuration | Context properties | Injected dependencies |

**Migration Steps**:
1. Convert service registration hooks to DI configuration
2. Replace `d()->service()` pattern with injected properties
3. Replace PDO operations with CLI client commands
4. Move hook implementations to service methods
5. Update error handling to use exceptions
6. Register services in `ProvisionServiceRegistry`

### Hook System Migration

**D7 Hook Pattern** (extensive hook system):

```php
// D7: Multiple files implement hooks for each command

// platform/install.provision.inc
function drush_provision_drupal_provision_install() {
  // Platform operations
  drush_log("Installing on platform");
  d()->write_alias();
}

// db/install.provision.inc
function drush_provision_mysql_pre_provision_install($url) {
  drush_log("Creating database");
  d()->service('db')->create_site_database();
}

// http/install.provision.inc
function drush_provision_apache_provision_install() {
  drush_log("Creating vhost");
  d()->service('http')->create_config('site');
  d()->service('http')->restart();
}

// provision.drush.inc - Hook dispatcher
function drush_provision_install() {
  // Validate phase
  drush_command_invoke_all('provision_install_validate');
  if (drush_get_error()) {
    return FALSE;
  }
  
  // Pre phase
  drush_command_invoke_all('pre_provision_install');
  
  // Main phase
  drush_command_invoke_all('provision_install');
  
  // Post phase
  drush_command_invoke_all('post_provision_install');
}

// Hooks discovered automatically by Drush
// Order controlled by module weight
```

**D11 Pattern** (explicit orchestration):

```php
// D11: No hook system, explicit service orchestration

// src/Drush/Commands/ProvisionInstallCommand.php
namespace Aegir\Provision\Drush\Commands;

#[AsCommand(name: 'provision:install')]
class ProvisionInstallCommand extends Command {
    use ProvisionAutowireTrait;
    
    public function __construct(
        private readonly ProvisionManager $manager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $site = $input->getArgument('site');
        
        try {
            // Single orchestrated method call
            $this->manager->install($site);
            $this->logger->success("Installed: $site");
            return Command::SUCCESS;
        }
        catch (\Exception $e) {
            $this->logger->error("Install failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

// src/ProvisionManager.php - Explicit orchestration
namespace Aegir\Provision;

class ProvisionManager {
    public function __construct(
        private readonly ContextRepository $contexts,
        private readonly MySqlService $db,
        private readonly ApacheService $http,
        private readonly SettingsWriter $drupal,
        private readonly ProcessRunner $runner,
        private readonly LoggerInterface $logger
    ) {}
    
    public function install(string $site_name): void {
        $site = $this->contexts->load($site_name);
        $platform = $this->contexts->load($site->get('platform'));
        $server = $this->contexts->load($site->get('server'));
        
        // Validate (explicit, throws on failure)
        $this->validateInstall($site, $platform);
        
        // Pre-install operations
        $this->logger->info("Creating database");
        $this->db->ensureDatabase(
            $site->get('db_name'),
            $site->get('db_user'),
            $site->get('db_passwd')
        );
        $this->db->grant($site->get('db_name'), $site->get('db_user'));
        
        // Generate settings.php
        $this->logger->info("Generating settings.php");
        $this->drupal->writeSettings($site, $platform);
        
        // Generate vhost
        $this->logger->info("Creating vhost");
        $this->http->createSiteVhost($site, $platform, $server);
        
        // Run Drupal site install
        $this->logger->info("Installing Drupal");
        $this->runner->run([
            'drush',
            'site:install',
            $site->get('profile', 'standard'),
            '--root=' . $platform->get('root'),
            '--site-name=' . $site->get('uri'),
            '--db-url=mysql://' . $site->get('db_user') . ':'
                . $site->get('db_passwd') . '@localhost/'
                . $site->get('db_name'),
        ]);
        
        // Post-install operations
        $this->logger->info("Restarting web server");
        $this->http->restart($server);
    }
    
    private function validateInstall(Context $site, Context $platform): void {
        if (!$platform->has('root')) {
            throw new \RuntimeException("Platform has no root defined");
        }
        
        if (!is_dir($platform->get('root'))) {
            throw new \RuntimeException("Platform root does not exist");
        }
    }
}
```

**Hook Replacement Strategy**:

| D7 Hook Pattern | D11 Replacement | Migration Notes |
|-----------------|-----------------|-----------------|
| `hook_drush_command()` | `#[AsCommand]` class | One command class per file |
| `hook_provision_services()` | Constructor injection | Services registered in ProvisionServiceRegistry |
| `hook_provision_*_validate()` | Validation methods in manager | Throw exceptions on failure |
| `hook_pre_provision_*()` | Method calls before main logic | Explicit order in ProvisionManager |
| `hook_provision_*()` | Service method calls | Direct service invocation |
| `hook_post_provision_*()` | Method calls after main logic | Cleanup in manager |
| `hook_provision_*_rollback()` | try/catch with cleanup | Exception handling |
| `drush_command_invoke_all()` | Method calls | No dynamic dispatch |
| `drush_set_error()` | throw Exception | Exception-based errors |

**Benefits of D11 Approach**:
- ✅ Clear execution order (no hook weight issues)
- ✅ Type-safe method calls (no string-based dispatch)
- ✅ IDE autocomplete and refactoring support
- ✅ Easier debugging (explicit call stack)
- ✅ Testable (mock services, not hooks)
- ✅ No global state (`d()` function eliminated)

**Challenges**:
- ❌ **CRITICAL: No extension system** - Third-party hooks/extensions cannot be implemented without a formal extension API
- ⚠️ More verbose (explicit orchestration code)
- ⚠️ Breaking change (D7 extensions won't work)

**Required Extension Points** (must be implemented):
- **Event system using Symfony EventDispatcher** - For lifecycle hooks (validate, pre, post, rollback)
- **Plugin system for service implementations** - For custom HTTP/DB/other services
- **Service tags and discovery** - Auto-registration of third-party services
- **Middleware pattern for command processing** - Request/response interceptors

---

### Migration Priority Assessment

**Completed Features** (D11 has these working):
1. ✅ All core commands (save, verify, install, backup, restore, etc.)
2. ✅ Context system (Context, ContextRepository, AliasStore)
3. ✅ MySQL service (database operations via CLI client)
4. ✅ Apache service (vhost generation and management)
5. ✅ Settings.php generation
6. ✅ SSL certificate management
7. ✅ Template rendering system
8. ✅ Process execution (ProcessRunner)
9. ✅ Filesystem operations
10. ✅ Drush 13.7+ command structure

**Missing Features** (D7 had, D11 doesn't yet):
1. 📋 **Nginx service** - D7 had full Nginx support (low priority/optional)
2. 📋 **Cluster/Pack services** - Multi-webserver configurations (low priority/optional)
3. 📋 **Remote server support** - SSH/rsync for remote operations (low priority/optional)
4. 📋 **provision-backup-delete** - Delete backup files (low priority/optional)
5. ⚠️ **Comprehensive testing** - D7 had minimal tests, D11 needs more (clear todo)
6. ❌ **Drush make integration** - Will NOT be implemented. D11 version supports: a) manual builds, and b) Composer projects from git repositories (GitHub, GitLab, etc.)
7. 📋 **Platform locking UI integration** - Frontend coordination (low priority/optional)

**Architecture Improvements in D11**:
1. ✅ Modern PHP 8.3+ (strict types, readonly properties, attributes)
2. ✅ Proper dependency injection (no globals)
3. ✅ Exception-based error handling
4. ✅ Immutable context objects
5. ✅ Composer PSR-4 autoloading
6. ✅ Symfony components (Process, Filesystem, Yaml)
7. ✅ One command per file (better organization)
8. ✅ Explicit service orchestration (no magic hooks)

**Regression Risks**:
- ⚠️ No remote server support yet (SSH operations)
- ⚠️ Only Apache (no Nginx alternative)
- ⚠️ Limited testing coverage
- ⚠️ No extension system for third parties

**Recommended Next Steps**:
1. **High Priority**: Add comprehensive test suite (unit + integration)
2. **High Priority**: Document Context data schemas
3. **Medium Priority**: Implement Nginx service
4. **Medium Priority**: Add remote server support (SSH/rsync)
5. **Low Priority**: Cluster/Pack multi-webserver support
6. **Low Priority**: provision-backup-delete command

---

## Summary: D7 to D11 Refactoring Complete

### What Changed

**Architecture Paradigm Shift**:
- D7: Procedural hooks + global state → D11: OOP + dependency injection
- D7: Dynamic hook dispatch → D11: Explicit method calls
- D7: `*.provision.inc` files → D11: Service classes
- D7: `hook_drush_command()` → D11: `#[AsCommand]` attributes
- D7: PDO database → D11: CLI client commands
- D7: Drush 8 patterns → D11: Symfony Console patterns

**Code Organization**:
- D7: Mixed procedural/OOP → D11: Pure OOP
- D7: Scattered hooks → D11: Centralized orchestration
- D7: Module-based extensions → D11: Service-based architecture
- D7: `d()` global function → D11: ContextRepository injection

**Developer Experience**:
- D7: String-based APIs → D11: Type-safe interfaces
- D7: Runtime hook discovery → D11: Compile-time resolution
- D7: Implicit execution order → D11: Explicit control flow
- D7: Limited IDE support → D11: Full IDE integration

### What Stayed the Same

**Core Concepts Preserved**:
1. ✅ Context model (server, platform, site)
2. ✅ YAML alias storage format
3. ✅ Service separation (http, db, ssl)
4. ✅ Template-based config generation
5. ✅ Backup/restore workflows
6. ✅ Migration patterns
7. ✅ Directory structures (vhost.d/, disabled.d/, etc.)
8. ✅ Command names and purposes

**Operational Compatibility**:
- ✅ Backup format compatible
- ✅ YAML aliases readable by both versions
- ✅ Apache config format unchanged
- ✅ MySQL operations equivalent
- ✅ Settings.php structure similar

### Migration Path for Users

**For Aegir Operators**:
1. Commands have same names (may need namespace: `provision:install` vs `provision-install`)
2. Context aliases compatible between versions
3. Backup files compatible
4. Can run both D7 and D11 side-by-side (different directories)

**For Developers/Contributors**:
1. Read this document to understand architectural changes
2. Study `src/ProvisionManager.php` for orchestration patterns
3. Review command implementations in `src/Drush/Commands/`
4. Follow Drush 13.7+ conventions: https://www.drush.org/13.x/
5. Use dependency injection, not global state
6. Write unit tests for new features

**For Extension Authors**:
- ⚠️ D7 provision extensions (via hooks) won't work in D11
- ⚠️ Need to create D11-native service implementations
- ❌ **No hook/extension system available currently** - This is a **MUST-HAVE feature** that needs to be implemented to allow third-party extensions (custom services, validation logic, post-processing hooks, etc.)
- ⚠️ Until extension system is implemented, consider contributing to core

### Success Criteria Met

✅ **All D7 commands documented** in [provision-d7.md](provision-d7.md)
✅ **Refactoring strategy defined** in this section
✅ **Architecture comparison complete** (D7 patterns vs D11 patterns)
✅ **Migration matrix created** (command-by-command status)
✅ **Code examples provided** for each pattern transformation
✅ **Priority assessment complete** (what's done, what's missing)

**Next Phase**: Implementation validation and testing.

