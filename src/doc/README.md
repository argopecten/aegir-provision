# Source Code Documentation

**Location**: `src/`  
**Purpose**: Core implementation of Aegir Provision for Drupal 11

---

## Directory Structure

```
src/
├── Config/                    # Template rendering
│   ├── TemplateRenderer.php
│   └── doc/README.md
│
├── Core/                      # Foundational abstractions
│   ├── Context.php
│   ├── ContextRepository.php
│   ├── ContextType.php
│   ├── Filesystem.php
│   ├── ProcessRunner.php
│   ├── ConfigPaths.php
│   ├── PlatformRoot.php
│   ├── AliasStore.php
│   └── doc/README.md
│
├── Drush/                     # Drush 13 integration
│   ├── Commands/              # All provision:* commands
│   ├── ProvisionServiceRegistry.php
│   └── doc/README.md
│
├── Event/                     # Event system
│   ├── ProvisionEvent.php
│   ├── ProvisionEvents.php
│   ├── InstallEvent.php
│   ├── VerifyEvent.php
│   ├── BackupEvent.php
│   ├── RestoreEvent.php
│   ├── MigrateEvent.php
│   ├── CloneEvent.php
│   ├── DeleteEvent.php
│   ├── DeployEvent.php
│   └── doc/README.md
│
├── Service/                   # Service implementations
│   ├── Db/MySqlService.php
│   ├── Drupal/SettingsWriter.php
│   ├── Http/ApacheService.php
│   ├── Ssl/SslManager.php
│   └── doc/README.md
│
└── ProvisionManager.php       # Central orchestrator
```

---

## Package Overview

### [Config/](Config/doc/README.md)
Template-based configuration file generation for Apache vhosts, Drupal settings.php, etc.

**Key Classes**: `TemplateRenderer`

### [Core/](Core/doc/README.md)
Foundational abstractions: Context system, filesystem operations, process execution, path resolution.

**Key Classes**: `Context`, `ContextRepository`, `Filesystem`, `ProcessRunner`

### [Drush/](Drush/doc/README.md)
Drush 13 command classes and service registration for dependency injection.

**Key Classes**: `ProvisionServiceRegistry`, 17 command classes

### [Event/](Event/doc/README.md)
Symfony EventDispatcher-based extension system with 52 lifecycle events.

**Key Classes**: `ProvisionEvent`, `ProvisionEvents`, 8 specific event classes

### [Service/](Service/doc/README.md)
Modular service implementations for HTTP, database, SSL, and Drupal concerns.

**Key Classes**: `ApacheService`, `MySqlService`, `SslManager`, `SettingsWriter`

### ProvisionManager
Central orchestrator that coordinates all operations by composing services and dispatching events.

---

## Architecture Overview

### Dependency Flow

```
Drush Commands
    ↓ (inject)
ProvisionManager
    ↓ (uses)
Services (Http, Db, Ssl, Drupal)
    ↓ (uses)
Core (Context, Filesystem, ProcessRunner)
    ↓ (uses)
Config (TemplateRenderer)
```

### Event Flow

```
Drush Command
    ↓
ProvisionManager
    ├→ Dispatch VALIDATE event
    ├→ Dispatch BEFORE event
    ├→ Execute operation (using Services)
    ├→ Dispatch AFTER event
    └→ Dispatch ROLLBACK event (on error)
```

### Context System

Three immutable context types stored as Drush YAML aliases:

1. **Server** (`@server_master`) - Hosting infrastructure
2. **Platform** (`@platform_d11`) - Drupal codebase
3. **Site** (`@example.com`) - Individual Drupal site

All operations work with contexts loaded from `~/.drush/sites/`.

---

## Key Design Patterns

### Dependency Injection
Services and managers receive dependencies via constructor:
```php
public function __construct(
    private readonly ContextRepository $contexts,
    private readonly Filesystem $filesystem,
    private readonly EventDispatcherInterface $dispatcher
) {}
```

### Immutable Contexts
Context objects are read-only after creation:
```php
$site = new Context('example.com', 'site', $data);
$uri = $site->get('uri');  // Read
// No setters - contexts are immutable
```

### Template-Based Config
Configuration files generated from PHP templates:
```php
$content = $this->templates->render('apache/vhost.tpl.php', [
    'uri' => 'example.com',
    'docroot' => '/var/aegir/platforms/drupal-11/web',
]);
```

### Event-Driven Extensibility
Third-party code extends via Symfony events:
```php
class MySubscriber implements EventSubscriberInterface {
    public static function getSubscribedEvents(): array {
        return [
            ProvisionEvents::AFTER_INSTALL => 'onInstall',
        ];
    }
}
```

---

## Development Guidelines

### Code Standards

- **PHP 8.3+** features (constructor property promotion, readonly, typed properties)
- **Strict types** in all files (`declare(strict_types=1);`)
- **Type hints** on all parameters and return values
- **Final classes** by default (no inheritance)
- **Dependency injection** over global state

### Error Handling

- Throw `RuntimeException` for general errors
- Throw `InvalidArgumentException` for invalid input
- Let exceptions bubble up - caught by Drush commands

### Testing

- Unit tests for core logic
- Integration tests for full workflows
- Mock Context, Filesystem, ProcessRunner for isolation

---

## Package Documentation

Each package has detailed documentation in its `doc/` subdirectory:

- **[Config/doc/README.md](Config/doc/README.md)** - Template rendering
- **[Core/doc/README.md](Core/doc/README.md)** - Core abstractions
- **[Drush/doc/README.md](Drush/doc/README.md)** - Command integration
- **[Event/doc/README.md](Event/doc/README.md)** - Event system
- **[Service/doc/README.md](Service/doc/README.md)** - Service implementations

---

## Related Documentation

### User Documentation
- [Quick Start Guide](../doc/guides/quickstart.md) - Get started quickly
- [Core Concepts](../doc/guides/concepts.md) - Understand the system
- [Extension System](../doc/guides/extension-system.md) - Create extensions

### Technical Documentation
- [API Reference](../doc/guides/api-reference.md) - Complete API docs
- [D11 Architecture](../doc/provision-d11.md) - Detailed architecture
- [Roadmap](../doc/roadmap.md) - Current status and plans

### Development
- [.github/AI-INSTRUCTIONS.md](../.github/AI-INSTRUCTIONS.md) - Coding standards for AI agents
- [README.md](../README.md) - Project overview
