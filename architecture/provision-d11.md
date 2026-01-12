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
Provision should be delivered as a Composer package that registers Drush 13 commands directly from `vendor/`, without requiring a Drupal module.

Recommended package layout:
- `composer.json` (type can be `drupal-drush` or `library`)
- `drush.services.yml` (register Drush 13 command classes)
- `src/Commands/ProvisionCommands.php` (Drush 13 command definitions)
- `src/Provision/*` (namespaced equivalents of `Provision_*` classes)
- `resources/templates/*` (config templates ported from `Provision/Config` and `http/Provision/Config`)

Legacy files remain the authoritative behavior reference and should be mapped into class-based services and command handlers inside the Drush extension package.

## Context model
Contexts are named Drush aliases representing infrastructure and Drupal objects. The `d()` helper resolves aliases to context objects and initializes service subscriptions.

### Server context
Responsibilities:
- Stores server identity and filesystem layout (`aegir_root`, `config_path`, `clients_path`, `backup_path`).
- Owns service instances (http, db) based on configuration and `provision_services` hooks.
- Provides remote execution (`shell_exec()`) and sync (`sync()`) over SSH/rsync.

Code references:
- Context creation/lookup: `provision.context.inc` (`d()`, `provision_context_factory()`)
- Server context: `Provision/Context/server.php` (`init_server()`, `spawn_service()`, `sync()`, `shell_exec()`)

D11 compatibility notes:
- SSH/rsync remains out-of-band to Drupal; no changes required for PHP 8.3, but hard-coded paths and permissions should be reviewed for modern OS layouts.

### Platform context
Responsibilities:
- Represents a Drupal codebase (root path) and associated webserver.
- Orchestrates platform verification and Drupal bootstrap actions.

Code references:
- Platform context: `Provision/Context/platform.php`
- Verification flow: `platform/verify.provision.inc`

D11 compatibility notes:
- Platform verification must recognize Drupal 11 layouts and bootstrap requirements.
- Composer install flow should remain optional and compatible with the Drupal 11 Symfony stack.

### Site context
Responsibilities:
- Represents a site within a platform (`uri`, `site_path`, `profile`, `install_method`).
- Generates Drush aliases for site access and provisioning.

Code references:
- Site context: `Provision/Context/site.php` (`init_site()`, `write_alias()`)
- Alias config: `Provision/Config/Drushrc/Alias.php`

D11 compatibility notes:
- Drush alias generation must be compatible with Drush 13 alias formats and options.

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
Provision writes configuration and state to disk. These files are the contract between Hostmaster, Provision, and runtime services.

Key files and stores:
- **Drush alias files**: `Provision/Config/Drushrc/Alias.php` writes `~/.drush/<alias>.alias.drushrc.php` in the legacy implementation. Drush 13 should use YAML site aliases in `~/.drush/sites/*.site.yml` or a project-level `drush/sites` directory.
- **Server drushrc**: `Provision/Config/Drushrc/Server.php` uses Drush user config (`_drush_config_file('user')`).
- **Platform drushrc**: `Provision/Config/Drushrc/Platform.php` writes `${platform.root}/sites/all/drush/drushrc.php` in legacy D7 layout; for D11/Drush 13, move to the recommended site-local config path (avoid `sites/all`).
- **Site drushrc**: `Provision/Config/Drushrc/Site.php` writes `${site_path}/drushrc.php`.
- **Drupal settings.php**: `Provision/Config/Drupal/Settings.php` selects a template based on `drush_drupal_major_version()` and writes `settings.php`.
- **Drupal sites.php**: `Provision/Config/Drupal/Alias/Store.php` manages `sites/sites.php` records for multisite aliasing.
- **Global include**: `Provision/Config/Global/Settings.php` writes `${server.include_path}/global.inc` for shared configuration.

