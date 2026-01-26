# Aegir Provision - AI Coding Agent Instructions

## Repository Overview

**Aegir Provision** is a Drush 13 extension that provides the backend automation layer for Aegir Hosting. It manages hosting infrastructure through a context-based system where Drush commands automate web hosting tasks (site install, migrate, backup, restore, SSL configuration).

**Technology Stack**: PHP 8.3+, Drush 13, Apache 2.4+ with PHP-FPM, MySQL 8.0+, Ubuntu 24.04 LTS

**Architecture**: Context-driven orchestration system with service abstractions for HTTP, database, and file operations.

## Directory Structure

```
aegir-provision/
├── src/
│   ├── Commands/             # Drush 13 command definitions
│   │   └── ProvisionCommands.php  # provision-* commands
│   ├── Core/                 # Core abstractions
│   │   ├── Context.php       # Immutable context data structure
│   │   ├── ContextRepository.php  # Load/save via Drush AliasStore
│   │   ├── ContextType.php   # Server, Platform, Site types
│   │   ├── Filesystem.php    # File/directory operations with permissions
│   │   └── ConfigPaths.php   # Path resolution for config storage
│   ├── Provision/            # Orchestration layer
│   │   └── ProvisionManager.php  # Central task orchestrator
│   ├── Service/              # Service implementations
│   │   ├── Http/
│   │   │   ├── ApacheService.php     # Apache vhost generation
│   │   │   └── PhpFpmService.php     # PHP-FPM pool management
│   │   ├── Db/
│   │   │   └── MySqlService.php      # MySQL database/user management
│   │   ├── Ssl/
│   │   │   └── SslManager.php        # SSL certificate management
│   │   └── Drupal/
│   │       └── SettingsWriter.php    # settings.php generation
│   └── Config/
│       ├── TemplateRenderer.php  # Template engine for configs
│       └── templates/            # Config templates (vhosts, settings)
├── drush.services.yml        # Service definitions for Drush DI
└── composer.json             # Package metadata

**Key Principle**: This is backend infrastructure automation - all operations must be idempotent and safe for remote execution.
```

## Context System

### Core Concept

Provision operates on **three immutable context types** stored as Drush YAML aliases in `~/.drush/sites/`:

1. **Server Context** (`@server_master`)
   - Infrastructure node with services (http/db)
   - Paths: `aegir_root`, `config_path`, `backup_path`, `platforms_path`
   - Services: `http_service_type`, `db_service_type`
   - Web server: `web_group` (www-data), `script_user` (aegir)

2. **Platform Context** (`@platform.local`)
   - Drupal codebase directory
   - Path: `root` (absolute path to Drupal docroot)
   - References: `server` (parent server context name)
   - Packages: Drupal core version, contrib modules

3. **Site Context** (`@example.com`)
   - Individual Drupal site
   - Identity: `uri` (domain name)
   - References: `platform`, `db_server`
   - Database: `db_name`, `db_user`, `db_passwd`, `db_host`, `db_port`
   - SSL: `ssl_enabled`, `ssl_redirect`, `ssl_cert_path`, `ssl_key_path`

### Context Files

**Location**: `~/.drush/sites/{context_name}.site.yml`

**Example Site Context**:
```yaml
# ~/.drush/sites/example.com.site.yml
type: site
uri: example.com
platform: platform_d11
db_server: server_master
db_name: example_com
db_user: example_com_user
db_passwd: generated_password
db_host: localhost
db_port: 3306
ssl_enabled: true
ssl_redirect: true
root: /var/aegir/platforms/drupal-11/web
```

### Context API

**File**: [src/Core/Context.php](src/Core/Context.php)

```php
// Immutable value object with typed property access
class Context implements ContextInterface {
  public function __construct(
    private string $name,
    private ContextType $type,
    private array $properties
  ) {}
  
  public function get(string $key, mixed $default = null): mixed;
  public function set(string $key, mixed $value): self;  // Returns new instance
  public function has(string $key): bool;
  public function getName(): string;
  public function getType(): ContextType;
}
```

**File**: [src/Core/ContextRepository.php](src/Core/ContextRepository.php)

