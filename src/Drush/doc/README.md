# Drush Package

**Location**: `src/Drush/`  
**Purpose**: Drush 13 command integration and service registration

---

## Overview

The Drush package provides integration with Drush 13.7+:

- **Commands** - Symfony Console commands for all provision operations
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
Called automatically by Drush during bootstrap via `drush.services.yml`.

**Why Needed**:
Drush 13 doesn't use Drupal's container. Services must be explicitly registered for autowiring in command classes.

---

## Command Classes

**Location**: `src/Drush/Commands/`

All provision commands are Symfony Console commands with Drush integration.

### Command Pattern

Each command follows this structure:

```php
<?php

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'provision:install')]
class ProvisionInstallCommand extends DrushCommands
{
    use ProvisionAutowireTrait;
    
    private ProvisionManager $manager;
    
    public function __construct(ProvisionManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }
    
    protected function configure(): void
    {
        $this
            ->setDescription('Install a Drupal site')
            ->addArgument('site', InputArgument::REQUIRED, 'Site context name');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $siteName = $input->getArgument('site');
        $this->manager->install($siteName);
        return Command::SUCCESS;
    }
}
```

### Available Commands

#### Context Management
- **ProvisionSaveCommand** - `provision:save` - Save or update context
- **ProvisionVerifyCommand** - `provision:verify` - Verify configuration
- **ProvisionDeleteCommand** - `provision:delete` - Delete context

#### Site Operations
- **ProvisionInstallCommand** - `provision:install` - Install new site
- **ProvisionImportCommand** - `provision:import` - Import existing site
- **ProvisionBackupCommand** - `provision:backup` - Create backup
- **ProvisionRestoreCommand** - `provision:restore` - Restore from backup
- **ProvisionDeployCommand** - `provision:deploy` - Deploy backup to site

#### Site Lifecycle
- **ProvisionMigrateCommand** - `provision:migrate` - Migrate to different platform
- **ProvisionCloneCommand** - `provision:clone` - Clone site
- **ProvisionEnableCommand** - `provision:enable` - Enable site
- **ProvisionDisableCommand** - `provision:disable` - Disable site
- **ProvisionLockCommand** - `provision:lock` - Lock site
- **ProvisionUnlockCommand** - `provision:unlock` - Unlock site
- **ProvisionLoginResetCommand** - `provision:login-reset` - Reset admin login

#### Backend Commands
- **BackendParseCommand** - `backend:parse` - Parse backend output

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
class MyCommand extends DrushCommands
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
2. Use `#[AsCommand]` attribute
3. Implement `configure()` and `execute()` methods
4. Package installed via Composer

### Namespace Rules

Commands must use `Aegir\Provision\Drush\Commands` namespace, not `Drush\Commands`.

**Correct**:
```php
namespace Aegir\Provision\Drush\Commands;
```

**Incorrect**:
```php
namespace Drush\Commands;  // Only for site-wide commands
```

### Service Registration

Services must be registered in `drush.services.yml`:

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    
  provision.service_registry:
    class: Aegir\Provision\Drush\ProvisionServiceRegistry
    tags:
      - { name: service_subscriber }
```

---

## Command Development

### Creating a New Command

1. **Create Command Class**:
```php
// src/Drush/Commands/ProvisionMyCommand.php
#[AsCommand(name: 'provision:my-operation')]
class ProvisionMyCommand extends DrushCommands
{
    use ProvisionAutowireTrait;
    
    public function __construct(private ProvisionManager $manager)
    {
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this->setDescription('My operation description');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Implementation
        return Command::SUCCESS;
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
- Return `Command::SUCCESS` (0) on success
- Return `Command::FAILURE` (1) on error
- Let exceptions bubble up for Drush to handle
- Use `$this->logger` for output

**Example**:
```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    try {
        $this->manager->install($siteName);
        $this->logger->success('Site installed successfully');
        return Command::SUCCESS;
    } catch (\Exception $e) {
        $this->logger->error($e->getMessage());
        return Command::FAILURE;
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
