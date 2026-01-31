# Aegir Provision - AI Coding Agent Instructions

## Repository Overview

**Aegir Provision** is a Drush 13.7+ extension that provides the backend automation layer for Aegir Hosting. It manages hosting infrastructure through a context-based system where Drush commands automate web hosting tasks (site install, migrate, backup, restore, SSL configuration).

**Technology Stack**: PHP 8.3+, Drush 13.7+, Apache 2.4+ with PHP-FPM, MySQL 8.0+, Ubuntu 24.04 LTS

**Architecture**: Context-driven orchestration system with service abstractions for HTTP, database, and file operations.

## � Documentation Structure and Guidelines

### Documentation File Organization

This repository maintains multiple documentation locations, each serving distinct purposes:

**1. Do Not Create New Documentation Files Unless Explicitly Requested**
- When adding documentation, extend existing files rather than creating new ones
- Only create new documentation files when specifically instructed by the user
- Ask for clarification if unsure whether to extend or create new documentation

**2. `doc/` Folder - Public-Facing Long-Term Documentation**
- **Purpose**: Public documentation synced to GitHub wiki for long-term reference
- **Audience**: External developers, system administrators, Aegir users
- **Content Types**:
  - Architectural documentation (system design, patterns, principles)
  - Task lists and development roadmaps
  - Detailed component documentation (classes, services, APIs)
  - How-to guides and tutorials
  - Function reference and usage examples
- **Maintenance**: Keep content current, comprehensive, and user-friendly
- **Examples**: `doc/extension-system.md`, `doc/provision-d11.md`, `doc/TODO.md`

**3. `.github/` Folder - AI Agent Instructions**
- **Purpose**: Specialized instructions for AI coding agents (beyond public documentation)
- **Audience**: AI assistants working on the codebase
- **Content Types**:
  - Repository architecture and conventions
  - Development patterns and anti-patterns
  - Context-specific coding guidelines
  - Tool and API reference specific to this codebase
- **File**: `.github/AI-INSTRUCTIONS.md` (this file)
- **Note**: Not synced to public wiki; internal development guidance only

**4. `README.md` - Short General Introduction**
- **Purpose**: Brief overview reflecting current state of the project
- **Audience**: First-time visitors, quick reference
- **Content**: Project summary, installation, basic usage, links to detailed docs
- **Style**: Concise, high-level, keep under 200 lines
- **Avoid**: Detailed implementation details, extensive tutorials, complete API reference

### Documentation Update Guidelines

When making changes to the codebase:
- Update relevant `doc/` files to reflect architectural changes
- Update `.github/AI-INSTRUCTIONS.md` if development patterns change
- Keep `README.md` brief but current with project status
- Ensure documentation remains synchronized with code reality

## �📚 External Documentation References

### Drush 13 Official Documentation (AUTHORITATIVE SOURCE)

**URL**: https://www.drush.org/13.x/

This is the **authoritative source** for all Drush 13 functionality. When working with Drush commands, APIs, attributes, or any Drush-related code in this project, you MUST analyze and incorporate guidance from the official Drush documentation.

**Critical sections to reference**:

1. **Creating Custom Commands**: https://www.drush.org/13.x/commands/#creating-custom-drush-commands
   - **CRITICAL**: Modern Drush 13.7+ uses Symfony Console commands with `#[AsCommand]` attribute
   - **DEPRECATED**: `DrushCommands` base class and `#[CLI\Command]` attributes (Drush 12 patterns)
   - Command class structure: one class per command
   - Return values: `Command::SUCCESS`, `Command::FAILURE`, `Command::INVALID`

2. **Dependency Injection**: https://www.drush.org/13.x/dependency-injection/
   - **CRITICAL**: Modern Drush 13+ uses `AutowireTrait` for constructor-based injection; in this repo use `ProvisionAutowireTrait`
   - **DEPRECATED**: `drush.services.yml` approach (do not use for Drush 13.7+)
   - PSR-4 auto-discovery replaces services.yml registration
   - Available Drush services auto-injected via type hints