Implementation details:
- Config rendering is done by `Provision_Config` (`Provision/Config.php`) using template discovery and optional hook overrides (`hook_provision_config_load_templates`, `hook_provision_config_variables_alter`).
- Data store configs (`Provision_Config_Data_Store` in `Provision/Config/Data/Store.php`) use file locks to avoid race conditions.
- HTTP configs extend `Provision_Config_Http` (`http/Provision/Config/Http.php`) and sync to remote servers after write/unlink.
- HTTP config layout is created in `Provision_Service_http_public::init_server()` (`http/Provision/Service/http/public.php`): `${config_path}/{webserver}/pre.d`, `post.d`, `platform.d`, `vhost.d`, `subdir.d` plus `${aegir_root}/platforms` for platform sync.
- Drupal settings generation in `Provision/Config/Drupal/Settings.php` selects a template per major version, controls file permissions, optionally creates `local.settings.php`, and supports DB credential cloaking via `provision_db_cloaking`.
- Drush config reload helper: `provision_reload_config()` in `platform/provision_drupal.drush.inc` re-includes drushrc files after updates.

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
Services provide reusable behaviors across contexts and are registered by `hook_provision_services()`.

Code references:
- Service registration: `db/db.drush.inc`, `http/http.drush.inc`
- Base service behaviors: `Provision/Service.php` (`init_server()`, `config()`, `write()`, `unlink()`)

### HTTP service (webservers)
Supported service types:
- Apache: `Provision_Service_http_apache` (`http/Provision/Service/http/apache.php`)
- Apache SSL: `Provision_Service_http_apache_ssl` (`http/Provision/Service/http/apache/ssl.php`)
- Nginx: `Provision_Service_http_nginx` (`http/Provision/Service/http/nginx.php`)
- Nginx SSL: `Provision_Service_http_nginx_ssl` (`http/Provision/Service/http/nginx/ssl.php`)
- Cluster: `Provision_Service_http_cluster` (`http/Provision/Service/http/cluster.php`)
- Pack: `Provision_Service_http_pack` (`http/Provision/Service/http/pack.php`)

Core behaviors:
- Config generation for server/platform/site vhosts and includes.
- Service restart/reload integration.
- Nginx capability detection (http2, gzip, etag, php-fpm mode).

D11 implementation details:
- Apache config classes/templates: `http/Provision/Config/Apache/*.php` and `http/Provision/Config/Apache/*.tpl.php`.
- Nginx config classes/templates: `http/Provision/Config/Nginx/*.php` and `http/Provision/Config/Nginx/*.tpl.php`.
- Subdir support is controlled by the hosting feature flag (`provision_hosting_feature_enabled('subdirs')`).

D11 compatibility notes:
- Update templates to match modern PHP-FPM and Nginx defaults for Drupal 11.
- Apache/Nginx config templates should reflect Drupal 11's `/web` root if using Composer-based docroots.

### Database service
Supported type:
- MySQL/MariaDB via PDO (`mysql` driver only)

Code references:
- Base DB service: `db/Provision/Service/db.php`
- PDO base: `db/Provision/Service/db/pdo.php`
- MySQL implementation: `db/Provision/Service/db/mysql.php`

Core behaviors:
- Create/drop databases and users.
- Grant/revoke privileges.
- Export/import via mysqldump.
- Detect and record utf8mb4 support for Drupal sites.
- Optional GTID suppression via `provision_mysqldump_suppress_gtid_restore`.

D11 compatibility notes:
- Ensure PDO usage and mysql client calls are compatible with PHP 8.3 and modern MySQL/MariaDB.
- No Postgres support is defined; keep MySQL scope explicit.

## Provisioning task lifecycle
Provision executes most tasks as Drush commands that call pre/execute/post hooks implemented in `platform/*.provision.inc` and `db/*.provision.inc`.

Key flows and code references:
- **Verify**: `drush_provision_verify()` in `provision.drush.inc` calls `provision-save` then `d()->command_invoke('verify')`, which fan-outs to service `*_verify_cmd` handlers. Platform/site verify logic lives in `platform/verify.provision.inc`.
- **Install**: `platform/install.provision.inc` (`drush_provision_drupal_provision_install_validate()`, `drush_provision_drupal_provision_install()`, `drush_provision_drupal_post_provision_install()`).
- **Backup**: `db/backup.provision.inc` generates DB dump; `platform/backup.provision.inc` packages files and symlinks backups to client directories.
- **Restore/Deploy**: `platform/restore.provision.inc`, `platform/deploy.provision.inc`.
- **Clone/Migrate**: `platform/clone.provision.inc`, `platform/migrate.provision.inc`.
- **Import**: `platform/import.provision.inc`.
- **Enable/Disable/Lock**: `platform/enable.provision.inc`, `platform/disable.provision.inc`, `platform/lock.provision.inc`, `platform/unlock.provision.inc`.

