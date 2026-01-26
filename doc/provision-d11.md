# Aegir Provision (D11) System Architecture Document

## Scope
This SAD defines a Drupal 10+ (Composer-only) oriented architecture for the Provision backend. It reuses the subsystem model described in `architecture/provision-d7.md`, and specifies the compatibility targets for Drupal 10+, Drush 13, and PHP 8.3+.

The document describes functionality, component map, context definitions, hostmaster integration, service types (http/db), SSL behavior, Drupal version support, Drush command surface, and dependencies within a Drupal 11 architecture.

## Goals
- Preserve Provision's core lifecycle behavior (server/platform/site management, config generation, task execution).
- Target **Drupal 10+** Composer-only platforms, **Drush 13**, and **PHP 8.3+** compatibility.
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
Provision is delivered as a Composer package that registers Drush 13 commands directly from `vendor/`, without requiring a Drupal module.

Actual package layout:
- `composer.json` (type: `drupal-drush`)
- `drush.services.yml` (registers Drush 13 command classes)
- `src/Commands/ProvisionCommands.php` (Drush 13 command definitions using PHP 8.3 attributes)
- `src/Core/*` (core infrastructure: Context, ContextRepository, ContextType, AliasStore, Filesystem, ProcessRunner, ConfigPaths, PlatformRoot)
- `src/Provision/ProvisionManager.php` (main orchestration and task execution)
- `src/Service/*` (service implementations: Db/MySqlService, Http/ApacheService, Drupal/SettingsWriter, Ssl/SslManager)
- `src/Config/TemplateRenderer.php` (template rendering engine)
- `resources/templates/*` (config templates for Apache, Drupal settings, etc.)

The implementation uses modern PHP 8.3+ features including strict types, constructor property promotion, readonly properties, and Drush PHP 8 attributes.

## Context model
Contexts are named Drush site aliases representing infrastructure and Drupal objects. The Context class (`Aegir\ProvisionD11\Core\Context`) is a simple data structure managed by ContextRepository. Contexts are stored as YAML site alias files in `~/.drush/sites/aegir/*.site.yml`.

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
- ProvisionManager platform verify: `src/Provision/ProvisionManager.php` (`verifyPlatform()`)

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
- Site verification: `src/Provision/ProvisionManager.php` (`verifySite()`)
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
Current implementation supports Apache only:
- Service class: `Aegir\ProvisionD11\Service\Http\ApacheService`
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
- Service class: `Aegir\ProvisionD11\Service\Db\MySqlService`
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
- **ProvisionManager**: `src/Provision/ProvisionManager.php` - Main orchestration class
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

Code reference: `src/Provision/ProvisionManager.php` (773 lines of orchestration logic)

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
Provision defines Drush 13 commands using PHP 8 attributes in `src/Commands/ProvisionCommands.php`.

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

Command implementation:
- All commands use Drush 13 PHP attributes (`#[CLI\Command]`, `#[CLI\Argument]`, `#[CLI\Option]`)
- Commands accept context name (with or without `@` prefix)
- ProvisionManager orchestrates all task execution
- Commands integrate with Drush logger for output

Code reference: `src/Commands/ProvisionCommands.php`

## Drush 13 integration (IMPLEMENTED)
Provision has been fully implemented using Drush 13's class-based command system and modern PHP 8.3+ features.

