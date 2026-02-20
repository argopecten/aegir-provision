# Architecture Overview

Understanding how Aegir Provision components work together.

---

## Component Map

```
┌─────────────────────────────────────────────────────────────────┐
│                      Aegir Provision D11                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Drush Commands (provision:*)                                  │
│      ↓ (inject)                                                │
│  ProvisionManager ←──────────────────┐                         │
│      ↓ (orchestrates)                │                         │
│  ┌──────────────────────────────────┴────────────────────┐    │
│  │ Services                                              │    │
│  │  • ApacheService  (HTTP/vhosts)                       │    │
│  │  • MySqlService   (databases)                         │    │
│  │  • SslManager     (certificates)                      │    │
│  │  • SettingsWriter (Drupal config)                     │    │
│  └────┬──────────────────────────────────────────────────┘    │
│       ↓ (uses)                                                 │
│  ┌─────────────────────────────────────────────────────┐      │
│  │ Core Utilities                                      │      │
│  │  • Context / ContextRepository (data layer)         │      │
│  │  • Filesystem (file operations)                     │      │
│  │  • ProcessRunner (shell commands)                   │      │
│  │  • TemplateRenderer (config generation)             │      │
│  └─────────────────────────────────────────────────────┘      │
│                                                                 │
│  Event System (52 lifecycle events)                            │
│      → VALIDATE → BEFORE → [Operation] → AFTER → ROLLBACK     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Package Hierarchy

### Layer 1: Foundation (Core, Config)

**Core Package** - Basic building blocks:
- `Context` - Immutable data structures
- `ContextRepository` - Persistence via Drush aliases
- `Filesystem` - Safe file operations
- `ProcessRunner` - Command execution
- `ConfigPaths`, `PlatformRoot` - Path utilities

**Config Package** - Template rendering:
- `TemplateRenderer` - PHP template engine

**Dependencies**: Symfony Filesystem, Symfony Process

---

### Layer 2: Services

**Service Package** - Domain-specific implementations:
- `Http/ApacheService` - Web server configuration
- `Db/MySqlService` - Database management
- `Ssl/SslManager` - Certificate handling
- `Drupal/SettingsWriter` - Drupal configuration

**Dependencies**: Core, Config packages

---

### Layer 3: Orchestration

**ProvisionManager** - Coordinates operations:
- Loads contexts via `ContextRepository`
- Calls services for specific tasks
- Dispatches lifecycle events
- Handles errors and rollback

**Dependencies**: All packages below it

---

### Layer 4: Extension

**Event Package** - Extensibility system:
- `ProvisionEvent` - Base event class
- `ProvisionEvents` - 52 event constants
- Specific events: `InstallEvent`, `MigrateEvent`, etc.

**Integration**: Used by ProvisionManager

---

### Layer 5: Interface

**Drush Package** - User interface:
- 17 command classes (provision:*)
- `ProvisionServiceRegistry` - Dependency injection
- `ProvisionAutowireTrait` - Command autowiring

**Dependencies**: All packages

---

## Data Flow

### Context Lifecycle

```
1. User creates context
   ↓
2. drush provision:save context_name --type=server --data='{...}'
   ↓
3. ContextRepository saves to ~/.drush/sites/context.yml
   ↓
4. Context becomes available for operations
```

### Operation Flow (Example: provision:install)

```
1. ProvisionInstallCommand receives site context_name argument
   ↓
2. Loads contexts: site, platform, server
   ↓
3. ProvisionManager.install(site)
   ↓
4. Dispatch VALIDATE_INSTALL event
   ↓ (subscribers can throw exception to abort)
5. Dispatch BEFORE_INSTALL event
   ↓ (subscribers can modify event data)
6. Execute installation:
   • MySqlService.createDatabase()
   • MySqlService.grant()
   • SettingsWriter.write()
   • ApacheService.createSiteVhost()
   • ProcessRunner.run(['drush', 'site:install', ...])
   • ApacheService.restart()
   ↓
7. Dispatch AFTER_INSTALL event
   ↓ (subscribers send notifications, log, etc.)
8. Return success

If exception at step 6:
   ↓
9. Dispatch ROLLBACK_INSTALL event
   ↓ (subscribers clean up partial work)