```php
// Load/save contexts via Drush AliasStore
class ContextRepository {
  public function load(string $name): ?Context;
  public function save(Context $context): void;
  public function delete(string $name): void;
  public function findByType(ContextType $type): array;
}
```

**Current State**:
- ✅ Context types defined (Server, Platform, Site)
- ✅ YAML alias storage via Drush
- ✅ Immutable context objects
- ⚠️ No schema validation on context properties
- ⚠️ No type hints for context property values
- ❌ No context migrations or versioning

**Future Goals**:
- Implement JSON Schema validation for context properties
- Add strict type definitions for context values
- Create context diff/merge utilities for distributed systems
- Support context inheritance (site inherits from platform/server)

## Provision Commands

### Command Surface

**File**: [src/Commands/ProvisionCommands.php](src/Commands/ProvisionCommands.php)

All commands use `drush provision-*` naming and accept context references as arguments:

```bash
# Context management
drush provision-save <context> --type=<server|platform|site> --data='{"key":"value"}'
drush provision-delete <context>

# Site lifecycle
drush provision-install @example.com      # Install Drupal site
drush provision-verify @context           # Regenerate configs, verify state
drush provision-migrate @site @new_platform
drush provision-backup @site              # Database + files
drush provision-restore @site <backup.tar.gz>
drush provision-clone @site @new_site @platform
drush provision-delete @site

# Platform management
drush provision-verify @platform          # Scan packages, regenerate configs

# Server management
drush provision-verify @server            # Regenerate all server configs
```

### Command Architecture

Commands delegate to **ProvisionManager** for orchestration:

```php
#[CLI\Command(name: 'provision:install')]
#[CLI\Argument(name: 'site', description: 'Site context name')]
class ProvisionCommands extends DrushCommands {
  
  public function __construct(
    private ProvisionManager $manager,
    private ContextRepository $contexts
  ) {}
  
  public function install(string $site): void {
    $context = $this->contexts->load($site);
    $this->manager->install($context);
  }
}
```

**Current State**:
- ✅ Core commands implemented (install, verify, migrate, backup, restore, clone)
- ✅ Context-based argument handling
- ✅ Output formatting via Drush logger
- ⚠️ Limited error recovery (no transaction rollback)
- ⚠️ No dry-run mode for destructive operations
- ❌ No progress indicators for long-running tasks
- ❌ No command hooks for extensions

**Future Goals**:
- Add `--dry-run` flag for all destructive commands
- Implement transaction-like rollback for failed operations
- Add progress bars for backup/restore/migrate operations
- Create command plugin system for custom operations
- Support remote execution via SSH for distributed servers
- Add `provision-status` command for health checks

## Service Architecture

### Service Abstraction

Services handle infrastructure resources with clear contracts:

**Base Interface** (should exist but may be incomplete):
```php
interface ServiceInterface {
  public function verify(Context $context): void;
  public function install(Context $context): void;
  public function configure(Context $context): void;
  public function remove(Context $context): void;
}
```

### Apache HTTP Service

**File**: [src/Service/Http/ApacheService.php](src/Service/Http/ApacheService.php)

**Responsibilities**:
- Generate Apache vhost configurations
- Manage SSL certificates via SslManager
- Enable/disable vhosts via `a2ensite`/`a2dissite`
- Reload Apache service

**Config Output Paths** (relative to `server.config_path`):
```
{config_path}/apache/
├── vhost.d/            # HTTP vhosts (port 80)
│   ├── example.com.conf
│   └── platform_d11.conf
├── vhost_ssl.d/        # HTTPS vhosts (port 443)
│   └── example.com.conf
└── platform.d/         # Platform-level configs
    └── platform_d11.conf
```

**Vhost Template Variables**:
```php
[
  'uri' => $context->get('uri'),
  'root' => $context->get('root'),
  'server_name' => $context->get('uri'),
  'server_alias' => $context->get('aliases', []),
  'ssl_cert' => $context->get('ssl_cert_path'),
  'ssl_key' => $context->get('ssl_key_path'),
  'php_fpm_socket' => "unix:/run/php/php8.3-fpm-{$site_name}.sock",
]
```