Environment preparation and safety:
- `provision_prepare_environment()` in `platform/provision_drupal.drush.inc` exports DB credentials into `$_SERVER` and invokes `hook_provision_prepare_environment()` for extensions.
- `provision_auto_fix_platform_root()` in `provision.inc` normalizes platform roots for `docroot`, `html`, or `web` layouts.
- Local Drush lock/unlock helpers in `provision.inc` (`provision_lock_some_vnd()`, `provision_unlock_some_vnd()`) guard against conflicting Drush binaries in vendor directories.
- Lock markers: `local_drush_locked.pid` and `local_drush_unlocked.pid` are used in install/verify/backup flows to enforce local Drush locking.
- Platform/site sync: `provision_drupal_push_site()` and `provision_drupal_fetch_site()` in `platform/provision_drupal.drush.inc` manage rsync-based sync between master and remote servers.
- Context persistence: `provision_save_server_data()`, `provision_save_platform_data()`, `provision_save_site_data()` in `provision.inc` write updated drushrc files and push site data.
- Drush exit hook: `provision_drupal_drush_exit()` in `platform/provision_drupal.drush.inc` saves platform/site data after successful tasks.
- Backup cloaking: `platform/backup.provision.inc` temporarily disables `provision_db_cloaking` to include credentials in backups, then restores it.

## Filesystem and sync layer
Provision uses a chained filesystem abstraction for consistent logging and error propagation, plus rsync-based sync for remote servers.

Code references:
- File operations: `Provision/FileSystem.php` (copy, mkdir, chmod/chgrp/chown, unlink, etc.).
- Chained logging wrapper: `Provision/ChainedState.php` (`succeed()`, `fail()`, `status()`).
- Remote sync and command execution: `Provision/Context/server.php` (`sync()`, `shell_exec()`).

D11 compatibility notes:
- Keep file mode defaults aligned with modern security expectations (e.g., 0640/0440 for settings).
- Ensure remote sync operations use secure SSH options and are resilient to new OS defaults.

## SSL/TLS behaviors
SSL support is provided through `http/Provision/Service/http/ssl.php` and SSL webserver subclasses.

Capabilities:
- Per-server certificate store under `config/ssl.d`.
- Self-signed certificate generation using `openssl`.
- SSL key/chain discovery and deployment.
- HTTPS redirect mode via `ssl_enabled == 2`.

Code references:
- SSL base: `http/Provision/Service/http/ssl.php`
- Apache/Nginx SSL services: `http/Provision/Service/http/apache/ssl.php`, `http/Provision/Service/http/nginx/ssl.php`

D11 compatibility notes:
- Review LetsEncrypt/hosting_le control files and avoid overwrites.
- Ensure TLS configuration templates match modern cipher requirements.

## Drush command surface
Provision defines Drush commands for context lifecycle and site operations.

Commands:
- `provision-save`, `provision-verify`, `provision-delete`
- `provision-install`, `provision-disable`, `provision-enable`
- `provision-backup`, `provision-restore`, `provision-deploy`, `provision-backup-delete`
- `provision-clone`, `provision-migrate`, `provision-import`
- `provision-lock`, `provision-unlock`, `provision-dlock`, `provision-dunlock`
- `hostmaster-install`, `hostmaster-migrate`, `hostmaster-uninstall`
- `provision-login-reset`, `backend-parse`

Option highlights (from `provision.drush.inc`):
- `provision-save`: `context_type` plus all context option documentation from `Provision/Context/*::option_documentation()`.
- `provision-install`: `client_email`, `profile`, `force-reinstall`.
- `provision-backup`: optional backup file argument, `provision_backup_suffix`.
- `provision-deploy`: `old_uri` for replacement in content.
- `hostmaster-install`: `http_service_type`, `aegir_db_*`, `client_email`, `client_name`, `aegir_host`, `script_user`, `web_group`, `http_port`, `version`, `aegir_root`, `root`, `backend-only`.