10. Re-throw exception
```

---

## Directory Structure

### Project Layout

```
aegir-provision/
├── composer.json               # Dependencies
├── README.md                   # Project overview
│
├── doc/                        # Documentation
│   ├── Home.md                 # Main entry point
│   ├── provision-d11.md        # Complete architecture
│   ├── provision-d7.md         # D7 reference
│   ├── roadmap.md              # Status and plans
│   └── guides/                 # User & developer guides
│       ├── quickstart.md
│       ├── concepts.md
│       ├── architecture.md     # This file
│       ├── api-reference.md
│       └── extension-system.md
│
├── src/                        # Source code
│   ├── ProvisionManager.php    # Central orchestrator
│   ├── Config/                 # Template rendering
│   ├── Core/                   # Foundation classes
│   ├── Drush/                  # Commands
│   ├── Event/                  # Event system
│   └── Service/                # Service implementations
│
├── resources/                  # Assets
│   └── templates/              # PHP templates
│       ├── apache/
│       └── drupal/
│
└── examples/                   # Example code
    └── CustomValidationSubscriber.php
```

### Runtime Layout (Installed)

```
/var/aegir/                     # aegir_root
├── platforms/                  # Platform codebases
│   └── drupal-11/
│       ├── composer.json
│       ├── vendor/
│       └── web/                # Docroot
│           └── sites/
│               └── example.com/
│                   ├── settings.php
│                   └── files/
│
└── .config/                    # config_path
    ├── apache/
    │   ├── vhost.d/            # HTTP vhosts
    │   ├── vhost_ssl.d/        # HTTPS vhosts
    │   └── platform.d/         # Platform configs
    └── ssl/
        └── example.com/
            ├── cert.pem
            └── key.pem

~/.drush/sites/                 # Context storage
├── server_master.server.yml
├── platform_d11.platform.yml
└── example.com.site.yml
```

---

## Service Communication Patterns

### 1. Direct Service Calls with Value Objects

ProvisionManager and services use immutable value objects:

```php
use Aegir\Provision\Core\ValueObject\{DatabaseCredentials, ServerPaths, ApacheVhostConfig};

// In DatabaseManager::ensureSiteDatabase()
$credentials = new DatabaseCredentials(
    host: $server->get('db_host', 'localhost'),
    port: (int) $server->get('db_port', 3306),
    name: $dbName,
    username: $dbUser,
    password: $dbPassword
);

// Returns immutable value object instead of array
return $credentials;

// In InstallationManager
$config = new ApacheVhostConfig(
    serverName: $site->get('uri'),
    documentRoot: $docroot,
    port: (int) $server->get('http_port', 80),
    sslCertPath: $site->get('ssl_cert'),
    sslKeyPath: $site->get('ssl_key')
);
$this->httpService->enableSite($site, $platform, $server, $config);
```

### 2. Event-Based Extension

Third-party code subscribes to events:

```php
class MySubscriber implements EventSubscriberInterface {
    public function onValidateInstall(InstallEvent $event): void {
        // Custom validation
        if (!$this->isAllowed($event->getSite()->get('uri'))) {
            throw new \RuntimeException('Domain not allowed');
        }
    }
}
```

### 3. Template-Based Configuration

Services use templates for config generation:

```php
// In ApacheService
$vhostConfig = $this->templates->render('apache/vhost.tpl.php', [
    'uri' => $site->get('uri'),
    'docroot' => $docroot,
]);
$this->filesystem->dumpFile($vhostPath, $vhostConfig);
```

### 4. Process Execution

Services run external commands:

```php
// In MySqlService
$this->runner->run([
    'mysql',
    '-e',
    "CREATE DATABASE IF NOT EXISTS `{$dbName}`"
]);
```

---

## Extension Points

### 1. Event Subscribers

**When to use**: Add custom logic to any operation

**How**: Create event subscriber class
```php
class MySubscriber implements EventSubscriberInterface {
    public static function getSubscribedEvents(): array {
        return [
            ProvisionEvents::AFTER_INSTALL => 'onInstall',
        ];
    }
}
```

**Examples**:
- Domain validation
- DNS updates
- Notifications (email, Slack, webhooks)
- Custom backup destinations
- Integration with monitoring tools

### 2. Service Plugin System (Planned)

**When to use**: Replace/extend service implementations

**How**: Implement service interface
```php
class NginxService implements HttpServiceInterface {
    // Alternative to ApacheService
}
```

**Examples**:
- Nginx instead of Apache
- PostgreSQL instead of MySQL
- Custom DNS providers
- Alternative backup systems

### 3. Template Overrides (Planned)

**When to use**: Customize generated configuration

**How**: Provide custom template directory
```php
$renderer = new TemplateRenderer('/custom/templates');
```

**Examples**:
- Custom vhost configurations
- Alternative settings.php structure
- Multi-tenant customizations

---

## Context System Deep Dive

### Why Immutable Contexts?

**Benefits**:
- **Thread-safe**: Multiple operations can read same context
- **Predictable**: Context can't change during operation
- **Auditable**: Context state at operation time is preserved
- **Testable**: Easy to mock in tests

**Example**:
```php
// Load context
$site = $this->contexts->load('example.com');

