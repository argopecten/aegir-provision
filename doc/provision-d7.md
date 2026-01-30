# Aegir Provision (D7) Architecture

> **⚠️ HISTORICAL REFERENCE ONLY**  
> This document describes the legacy Drupal 7 Provision architecture. **None of these files exist in the current D11 codebase.**  
> This documentation is preserved for historical context and understanding the evolution to the D11 implementation.  
> For current D11 architecture, see [provision-d11.md](provision-d11.md).

## Scope
This document describes the Aegir Provision backend found in `aegir-provision`. It covers core functionality, frontend integration points, context modeling, service types, webserver/database/SSL support, Drush extensions, supported Drupal versions, and runtime dependencies.

## Overview
Provision is the Drush-driven backend that performs site, platform, and server lifecycle operations for Aegir. It models infrastructure and Drupal installations as contexts, generates and synchronizes configuration files, and executes tasks by invoking Drush commands against those contexts.

Primary entry points:
- Drush command definitions and hooks: `provision.drush.inc`
- Core provisioning API and helpers: `provision.inc`
- Context model and lookup: `provision.context.inc`, `Provision/Context/*.php`
- Service implementations (http/db): `http/http.drush.inc`, `db/db.drush.inc`

## Major components
- **Context system**: `Provision/Context.php`, `Provision/Context/server.php`, `Provision/Context/platform.php`, `Provision/Context/site.php`
- **Service layer**: `Provision/Service.php`, `db/Provision/Service/*`, `http/Provision/Service/*`
- **Config generation**: `Provision/Config/*`, `http/Provision/Config/*`
- **Platform engine**: `platform/*.provision.inc`, `platform/provision_drupal.drush.inc`
- **Drupal version shims**: `platform/drupal/*.inc`
- **Hostmaster integration**: `install.hostmaster.inc`, `migrate.hostmaster.inc`, `uninstall.hostmaster.inc`

## Context model
Provision uses named Drush aliases as contexts. The `d()` helper in `provision.context.inc` loads or creates contexts by name and runs init hooks.

### Server context
Defined in `Provision/Context/server.php`.
- Key properties: `remote_host`, `script_user`, `aegir_root`, `master_url`, `admin_email`, `ip_addresses`
- Derived paths (under `aegir_root`):
  - `config_path`: `config/<server>`
  - `include_path`: `config/includes`
  - `backup_path`: `backups`
  - `clients_path`: `clients`
- Spawns service handlers for each registered service type (currently http and db).
- Uses SSH + rsync for remote file sync (`sync()` and `shell_exec()` in `Provision/Context/server.php`).

Code references:
- Context lookup/creation: `provision.context.inc` (functions `d()`, `provision_context_factory()`, `provision_sitealias_get_record()`)
- Base context behavior: `Provision/Context.php` (`init()`, `setProperty()`, `method_invoke()`, `type_invoke()`)
- Service bootstrap: `Provision/Context/server.php` (`load_services()`, `spawn_service()`)
- Remote execution/sync: `Provision/Context/server.php` (`shell_exec()`, `sync()`)

### Platform context
Defined in `Provision/Context/platform.php`.
- Key properties: `root` (Drupal codebase path), `server` (backend server), `web_server` (http service server).
- Service subscriptions are attached based on the server configuration.
- Platforms are verified and bootstrapped by the platform engine (`platform/verify.provision.inc`).

Code references:
- Platform context: `Provision/Context/platform.php` (`init_platform()`)
- Platform verification entry point: `platform/verify.provision.inc` (`drush_provision_drupal_pre_provision_verify()`)

### Site context
Defined in `Provision/Context/site.php`.
- Key properties: `uri`, `platform`, `db_server`, `profile`, `install_method`, `aliases`, `redirection`, `client_name`, `drush_aliases`.
- Computes `site_path` as `${platform.root}/sites/${uri}`.
- Writes Drush alias files for the primary alias and any `drush_aliases` via `Provision_Config_Drushrc_Alias`.

Code references:
- Site context: `Provision/Context/site.php` (`init_site()`, `write_alias()`)
- Alias writer: `Provision/Config/Drushrc/Alias.php` (`filename()`)

## Frontend (Hostmaster) integration
Provision is designed to be driven by the Hostmaster frontend (Drupal). The integration is purely Drush-based:
- **Hostmaster install/migrate/uninstall** are provided as Drush commands and wire up the core contexts:
  - `@server_master` (server)
  - `@platform_hostmaster` (platform)
  - `@hostmaster` (site)
  - Files: `install.hostmaster.inc`, `migrate.hostmaster.inc`, `uninstall.hostmaster.inc`
- **Backend invocation** uses `provision_backend_invoke()` in `provision.inc`, which calls `drush_invoke_process()` with `integrate => TRUE`. This is how Hostmaster tasks enqueue and execute backend work across contexts.
- **Backend output parsing** is provided via `parse.backend.inc`, which integrates Drush backend output into logs/UI.

In practice, the frontend writes/updates context aliases and then dispatches provisioning tasks (verify/install/migrate/clone/backup/etc.) against those contexts.

