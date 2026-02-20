# API Reference

Complete reference for all Aegir Provision components and their APIs.

---

## Table of Contents

- [ProvisionManager](#provisionmanager) - Central orchestrator
- [Core Package](#core-package) - Context system, filesystem, process execution
- [Service Package](#service-package) - HTTP, database, SSL, Drupal services
- [Event Package](#event-package) - Event system and lifecycle hooks
- [Config Package](#config-package) - Template rendering
- [Drush Package](#drush-package) - Command classes and service registry

**See also**:
- [Core Concepts](concepts.md) - Architectural overview
- [Extension System](extension-system.md) - Creating extensions
- Detailed package docs: [src/*/doc/README.md](../../src/doc/README.md)

---

## ProvisionManager

**Location**: `Aegir\Provision\ProvisionManager`  
**Purpose**: Central orchestrator for all provision operations

The main entry point that coordinates services and dispatches events.

### Constructor Dependencies

```php
public function __construct(
    private readonly ContextRepository $contexts,
    private readonly Filesystem $filesystem,
    private readonly ProcessRunner $runner,
    private readonly TemplateRenderer $templates,
    private readonly LoggerInterface $logger,
    private readonly EventDispatcherInterface $dispatcher
)
```

### Key Operations

#### Site Operations
```php
// Install a new Drupal site
public function install(string $siteName): void

// Import an existing site
public function import(string $siteName): void

// Verify site configuration
public function verify(string $contextName): void

// Create site backup
public function backup(string $contextName, string $backupPath): void

// Restore from backup
public function restore(string $contextName, string $backupPath): void

// Deploy backup to a site
public function deploy(string $siteName, string $backupPath): void
```

#### Site Lifecycle
```php
// Migrate site to different platform
public function migrate(string $siteName, string $newPlatformName): void

// Clone site to new context
public function cloneSite(string $sourceName, string $targetName): void

// Enable/disable site
public function enable(string $siteName): void
public function disable(string $siteName): void

// Lock/unlock site
public function lock(string $siteName): void
public function unlock(string $siteName): void

// Delete context
public function delete(string $contextName): void
```

#### Context Management
```php
// Save or update context
public function saveContext(
    string $contextName,
    array $data,
    ?string $type = null,
    bool $delete = false
): void
```

### Event Integration

Every operation follows this pattern:
1. Dispatch `VALIDATE_*` event
2. Dispatch `BEFORE_*` event
3. Execute main logic (using services)
4. Dispatch `AFTER_*` event
5. On error: dispatch `ROLLBACK_*` event

**Example**:
```php
// In ProvisionManager::install()
$event = new InstallEvent('validate', $site, $platform, $server);
$this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_INSTALL);

// ... installation logic ...

$event = new InstallEvent('after', $site, $platform, $server);
$this->dispatcher->dispatch($event, ProvisionEvents::AFTER_INSTALL);
```

**See**: [Event Package](#event-package) for complete event reference

---

## Core Package

---

## Core Package

**Location**: `Aegir\Provision\Core`  
**Purpose**: Foundational abstractions and utilities

The Core package provides the building blocks used throughout Aegir Provision.

**Detailed docs**: [src/Core/doc/README.md](../../src/Core/doc/README.md)

### Value Objects

**Location**: `Aegir\Provision\Core\ValueObject`

**Purpose**: Immutable, type-safe data structures replacing arrays

Aegir Provision uses value objects for critical configuration data, providing compile-time type safety and validation.

#### DatabaseCredentials

**Location**: `Aegir\Provision\Core\ValueObject\DatabaseCredentials`

Immutable database connection credentials.

```php
// Constructor
public function __construct(
    public readonly string $host,
    public readonly int $port,
    public readonly string $name,
    public readonly string $username,
    public readonly string $password,
    public readonly string $driver = 'mysql',
    public readonly string $prefix = '',
)

// Helper methods
public function getDsn(): string  // Returns PDO DSN string
public function toArray(): array  // For template rendering
```

**Usage Example**:
```php
$credentials = new DatabaseCredentials(
    host: 'localhost',
    port: 3306,
    name: 'drupal_db',
    username: 'drupal_user',
    password: 'secure_pass'
);

// Access properties
$dsn = $credentials->getDsn();  // "mysql:host=localhost;port=3306;dbname=drupal_db"

// Pass to services
$settingsWriter->write($docroot, $sitePath, $credentials);
```

#### ServerPaths

**Location**: `Aegir\Provision\Core\ValueObject\ServerPaths`

Immutable server directory paths.

```php
// Constructor
public function __construct(
    public readonly string $aegirRoot,
    public readonly string $configPath,
    public readonly string $backupPath,
    public readonly string $platformsPath,
)

// Helper methods
public function ensureDirectoriesExist(int $mode = 0755): void
public function withBackupPath(string $path): self
public function withRoot(string $root): self
```

**Usage Example**:
```php
$paths = new ServerPaths(
    aegirRoot: '/var/aegir',
    configPath: '/var/aegir/.config',
    backupPath: '/var/aegir/backups',
    platformsPath: '/var/aegir/platforms'
);

// Ensure all directories exist
$paths->ensureDirectoriesExist(0750);

// Create modified copy
$customPaths = $paths->withBackupPath('/mnt/backup');
```

#### ApacheVhostConfig

**Location**: `Aegir\Provision\Core\ValueObject\ApacheVhostConfig`

Immutable Apache virtual host configuration.

```php
// Constructor
public function __construct(
    public readonly string $serverName,
    public readonly string $documentRoot,
    public readonly int $port = 80,
    public readonly array $serverAliases = [],
    public readonly ?string $sslCertPath = null,
    public readonly ?string $sslKeyPath = null,
    public readonly ?string $sslCaPath = null,
    public readonly array $customDirectives = [],
)

// Helper methods
public function isSslEnabled(): bool
public function getFullUrl(): string
public function withSsl(string $cert, string $key, ?string $ca = null): self
```

**Usage Example**:
```php
$config = new ApacheVhostConfig(
    serverName: 'example.com',
    documentRoot: '/var/aegir/platforms/drupal-11/web',
    port: 80,
    serverAliases: ['www.example.com'],
    sslCertPath: '/var/aegir/.config/ssl/example.com/cert.pem',
    sslKeyPath: '/var/aegir/.config/ssl/example.com/key.pem',
    customDirectives: ['custom' => 'RewriteEngine On']
);

// Check SSL status
if ($config->isSslEnabled()) {
    $url = $config->getFullUrl();  // "https://example.com"
}

// Pass to HTTP service
$httpService->enableSite($site, $platform, $server, $config);
```

### Context

**Location**: `Aegir\Provision\Core\Context`

Immutable data structure representing a server, platform, or site.

**Constructor**:
```php
public function __construct(string $name, string $type, array $data = [])
```

**Key Methods**:

```php
// Get context type
public function type(): ContextType

// Get context name
public function name(): string

// Get a property value
public function get(string $key, mixed $default = null): mixed

// Check if property exists
public function has(string $key): bool

// Set a property (returns new Context instance)
public function set(string $key, mixed $value): Context

// Get all data as array
public function toArray(): array
```

**Usage Example**:

```php
$site = $contextRepository->load('example.com');
$uri = $site->get('uri');
$platform = $site->get('platform');
$dbName = $site->get('db_name');

// Contexts are immutable - set() returns a new instance
$updatedSite = $site->set('ssl_enabled', true);
```

---

### ContextRepository

**Location**: `Aegir\Provision\Core\ContextRepository`

Manages loading and saving contexts using Drush's AliasStore.

**Key Methods**:

```php
// Load a context by name
public function load(string $name): Context

// Save a context
public function save(Context $context): void

// Delete a context
public function delete(string $name): void

// Check if context exists
public function exists(string $name): bool
```

**Usage Example**:

```php
// Inject via constructor
public function __construct(
    private readonly ContextRepository $contexts
) {}

// Load and modify
$site = $this->contexts->load('example.com');
$site = $site->set('ssl_enabled', true);
$this->contexts->save($site);
```

---

### ContextType

**Location**: `Aegir\Provision\Core\ContextType`

Enum-like class defining the three context types.

**Constants**:

```php
const SERVER = 'server';
const PLATFORM = 'platform';
const SITE = 'site';
```

**Usage Example**:

```php
if ($context->type() === ContextType::SITE) {
    // Site-specific logic
}
```

---

## Service Package

**Location**: `Aegir\Provision\Service`  
**Purpose**: Modular service implementations for hosting operations

Services implement specific hosting concerns: web servers, databases, SSL, Drupal configuration.

**Detailed docs**: [src/Service/doc/README.md](../../src/Service/doc/README.md)

### ApacheService

**Location**: `Aegir\Provision\Service\Http\ApacheService`

Manages Apache web server configuration.

**Key Methods**:

```php
use Aegir\Provision\Core\ValueObject\ApacheVhostConfig;

// Enable a site (generate and activate vhost configuration)
public function enableSite(
    Context $site,
    Context $platform,
    Context $server,
    ApacheVhostConfig $config
): array

// Disable a site (remove symlink)
public function disableSite(string $serverName, string $siteName): void

// Remove site vhost entirely
public function removeSite(string $serverName, string $siteName): void

// Reload Apache configuration
public function reload(string $restartCmd): void
```

---

### MySqlService

**Location**: `Aegir\Provision\Service\Db\MySqlService`

Manages MySQL database operations.

**Key Methods**:

```php
// Create database
public function createDatabase(Context $server, string $dbName): void

// Create user
public function createUser(
    Context $server,
    string $dbUser,
    string $dbPass,
    string $dbHost = 'localhost'
): void

// Grant privileges
public function grant(
    Context $server,
    string $dbName,
    string $dbUser,
    string $dbHost = 'localhost'
): void

// Drop database
public function dropDatabase(Context $server, string $dbName): void

// Drop user
public function dropUser(
    Context $server,
    string $dbUser,
    string $dbHost = 'localhost'
): void

// Dump database to file
public function dump(
    Context $server,
    string $dbName,
    string $outputFile
): void

// Restore database from file
public function restore(
    Context $server,
    string $dbName,
    string $inputFile
): void
```

---

### SettingsWriter

**Location**: `Aegir\Provision\Service\Drupal\SettingsWriter`

Generates Drupal `settings.php` from templates.

**Key Methods**:

```php
// Write settings.php for a site
public function write(
    string $docroot,
    string $sitePath,
    array $dbConfig,
    array $extraVars = []
): void
```

**Database Config Array**:

```php
$dbConfig = [
    'driver' => 'mysql',
    'database' => $dbName,
    'username' => $dbUser,
    'password' => $dbPass,
    'host' => $dbHost,
    'port' => 3306,
    'prefix' => '',
];
```

---

## Infrastructure Classes

### Filesystem

**Location**: `Aegir\Provision\Core\Filesystem`

Wraps Symfony Filesystem with logging and permission handling.

**Key Methods**:

```php
// Ensure directory exists with correct permissions
public function mkdir(string $path, int $mode = 0755): void

// Remove file or directory
public function remove(string|array $files): void

// Copy file
public function copy(string $source, string $target): void

// Create symlink
public function symlink(string $target, string $link): void

// Check if file/directory exists
public function exists(string $path): bool

// Change ownership
public function chown(string $path, string $user, ?string $group = null): void

// Change permissions
public function chmod(string $path, int $mode): void
```

---

### ProcessRunner

**Location**: `Aegir\Provision\Core\ProcessRunner`

Executes external commands using Symfony Process.

**Key Methods**:

```php
// Run a command and return output
public function run(
    array $command,
    ?string $cwd = null,
    ?array $env = null,
    ?int $timeout = 60
): string

// Run command in background
public function runAsync(array $command, ?string $cwd = null): void

// Check if command exists
public function commandExists(string $command): bool
```

**Usage Example**:

```php
// Run drush command
$output = $this->runner->run([
    'drush',
    'site:install',
    'standard',
    '--root=' . $docroot,
    '--db-url=mysql://user:pass@localhost/dbname'
]);

// Run with timeout
$output = $this->runner->run(
    ['mysqldump', $dbName],
    null,
    null,
    300  // 5 minutes
);
```

---

## Config Package

**Location**: `Aegir\Provision\Config`  
**Purpose**: Template-based configuration file generation

**Detailed docs**: [src/Config/doc/README.md](../../src/Config/doc/README.md)

### TemplateRenderer

**Location**: `Aegir\Provision\Config\TemplateRenderer`

Renders PHP templates with variable substitution.

**Key Methods**:

```php
// Render a template to string
public function render(string $templateName, array $variables = []): string

// Render template to file
public function renderToFile(
    string $templateName,
    string $outputPath,
    array $variables = []
): void
```

**Template Locations**:

- `resources/templates/apache/vhost.tpl.php`
- `resources/templates/apache/vhost_ssl.tpl.php`
- `resources/templates/drupal/settings.php.tpl.php`

**Usage Example**:

```php
$vhostConfig = $this->templates->render('apache/vhost', [
    'uri' => $site->get('uri'),
    'docroot' => $docroot,
    'http_port' => $server->get('http_port', 80),
]);
```

---

## Event Package

**Location**: `Aegir\Provision\Event`  
**Purpose**: Event system for extension and customization

The Event package provides lifecycle hooks via Symfony EventDispatcher.

**Detailed docs**: [src/Event/doc/README.md](../../src/Event/doc/README.md)

### ProvisionEvent

**Location**: `Aegir\Provision\Event\ProvisionEvent`

Base class for all provision events.

**Key Methods**:

```php
// Get the operation name
public function getOperation(): string

// Get the context
public function getContext(): Context

// Get event data
public function getData(): array

// Set data value
public function setData(string $key, mixed $value): void

// Get specific data value
public function getDataValue(string $key, mixed $default = null): mixed
```

---

### Specific Event Classes

All events extend `ProvisionEvent`:

- **InstallEvent** - `getSite()`, `getPlatform()`, `getServer()`
- **VerifyEvent** - `getContext()`, `getPlatform()`, `getServer()`
- **BackupEvent** - `getContext()`, `getBackupPath()`
- **RestoreEvent** - `getContext()`, `getBackupPath()`
- **MigrateEvent** - `getSite()`, `getOldPlatform()`, `getNewPlatform()`, `getServer()`
- **CloneEvent** - `getSourceSite()`, `getTargetSite()`, `getPlatform()`, `getServer()`
- **DeleteEvent** - `getContext()`, `shouldDeleteDatabase()`, `shouldDeleteFiles()`
- **DeployEvent** - `getContext()`, `getBackupPath()`

---

## Drush Package

**Location**: `src/Drush`  
**Purpose**: Drush 13 command integration and service registration

**Detailed docs**: [src/Drush/doc/README.md](../../src/Drush/doc/README.md)

### Available Commands

Target command namespace: `Aegir\Provision\Drush\Commands` (Drush 13.7+ discovers commands via the `\Drush\Commands\` sub-namespace in PSR-4 roots).

**Context Management**:
- `ProvisionSaveCommands` - `provision:save` - Save or update context
- `ProvisionVerifyCommands` - `provision:verify` - Verify configuration
- `ProvisionDeleteCommands` - `provision:delete` - Delete context

**Site Operations**:
- `ProvisionInstallCommands` - `provision:install` - Install new site
- `ProvisionImportCommands` - `provision:import` - Import existing site
- `ProvisionBackupCommands` - `provision:backup` - Create backup
- `ProvisionRestoreCommands` - `provision:restore` - Restore from backup
- `ProvisionDeployCommands` - `provision:deploy` - Deploy backup to site

**Site Lifecycle**:
- `ProvisionMigrateCommands` - `provision:migrate` - Migrate to different platform
- `ProvisionCloneCommands` - `provision:clone` - Clone site
- `ProvisionEnableCommands` - `provision:enable` - Enable site
- `ProvisionDisableCommands` - `provision:disable` - Disable site
- `ProvisionLockCommands` - `provision:lock` - Lock site
- `ProvisionUnlockCommands` - `provision:unlock` - Unlock site
- `ProvisionLoginResetCommands` - `provision:login-reset` - Reset admin login

**Backend**:
- `BackendParseCommands` - `backend:parse` - Parse backend output

### ProvisionAutowireTrait

**Location**: `Aegir\Provision\Drush\Commands\ProvisionAutowireTrait`

Enables dependency injection in command classes.

**Usage**:
```php
namespace Aegir\Provision\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;

#[CLI\Bootstrap(level: 0)]
class MyCommands extends DrushCommands
{
    use ProvisionAutowireTrait;
    
    public function __construct(
        private readonly ProvisionManager $manager
    ) {
        parent::__construct();
    }
    
    #[CLI\Command(name: 'provision:my-command', description: 'My command description')]
    public function myCommand(): int {
        // Command logic
        return self::EXIT_SUCCESS;
    }
}
```

### ProvisionServiceRegistry

**Location**: `Aegir\Provision\Drush\ProvisionServiceRegistry`

Registers Provision services in the Drush container for autowiring.

**Registered Services**:
- `ContextRepository`
- `Filesystem`
- `ProcessRunner`
- `TemplateRenderer`
- `EventDispatcherInterface`
- `ProvisionManager`

---

## Dependency Injection

### ProvisionAutowireTrait

**Location**: `Aegir\Provision\Drush\Commands\ProvisionAutowireTrait`

Wraps Drush's `AutowireTrait` and registers Provision services in the Drush container.

---

## Quick Reference Tables

### Context Properties by Type

| Property | Server | Platform | Site | Description |
|----------|--------|----------|------|-------------|
| `type` | ✓ | ✓ | ✓ | Context type |
| `aegir_root` | ✓ | | | Base directory |
| `web_group` | ✓ | | | Web server group |
| `http_port` | ✓ | | | HTTP port (80) |
| `http_ssl_port` | ✓ | | | HTTPS port (443) |
| `root` | | ✓ | ✓ | Platform/docroot path |
| `server` | | ✓ | | Server reference |
| `uri` | | | ✓ | Site domain |
| `platform` | | | ✓ | Platform reference |
| `db_server` | | | ✓ | DB server reference |
| `db_name` | | | ✓ | Database name |
| `db_user` | | | ✓ | Database user |
| `db_passwd` | | | ✓ | Database password |
| `ssl_enabled` | | | ✓ | Enable HTTPS |

### Event Lifecycle by Operation

| Operation | VALIDATE | BEFORE | AFTER | ROLLBACK |
|-----------|----------|--------|-------|----------|
| install | ✓ | ✓ | ✓ | ✓ |
| verify | ✓ | ✓ | ✓ | |
| backup | ✓ | ✓ | ✓ | |
| restore | ✓ | ✓ | ✓ | ✓ |
| migrate | ✓ | ✓ | ✓ | ✓ |
| clone | ✓ | ✓ | ✓ | ✓ |
| delete | ✓ | ✓ | ✓ | |
| deploy | ✓ | ✓ | ✓ | |
| enable | ✓ | ✓ | ✓ | |
| disable | ✓ | ✓ | ✓ | |
| lock | ✓ | ✓ | ✓ | |
| unlock | ✓ | ✓ | ✓ | |

### Service Methods by Context Type

| Service | Server | Platform | Site |
|---------|--------|----------|------|
| ApacheService | restart() | createPlatformConfig() | createSiteVhost(), enableSite() |
| MySqlService | - | - | createDatabase(), grant() |
| SslManager | - | - | ensureCertificate() |
| SettingsWriter | - | - | write() |

---

## Package Cross-References

For detailed documentation of each package, see:

- **[src/doc/README.md](../../src/doc/README.md)** - Source code overview
- **[src/Config/doc/README.md](../../src/Config/doc/README.md)** - Template rendering details
- **[src/Core/doc/README.md](../../src/Core/doc/README.md)** - Context system architecture
- **[src/Drush/doc/README.md](../../src/Drush/doc/README.md)** - Command development guide
- **[src/Event/doc/README.md](../../src/Event/doc/README.md)** - Event system details
- **[src/Service/doc/README.md](../../src/Service/doc/README.md)** - Service implementations

---

## Dependency Injection (Legacy Section)

**Note**: This section retained for backward compatibility. See [Drush Package](#drush-package) above.

### ProvisionAutowireTrait

**Location**: `Aegir\Provision\Drush\ProvisionServiceRegistry`

Registers Provision services in the Drush container.

**Registered Services**:

- `ContextRepository`
- `Filesystem`
- `ProcessRunner`
- `TemplateRenderer`
- `EventDispatcherInterface`
- `ProvisionManager`

**Usage**:

The registry is automatically called by `ProvisionAutowireTrait`. Services are resolved via type hints in command constructors.

---

## Event Constants

### ProvisionEvents

**Location**: `Aegir\Provision\Event\ProvisionEvents`

Defines all available event names.

**Event Phases**:

- **VALIDATE_*** - Pre-flight checks (throw exception to prevent)
- **BEFORE_*** - Preparation phase (modify event data)
- **AFTER_*** - Post-execution (notifications, logging)
- **ROLLBACK_*** - Cleanup on failure

**Available Events**:

```php
// Install events
ProvisionEvents::VALIDATE_INSTALL
ProvisionEvents::BEFORE_INSTALL
ProvisionEvents::AFTER_INSTALL
ProvisionEvents::ROLLBACK_INSTALL

// Verify events
ProvisionEvents::VALIDATE_VERIFY
ProvisionEvents::BEFORE_VERIFY
ProvisionEvents::AFTER_VERIFY

// Delete events
ProvisionEvents::VALIDATE_DELETE
ProvisionEvents::BEFORE_DELETE
ProvisionEvents::AFTER_DELETE

// ... and more for backup, restore, migrate, clone, etc.
```

See [Extension System](extension-system.md) for complete list and usage.

---

## Further Reading

### User Guides
- **[Core Concepts](concepts.md)** - Understanding contexts and workflows
- **[Quick Start](quickstart.md)** - Installation and first operations
- **[Extension System](extension-system.md)** - Creating event subscribers

### Technical Documentation
- **[D11 Architecture](../provision-d11.md)** - Complete architecture deep-dive
- **[Source Overview](../../src/doc/README.md)** - Source code structure
- **[Roadmap](../roadmap.md)** - Current status and future plans

### Package Details
Each package has comprehensive documentation in `src/*/doc/README.md`:
- Config - Template rendering
- Core - Context system, utilities
- Drush - Commands, service registry
- Event - Event classes, lifecycle
- Service - HTTP, DB, SSL, Drupal services
