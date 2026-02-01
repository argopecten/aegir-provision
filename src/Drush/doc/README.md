# Drush Package

**Location**: `src/Drush/`  
**Purpose**: Drush 13 command integration and service registration

---

## Overview

The Drush package provides integration with Drush 13.7+:

- **Commands** - Drush 13.7+ command classes for all provision operations
- **Service Registry** - Registers Provision services in Drush container
- **Autowire Trait** - Dependency injection for commands

---

## Service Registry

### ProvisionServiceRegistry

**File**: `ProvisionServiceRegistry.php`

Registers Aegir Provision services into the Drush container for dependency injection.

**Key Method**:
```php
public static function register(ContainerInterface $container): void
```

**Registered Services**:
- `ContextRepository` - Context loading and persistence
- `Filesystem` - File system operations
- `ProcessRunner` - Command execution
- `TemplateRenderer` - Template rendering
- `EventDispatcherInterface` - Event system
- `ProvisionManager` - Main orchestrator

**Usage**:
Called during Drush bootstrap by `ProvisionAutowireTrait`.

**Why Needed**:
Provision services must be explicitly registered for autowiring in command classes (Drush uses its own container).

---

## Command Classes

**Location**: `src/Drush/Commands/`

All provision commands are Drush 13.7+ command classes using method-level attributes.

### Command Pattern

Each command follows this structure:

```php
<?php

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Commands\DrushCommands;
use Drush\Attributes\Command;
use Drush\Attributes\Argument;

class ProvisionInstallCommands extends DrushCommands
{
    use ProvisionAutowireTrait;
    
    private ProvisionManager $manager;
    
    public function __construct(ProvisionManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }
    
    #[Command(name: 'provision:install', description: 'Install a Drupal site')]
    #[Argument(name: 'site', description: 'Site context name')]
    public function install(string $site): int
    {
        $this->manager->install($site);
        return self::EXIT_SUCCESS;
    }
}
```

### Available Commands

#### Context Management
- **ProvisionSaveCommands** - `provision:save` - Save or update context
- **ProvisionVerifyCommands** - `provision:verify` - Verify configuration
- **ProvisionDeleteCommands** - `provision:delete` - Delete context

#### Site Operations
- **ProvisionInstallCommands** - `provision:install` - Install new site
- **ProvisionImportCommands** - `provision:import` - Import existing site
- **ProvisionBackupCommands** - `provision:backup` - Create backup
- **ProvisionRestoreCommands** - `provision:restore` - Restore from backup
- **ProvisionDeployCommands** - `provision:deploy` - Deploy backup to site

#### Site Lifecycle
- **ProvisionMigrateCommands** - `provision:migrate` - Migrate to different platform
- **ProvisionCloneCommands** - `provision:clone` - Clone site
- **ProvisionEnableCommands** - `provision:enable` - Enable site
- **ProvisionDisableCommands** - `provision:disable` - Disable site
- **ProvisionLockCommands** - `provision:lock` - Lock site
- **ProvisionUnlockCommands** - `provision:unlock` - Unlock site
- **ProvisionLoginResetCommands** - `provision:login-reset` - Reset admin login

#### Backend Commands
- **BackendParseCommands** - `backend:parse` - Parse backend output

---

## ProvisionAutowireTrait

**File**: `ProvisionAutowireTrait.php`

Provides dependency injection support for command classes.

**Purpose**:
- Enables constructor injection in Drush commands
- Integrates with ProvisionServiceRegistry
- Required because Drush doesn't use Drupal's container

**Usage**:
```php
class MyCommands extends DrushCommands
{
    use ProvisionAutowireTrait;
    
    public function __construct(
        private ProvisionManager $manager,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }
}
```

---

## Drush 13 Requirements

### Command Discovery

Commands are auto-discovered when:
1. Located in `src/Drush/Commands/` directory
2. Extend `Drush\Commands\DrushCommands` base class
3. Use `#[Command]` attribute on public methods
4. Filename ends with `*Commands.php`
5. Use `Drush\Commands\provision` namespace
6. Package installed via Composer

### Namespace Rules

For Drush 13.7+ site-wide command discovery, commands must use `Drush\Commands\provision` namespace.

**Correct**:
```php
namespace Drush\Commands\provision;
```

**Note**: Core Aegir Provision classes use `Aegir\Provision` namespace, but Drush commands must be in `Drush\Commands` namespace for auto-discovery.

### Service Registration

Services are registered via `ProvisionAutowireTrait` during Drush bootstrap (no `drush.services.yml` in Drush 13.7+). The trait overrides `create()` to call `ProvisionServiceRegistry::register()` before autowiring.

---

## Command Development

### Creating a New Command

1. **Create Command Class**:
```php
// src/Drush/Commands/ProvisionMyCommands.php
namespace Drush\Commands\provision;

use Drush\Commands\DrushCommands;
use Drush\Attributes\Command;

class ProvisionMyCommands extends DrushCommands
{
    use ProvisionAutowireTrait;
    
    public function __construct(private ProvisionManager $manager)
    {
        parent::__construct();
    }
    
    #[Command(name: 'provision:my-operation', description: 'My operation description')]
    public function myOperation(): int
    {
        // Implementation
        return self::EXIT_SUCCESS;
    }
}
```

2. **No Additional Registration Needed**:
   - Command is auto-discovered
   - Services are autowired via ProvisionServiceRegistry

### Testing Commands

```bash
# List all provision commands
drush list provision

# Get command help
drush provision:install --help

# Run command
drush provision:install @example.com
```

---

## Error Handling

Commands should:
- Return `self::EXIT_SUCCESS` (0) on success
- Return `self::EXIT_FAILURE` (1) on error
- Let exceptions bubble up for Drush to handle
- Use `$this->logger()` for output

**Example**:
```php
#[Command(name: 'provision:install')]
#[Argument(name: 'site', description: 'Site context name')]
public function install(string $site): int
{
    try {
        $this->manager->install($site);
        $this->logger()->success('Site installed successfully');
        return self::EXIT_SUCCESS;
    } catch (\Exception $e) {
        $this->logger()->error($e->getMessage());
        return self::EXIT_FAILURE;
    }
}
```

---

## Related Documentation

- [Drush 13 Documentation](https://www.drush.org/13.x/) - Official Drush docs
- [Symfony Console](https://symfony.com/doc/current/console.html) - Console component docs
- [API Reference](../../../doc/guides/api-reference.md) - ProvisionManager API
- [Quick Start](../../../doc/guides/quickstart.md) - Command usage examples
- [D11 Architecture](../../../doc/provision-d11.md) - Command integration details