**Current State**:
- ✅ Vhost generation from templates
- ✅ SSL certificate integration
- ✅ Platform-level includes
- ⚠️ Hardcoded Apache reload command
- ⚠️ No validation of generated configs (`apache2ctl -t`)
- ❌ No support for custom vhost directives
- ❌ No HTTP/2 or HTTP/3 configuration
- ❌ No Nginx service implementation

**Future Goals**:
- Add config validation before reload
- Support custom vhost directives via context properties
- Implement PHP-FPM pool per-site configuration
- Add Nginx service as alternative to Apache
- Support HTTP/3 (QUIC) configuration
- Implement zero-downtime reloads (graceful restart)

### MySQL Database Service

**File**: [src/Service/Db/MySqlService.php](src/Service/Db/MySqlService.php)

**Responsibilities**:
- Create/drop databases
- Create/drop users with proper grants
- Generate secure passwords
- Dump/restore databases
- Test database connectivity

**Operations**:
```php
// Create database and user
public function createDatabase(Context $site): void;
public function createUser(Context $site): void;
public function grantPrivileges(Context $site): void;

// Backup/restore
public function dump(Context $site, string $output_file): void;
public function restore(Context $site, string $input_file): void;

// Cleanup
public function dropDatabase(Context $site): void;
public function dropUser(Context $site): void;
```

**MySQL CLI Integration**:
```php
// Uses environment variables for credentials
private function mysqlEnv(Context $server): array {
  return [
    'MYSQL_HOST' => $server->get('db_host', 'localhost'),
    'MYSQL_PORT' => $server->get('db_port', 3306),
    'MYSQL_USER' => $server->get('db_root_user', 'root'),
    'MYSQL_PWD' => $server->get('db_root_password'),
  ];
}
```

**Current State**:
- ✅ Database/user creation with grants
- ✅ Dump/restore via mysqldump
- ✅ Password generation
- ⚠️ No SSL/TLS support for database connections
- ⚠️ No connection pooling or optimization
- ❌ No PostgreSQL service implementation
- ❌ No replication/cluster support
- ❌ No backup compression options

**Future Goals**:
- Add SSL/TLS support for MySQL connections
- Implement PostgreSQL service
- Add backup compression (gzip, zstd)
- Support MySQL 8.0+ authentication plugins
- Add database cluster/replication support
- Implement incremental backups
- Add database size monitoring and quotas

### Drupal Settings Service

**File**: [src/Service/Drupal/SettingsWriter.php](src/Service/Drupal/SettingsWriter.php)

**Responsibilities**:
- Generate `settings.php` from template
- Inject database credentials
- Configure file paths (public, private, temp)
- Set trusted host patterns
- Add Aegir-specific settings

**Template Output**:
```php
// Generated settings.php structure
$databases['default']['default'] = [
  'driver' => 'mysql',
  'database' => $site->get('db_name'),
  'username' => $site->get('db_user'),
  'password' => $site->get('db_passwd'),
  'host' => $site->get('db_host'),
  'port' => $site->get('db_port'),
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

$settings['file_public_path'] = 'sites/' . $site->get('uri') . '/files';
$settings['file_private_path'] = '/var/aegir/private/' . $site->get('uri');
$settings['config_sync_directory'] = '../config/sync';

// Aegir-specific
$_SERVER['db_type'] = 'mysql';
$_SERVER['db_name'] = $site->get('db_name');
$_SERVER['db_user'] = $site->get('db_user');
```

**Current State**:
- ✅ Database credential injection
- ✅ File path configuration
- ✅ Trusted host patterns
- ⚠️ No Drupal 11 config sync directory handling
- ⚠️ No environment-specific settings (dev/staging/prod)
- ❌ No Redis/Memcache configuration
- ❌ No CDN configuration
- ❌ No multisite directory detection

**Future Goals**:
- Add environment-aware settings generation
- Support Redis/Memcache configuration
- Implement CDN configuration (CloudFront, Cloudflare)
- Add performance tuning presets (dev/staging/prod)
- Support Drupal 11 environment detection
- Add local.settings.php for developer overrides
- Implement settings.php validation

### SSL Certificate Management

**File**: [src/Service/Ssl/SslManager.php](src/Service/Ssl/SslManager.php)