Implemented features:
- ✅ Class-based commands in `src/Commands/ProvisionCommands.php` using PHP 8 attributes
- ✅ YAML site alias storage in `~/.drush/sites/aegir/*.site.yml` (managed by AliasStore)
- ✅ Service registration via `drush.services.yml`
- ✅ PSR-4 autoloading with `Aegir\ProvisionD11\` namespace
- ✅ Modern dependency injection and service architecture
- ✅ ProcessRunner for external command execution (replaces `drush_shell_exec`)
- ✅ Strict typing and PHP 8.3+ syntax throughout codebase
- ✅ Context management via ContextRepository and AliasStore

Architecture:
- **Commands layer**: `src/Commands/ProvisionCommands.php` - Drush command definitions
- **Core layer**: `src/Core/*` - Context, ContextRepository, AliasStore, Filesystem, ProcessRunner, ConfigPaths, PlatformRoot
- **Provision layer**: `src/Provision/ProvisionManager.php` - Main orchestration and task execution
- **Service layer**: `src/Service/*` - ApacheService, MySqlService, SettingsWriter, SslManager
- **Config layer**: `src/Config/TemplateRenderer.php` - Template rendering engine

Package registration:
```yaml
# drush.services.yml
services:
  aegir_provision_d11.commands:
    class: Aegir\ProvisionD11\Commands\ProvisionCommands
    tags:
      - { name: drush.command }
```

Composer package example:
```json
{
  "name": "argopecten/aegir-provision",
  "type": "drupal-drush",
  "require": {
    "php": ">=8.3",
    "drush/drush": "^13.6",
    "symfony/process": "^7.0"
  },
  "autoload": {
    "psr-4": {
      "Aegir\\ProvisionD11\\": "src/"
    }
  },
  "extra": {
    "drush": {
      "services": {
        "drush.services.yml": "^13"
      }
    },
    "branch-alias": {
      "dev-main": "11.x-dev"
    }
  }
}
```

## Extension points (future)
The current implementation is designed for extensibility through dependency injection and service architecture.

Planned extension mechanisms:
- Service plugin system for additional HTTP servers (Nginx, Caddy, etc.)
- Database service plugins for PostgreSQL, MongoDB, etc.
- Custom template directories
- Event/hook system for task lifecycle
- Service discovery via Drush service tags

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
- Platform tasks: `src/Provision/ProvisionManager.php` (`verifyPlatform()`, etc.)

## Dependencies (Drupal 11+ architecture - CURRENT IMPLEMENTATION)
Runtime dependencies (from `composer.json`):\n- **PHP 8.3+** with extensions: `ext-json`, `ext-pdo`, `ext-pdo_mysql`
- **Drush 13.6+** for command execution and alias management
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
    "drush/drush": "^13.6",
    "symfony/process": "^7.0",
    "symfony/yaml": "^7.0",
    "symfony/filesystem": "^7.0"
  },
  "autoload": {
    "psr-4": {
      "Aegir\\ProvisionD11\\": "src/"
    }
  },
  "extra": {
    "drush": {
      "services": {
        "drush.services.yml": "^13"
      }
    },
    "branch-alias": {
      "dev-main": "11.x-dev"
    }
  }
}
```

Installation:
- Install via Composer: `composer require argopecten/aegir-provision`
- Drush automatically discovers commands via `drush.services.yml`
- No Drupal module required - runs as vendor package

Code organization:
- Namespace: `Aegir\\ProvisionD11\\`
- All code uses strict types (`declare(strict_types=1);`)
- Modern PHP 8.3+ features: attributes, constructor property promotion, readonly properties

## Implementation status and roadmap

### Completed (✅)
- **Core architecture**: Modern PHP 8.3+ class-based design with strict types
- **Drush 13 integration**: Full command system using PHP 8 attributes
- **Context management**: Context, ContextRepository, ContextType, AliasStore
- **YAML alias storage**: Drush 13 site aliases in `~/.drush/sites/aegir/`
- **Service architecture**: ApacheService, MySqlService, SettingsWriter, SslManager
- **Infrastructure**: Filesystem, ProcessRunner, ConfigPaths, PlatformRoot
- **Template system**: TemplateRenderer with PHP template support
- **Composer packaging**: PSR-4 autoloading, proper dependencies
- **Command set**: save, verify, install, backup, restore, deploy, migrate, clone, enable, disable, lock, unlock, delete, login-reset
- **Apache support**: Full vhost generation and management
- **MySQL support**: Database and user management, backup/restore via mysqldump
- **SSL/TLS**: Certificate management through SslManager
- **Platform detection**: Auto-detect `/web`, `/docroot`, `/html` Composer layouts

### In progress or planned (🔄)
- **Nginx support**: Nginx service implementation (Apache only currently)
- **Drupal 11 bootstrap**: Direct Drupal API integration for site install/import
- **Multi-server**: SSH/rsync integration for remote server management
- **Backup compression**: Advanced backup formats and compression options
- **Migration tools**: Enhanced platform migration workflows
- **Hook system**: Extension points for custom service implementations
- **Testing**: Automated test suite for core functionality
- **Documentation**: Additional guides for common workflows

### Architecture differences from legacy Provision
Legacy Provision used procedural PHP with hooks (`provision.inc`, `Provision_*` classes). Current implementation uses:
- Modern OOP with strict typing and dependency injection
- Drush 13 attributes instead of `hook_drush_command()`
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