Code references:
- Command definitions: `provision.drush.inc`
- Task implementations: `platform/*.provision.inc`

D11 compatibility notes:
- Drush 13 may require updates to command registration metadata, bootstrap phases, and alias handling.
- Audit uses of deprecated Drush APIs and update to Drush 13 equivalents.

## Drush 13 integration requirements
Implement Drush commands and alias handling using Drush 13's class-based command system and updated config/alias format.

Required changes for D11/Drush 13:
- Replace `hook_drush_command()` definitions with a `DrushCommands` class (e.g., `src/Commands/ProvisionCommands.php`) and command annotations/attributes.
- Migrate alias storage from PHP `*.alias.drushrc.php` files to YAML site alias files in `drush/sites/*.site.yml` and/or user-level aliases in `~/.drush/sites/`.
- Replace direct use of Drush globals (`drush_get_context`, `drush_set_context`) with Drush 13 context/config APIs where possible.
- Preserve backend invocation behavior by routing `provision_backend_invoke()` through Drush 13's process manager or equivalent API.
- Ensure command bootstrap phases align with Drupal 11 requirements (configuration bootstrap vs full bootstrap when required).
- Update `Provision/Service.php` usage of `drush` rsync includes (`rsync.core.inc`) to Drush 13's rsync APIs.
- Review `drush_core_call_rsync()` usage in `Provision/Context/server.php` and replace with Drush 13 process/rsync helpers if needed.
- Replace `drush_sitealias_get_record()` usage in `provision.context.inc` with Drush 13's site alias manager.

Vendor packaging requirements:
- Ship as a Composer package installed in `vendor/` (no Drupal module needed).
- Provide `drush.services.yml` at package root so Drush discovers command classes.
- Keep templates and resources within the package and resolve them via absolute paths.

Composer package example:
```json
{
  "name": "aegir/provision",
  "type": "drupal-drush",
  "require": {
    "php": "^8.3",
    "drush/drush": "^13.0",
    "symfony/process": "^6.4"
  },
  "autoload": {
    "psr-4": {
      "Aegir\\Provision\\": "src/"
    }
  },
  "extra": {
    "drush": {
      "services": {
        "drush.services.yml": "^13"
      }
    }
  }
}
```

## Extension points and settings
Provision exposes extension hooks and configurable options for behavior changes without modifying core logic.

Hook surface (from `provision.api.php`):
- Services: `hook_provision_services()`
- Context alteration: `hook_provision_context_alter()`
- Config templates and variables: `hook_provision_config_load_templates()`, `hook_provision_config_load_templates_alter()`, `hook_provision_config_variables_alter()`
- Apache/Nginx config injection: `hook_provision_apache_server_config()`, `hook_provision_apache_dir_config()`, `hook_provision_apache_vhost_config()`, `hook_provision_nginx_server_config()`, `hook_provision_nginx_dir_config()`, `hook_provision_nginx_vhost_config()`
- Sync and deploy: `hook_provision_platform_sync_path_alter()`, `hook_provision_deploy_options_alter()`
- Filesystem handling: `hook_provision_drupal_create_directories_alter()`, `hook_provision_drupal_chgrp_directories_alter()`, `hook_provision_drupal_chgrp_not_recursive_directories_alter()`, `hook_provision_drupal_chmod_not_recursive_directories_alter()`
- Database handling: `hook_provision_db_options_alter()`, `hook_provision_db_username_alter()`, `hook_provision_suggest_db_name_alter()`, `hook_provision_mysql_regex_alter()`
- Backup customization: `hook_provision_backup_exclusions_alter()`
- Environment setup: `hook_provision_prepare_environment()`