**Responsibilities**:
- Generate self-signed certificates (development)
- Integrate with Let's Encrypt (production)
- Store certificates in server config path
- Validate certificate expiry
- Auto-renewal workflow

**Certificate Paths**:
```
{config_path}/ssl/
├── example.com/
│   ├── cert.pem       # Certificate
│   ├── key.pem        # Private key
│   ├── chain.pem      # Certificate chain
│   └── fullchain.pem  # Full certificate chain
```

**Current State**:
- ✅ Self-signed certificate generation
- ⚠️ Let's Encrypt integration incomplete
- ⚠️ No certificate expiry monitoring
- ❌ No automatic renewal
- ❌ No wildcard certificate support
- ❌ No ACME v2 implementation

**Future Goals**:
- Complete Let's Encrypt ACME v2 integration
- Add automatic certificate renewal (30 days before expiry)
- Support wildcard certificates
- Implement OCSP stapling
- Add certificate monitoring and alerts
- Support custom CA certificates
- Implement DNS-01 challenge for internal domains

## ProvisionManager Orchestration

**File**: [src/Provision/ProvisionManager.php](src/Provision/ProvisionManager.php)

Central orchestrator that coordinates service operations:

### Workflow Pattern

```php
class ProvisionManager {
  
  public function install(Context $site): void {
    // 1. Load dependent contexts
    $platform = $this->contexts->load($site->get('platform'));
    $server = $this->contexts->load($platform->get('server'));
    
    // 2. Verify dependencies exist
    if (!$platform || !$server) {
      throw new \RuntimeException('Missing platform or server context');
    }
    
    // 3. Execute service operations in order
    $this->dbService->createDatabase($site);
    $this->dbService->createUser($site);
    $this->dbService->grantPrivileges($site);
    
    $this->settingsWriter->write($site);
    
    $this->httpService->configure($site);
    $this->httpService->verify($site);
    
    // 4. Run Drupal site install
    $this->runDrushSiteInstall($site);
  }
  
  public function verify(Context $context): void {
    match ($context->getType()) {
      ContextType::Server => $this->verifyServer($context),
      ContextType::Platform => $this->verifyPlatform($context),
      ContextType::Site => $this->verifySite($context),
    };
  }
}
```

### Operation Types

**Install**: Create new site with fresh database
1. Create database and user
2. Generate settings.php
3. Configure Apache vhost
4. Run `drush site:install`
5. Enable site

**Verify**: Regenerate configs and validate state
1. Load context and dependencies
2. Regenerate all service configs
3. Validate file permissions
4. Test service connectivity
5. Report status

**Migrate**: Move site to new platform
1. Backup current site
2. Update context with new platform
3. Copy files to new platform
4. Regenerate settings.php
5. Update Apache vhost
6. Verify site functionality

**Backup**: Create archive of database and files
1. Dump database to SQL file
2. Archive files directory
3. Create tarball with metadata
4. Store in backup_path
5. Log backup location

**Restore**: Restore site from backup
1. Extract backup archive
2. Drop existing database
3. Restore database from SQL
4. Restore files directory
5. Verify site functionality

**Current State**:
- ✅ Core operations implemented
- ✅ Context dependency resolution
- ✅ Service coordination
- ⚠️ No operation rollback on failure
- ⚠️ No parallel operation support
- ❌ No operation logging/audit trail
- ❌ No operation status tracking
- ❌ No operation queuing

**Future Goals**:
- Implement operation transaction system with rollback
- Add parallel operation support for bulk operations
- Create operation audit trail (who, what, when)
- Add operation status tracking (queued, running, completed, failed)
- Implement operation priority queue
- Add operation hooks for extensions
- Support distributed operation execution

## File System Operations

**File**: [src/Core/Filesystem.php](src/Core/Filesystem.php)

Handles file/directory operations with proper permission management.

### Permission Model

**Ubuntu 24.04 LTS Requirements**:
- **Aegir user**: `aegir` (owns all files)
- **Web server group**: `www-data` (group ownership for web-writable)
- **Script execution**: Drush runs as `aegir` user