3. **Site Aliases**: https://www.drush.org/13.x/site-aliases/
   - YAML alias file format
   - Alias discovery paths
   - Remote execution via aliases
   - Custom alias properties

4. **Command API**: https://www.drush.org/13.x/commands/
   - Available attributes and their parameters
   - Input/output handling
   - Progress indicators
   - Interactive prompts

5. **Output Formatting**: https://www.drush.org/13.x/output-formats-filters/
   - Table formatting
   - JSON/YAML output
   - Custom formatters

6. **Bootstrap Levels**: https://www.drush.org/13.x/bootstrap/
   - When and how to bootstrap Drupal
   - Running commands without bootstrap
   - Remote site execution

**When to consult Drush documentation**:
- ✅ Before adding new command attributes or options
- ✅ When implementing service injection in commands
- ✅ When working with site alias YAML files
- ✅ When deciding whether to bootstrap Drupal
- ✅ When implementing output formatting or progress indicators
- ✅ When troubleshooting command discovery or registration issues
- ✅ When implementing remote command execution

**How to use this documentation**:
1. Search for the specific Drush feature you're implementing
2. Read the official documentation for that feature
3. Follow Drush's conventions and best practices
4. Adapt examples to aegir-provision's architecture
5. Test against actual Drush 13 behavior

**Important notes**:
- Drush 13 uses PHP 8+ attributes, not annotations (older Drush versions used annotations)
- Commands extend `Symfony\Component\Console\Command\Command` and use `#[AsCommand]`
- Command classes are auto-discovered from `src/Drush/Commands` in the `Aegir\Provision\Drush\Commands` namespace
- Service container is Symfony-based; dependency injection uses `ProvisionAutowireTrait` (wraps `AutowireTrait`) and `ProvisionServiceRegistry` to register Provision services in the Drush container
- Alias files use YAML format, not legacy PHP arrays

## ⚠️ CRITICAL: Standalone Drush Command Package Architecture

### This is NOT a Drupal Module

**Aegir Provision is a standalone Drush command package** that operates independently of Drupal's module system. This is a fundamental architectural decision with significant implications:

**Package Type**: `drupal-drush` (Composer type)
- Installed via Composer: `composer require argopecten/aegir-provision`
- Placed in `drush/Commands/contrib/aegir-provision/` by Composer
- PSR-4 autoloading: `Aegir\Provision\` → `src/`

### ✅ Drush 13.7+ Migration

**Status**: Completed - Commands now follow Drush 13.7+ standards (Symfony Console + `#[AsCommand]` + `ProvisionAutowireTrait` + auto-discovery).

The codebase now uses **modern Drush 13.7+ patterns** (Symfony `Command`, `#[AsCommand]`, one-class-per-command, PSR-4 auto-discovery). Legacy `drush.services.yml` registration has been removed, and Provision services are registered via `ProvisionServiceRegistry` during autowire.

**Complete details**: [doc/TODO.md](../doc/TODO.md) contains the remaining validation and testing checklist.

**Quick reference**: https://www.drush.org/13.x/commands/

### Critical Distinctions (Still Relevant)
- Commands run **outside** Drupal bootstrap (can operate on multiple sites)
- **CANNOT** use Drupal APIs, entities, hooks, or database abstraction
- **CANNOT** access Drupal's configuration, state, or cache systems

### Why This Architecture Exists

**Provision must operate OUTSIDE Drupal because**:

1. **Pre-Drupal Operations**
   - `provision-install` creates sites that don't exist yet
   - Must generate `settings.php` before Drupal can bootstrap
   - Configures databases before Drupal connects to them

2. **Server-Level Operations**
   - Apache vhost configuration: `/etc/apache2/sites-available/`
   - MySQL admin operations: `CREATE DATABASE`, `GRANT ALL`
   - Filesystem operations: `/var/aegir/`, platform directories
   - SSL certificate management: `/etc/ssl/`

3. **Multi-Site Management**
   - Single command context manages multiple Drupal sites
   - Cannot be "inside" any one site to operate on all sites
   - Context system (`@server`, `@platform`, `@site`) spans multiple installations