Code references:
- Hostmaster install flow: `install.hostmaster.inc` (`drush_provision_hostmaster_install_validate()`, `drush_provision_hostmaster_install()`)
- Hostmaster migration: `migrate.hostmaster.inc` (`drush_provision_hostmaster_migrate_validate()`, `drush_provision_hostmaster_migrate()`)
- Hostmaster uninstall: `uninstall.hostmaster.inc` (`drush_provision_hostmaster_uninstall_validate()`, `drush_provision_hostmaster_uninstall()`)
- Backend invocation glue: `provision.inc` (`provision_backend_invoke()`)
- Backend output parsing: `parse.backend.inc` (`drush_provision_backend_parse()`)

## Service layer
Services are registered through `hook_provision_services()` in `db/db.drush.inc` and `http/http.drush.inc`. Each service type provides context hooks, config generation, and lifecycle hooks (verify/save/delete).

Code references:
- Service registration: `db/db.drush.inc` (`db_provision_services()`), `http/http.drush.inc` (`http_provision_services()`)
- Base service behavior: `Provision/Service.php` (`init_server()`, `config()`, `write()`, `unlink()`)

### HTTP service (webservers)
Base class: `http/Provision/Service/http.php` with public/SSL subclasses.
Supported webserver types (by class name):
- **Apache**: `Provision_Service_http_apache` (`http/Provision/Service/http/apache.php`)
- **Apache + SSL**: `Provision_Service_http_apache_ssl` (`http/Provision/Service/http/apache/ssl.php`)
- **Nginx**: `Provision_Service_http_nginx` (`http/Provision/Service/http/nginx.php`)
- **Nginx + SSL**: `Provision_Service_http_nginx_ssl` (`http/Provision/Service/http/nginx/ssl.php`)
- **Cluster (multi-webserver)**: `Provision_Service_http_cluster` (`http/Provision/Service/http/cluster.php`)
- **Pack (master/slave webservers)**: `Provision_Service_http_pack` (`http/Provision/Service/http/pack.php`)

Key behaviors:
- Generates per-server, per-platform, and per-site config snippets under `config/<server>/<webserver>/*`.
- Apache restart command discovery uses `apachectl`/`apache2ctl` scanning.
- Nginx service probes features (`http_v2`, `gzip`, `etag`) and detects PHP-FPM socket vs port modes.
- Cluster/pack services fan out config generation and restarts across multiple webservers.

Code references:
- Base HTTP service: `http/Provision/Service/http.php` (`subscribe_platform()`, `verify_server_cmd()`)
- Public HTTP service: `http/Provision/Service/http/public.php` (`init_server()`, `config_data()`)
- Apache: `http/Provision/Service/http/apache.php` (`apache_restart_cmd()`, `init_server()`)
- Apache SSL: `http/Provision/Service/http/apache/ssl.php` (`init_server()`)
- Nginx: `http/Provision/Service/http/nginx.php` (`save_server()`, `getPhpFpmMode()`)
- Nginx SSL: `http/Provision/Service/http/nginx/ssl.php` (`save_server()`)
- Cluster: `http/Provision/Service/http/cluster.php` (`_each_server()`, `grant_server_list()`)
- Pack: `http/Provision/Service/http/pack.php` (`_each_server()`, `grant_server_list()`)

### Database service
Base classes: `db/Provision/Service/db.php`, `db/Provision/Service/db/pdo.php`
Implementation: `db/Provision/Service/db/mysql.php`

Behavior highlights:
- MySQL/MariaDB only (PDO `mysql` driver). No Postgres support is present.
- Creates databases and users, grants privileges, and generates per-site credentials.
- Supports `db_grant_all_hosts` to allow broad host grants.
- Performs mysqldump-based backups and restore operations.
- Detects utf8mb4 support for Drupal 7 and records it on server save.

Code references:
- DB service base: `db/Provision/Service/db.php` (`create_site_database()`, `destroy_site_database()`, `generate_site_credentials()`)
- PDO DB base: `db/Provision/Service/db/pdo.php` (`connect()`, `query()`, `close()`)
- MySQL implementation: `db/Provision/Service/db/mysql.php` (`create_database()`, `import_dump()`, `generate_dump()`)

## Config generation
Provision writes Drupal, Drush alias, and webserver configuration using config classes and templates. These are invoked by service `config()` and `write()` calls during verify/install operations.

Code references:
- Config base classes: `Provision/Config.php`, `Provision/Config/Data/*`
- Drush alias configs: `Provision/Config/Drushrc.php`, `Provision/Config/Drushrc/Alias.php`
- Drupal settings configs: `Provision/Config/Drupal/Settings.php`
- HTTP config templates: `http/Provision/Config/Apache/*`, `http/Provision/Config/Nginx/*`

## SSL/TLS support
Implemented in `http/Provision/Service/http/ssl.php` and extended by Apache/Nginx SSL services.