**Permission Patterns**:
```php
0750  // Config directories (readable by aegir only)
0755  // Platform/site paths (readable by web server)
0770  // Private files (writable by web server)
0775  // Public files (writable by web server)
0700  // SSL certificates (secure storage)

0640  // Generated configs (readable by aegir only)
0644  // Apache vhosts (readable by Apache)
0664  // Drupal settings.php (readable by web server)
0600  // SSL private keys (secure)
```

### Operations

```php
class Filesystem {
  // Directory operations
  public function createDirectory(string $path, int $mode = 0755): void;
  public function removeDirectory(string $path): void;
  public function copyDirectory(string $source, string $destination): void;
  
  // File operations
  public function writeFile(string $path, string $content, int $mode = 0644): void;
  public function copyFile(string $source, string $destination): void;
  public function removeFile(string $path): void;
  
  // Permission operations
  public function chmod(string $path, int $mode): void;
  public function chown(string $path, string $user, string $group): void;
  public function fixDrupalPermissions(string $path): void;
}
```

**Current State**:
- ✅ Basic file/directory operations
- ✅ Permission setting
- ⚠️ No atomic file writes (temp file + rename)
- ⚠️ No permission verification
- ❌ No ACL support for complex permissions
- ❌ No remote filesystem operations (SSH)
- ❌ No permission auditing

**Future Goals**:
- Implement atomic file writes for config generation
- Add permission verification and auto-fix
- Support POSIX ACLs for fine-grained permissions
- Implement remote filesystem operations via SSH
- Add permission audit logging
- Create systemd path units for permission monitoring
- Support immutable file flags for production

## Configuration Templates

**Directory**: [src/Config/templates/](src/Config/templates/)

**File**: [src/Config/TemplateRenderer.php](src/Config/TemplateRenderer.php)

Templates use Twig engine for config generation.

### Template Types

**Apache Vhost** (`apache/vhost.conf.twig`):
```apache
<VirtualHost *:80>
  ServerName {{ uri }}
  {% if aliases %}
  ServerAlias {{ aliases|join(' ') }}
  {% endif %}
  
  DocumentRoot {{ root }}
  
  <Directory {{ root }}>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
  </Directory>
  
  # PHP-FPM
  <FilesMatch \.php$>
    SetHandler "proxy:unix:/run/php/php8.3-fpm-{{ site_name }}.sock|fcgi://localhost"
  </FilesMatch>
  
  # Logging
  ErrorLog ${APACHE_LOG_DIR}/{{ uri }}-error.log
  CustomLog ${APACHE_LOG_DIR}/{{ uri }}-access.log combined
</VirtualHost>
```

**Apache SSL Vhost** (`apache/vhost_ssl.conf.twig`):
```apache
<VirtualHost *:443>
  ServerName {{ uri }}
  DocumentRoot {{ root }}
  
  SSLEngine on
  SSLCertificateFile {{ ssl_cert }}
  SSLCertificateKeyFile {{ ssl_key }}
  SSLCertificateChainFile {{ ssl_chain }}
  
  # Modern SSL configuration
  SSLProtocol -all +TLSv1.3 +TLSv1.2
  SSLCipherSuite HIGH:!aNULL:!MD5:!3DES
  SSLHonorCipherOrder on
  
  {% if ssl_redirect %}
  # HSTS
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
  {% endif %}
  
  # ... rest of config same as HTTP vhost
</VirtualHost>
```

**Drupal Settings** (`drupal/settings.php.twig`):
```php
<?php
/**
 * Aegir-generated settings.php
 * DO NOT EDIT - Changes will be overwritten on next verify
 */

// Database
$databases['default']['default'] = [
  'driver' => 'mysql',
  'database' => '{{ db_name }}',
  'username' => '{{ db_user }}',
  'password' => '{{ db_passwd }}',
  'host' => '{{ db_host }}',
  'port' => {{ db_port }},
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

// File paths
$settings['file_public_path'] = 'sites/{{ uri }}/files';
$settings['file_private_path'] = '{{ private_path }}';
$settings['file_temp_path'] = '/tmp';

// Trusted hosts
$settings['trusted_host_patterns'] = [
  '^{{ uri|replace({'.': '\\.'}) }}$',
{% for alias in aliases %}
  '^{{ alias|replace({'.': '\\.'}) }}$',
{% endfor %}
];

// Config sync
$settings['config_sync_directory'] = '../config/sync';

// Aegir integration
$_SERVER['db_type'] = 'mysql';
$_SERVER['db_name'] = '{{ db_name }}';
$_SERVER['db_user'] = '{{ db_user }}';

// Load local settings (not managed by Aegir)
if (file_exists(__DIR__ . '/local.settings.php')) {
  include __DIR__ . '/local.settings.php';
}
```