4. **Remote Execution**
   - Commands run via SSH on remote servers
   - No Drupal available on infrastructure management servers
   - Backend invocation from frontend: `drush @remote provision-verify @site`

### What You CANNOT Do

**❌ DO NOT use Drupal APIs**:
```php
// ❌ WRONG - These are not available
\Drupal::service('some.service');
\Drupal::database()->query();
\Drupal::config('system.site')->get('name');
$entity_manager = \Drupal::entityTypeManager();
\Drupal::logger('provision')->notice();
```

**❌ DO NOT expect Drupal hooks**:
```php
// ❌ WRONG - This is not a Drupal module
function provision_install() { }  // Hook will never be called
function provision_entity_insert($entity) { }  // Not available
```

**❌ DO NOT use Drupal database abstraction**:
```php
// ❌ WRONG - Drupal's database layer requires bootstrap
$connection = \Drupal\Core\Database\Database::getConnection();
$query = $connection->select('node', 'n');
```

**❌ DO NOT register routes, forms, or entity types**:
- No `*.routing.yml` (use Drush commands)
- No `*.permissions.yml` (Drush commands run as system user)
- No entity definitions (that's aegir-hosting frontend)

### What You CAN Do

**✅ Use Drush to interact with Drupal sites**:
```php
// Execute Drush commands on a specific site context
use Aegir\Provision\Core\ProcessRunner;

$runner = new ProcessRunner();
$result = $runner->run([
  'drush',
  '@site',  // Context alias
  'status',
  '--format=json'
]);

// Install a site using Drush
$result = $runner->run([
  'drush',
  'site:install',
  'standard',
  '--site-name=Example',
  '--root=/var/aegir/platforms/drupal-11/web',
  '--db-url=mysql://user:pass@localhost/db'
]);
```

**✅ Use Symfony components**:
```php
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

$process = new Process(['apache2ctl', 'configtest']);
$process->run();

$fs = new Filesystem();
$fs->mkdir('/var/aegir/platforms/new-platform');
$fs->chmod('/var/www/sites/default', 0755);

$data = Yaml::parseFile('~/.drush/sites/example.com.site.yml');
```

**✅ Execute system commands**:
```php
// MySQL admin operations (requires mysql CLI client)
$runner->run([
  'mysql',
  '-u', 'root',
  '-e', 'CREATE DATABASE example_com'
]);

// Apache configuration
$runner->run(['a2ensite', 'example.com.conf']);
$runner->run(['systemctl', 'reload', 'apache2']);
```

**✅ Generate configuration files**:
```php
// Generate Apache vhost from template
$renderer = new TemplateRenderer();
$vhost = $renderer->render('apache/vhost.tpl.php', [
  'uri' => 'example.com',
  'root' => '/var/aegir/platforms/drupal-11/web',
  'port' => 80
]);
file_put_contents('/etc/apache2/sites-available/example.com.conf', $vhost);
```

**✅ Use Drush's service container**:
```php
// In ProvisionCommands
public function __construct(
  private ProvisionManager $manager,        // Injected by Drush
  private ContextRepository $contexts,      // Injected by Drush
  private LoggerInterface $logger           // Drush's PSR-3 logger
) {}

$this->logger->notice('Site installed: @uri', ['@uri' => $uri]);
```

### Testing Implications

**Unit tests**:
- Do NOT require Drupal test base classes
- Use PHPUnit directly
- Mock ProcessRunner for command execution
- Mock ContextRepository for context loading

**Integration tests**:
- Test against actual Apache, MySQL, filesystem
- Do NOT use Drupal's BrowserTestBase or KernelTestBase
- Use Docker containers or test VMs
- Clean up created sites/databases/vhosts after tests

### Frontend Integration

The **aegir-hosting** Drupal module (separate repository) invokes provision commands:

```php
// In aegir-hosting module (frontend)
namespace Drupal\aegir_hosting\Service;

class BackendInvoker {
  public function install(Site $entity): void {
    // Frontend has Drupal entity
    // Converts to context and invokes backend
    $alias = '@' . $entity->uri->value;
    
    $process = new Process([
      'drush',
      'provision:install',
      $alias
    ]);
    $process->run();
  }
}
```

**Data flow**:
1. Frontend (aegir-hosting) manages entities in Drupal
2. Frontend writes context aliases to `~/.drush/sites/` via ContextRegistry
3. Frontend invokes backend via Drush commands
4. Backend (aegir-provision) reads contexts from YAML aliases
5. Backend performs system operations (Apache, MySQL, filesystem)
6. Backend returns status via Drush exit codes and output
7. Frontend parses output and updates entities

**Separation of concerns**:
- Frontend: Drupal entities, forms, validation, permissions, UI
- Backend: Infrastructure automation, config generation, system operations

## Directory Structure

```
aegir-provision/
├── src/
│   ├── Drush/Commands/        # Drush 13.7+ auto-discovered command classes
│   │   └── Provision*Command.php  # one command per file
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

**File**: [src/Drush/Commands](src/Drush/Commands) (one command per file)

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
use Aegir\Provision\Drush\Commands\ProvisionAutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'provision:install')]
final class ProvisionInstallCommand extends Command {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager,
    private readonly ContextRepository $contexts
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addArgument('site', InputArgument::REQUIRED, 'Site context name');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $context = $this->contexts->load($input->getArgument('site'));
    $this->manager->install($context);
    return Command::SUCCESS;
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

### Drush 13.7+ Integration

Drush 13.7+ requires auto-discovered Symfony Console commands (annotated Drush commands are deprecated).

**Command auto-discovery requirements**:
- Commands live in `src/Drush/Commands`
- Namespace: `Aegir\Provision\Drush\Commands`
- One command per class file
- Extend `Symfony\Component\Console\Command\Command`
- Use `#[AsCommand]`, define args/options in `configure()`, logic in `execute()`
- Use `ProvisionAutowireTrait` (wraps `AutowireTrait`) for constructor-based DI
- Do not use `drush.services.yml`
- Do not use global command registration via `drush.commands` configuration

**Site-wide commandfile rules (Drush 13.7+)**:
- Site-wide commandfiles live under `$PROJECT_ROOT/drush/Commands` or are installed via Composer.
- Do not include `src` in the commandfile path.
- Examples of valid paths/namespaces:
  - `$PROJECT_ROOT/drush/Commands/ExampleCommands.php` → `Drush\Commands`
  - `$PROJECT_ROOT/drush/Commands/example/ExampleCommands.php` → `Drush\Commands\example`
  - `$PROJECT_ROOT/drush/Commands/contrib/dev_modules/ExampleCommands.php` → `Drush\Commands\dev_modules`

Example repository:
```text
https://github.com/drush-ops/drush/tree/13.x/examples/Commands
```

**Example Command**:
```php
use Aegir\Provision\Drush\Commands\ProvisionAutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'provision:install',
  description: 'Install a Drupal site',
  aliases: ['pvi']
)]
final class ProvisionInstallCommand extends Command {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addArgument('site', InputArgument::REQUIRED, 'Site context name');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->manager->install($input->getArgument('site'));
    return Command::SUCCESS;
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

- [src/Drush/Commands](src/Drush/Commands) - Drush 13.7+ auto-discovered command classes
- [src/Core/Context.php](src/Core/Context.php) - Immutable context structure
- [src/Core/ContextRepository.php](src/Core/ContextRepository.php) - Context persistence
- [src/Provision/ProvisionManager.php](src/Provision/ProvisionManager.php) - Central orchestrator
- [src/Service/Http/ApacheService.php](src/Service/Http/ApacheService.php) - Apache vhost management
- [src/Service/Db/MySqlService.php](src/Service/Db/MySqlService.php) - MySQL database operations
- [src/Service/Drupal/SettingsWriter.php](src/Service/Drupal/SettingsWriter.php) - settings.php generation
- [src/Core/Filesystem.php](src/Core/Filesystem.php) - File operations with permissions
