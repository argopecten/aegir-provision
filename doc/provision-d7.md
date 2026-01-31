# Aegir Provision (D7) Architecture

> **⚠️ HISTORICAL REFERENCE ONLY**  
> This document describes the legacy Drupal 7 Provision architecture. **None of these files exist in the current D11 codebase.**  
> This documentation is preserved for historical context and understanding the evolution to the D11 implementation.  
> For current D11 architecture, see [provision-d11.md](provision-d11.md).


**Generated:** January 31, 2026  
**Source:** /var/aegir/d7/aegir-provision  
**Purpose:** Document complete D7 implementation for refactoring to Drush 13.7+ / Drupal 11

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture Components](#architecture-components)
3. [Commands](#commands)
4. [Context System](#context-system)
5. [Service Architecture](#service-architecture)
6. [Configuration Generation](#configuration-generation)
7. [Hook System](#hook-system)
8. [Backend & Frontend Integration](#backend--frontend-integration)
9. [Backup & Restore](#backup--restore)
10. [Migration & Clone](#migration--clone)
11. [SSL/TLS Support](#ssltls-support)
12. [Platform/Drupal Engine](#platformdrupal-engine)
13. [Key Files](#key-files)
14. [Refactoring Considerations](#refactoring-considerations)

---

## Overview

The Drupal 7 / Drush 8 version of Aegir Provision is a comprehensive framework for provisioning and managing Drupal sites via the command line. It provides:

- **Context Objects**: Server, Platform, Site abstractions
- **Service Layer**: Pluggable http, db, and other services
- **Hook System**: Pre/post hooks for all commands
- **Config Generation**: Template-based vhost, settings.php, drushrc generation
- **Backup/Restore**: Complete site packaging and deployment

---


## Architecture Components

### Major Components

- **Context system**: `Provision/Context.php`, `Provision/Context/server.php`, `Provision/Context/platform.php`, `Provision/Context/site.php`
- **Service layer**: `Provision/Service.php`, `db/Provision/Service/*`, `http/Provision/Service/*`
- **Config generation**: `Provision/Config/*`, `http/Provision/Config/*`
- **Platform engine**: `platform/*.provision.inc`, `platform/provision_drupal.drush.inc`
- **Drupal version shims**: `platform/drupal/*.inc`
- **Hostmaster integration**: `install.hostmaster.inc`, `migrate.hostmaster.inc`, `uninstall.hostmaster.inc`

### Supported Environments

**Webservers:**
- Apache (with mod_php, PHP-FPM)
- Apache with SSL
- Nginx (with PHP-FPM)
- Nginx with SSL
- Cluster (multi-webserver)
- Pack (master/slave webservers)

**Databases:**
- MySQL/MariaDB via PDO (`mysql` driver)
- mysqldump-based backup/restore
- No PostgreSQL support in core

**SSL/TLS:**
- Self-signed certificate generation
- Custom certificate support
- Certificate chain support
- Per-site SSL flags
- HTTP→HTTPS redirect mode
- LetsEncrypt integration support

### Versions and Dependencies

- **Provision module version**: `7.x-3.x` (`provision.info`)
- **Composer package**: `aegir/provision` (`composer.json`)
- **Runtime dependency**: `symfony/process` ^3.4
- **PHP platform target**: 7.0.8 (composer platform setting)
- **Dev dependency**: `overtrue/phplint` ^1.2
- **External tools**: Drush 8, rsync/ssh, mysql/mysqldump, openssl, apache2ctl/nginx

---

## Commands

### Command Structure

All commands are defined in `provision.drush.inc` via `provision_drush_command()`.

### Core Commands

#### 1. **provision-save**
- **Purpose**: Save/update Drush alias and context configuration
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUSH`
- **Arguments**: `@context_name`
- **Options**:
  - `context_type`: server, platform, or site
  - `delete`: Remove the alias
  - Plus all context-specific options
- **Hooks**: N/A (direct save operation)
- **Implementation**:
  ```php
  function drush_provision_save($alias = NULL) {
    if (drush_get_option('delete', FALSE)) {
      $config = new Provision_Config_Drushrc_Alias($alias);
      $config->unlink();
    }
    else {
      d($alias)->type_invoke('save');
      d($alias)->write_alias();
    }
  }
  ```
- **Output**: Creates/updates alias files in `~/.drush/*.alias.drushrc.php`

#### 2. **provision-verify**
- **Aliases**: `v`, `pv`, `verify`
- **Purpose**: Verify configuration and regenerate config files
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUSH`
- **Options**:
  - `working-copy`: Keep VCS files for make
  - `override_slave_authority`: Push files to slave
- **Hooks**:
  - `drush_provision_drupal_provision_verify_validate()`
  - `drush_provision_drupal_pre_provision_verify()`
  - `drush_provision_drupal_post_provision_verify()`
- **Services Involved**: All services (http, db)
- **Implementation Flow**:
  1. Calls `provision-save` via backend invoke
  2. Calls `d()->command_invoke('verify')`
  3. For **servers**: Creates config directories, loads services
  4. For **platforms**: Runs drush make if needed, composer install, detects Drupal version
  5. For **sites**: Creates directories, settings.php, aliases, rebuilds caches

#### 3. **provision-install**
- **Purpose**: Install a new Drupal site
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_ROOT`
- **Options**:
  - `client_email`: Client email address
  - `profile`: Installation profile (default: standard)
  - `force-reinstall`: Delete existing database and files
- **Hooks**:
  - `drush_provision_drupal_provision_install_validate()`
  - `drush_provision_drupal_pre_provision_install()`
  - `drush_provision_drupal_provision_install()`
  - `drush_provision_drupal_post_provision_install()`
  - `drush_provision_drupal_pre_provision_install_rollback()`
- **Services Involved**:
  - **db**: Create database and user
  - **http**: Generate vhost configuration
- **Implementation Flow**:
  1. Validate site doesn't exist (or force-reinstall)
  2. Create site directories (sites/example.com)
  3. Create database and grant privileges
  4. Generate settings.php
  5. Run `provision-install-backend` for Drupal installation
  6. Generate vhost configuration
  7. Restart web server
- **Sub-command**: `provision-install-backend` (hidden, runs Drupal install profile)

#### 4. **provision-backup**
- **Purpose**: Create site backup tarball
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_ROOT`
- **Arguments**: `backup-file` (optional)
- **Options**:
  - `provision_backup_suffix`: Compression format (default: .tar.gz)
- **Hooks**:
  - `drush_provision_drupal_provision_backup_validate()`
  - `drush_provision_drupal_provision_backup()`
  - `drush_provision_drupal_post_provision_backup()`
  - `drush_provision_drupal_provision_backup_rollback()`
- **Services Involved**:
  - **db**: Dump database to database.sql
  - **http**: May temporarily uncloak DB credentials
- **Backup Contents**:
  - Site directory (sites/example.com/)
  - settings.php and drushrc.php
  - files/ directory
  - database.sql dump
  - Excludes: files/css, files/js, private/temp
- **File Location**: `~/backups/example.com-YYYYMMDD.HHMMSS.tar.gz`

#### 5. **provision-restore**
- **Purpose**: Restore site from backup
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_ROOT`
- **Arguments**: `site_backup.tar.gz`
- **Hooks**:
  - Similar to deploy (extracts, imports DB, replaces site)
- **Services Involved**: db, http
- **Implementation**: Uses deploy mechanism internally

#### 6. **provision-deploy**
- **Purpose**: Deploy backup to new/existing site
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_ROOT`
- **Arguments**: `site_backup.tar.gz`
- **Options**:
  - `old_uri`: Old site URI for database content replacement
- **Hooks**:
  - `drush_provision_drupal_provision_deploy_validate()`
  - `drush_provision_drupal_pre_provision_deploy()`
  - `drush_provision_drupal_provision_deploy()`
  - `drush_provision_drupal_post_provision_deploy()`
  - `drush_provision_drupal_pre_provision_deploy_rollback()`
- **Services Involved**: db, http
- **Implementation Flow**:
  1. Extract backup to extract_path
  2. Switch directories if replacing existing site
  3. Import database
  4. Validate module schema versions
  5. Run updatedb
  6. Rebuild registry and caches
  7. Rebuild node access
  8. Remove old database

#### 7. **provision-migrate**
- **Purpose**: Move site to different platform
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_ROOT`
- **Arguments**:
  - `@platform_name`: Target platform
- **Options**:
  - `profile`: Drupal profile
  - `new_db_server`: Change database server
- **Hooks**:
  - `drush_provision_drupal_provision_migrate_validate()`
  - `drush_provision_drupal_pre_provision_migrate()`
  - `drush_provision_drupal_provision_migrate()`
  - `drush_provision_drupal_post_provision_migrate()`
  - `drush_provision_drupal_provision_migrate_rollback()`
- **Services Involved**: All services
- **Implementation Flow**:
  1. Put site in maintenance mode
  2. Create backup
  3. Verify source and target platforms
  4. Update context with new platform
  5. Call `provision-deploy` with backup
  6. Verify new site
  7. Clean up old site directory
  8. Remove old vhost configuration

#### 8. **provision-clone**
- **Purpose**: Clone site to new site on different platform
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_ROOT`
- **Arguments**:
  - `@new_site`: New site alias
  - `@platform_name`: Target platform
- **Options**:
  - `profile`: Drupal profile
- **Hooks**:
  - `drush_provision_drupal_provision_clone_validate()`
  - `drush_provision_drupal_pre_provision_clone()`
  - Creates backup, then uses deploy
- **Services Involved**: All services
- **Implementation**: Backup + Deploy to new alias

#### 9. **provision-delete**
- **Purpose**: Delete site or platform
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUSH`
- **Options**:
  - `force`: Force deletion
- **Hooks**:
  - Various delete hooks in service files
- **Services Involved**:
  - **db**: Drop database and revoke privileges
  - **http**: Remove vhost configuration
- **Implementation**:
  - For sites: Backup, drop database, remove site directory
  - For platforms: Verify no sites exist, remove platform

#### 10. **provision-enable / provision-disable**
- **Purpose**: Enable or disable a site
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_ROOT`
- **Hooks**: Service-specific enable/disable
- **Services Involved**:
  - **http**: Generate enabled/disabled vhost
- **Implementation**:
  - Disable: Replaces vhost with redirect to disabled page
  - Enable: Restores normal vhost configuration

#### 11. **provision-lock / provision-unlock**
- **Purpose**: Lock/unlock platform from provisioning
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_ROOT`
- **Services Involved**: None (metadata only)
- **Implementation**: Sets platform property, prevents new sites

#### 12. **provision-login-reset**
- **Purpose**: Generate one-time login URL
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_ROOT`
- **Implementation**:
  ```php
  function provision_generate_login_reset() {
    $uri = d()->redirection ?: d()->uri;
    $result = drush_invoke_process(d()->name, 'user-login', 
      array(), array('uri' => $uri, 'no-browser' => TRUE));
    return $result['output'];
  }
  ```

#### 13. **provision-backup-delete**
- **Purpose**: Delete a backup file
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUSH`
- **Arguments**: `backup-file` (required)
- **Implementation**: Simple file deletion

#### 14. **hostmaster-install**
- **Purpose**: Install Aegir frontend
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUSH`
- **Options**: Many server configuration options
- **Implementation**: Special installer for Hostmaster

#### 15. **hostmaster-migrate**
- **Purpose**: Migrate Hostmaster to new platform
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_ROOT`
- **Implementation**: Special migration for Hostmaster

#### 16. **hostmaster-uninstall**
- **Purpose**: Uninstall Aegir frontend
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUPAL_SITE`
- **Options**:
  - `all`: Destroy all managed sites
- **Implementation**: Clean removal of Hostmaster

#### 17. **backend-parse**
- **Purpose**: Parse --backend command output to human-readable form
- **Bootstrap**: `DRUSH_BOOTSTRAP_DRUSH`
- **Implementation**: Output parser for backend mode

---


## Context System

### Architecture

The context system is the foundation of Aegir Provision, providing object-oriented abstractions for servers, platforms, and sites.

### Core Function: `d()`

Located in `provision.context.inc`, the `d()` function is the primary access point:

```php
function & d($name = NULL, $_root_object = FALSE, $allow_creation = TRUE)
```

- **Purpose**: Load and cache context objects
- **Parameters**:
  - `$name`: Context name (e.g., '@server_master', '@example.com')
  - `$_root_object`: Set as default instance
  - `$allow_creation`: Create if doesn't exist
- **Returns**: Reference to Provision_Context object
- **Caching**: Maintains static array of loaded contexts

### Context Types

#### Base: `Provision_Context`

Located in `Provision/Context.php`

**Key Features:**
- **Magic Methods**: `__get()`, `__set()`, `__isset()`, `__unset()`
- **Properties Array**: Stores all context properties
- **OID Mapping**: Track properties that reference other contexts
- **Service Subscriptions**: Link to service providers

**Key Methods:**
```php
// Property management
function setProperty($field, $default = NULL, $array = FALSE, $force = FALSE)

// Service access
function service($service, $name = NULL)
function services_invoke($callback, $args = array())

// Method dispatch
function method_invoke($func, $args = array(), $services = TRUE)
function type_invoke($name, $args = array())
function command_invoke($command, $args = array())

// Configuration
function write_alias()
static function option_documentation()
```

#### Server Context: `Provision_Context_server`

Located in `Provision/Context/server.php`

**Properties:**
```php
'remote_host' => 'localhost',
'script_user' => provision_current_user(),
'aegir_root' => getenv('HOME'),
'master_url' => NULL,
'admin_email' => 'admin@localhost',
'ip_addresses' => array(),
'backup_path' => '~/backups',
'config_path' => '~/config/server_name',
'include_path' => '~/config/includes',
'clients_path' => '~/clients',
```

**Services:**
- Spawns service instances for: http, db, dns, etc.
- Each service type determined by `{service}_service_type` option

**Key Methods:**
```php
function init_server()
function load_services()
function spawn_service($service, $default = null)
function service($service, $name = null)
function shell_exec($command)  // SSH if remote
function sync($path, $additional_options)  // Rsync to remote
function fetch($path, $additional_options)  // Rsync from remote
```

**Remote Server Support:**
- SSH execution via `drush_shell_exec('ssh ...')`
- Rsync for file synchronization
- Credential-less SSH (key-based auth required)

#### Platform Context: `Provision_Context_platform`

Located in `Provision/Context/platform.php`

**Properties:**
```php
'server' => '@server_master',  // Parent server
'web_server' => '@server_master',  // HTTP service provider
'root' => NULL,  // Path to Drupal root
'makefile' => '',  // Optional drush makefile
'make_working_copy' => FALSE,
```

**Parent Key:** `'server'` (inherits services from server)

**Key Features:**
- Auto-discovery of Drupal root for Composer-based installs
- Drush make support for platform creation
- Composer install support
- Package discovery (modules, themes, profiles)

#### Site Context: `Provision_Context_site`

Located in `Provision/Context/site.php`

**Properties:**
```php
'platform' => NULL,  // Parent platform
'db_server' => '@server_master',  // Database service
'uri' => NULL,  // Primary domain
'language' => 'en',
'client_name' => NULL,
'profile' => 'standard',
'install_method' => 'profile',
'aliases' => array(),  // Additional domains
'redirection' => FALSE,  // Redirect aliases
'drush_aliases' => array(),  // Additional drush aliases
'site_enabled' => TRUE,
'cron_key' => '',
```

**Computed Properties:**
```php
'root' => $platform->root,  // Drupal root
'site_path' => root + '/sites/' + uri,  // Site directory
```

**Parent Key:** `'platform'` (inherits services from platform)

**Key Features:**
- Database credentials (db_user, db_name, db_passwd, db_host, db_port, db_type)
- Multi-domain support via aliases
- Client association for organization
- Installation method (profile, manual)

### Context Factory

**Function:** `provision_context_factory($name, $allow_creation = TRUE)`

**Process:**
1. Load drush alias record
2. Determine context type from `context_type` option
3. Instantiate `Provision_Context_{$type}` class
4. Return context object

### Context Initialization

When a context is loaded via `d()`:

1. **Factory Creation**: `provision_context_factory()` instantiates class
2. **Method Init**: Calls `$context->method_invoke('init')`
3. **Type Init**: Calls `$context->type_invoke('init')`
4. **Hook**: `drush_command_invoke_all_ref('provision_context_alter', $context)`

### Context Storage

**Alias Files:** `~/.drush/{name}.alias.drushrc.php`

**Format:**
```php
<?php
$aliases['example'] = array(
  'context_type' => 'site',
  'platform' => '@platform_drupal7',
  'db_server' => '@server_master',
  'uri' => 'example.com',
  'root' => '/var/aegir/platforms/drupal-7.58',
  'site_path' => '/var/aegir/platforms/drupal-7.58/sites/example.com',
  // ... more properties
);
```

**Drushrc Files:** `{site_path}/drushrc.php`

Stores site-specific configuration within the site directory.

---


## Service Architecture

### Overview

Services provide pluggable functionality for different aspects of site provisioning:
- **http**: Web server configuration (Apache, Nginx)
- **db**: Database management (MySQL, PostgreSQL)
- **dns**: DNS record management
- **file**: File system operations
- Others can be added via `hook_provision_services()`

### Base Service: `Provision_Service`

Located in `Provision/Service.php`

**Key Properties:**
```php
protected $server = '@server_master';  // Server context
public $context;  // Current context being operated on
protected $service = NULL;  // Service name (http, db, etc)
protected $application_name = NULL;  // Application name (apache, nginx, mysql)
protected $has_restart_cmd = FALSE;
protected $has_port = FALSE;
protected $configs = array();  // Config file classes
```

**Key Methods:**
```php
// Initialization
function init()
function init_server()
function init_platform()
function init_site()

// Configuration management
function config($config, $data = array())
function create_config($config, $data = array())
function delete_config($config, $data = array())
function config_data($config = NULL, $class = NULL)
function write()
function unlink()

// Record management (for data stores)
function record_set($arg1, $arg2 = NULL)
function record_del($record)
function record_exists($record)
function record_get($key = NULL, $default = NULL)

// Service control
function restart()
function verify()
function sync($path, $additional_options)
function fetch($path)

// Context management
function setContext($context)

// Documentation
static function option_documentation()
```

### HTTP Service

#### Base: `Provision_Service_http`

Located in `http/Provision/Service/http.php`

**Service Type:** `http`

**Implementations:**
- **apache**: Apache web server
- **nginx**: Nginx web server
- **cluster**: Multiple web servers
- **pack**: Packaged/alternative configurations

**Key Methods:**
```php
function verify_server_cmd()  // Generate server config
function verify_platform_cmd()  // Generate platform config
function verify_site_cmd()  // Generate site vhost
function cloaked_db_creds()  // Support for cloaking DB credentials
```

**Subscription:**
```php
static function subscribe_platform($context) {
  $context->setProperty('web_server', '@server_master');
  $context->is_oid('web_server');
  $context->service_subscribe('http', $context->web_server->name);
}
```

#### Apache Implementation: `Provision_Service_http_apache`

Located in `http/Provision/Service/http/apache.php`

**Configuration Files Generated:**
- **Server Config**: `~/config/server_name/apache.conf`
- **Platform Config**: `~/config/server_name/apache/platform.d/platform_name.conf`
- **Site Vhost**: `~/config/server_name/apache/vhost.d/example.com`
- **Disabled Vhost**: `~/config/server_name/apache/vhost.d/example.com` (redirect)
- **SSL Vhost**: `~/config/server_name/apache/vhost.d/example.com` (port 443)

**Configuration Classes:**
```php
'server' => 'Provision_Config_Apache_Server',
'platform' => 'Provision_Config_Apache_Platform', 
'site' => 'Provision_Config_Apache_Site',
'site_disabled' => 'Provision_Config_Apache_Site_Disabled',
```

**Features:**
- Name-based virtual hosts
- SSL support (separate class: `Provision_Service_http_apache_ssl`)
- Subdir multisite support
- Include directories for custom config

#### Nginx Implementation: `Provision_Service_http_nginx`

Located in `http/Provision/Service/http/nginx.php`

Similar structure to Apache but with Nginx-specific templates and configuration.

**Key Differences:**
- Different vhost template syntax
- Different SSL configuration
- FastCGI/PHP-FPM integration

### Database Service

#### Base: `Provision_Service_db`

Located in `db/Provision/Service/db.php`

**Service Type:** `db`

**Key Properties:**
```php
protected $creds;  // Database credentials from master_db
```

**Key Methods:**
```php
// Server operations
function verify_server_cmd()
function save_server()

// Database operations
function suggest_db_name()
function create_site_database($creds = array())
function destroy_site_database($creds = array())
function import_site_database($dump_file = null, $creds = array())

// Credential management
function generate_site_credentials()
function fetch_site_credentials()

// Low-level operations (implemented by subclasses)
function database_exists($name)
function drop_database($name)
function create_database($name)
function can_create_database()
function can_grant_privileges()
function grant($name, $username, $password, $host = '')
function revoke($name, $username, $host = '')
function import_dump($dump_file, $creds)
function generate_dump()

// Grant management
function grant_host_list()
function grant_host(Provision_Context_server $server)
function utf8mb4_is_supported()
```

**Subscription:**
```php
static function subscribe_site($context) {
  $context->setProperty('db_server', '@server_master');
  $context->is_oid('db_server');
  $context->service_subscribe('db', $context->db_server->name);
}
```

**Credentials:**
- **Master DB**: `master_db` property on server: `mysql://user:pass@host:port`
- **Site DB**: Generated credentials stored in site context

#### MySQL Implementation: `Provision_Service_db_mysql`

Located in `db/Provision/Service/db/mysql.php`

**Extends:** `Provision_Service_db_pdo`

**Key Features:**
- PDO-based database operations
- Credential file via `/dev/fd/3` for security
- Grants to both `%` and `127.0.0.1` for flexibility
- UTF8MB4 support detection
- GTID handling for replication

**Database Operations:**
```php
function drop_database($name)
function create_database($name)
function can_create_database()
function can_grant_privileges()
```

**User Management:**
```php
function create_user($username, $host)
function alter_user($username, $host, $password)
function grant_privileges($name, $username, $password, $host)
function revoke($name, $username, $host)
```

**Import/Export:**
```php
function import_dump($dump_file, $creds)  // mysql < dump
function generate_dump()  // mysqldump with filters
```

**Security Features:**
- Credentials passed via file descriptor 3
- Never exposed in command line
- Safe shell execution wrapper

**Dump Filtering:**
- Remove DEFINER entries
- Remove broken CREATE ALGORITHM entries
- Extensible via `hook_provision_mysql_regex_alter()`

---


## Configuration Generation

### Architecture

Configuration generation uses a template-based system with PHP templates.

### Base Class: `Provision_Config`

Located in `Provision/Config.php`

**Key Properties:**
```php
public $template = NULL;  // Template filename
public $data = array();  // Variables for template
public $context = NULL;  // Provision_Context object
public $description = NULL;  // Description for logs
protected $mode = NULL;  // File permissions (octal)
protected $group = NULL;  // File group
protected $data_store_class = NULL;  // Optional data store
public $store = NULL;  // Data store instance
```

**Key Methods:**
```php
function __construct($context, $data = array())
function process()  // Prepare data before rendering
function filename()  // Return output filename
function write()  // Generate and write config
function unlink()  // Delete config file
```

**Write Process:**
1. Create parent directory if needed
2. Call `process()` to prepare data
3. Load template via `load_template()`
4. Render template with `render_template($template, $data)`
5. Write to file
6. Set permissions and group

### Template Rendering

**Method:** `render_template($template, $variables)`

**Process:**
```php
extract($variables, EXTR_SKIP);
ob_start();
eval('?>' . $template);
$contents = ob_get_contents();
ob_end_clean();
return $contents;
```

**Variables Available:**
- `$this`: The config object
- All properties from `$data` array
- Access to `$this->context` (the Provision_Context)

### Configuration Types

#### Drushrc Configurations

**Base:** `Provision_Config_Drushrc`

**Classes:**
- `Provision_Config_Drushrc_Alias`: Drush alias files
- `Provision_Config_Drushrc_Site`: Site drushrc.php
- `Provision_Config_Drushrc_Platform`: Platform drushrc.php
- `Provision_Config_Drushrc_Server`: Server drushrc.php
- `Provision_Config_Drushrc_Aegir`: Global aegir drushrc.php

**Location:** `~/.drush/*.alias.drushrc.php` or `{site_path}/drushrc.php`

**Template:** `provision_drushrc.tpl.php`

#### Drupal Settings

**Class:** `Provision_Config_Drupal_Settings`

**Location:** `{site_path}/settings.php`

**Templates:**
- `provision_drupal_settings_6.tpl.php` (Drupal 6)
- `provision_drupal_settings_7.tpl.php` (Drupal 7)
- `provision_drupal_settings_8.tpl.php` (Drupal 8)
- `provision_drupal_settings_9.tpl.php` (Drupal 9)

**Generated Content:**
- Database credentials
- File paths
- Extra configuration from hooks
- Include for global.inc

**Security:**
- Mode: 0440 (read-only)
- Group: web_group

#### Apache Configurations

**Classes:**
- `Provision_Config_Apache_Server`: Main apache.conf
- `Provision_Config_Apache_Platform`: Platform config
- `Provision_Config_Apache_Site`: Site vhost
- `Provision_Config_Apache_Site_Disabled`: Disabled site redirect

**Templates:**
- `server.tpl.php`
- `platform.tpl.php`
- `vhost.tpl.php`
- `vhost_disabled.tpl.php`

**SSL Classes:** Separate classes for SSL vhosts

**Location:** `~/config/server_name/apache/`

**Structure:**
```
~/config/
  server_master/
    apache.conf  (symlinked from aegir_root/config/)
    apache/
      pre.d/  (included before vhosts)
      platform.d/  (platform configs)
      vhost.d/  (site vhosts)
      post.d/  (included after vhosts)
```

#### Nginx Configurations

**Classes:**
- `Provision_Config_Nginx_Server`
- `Provision_Config_Nginx_Platform`
- `Provision_Config_Nginx_Site`

**Templates:**
- `server.tpl.php`
- `vhost.tpl.php`
- `vhost_disabled.tpl.php`

**Similar structure to Apache**

#### Data Stores

**Purpose:** Persist and merge records across config generations

**Base:** `Provision_Config_Data_Store`

**Example:** `Provision_Config_Drupal_Alias_Store`

**Usage:** Store site aliases in sites.php

**Template:** `data_store.tpl.php`

**Methods:**
```php
function loaded_records  // Records from existing file
function records  // New records to write
function merged_records()  // Combination of both
```

### Config File Hooks

Files ending in `.provision.inc` in service directories provide hooks for specific commands on specific services.

**Example:** `http/install.provision.inc`
```php
function drush_provision_apache_pre_provision_install() {
  d()->service('http')->create_config('site');
}
```

---


## Hook System

### Overview

Aegir Provision uses Drush's hook system extensively for extensibility.

### Command Hooks

For each provision command, hooks are invoked in this order:

1. **validate**: `drush_HOOK_COMMAND_validate()`
2. **pre**: `drush_HOOK_pre_COMMAND()`
3. **command**: `drush_HOOK_COMMAND()`
4. **post**: `drush_HOOK_post_COMMAND()`
5. **rollback** (on error): `drush_HOOK_COMMAND_rollback()` or `drush_HOOK_pre_COMMAND_rollback()`

### Hook Locations

Hooks are defined in:
- **provision.drush.inc**: Main command implementations
- **platform/*.provision.inc**: Platform-level hooks
- **db/*.provision.inc**: Database service hooks
- **http/*.provision.inc**: HTTP service hooks

### Service Hooks

Services extend command hooks with service-specific implementations:

**Format:** `{service}/*.provision.inc`

**Example:** `db/backup.provision.inc`
```php
function drush_provision_mysql_provision_backup() {
  // Generate mysqldump
}
```

### Registering Services

**Hook:** `hook_provision_services()`

**Example:**
```php
function mymodule_provision_services() {
  return array('myservice' => 'default_implementation');
}
```

### Configuration Hooks

#### Template Loading

**Hook:** `hook_provision_config_load_templates($config)`

**Purpose:** Provide alternative template file

**Example:**
```php
function mymodule_provision_config_load_templates($config) {
  if (is_a($config, 'Provision_Config_Apache_Site')) {
    return '/path/to/custom/vhost.tpl.php';
  }
}
```

**Alter Hook:** `hook_provision_config_load_templates_alter(&$templates, $config)`

#### Variable Alteration

**Hook:** `hook_provision_config_variables_alter(&$variables, $template, $config)`

**Purpose:** Modify variables before template rendering

#### Apache/Nginx Hooks

**Hooks:**
- `drush_HOOK_provision_apache_vhost_config($uri, $data)`
- `drush_HOOK_provision_apache_dir_config($data)`
- `drush_HOOK_provision_apache_server_config($data)`
- `drush_HOOK_provision_nginx_vhost_config($uri, $data)`
- etc.

**Purpose:** Inject additional configuration into generated files

**Example:**
```php
function mymodule_provision_apache_vhost_config($uri, $data) {
  return "  # Custom configuration\n  Header set X-Custom-Header 'value'";
}
```

#### Settings.php Hook

**Hook:** `hook_provision_drupal_config($uri, $data)`

**Purpose:** Add PHP code to settings.php

**Example:**
```php
function mymodule_provision_drupal_config($uri, $data) {
  return "\$conf['mymodule_setting'] = 'value';";
}
```

### Context Hooks

**Hook:** `hook_provision_context_alter(&$context)`

**Purpose:** Modify or replace context object after loading

**Example:**
```php
function mymodule_provision_context_alter(&$context) {
  if ($context->type == 'site') {
    $context->my_custom_property = 'value';
  }
}
```

### Drupal Hooks

Standard Drupal bootstrap hooks are available after `drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL)`:

- `hook_install()`
- `hook_update_N()`
- `hook_enable()`
- `hook_disable()`
- etc.

---



## Backend & Frontend Integration

### Frontend (Hostmaster) Integration

Provision is designed to be driven by the Hostmaster frontend (Drupal). The integration is purely Drush-based:

- **Hostmaster install/migrate/uninstall** are provided as Drush commands and wire up the core contexts:
  - `@server_master` (server)
  - `@platform_hostmaster` (platform)
  - `@hostmaster` (site)
  - Files: `install.hostmaster.inc`, `migrate.hostmaster.inc`, `uninstall.hostmaster.inc`

- **Backend invocation** uses `provision_backend_invoke()` in `provision.inc`, which calls `drush_invoke_process()` with `integrate => TRUE`. This is how Hostmaster tasks enqueue and execute backend work across contexts.

- **Backend output parsing** is provided via `parse.backend.inc`, which integrates Drush backend output into logs/UI.

In practice, the frontend writes/updates context aliases and then dispatches provisioning tasks (verify/install/migrate/clone/backup/etc.) against those contexts.

**Code references:**
- Hostmaster install flow: `install.hostmaster.inc` (`drush_provision_hostmaster_install_validate()`, `drush_provision_hostmaster_install()`)
- Hostmaster migration: `migrate.hostmaster.inc` (`drush_provision_hostmaster_migrate_validate()`, `drush_provision_hostmaster_migrate()`)
- Hostmaster uninstall: `uninstall.hostmaster.inc` (`drush_provision_hostmaster_uninstall_validate()`, `drush_provision_hostmaster_uninstall()`)
- Backend invocation glue: `provision.inc` (`provision_backend_invoke()`)
- Backend output parsing: `parse.backend.inc` (`drush_provision_backend_parse()`)


## Backup & Restore

### Backup Process

**Command:** `provision-backup [backup-file]`

**Implementation File:** `platform/backup.provision.inc`

#### Validation Phase

**Function:** `drush_provision_drupal_provision_backup_validate($backup_file = NULL)`

**Checks:**
1. Site is installed (unless `--force`)
2. Backup directory exists
3. Backup file doesn't already exist
4. Suggests filename if not provided

**Filename Format:** `{uri}-{YYYYMMDD.HHMMSS}.tar.gz`

#### Backup Phase

**Function:** `drush_provision_drupal_provision_backup()`

**Process:**

1. **Fetch Remote Files**: `provision_drupal_fetch_site()`
   - Rsync files from remote server if applicable

2. **Database Credential Cloaking**:
   ```php
   if ($cloaked) {
     drush_set_option('provision_db_cloaking', FALSE);
     _provision_drupal_create_settings_file();
     provision_drupal_push_site();
   }
   ```

3. **Database Dump**: `d()->service('db')->generate_dump()`
   - Creates `{site_path}/database.sql`
   - MySQL: Uses mysqldump with filters
   - Removes DEFINER entries
   - UTF8MB4 aware

4. **Create Exclusion Tags**:
   ```php
   foreach (PROVISION_BACKUP_EXCLUDED_DIRECTORIES as $dir) {
     touch("{$dir}/exclude.tag");
   }
   ```
   - Excludes: `./files/css`, `./files/js`, `./private/temp`

5. **Create Tarball**:
   ```php
   tar cpfz backup.tar.gz --exclude-tag=exclude.tag .
   ```
   - Runs from site directory
   - Includes entire sites/example.com/

6. **Re-cloak Credentials** (if applicable)

7. **Record Backup Size**: `drush_set_option('backup_file_size', $size)`

#### Post-Backup Phase

**Function:** `drush_provision_drupal_post_provision_backup()`

**Process:**
1. Log success message
2. Create symlink in client backup directory
   - Location: `~/clients/{client_name}/backups/`
   - Allows client-based organization

#### Rollback Phase

**Function:** `drush_provision_drupal_provision_backup_rollback()`

**Process:**
- Delete backup file if creation failed

### Backup Contents

A typical backup tarball contains:

```
sites/example.com/
  database.sql         # Database dump
  drushrc.php         # Site configuration
  settings.php        # Drupal settings
  files/              # Uploaded files
    ...
  private/            # Private files (if configured)
    files/
    temp/
    config/
  modules/            # Custom modules (if any)
  themes/             # Custom themes (if any)
```

### Restore Process

**Command:** `provision-restore site_backup.tar.gz`

**Implementation:** Uses `provision-deploy` internally

**Process:**
1. Extract backup to site directory
2. Import database from database.sql
3. Update settings.php with current credentials
4. Verify site

### Deploy Process

**Command:** `provision-deploy site_backup.tar.gz`

**Implementation File:** `platform/deploy.provision.inc`

#### Validation Phase

**Function:** `drush_provision_drupal_provision_deploy_validate($backup_file = NULL)`

**Checks:**
1. Backup file exists
2. Determine if replacing existing site
3. Set extract path:
   - New site: `{site_path}`
   - Replace: `{site_path}.restore` (temporary)

#### Pre-Deploy Phase

**Function:** `drush_provision_drupal_pre_provision_deploy($backup_file)`

**Process:**

1. **Extract Backup**:
   ```php
   provision_file()->extract($backup_file, $extract_path)
   ```

2. **Fix Permissions**:
   - `chgrp` files to web_group
   - Applies to: files/, private/files/, private/config/, private/temp/

3. **Directory Swap** (if replacing):
   ```php
   provision_file()->switch_paths($old, $new)
   ```
   - Swaps `{site}.restore` with `{site}` atomically

4. **Update Configuration**:
   - `provision_prepare_environment()`
   - `provision_save_site_data()`
   - Bootstrap to site level
   - Generate new settings.php with current DB creds

5. **Validate Modules**:
   - Compare site's module schema versions
   - Compare with platform's module versions
   - Fail if platform modules are older

#### Deploy Phase

**Function:** `drush_provision_drupal_provision_deploy()`

**Process:**
1. Maintain aliases
2. Create necessary directories

#### Post-Deploy Phase

**Function:** `drush_provision_drupal_post_provision_deploy()`

**Process:**

1. **Registry Rebuild** (Drupal 6-7):
   ```php
   provision_backend_invoke(d()->name, 'registry-rebuild --no-cache-clear');
   ```

2. **Update Database**:
   ```php
   provision_backend_invoke(d()->name, 'updatedb');
   ```

3. **Registry Rebuild Again** (with cache clear)

4. **Bootstrap Full Drupal**

5. **Update Packages**:
   ```php
   drush_set_option('packages', provision_drupal_system_map());
   ```

6. **Rebuild Caches**:
   ```php
   _provision_drupal_rebuild_caches();
   ```

7. **Rebuild Node Access**:
   ```php
   if (node_access_needs_rebuild()) {
     node_access_rebuild();
   }
   ```

8. **Cleanup**:
   - Remove `.restore` directory
   - Drop old database (if replacing)

#### Rollback Phase

**Function:** `drush_provision_drupal_pre_provision_deploy_rollback()`

**Process:**
1. Swap directories back
2. Restore settings.php
3. Delete extracted files

---


## Migration & Clone

### Migration Process

**Command:** `provision-migrate @platform_name [new_name]`

**Purpose:** Move site to different platform (Drupal upgrade, platform change)

**Implementation File:** `platform/migrate.provision.inc`

#### Validation Phase

**Function:** `drush_provision_drupal_provision_migrate_validate($platform = NULL)`

**Process:**
- Bootstrap to site level
- Verify site is accessible

#### Pre-Migrate Phase

**Function:** `drush_provision_drupal_pre_provision_migrate($platform, $new_name = NULL)`

**Process:**

1. **Enable Maintenance Mode**:
   ```php
   d()->site_enabled = FALSE;
   _provision_drupal_create_settings_file();
   ```

2. **Create Backup**:
   ```php
   drush_invoke('provision-backup');
   ```

3. **Store Old Platform**:
   ```php
   drush_set_option('old_platform', d()->platform->name);
   ```

4. **Handle Rename** (if new_name provided):
   - Set target_name and source_name
   - Rewrite aliases for new domain
   - Handle www/non-www variations

5. **Verify Platforms** (unless Hostmaster):
   ```php
   provision_backend_invoke('@hostmaster', 'hosting-task', 
     array(d()->platform->name, 'verify'));
   provision_backend_invoke('@hostmaster', 'hosting-task', 
     array($platform, 'verify'));
   ```

#### Migrate Phase

**Function:** `drush_provision_drupal_provision_migrate($platform, $new_name = NULL)`

**Process:**

1. **Build New Options Array**:
   ```php
   $options = d()->options;
   $options['platform'] = $platform;
   $options['root'] = d($platform)->root;
   $options['uri'] = $new_name ?: d()->uri;
   ```

2. **Update Aliases** (if renaming):
   - Rewrite all aliases
   - Detect aliases automatically if needed

3. **Save New Context**:
   ```php
   drush_invoke_process('@none', 'provision-save', 
     array($target), $options);
   ```

4. **Deploy Backup**:
   ```php
   $deploy_options = array(
     'old_uri' => d()->uri,
     'strict' => 0,
   );
   provision_backend_invoke($target, 'provision-deploy', 
     array(drush_get_option('backup_file')), $deploy_options);
   ```

5. **Verify New Site**:
   ```php
   d()->site_enabled = TRUE;
   provision_backend_invoke($target, 'provision-verify');
   ```

#### Post-Migrate Phase

**Function:** `drush_provision_drupal_post_provision_migrate($platform, $new_name = NULL)`

**Process:**

1. **Cleanup Old Site**:
   - Delete aliases
   - Remove site directory
   - Remove vhost config
   - Remove client symlink

2. **Handle Rename Cleanup** (if applicable):
   - Remove old alias
   - Delete old site directory
   - Sync to remote server

3. **Reload New Config**:
   ```php
   provision_reload_config('site', 
     drush_get_option('new_site_path') . '/drushrc.php');
   ```

#### Rollback Phase

**Function:** `drush_provision_drupal_pre_provision_migrate_rollback($platform, $new_name = NULL)`

**Process:**
1. Disable maintenance mode
2. Restore original vhost
3. Remove unused backup

**Function:** `drush_provision_drupal_provision_migrate_rollback($platform)`

**Process:**
- Restore original platform in context

### Clone Process

**Command:** `provision-clone @new_site @platform_name`

**Purpose:** Duplicate site to new domain/platform

**Implementation File:** `platform/clone.provision.inc`

#### Validation Phase

**Function:** `drush_provision_drupal_provision_clone_validate($new_name = null, $platform = null)`

**Process:**
- Bootstrap to site level

#### Pre-Clone Phase

**Function:** `drush_provision_drupal_pre_provision_clone($new_name, $platform = null)`

**Process:**
1. Create backup of source site
2. Store backup path for deploy

#### Clone Phase

Uses the deploy mechanism with the new site name and platform.

**Implementation:**
1. Backup source site
2. Create new context with new name
3. Deploy backup to new context
4. Both sites exist independently

#### Rollback Phase

**Function:** `drush_provision_drupal_pre_provision_clone_rollback($new_name, $platform = null)`

**Process:**
- Remove unused backup file

### Key Differences: Migrate vs Clone

| Aspect | Migrate | Clone |
|--------|---------|-------|
| **Original Site** | Removed after migration | Remains unchanged |
| **Purpose** | Move/upgrade site | Duplicate site |
| **Downtime** | Yes (maintenance mode) | No (on source) |
| **Backup Cleanup** | Yes | Yes |
| **Database** | Moved to new name | Copied to new name |

### Migration Use Cases

1. **Drupal Upgrade**: D7 → D8 → D9
2. **Platform Change**: Move to newer platform
3. **Server Change**: Move to different server
4. **Domain Rename**: Change primary domain
5. **Combined**: Any combination of above

### Clone Use Cases

1. **Development Copy**: Production → Dev
2. **Staging**: Production → Staging
3. **Testing**: Before upgrades
4. **Multi-site**: Create similar sites

---



## SSL/TLS Support

Implemented in `http/Provision/Service/http/ssl.php` and extended by Apache/Nginx SSL services.

### Capabilities

- **Per-server SSL store**: `${aegir_root}/config/ssl.d` and per-server `http_ssld_path`.
- **Self-signed certificates**: Generated on-demand using `openssl` key/cert generation.
- **Certificate chains**: Supports `openssl_chain.crt` when provided.
- **Per-site SSL settings**: `ssl_enabled`, `ssl_key`, and optional IP assignments.
- **HTTP→HTTPS redirection**: `ssl_enabled == 2` enables automatic redirection.
- **LetsEncrypt integration**: Respects hosting_le control files to avoid overwriting managed certs.

### Code References

- SSL base service: `http/Provision/Service/http/ssl.php` (`init_server()`, `get_certificates()`, `generate_certificates()`)
- Certificate assignment: `http/Provision/Service/http/ssl.php` (`assign_certificate_site()`, `free_certificate_site()`)
- SSL webserver services: `http/Provision/Service/http/apache/ssl.php`, `http/Provision/Service/http/nginx/ssl.php`



## Platform/Drupal Engine

The platform engine in `platform/provision_drupal.drush.inc` and `platform/*.provision.inc` implements Drupal-specific operations.

### Supported Drupal Versions

- Drupal 6, 7, 8, 9, 10 (version-specific helpers in `platform/drupal/*.inc`)

### Key Behaviors

- Verifies Drupal root, pushes site data, and (optionally) runs `composer install` during platform verify if `provision_composer_install_platforms` is enabled.
- Generates/updates site settings and drushrc files via config templates under `Provision/Config/Drupal` and `Provision/Config/Drushrc`.
- Runs version-specific install/import/verify workflows fully bootstrapped to the target Drupal site.

### Code References

- Platform task wiring: `platform/provision_drupal.drush.inc` (`provision_drupal_drush_exit()`, `provision_drupal_push_site()`)
- Verify task: `platform/verify.provision.inc` (`drush_provision_drupal_pre_provision_verify()`)
- Install task: `platform/install.provision.inc` (`drush_provision_drupal_provision_install_backend()`)
- Deploy/clone/migrate tasks: `platform/deploy.provision.inc`, `platform/clone.provision.inc`, `platform/migrate.provision.inc`
- Version shims: `platform/drupal/install_6.inc`, `platform/drupal/install_7.inc`, `platform/drupal/install_8.inc`, `platform/drupal/install_9.inc`, `platform/drupal/install_10.inc`
- Drupal helpers: `platform/drupal/verify.inc`, `platform/drupal/import_*.inc`, `platform/drupal/deploy_*.inc`

### Notable Operational Flows

#### Hostmaster Install (Simplified)

1. Create `@server_master` and optional DB server contexts.
2. Create `@platform_hostmaster` pointing at the hostmaster codebase.
3. Create `@hostmaster` site context and run `provision-install` + `provision-verify`.
4. Run `hosting-setup` to finalize frontend integration.

#### Site Provisioning

1. `provision-save` updates context alias data.
2. `provision-verify` generates webserver/db config and validates runtime.
3. `provision-install` bootstraps the site (profile install or empty DB).
4. Optional `provision-deploy`/`provision-clone` flows use backups to move sites.


## Key Files

### Core Files

| File | Purpose |
|------|---------|
| **provision.drush.inc** | Main command definitions |
| **provision.inc** | Core API functions, autoloader |
| **provision.context.inc** | Context system and d() function |
| **provision.service.inc** | Service loading (now just autoloader) |
| **provision.file.inc** | File operation wrapper |
| **provision.api.php** | API documentation and hooks |

### Context Files

| File | Purpose |
|------|---------|
| **Provision/Context.php** | Base context class |
| **Provision/Context/server.php** | Server context |
| **Provision/Context/platform.php** | Platform context |
| **Provision/Context/site.php** | Site context |

### Service Files

| File | Purpose |
|------|---------|
| **Provision/Service.php** | Base service class |
| **http/Provision/Service/http.php** | HTTP service base |
| **http/Provision/Service/http/apache.php** | Apache implementation |
| **http/Provision/Service/http/nginx.php** | Nginx implementation |
| **db/Provision/Service/db.php** | Database service base |
| **db/Provision/Service/db/mysql.php** | MySQL implementation |

### Config Files

| File | Purpose |
|------|---------|
| **Provision/Config.php** | Base config class |
| **Provision/Config/Drushrc.php** | Drushrc configs |
| **Provision/Config/Drupal/Settings.php** | settings.php generator |
| **http/Provision/Config/Apache/*** | Apache config classes |
| **http/Provision/Config/Nginx/*** | Nginx config classes |

### Hook Implementation Files (*.provision.inc)

| Directory | Files | Purpose |
|-----------|-------|---------|
| **platform/** | backup, clone, delete, deploy, install, migrate, restore, verify | Platform-level operations |
| **http/** | backup, clone, delete, deploy, disable, enable, install, migrate, restore | HTTP service operations |
| **db/** | backup, clone, delete, deploy, install, migrate, restore | Database service operations |

### Template Files (*.tpl.php)

| Location | Files | Purpose |
|----------|-------|---------|
| **Provision/Config/Drushrc/** | provision_drushrc_*.tpl.php | Drushrc templates |
| **Provision/Config/Drupal/** | provision_drupal_settings_*.tpl.php | settings.php templates (by Drupal version) |
| **http/Provision/Config/Apache/** | server.tpl.php, platform.tpl.php, vhost.tpl.php, etc. | Apache config templates |
| **http/Provision/Config/Nginx/** | server.tpl.php, vhost.tpl.php, etc. | Nginx config templates |

### Installer Files

| File | Purpose |
|------|---------|
| **install.hostmaster.inc** | Hostmaster installation |
| **migrate.hostmaster.inc** | Hostmaster migration |
| **uninstall.hostmaster.inc** | Hostmaster uninstallation |

### Support Files

| File | Purpose |
|------|---------|
| **Provision/FileSystem.php** | File operation wrappers |
| **Provision/ChainedState.php** | State management helper |
| **parse.backend.inc** | Backend output parser |
| **example.drushrc.php** | Example configuration |
| **example.sudoers** | Example sudo configuration |

---


## Refactoring Considerations


### 1. Command Flow

Every command follows this pattern:
1. **Validate**: Check preconditions
2. **Pre**: Setup and preparation
3. **Execute**: Main operation
4. **Post**: Cleanup and finalization
5. **Rollback**: Error recovery

### 2. Context Inheritance

Services and configuration inherit through the context chain:
- **Site** → **Platform** → **Server**
- Sites inherit services from platform
- Platforms inherit services from server
- Service subscriptions can override inheritance

### 3. Service Subscriptions

Sites can subscribe to services on different servers:
```php
$site->service_subscribe('db', '@db_server_cluster');
$site->service_subscribe('http', '@web_server_cdn');
```

### 4. Configuration Generation

All configuration uses template-based generation:
1. Load template file
2. Process data
3. Render with PHP eval
4. Write to file
5. Set permissions

### 5. Backend Invocation

Cross-context operations use backend invocation:
```php
provision_backend_invoke($target, $command, $arguments, $options);
```

This allows operations on remote servers via SSH.

### 6. File Operations

All file operations use `provision_file()` wrapper:
```php
provision_file()->exists($path)->status();
provision_file()->chmod($path, $mode)
  ->succeed('Success message')
  ->fail('Error message', 'ERROR_CODE');
```

### 7. Property Storage

Context properties are:
- **Persistent**: Saved to drushrc.php files
- **Computed**: Generated on-the-fly
- **Inherited**: From parent contexts
- **Overridable**: Via command options

### 8. Security Model

- Database credentials passed via file descriptors
- Settings.php is mode 0440
- Files directory is web_group
- Sudo used for privileged operations
- SSH keys for remote access

### 9. Extensibility

Everything is extensible via:
- Service plugins
- Hook implementations
- Template overrides
- Context alterations
- Configuration alterations

### 10. State Management

State is maintained in:
- Drush alias files (~/.drush/)
- Site drushrc.php files
- Drush options context
- Server filesystem (config files, databases)

---

## Refactoring Considerations for Drush 13.7+

### Breaking Changes in Drush 13

1. **No more --backend mode**: Need new inter-process communication
2. **New command structure**: Command classes instead of hook functions
3. **Symfony Console**: New command framework
4. **Composer-first**: Full composer integration required
5. **PHP 8.1+**: Can use modern PHP features

### Components to Preserve

1. **Context System**: Core abstraction is sound
2. **Service Architecture**: Pluggable services work well
3. **Template System**: Configuration generation is flexible
4. **Hook System**: Extensibility is crucial

### Components to Refactor

1. **Command Definitions**: Move to command classes
2. **Backend Invocation**: Replace with modern IPC (gRPC, REST, or process)
3. **Bootstrap**: Adapt to Drush 13 bootstrap
4. **File Operations**: Consider using Symfony Filesystem
5. **Validation**: Use command argument validation
6. **Output**: Use Symfony OutputInterface

### New Opportunities

1. **Dependency Injection**: Use Symfony DI container
2. **Testing**: Better unit test support
3. **Async Operations**: Modern async patterns
4. **Type Safety**: Full type hints
5. **Modern PHP**: Attributes, enums, readonly properties

---

**End of Analysis Report**

This report documents the complete architecture of the Drupal 7 / Drush 8 version of Aegir Provision. Use this as a reference for understanding the legacy implementation and planning the refactoring to Drush 13.7+.