**PHP-FPM Pool** (`php-fpm/pool.conf.twig`):
```ini
[{{ site_name }}]
user = www-data
group = www-data

listen = /run/php/php8.3-fpm-{{ site_name }}.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

php_admin_value[error_log] = /var/log/php-fpm/{{ site_name }}-error.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
```

**Current State**:
- ✅ Twig templating for Apache and Drupal settings
- ✅ Template variable injection
- ⚠️ No template validation
- ⚠️ No template caching
- ❌ PHP-FPM pool templates not implemented
- ❌ Nginx templates not available
- ❌ No template inheritance/composition

**Future Goals**:
- Implement PHP-FPM pool per-site configuration
- Add Nginx template alternatives
- Create template validation system
- Add template caching for performance
- Support template inheritance and composition
- Implement template versioning
- Add custom template directories for extensions

## Development Conventions

### Drush 13 Integration

**Service Registration** ([drush.services.yml](drush.services.yml)):
```yaml
services:
  provision.commands:
    class: Aegir\Provision\Commands\ProvisionCommands
    arguments:
      - '@provision.manager'
      - '@provision.context_repository'
    tags:
      - { name: drush.command }
  
  provision.manager:
    class: Aegir\Provision\Provision\ProvisionManager
    arguments:
      - '@provision.context_repository'
      - '@provision.service.http'
      - '@provision.service.db'
      - '@provision.service.drupal'
  
  provision.context_repository:
    class: Aegir\Provision\Core\ContextRepository
    arguments:
      - '@drush.alias.manager'
```

**Command Attributes**:
```php
use Drush\Attributes as CLI;

#[CLI\Command(name: 'provision:install', aliases: ['pvi'])]
#[CLI\Argument(name: 'site', description: 'Site context name')]
#[CLI\Option(name: 'profile', description: 'Drupal install profile')]
#[CLI\Option(name: 'client-email', description: 'Admin user email')]
#[CLI\Usage(name: 'drush provision:install example.com', description: 'Install site')]
class ProvisionCommands extends DrushCommands {
  
  public function install(
    string $site,
    array $options = ['profile' => 'standard', 'client-email' => null]
  ): void {
    // Implementation
  }
}
```

### Error Handling

**Pattern**:
```php
use Aegir\Provision\Exception\ProvisionException;

try {
  $this->dbService->createDatabase($site);
} catch (\PDOException $e) {
  throw new ProvisionException(
    "Failed to create database: {$e->getMessage()}",
    previous: $e
  );
}
```

**Current State**:
- ✅ Custom exception types
- ⚠️ Limited context in error messages
- ❌ No error recovery strategies
- ❌ No structured error logging

**Future Goals**:
- Add rich error context (operation, context, state)
- Implement error recovery strategies
- Add structured error logging (JSON)
- Create error reporting interface

### Testing

**Current State**:
- ❌ No unit tests
- ❌ No integration tests
- ❌ No functional tests
- ❌ No CI/CD pipeline

**Future Goals**:
- Implement PHPUnit test suite
- Add integration tests with real Apache/MySQL
- Create functional tests for full workflows
- Set up CI/CD with GitHub Actions
- Add code coverage reporting
- Implement test containers for isolation

## Anti-Patterns

### ❌ Don't modify contexts directly
```php
// WRONG: Bypass ContextRepository
file_put_contents("~/.drush/sites/example.com.site.yml", yaml_emit($data));

// CORRECT: Use ContextRepository
$context = new Context('example.com', ContextType::Site, $data);
$this->contextRepository->save($context);
```