// Get properties (always succeeds)
$uri = $site->get('uri');
$platform = $site->get('platform');

// Contexts are immutable - no setters
// To change: create new context and save
$updated = new Context($site->name(), $site->type(), [
    ...$site->all(),
    'ssl_enabled' => true,
]);
$this->contexts->save($updated);
```

### Context Relationships

```
Server Context (@server_master)
    ↓ (hosts)
Platform Context (@platform_d11)
    ↓ (runs)
Site Context (@example.com)
```

**Navigation**:
```php
$site = $contexts->load('example.com');
$platform = $contexts->load($site->get('platform'));
$server = $contexts->load($platform->get('server'));
```

---

## Error Handling Strategy

### Exception Flow

```
Operation starts
    ↓
Try {
    VALIDATE event → (exception aborts immediately)
    BEFORE event → (exception aborts immediately)
    Main logic → (exception triggers rollback)
    AFTER event → (exception logged, operation succeeds)
}
Catch {
    ROLLBACK event → (cleanup partial work)
    Re-throw exception
}
```

### Error Types

| Exception | When | Effect |
|-----------|------|--------|
| During VALIDATE | Pre-flight check failed | Operation aborted, no changes |
| During BEFORE | Preparation failed | Operation aborted, no changes |
| During main logic | Operation failed | Rollback triggered, exception thrown |
| During AFTER | Post-processing failed | Logged, operation considered successful |
| During ROLLBACK | Cleanup failed | Logged, original exception thrown |

---

## Performance Considerations

### Context Loading

- Contexts loaded lazily
- Cached in ContextRepository during operation
- YAML parsing overhead minimal (<1ms per context)

### Template Rendering

- Native PHP `include()` - very fast
- No caching needed (rendered on-demand)
- Templates typically <5KB

### Process Execution

- Most time spent in external commands (drush, mysql, apache)
- Use `--quiet` flags when output not needed
- Background processes via `runAsync()` (not yet implemented)

### Event Dispatching

- Minimal overhead (~0.1ms per event)
- Only subscribers for specific events are called
- No global event listeners

---

### Security Considerations

### Credential Handling

**Value Objects for Type Safety**:
- Database credentials encapsulated in `DatabaseCredentials` value object
- Immutable and validated at construction time
- No plain array passing reduces security bugs

**Storage**:
- Database passwords stored in context YAML (file permissions: 0600)
- MySQL authentication via PDO with prepared statements (no SQL injection)
- No credentials in command output or logs

### File Permissions

- `settings.php`: 0440 (read-only)
- Site `files/`: 0775 (web-writable)
- Context files: 0600 (user-only)
- SSL keys: 0600 (user-only)

### Command Injection Prevention

- All external commands use array syntax (not shell strings)
- Symfony Process escapes arguments automatically
- No user input directly in shell commands

---

## Testing Strategy

### Unit Tests

**What**: Individual classes in isolation
- Mock dependencies (Context, Filesystem, ProcessRunner)
- Test business logic without side effects
- Fast (<1s for entire suite)

### Integration Tests

**What**: Full workflows with real filesystem/database
- Use temporary directories
- Use test database (aegir_test_*)
- Clean up after each test

### Example Test Structure

```php
class InstallTest extends TestCase {
    private ProvisionManager $manager;
    
    protected function setUp(): void {
        // Mock dependencies
        $this->contexts = $this->createMock(ContextRepository::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        
        $this->manager = new ProvisionManager(
            $this->contexts,
            $this->filesystem,
            // ... other mocks
        );
    }
    
    public function testInstallCreatesDatabase(): void {
        // Arrange
        $site = new Context('test.com', 'site', ['uri' => 'test.com']);
        
        // Act
        $this->manager->install('test.com');
        
        // Assert
        $this->assertDatabaseExists('test_com');
    }
}
```

---

## Related Documentation

- **[Core Concepts](concepts.md)** - Fundamental concepts explained
- **[API Reference](api-reference.md)** - Complete API documentation
- **[Extension System](extension-system.md)** - Creating extensions
- **[D11 Architecture](../provision-d11.md)** - Detailed technical documentation
- **[Source Overview](../../src/doc/README.md)** - Source code structure