Capabilities:
- Per-server SSL store: `${aegir_root}/config/ssl.d` and per-server `http_ssld_path`.
- Generates self-signed certificates on-demand (`openssl` key/cert generation).
- Supports certificate chains (`openssl_chain.crt`) when provided.
- Per-site SSL settings: `ssl_enabled`, `ssl_key`, and optional IP assignments.
- `ssl_enabled == 2` enables HTTP->HTTPS redirection.
- Respects LetsEncrypt/hosting_le control files to avoid overwriting managed certs.

Code references:
- SSL base service: `http/Provision/Service/http/ssl.php` (`init_server()`, `get_certificates()`, `generate_certificates()`)
- Certificate assignment: `http/Provision/Service/http/ssl.php` (`assign_certificate_site()`, `free_certificate_site()`)
- SSL webserver services: `http/Provision/Service/http/apache/ssl.php`, `http/Provision/Service/http/nginx/ssl.php`

## Drush extensions
Provision extends Drush with a set of provisioning commands and hooks in `provision.drush.inc` and the platform modules in `platform/*.provision.inc`.

Core command families:
- Context lifecycle: `provision-save`, `provision-delete`, `provision-verify`
- Site lifecycle: `provision-install`, `provision-disable`, `provision-enable`, `provision-clone`, `provision-migrate`, `provision-import`
- Backup/restore: `provision-backup`, `provision-restore`, `provision-deploy`, `provision-backup-delete`
- Platform control: `provision-lock`, `provision-unlock`, `provision-dlock`, `provision-dunlock`
- Hostmaster ops: `hostmaster-install`, `hostmaster-migrate`, `hostmaster-uninstall`
- Utility: `provision-login-reset`, `backend-parse`

Provision relies on Drush bootstrap phases and integrates with Drush aliases to scope work to server/platform/site contexts.

Code references:
- Command registration: `provision.drush.inc` (`provision_drush_command()`, `provision_drush_init()`)
- Backend parse command: `parse.backend.inc` (`drush_provision_backend_parse()`)
- Hook examples and options: `provision.api.php`

## Platform/Drupal engine
The platform engine in `platform/provision_drupal.drush.inc` and `platform/*.provision.inc` implements Drupal-specific operations.

Supported Drupal versions:
- Drupal 6, 7, 8, 9, 10 (version-specific helpers in `platform/drupal/*.inc`)

Key behaviors:
- Verifies Drupal root, pushes site data, and (optionally) runs `composer install` during platform verify if `provision_composer_install_platforms` is enabled.
- Generates/updates site settings and drushrc files via config templates under `Provision/Config/Drupal` and `Provision/Config/Drushrc`.
- Runs version-specific install/import/verify workflows fully bootstrapped to the target Drupal site.

Code references:
- Platform task wiring: `platform/provision_drupal.drush.inc` (`provision_drupal_drush_exit()`, `provision_drupal_push_site()`)
- Verify task: `platform/verify.provision.inc` (`drush_provision_drupal_pre_provision_verify()`)
- Install task: `platform/install.provision.inc` (`drush_provision_drupal_provision_install_backend()`)
- Deploy/clone/migrate tasks: `platform/deploy.provision.inc`, `platform/clone.provision.inc`, `platform/migrate.provision.inc`
- Version shims: `platform/drupal/install_6.inc`, `platform/drupal/install_7.inc`, `platform/drupal/install_8.inc`, `platform/drupal/install_9.inc`, `platform/drupal/install_10.inc`
- Drupal helpers: `platform/drupal/verify.inc`, `platform/drupal/import_*.inc`, `platform/drupal/deploy_*.inc`

## Webserver, DB, and SSL support summary
- Webservers: Apache, Apache SSL, Nginx, Nginx SSL, Cluster, Pack
- Databases: MySQL/MariaDB via PDO (`mysql`), with mysqldump-based backup/restore
- SSL: Self-signed cert generation, chain support, per-site SSL flags, HTTPS redirect mode

## Versions and dependencies
- Provision module version: `7.x-3.x` (`provision.info`)
- Composer package: `aegir/provision` (`composer.json`)
- Runtime dependency: `symfony/process` ^3.4
- PHP platform target: 7.0.8 (composer platform setting)
- Dev dependency: `overtrue/phplint` ^1.2
- External tools used at runtime: Drush, rsync/ssh, mysql/mysqldump, openssl

## Notable operational flows
### Hostmaster install (simplified)
1. Create `@server_master` and optional DB server contexts.
2. Create `@platform_hostmaster` pointing at the hostmaster codebase.
3. Create `@hostmaster` site context and run `provision-install` + `provision-verify`.
4. Run `hosting-setup` to finalize frontend integration.

### Site provisioning
1. `provision-save` updates context alias data.
2. `provision-verify` generates webserver/db config and validates runtime.
3. `provision-install` bootstraps the site (profile install or empty DB).
4. Optional `provision-deploy`/`provision-clone` flows use backups to move sites.

## Extension points
Provision exposes hook APIs for customization (examples in `provision.api.php`), including:
- Service registration (`hook_provision_services`)
- Context alteration (`hook_provision_context_alter`)
- Webserver config injection for Apache/Nginx
- DB and mysqldump customization hooks