Configurable options (drushrc or equivalent in Drush 13):
- `provision_backup_suffix` (backup compression suffix)
- `provision_apache_conf_suffix` (use `.conf` vhost files)
- `provision_create_local_settings_file` (auto-create `local.settings.php`)
- `provision_mysqldump_suppress_gtid_restore` (GTID behavior)
- `provision_composer_install_platforms` / `provision_composer_install_platforms_verify_always`
- `provision_composer_install_command`
- `provision_db_cloaking` (credential cloaking in config templates)

## Platform/Drupal engine
Provision's Drupal engine binds platform/site tasks to Drupal's bootstrap and API surface.

Key behaviors:
- Verifies Drupal root, pushes site data, and optionally runs `composer install` during platform verify if `provision_composer_install_platforms` is enabled.
- Generates/updates site settings and drushrc files via config templates under `Provision/Config/Drupal` and `Provision/Config/Drushrc`.
- Runs version-specific install/import/verify workflows fully bootstrapped to the target Drupal site.
- Collects package/module/theme metadata via `platform/drupal/packages_*.inc` and stores it in the site/platform context (`packages` option).
- Manages per-site cron keys via `platform/drupal/cron_key*.inc` during install/verify.

Code references:
- Platform task driver: `platform/provision_drupal.drush.inc`
- Version shims: `platform/drupal/*.inc`

## Drupal 10+ platform support (Composer-only)
Provision's Drupal engine is versioned by `platform/drupal/*.inc`. Current code includes version shims up to Drupal 10.

Required D10+ changes:
- Treat Composer-based layouts as the only supported platform layout (typically `/web` docroot).
- Add `platform/drupal/install_11.inc`, `verify_11.inc`, `import_11.inc`, `deploy_11.inc`, and `packages_11.inc` (as needed) mirroring the D10 patterns.
- Ensure bootstrap mode matches Drupal 10+ requirements and modern service container behavior.
- Update YAML parsing and extension discovery logic to match Drupal 10+ API expectations.
- Add `Provision/Config/Drupal/provision_drupal_settings_11.tpl.php` and update `Provision/Config/Drupal/Settings.php` to select it when `drush_drupal_major_version() == 11`.
- Update platform drushrc path handling for Composer-based docroots (`/web`) and Drupal 10+ conventions.

Code references:
- Drupal version hooks: `platform/drupal/*.inc`
- Platform task driver: `platform/provision_drupal.drush.inc`

## Dependencies (Drupal 10+ architecture)
Runtime dependencies:
- **Drupal 10+** Composer-only platform codebases managed by Provision contexts.
- **Drush 13** for command execution and alias dispatch.
- **PHP 8.3+** for Provision runtime and subprocesses.
- **Composer** for platform dependency install (optional, controlled by `provision_composer_install_platforms`).
- **Provision package** installed in `vendor/` as a Drush extension (no Drupal module required).
- External tools: `mysql`, `mysqldump`, `openssl`, `rsync`, `ssh`.
- Optional system helper: `/usr/local/bin/fix-drupal-platform-ownership.sh` (used in verify/install flows for ownership fixes).

Code references:
- Composer runner: `platform/verify.provision.inc` (composer install path)
- Process execution: `provision.inc` (`provision_process()` uses `symfony/process`)
- Package metadata: `composer.json` (update platform PHP and symfony/process version for PHP 8.3/Drupal 11)

Implementation requirement:
- Update `composer.json` to target PHP 8.3+, replace PSR-0 autoloading with PSR-4 namespaces, and align `symfony/process` with the Symfony version used by Drupal 11 and Drush 13.

## Open gaps vs D10+ targets
- **Drupal 11 shims**: currently missing; must be added under `platform/drupal/`.
- **Drush 13 API changes**: audit and update `provision.drush.inc`, `parse.backend.inc`, and any deprecated Drush API usage.
- **PHP 8.3 compatibility**: update Composer constraints and audit code for removed PHP features.
- **Symfony Process version**: update `composer.json` to a version compatible with PHP 8.3 and Drupal 10+ Symfony stack.

## Security and operational considerations
- Avoid running provisioning commands as root (enforced in `provision.drush.inc`).
- Ensure SSH and filesystem permissions are aligned with webserver group and Aegir user.
- SSL certificate management should respect external certificate managers.
- Database credential handling should avoid logging secrets and use Drush options where applicable.