### ❌ Don't hardcode paths
```php
// WRONG: Hardcode config paths
$vhost = '/var/aegir/config/apache/vhost.d/site.conf';

// CORRECT: Use ConfigPaths service
$vhost = $this->paths->serverConfigPath($server) . '/apache/vhost.d/site.conf';
```

### ❌ Don't execute shell commands directly
```php
// WRONG: Direct shell execution
exec("mysql -u root -p{$pass} -e 'CREATE DATABASE {$db}'");

// CORRECT: Use service methods
$this->dbService->createDatabase($site);
```

### ❌ Don't ignore file permissions
```php
// WRONG: Write file without permissions
file_put_contents($path, $content);

// CORRECT: Use Filesystem service
$this->filesystem->writeFile($path, $content, 0644);
$this->filesystem->chown($path, 'aegir', 'www-data');
```

## System Requirements

**Operating System**: Ubuntu 24.04 LTS only

**PHP**: 8.3+ with extensions:
- `php8.3-cli`
- `php8.3-fpm`
- `php8.3-mysql`
- `php8.3-gd`
- `php8.3-curl`
- `php8.3-xml`
- `php8.3-mbstring`
- `php8.3-zip`

**Web Server**: Apache 2.4+ with modules:
- `mod_rewrite`
- `mod_ssl`
- `mod_proxy_fcgi`
- `mod_headers`

**Database**: MySQL 8.0+ or MariaDB 10.6+

**Drush**: 13.x

**System Tools**:
- `tar` (backup/restore)
- `gzip` (compression)
- `rsync` (file operations)
- `openssl` (SSL certificates)

## Future Development Goals

### High Priority

1. **Context Schema Validation**
   - Implement JSON Schema for context properties
   - Add type validation on context save
   - Create context migration system

2. **PHP-FPM Integration**
   - Generate per-site PHP-FPM pools
   - Configure pool settings (memory, workers)
   - Integrate with Apache vhosts

3. **Let's Encrypt Integration**
   - Complete ACME v2 client implementation
   - Add automatic renewal (30 days before expiry)
   - Support wildcard certificates

4. **Operation Rollback**
   - Implement transaction-like operation system
   - Add rollback on failure
   - Create operation audit trail

5. **Testing Infrastructure**
   - Set up PHPUnit test suite
   - Add integration tests
   - Implement CI/CD pipeline

### Medium Priority

6. **Nginx Support**
   - Implement NginxService alternative
   - Create Nginx config templates
   - Add service detection/switching

7. **PostgreSQL Support**
   - Implement PostgreSqlService
   - Add PostgreSQL templates
   - Support mixed MySQL/PostgreSQL

8. **Remote Execution**
   - Add SSH-based remote operations
   - Support distributed server management
   - Implement remote context sync

9. **Backup Enhancements**
   - Add incremental backups
   - Implement compression options (gzip, zstd)
   - Support remote backup storage (S3)

10. **Performance Monitoring**
    - Add site performance metrics
    - Implement resource usage tracking
    - Create alert system for issues

### Low Priority

11. **Containerization**
    - Create container images for provision runtime
    - Support Docker/Podman execution
    - Implement container-based isolation

12. **Multi-tenancy**
    - Add client isolation
    - Implement resource quotas
    - Support billing integration

13. **High Availability**
    - Support database replication
    - Add load balancer configuration
    - Implement failover automation

## Key Files Reference

- [src/Commands/ProvisionCommands.php](src/Commands/ProvisionCommands.php) - Drush command definitions
- [src/Core/Context.php](src/Core/Context.php) - Immutable context structure
- [src/Core/ContextRepository.php](src/Core/ContextRepository.php) - Context persistence
- [src/Provision/ProvisionManager.php](src/Provision/ProvisionManager.php) - Central orchestrator
- [src/Service/Http/ApacheService.php](src/Service/Http/ApacheService.php) - Apache vhost management
- [src/Service/Db/MySqlService.php](src/Service/Db/MySqlService.php) - MySQL database operations
- [src/Service/Drupal/SettingsWriter.php](src/Service/Drupal/SettingsWriter.php) - settings.php generation
- [src/Core/Filesystem.php](src/Core/Filesystem.php) - File operations with permissions
- [drush.services.yml](drush.services.yml) - Service container configuration
