# Event Package

**Location**: `src/Event/`  
**Purpose**: Event system for extension and customization

---

## Overview

The Event package provides a Symfony EventDispatcher-based system for extending Aegir Provision:

- **ProvisionEvent** - Base event class
- **ProvisionEvents** - Event name constants (52 events)
- **Specific Event Classes** - Type-safe event classes for each operation

This enables third-party code to:
- Validate operations before execution
- React to lifecycle events (before/after)
- Add custom logic to any provision workflow
- Clean up after failures (rollback events)

---

## Classes

### ProvisionEvent

**File**: `ProvisionEvent.php`

Base class for all Provision events. Extends Symfony's `Event` class.

**Constructor**:
```php
public function __construct(
    protected readonly string $operation,
    protected readonly Context $context,
    protected array $data = []
)
```

**Key Methods**:
- `getOperation(): string` - Returns operation phase: `validate`, `before`, `after`, `rollback`
- `getContext(): Context` - Returns the primary context for this event
- `getData(): array` - Returns all event data
- `setData(string $key, mixed $value): void` - Store custom data in event
- `hasData(string $key): bool` - Check if data key exists
- `getDataValue(string $key, mixed $default = null): mixed` - Get specific data value

**Usage**:
```php
$event = new InstallEvent('validate', $site, $platform, $server);
$event->setData('custom_field', 'value');
```

---

### ProvisionEvents

**File**: `ProvisionEvents.php`

Defines all available event constants (52 total).

**Event Phases**:

1. **VALIDATE Events** - Pre-flight checks, throw exceptions to prevent operation
   - `VALIDATE_INSTALL`, `VALIDATE_VERIFY`, `VALIDATE_BACKUP`, etc.

2. **BEFORE Events** - Preparation before main operation
   - `BEFORE_INSTALL`, `BEFORE_VERIFY`, `BEFORE_BACKUP`, etc.

3. **AFTER Events** - Post-operation actions (notifications, cleanup)
   - `AFTER_INSTALL`, `AFTER_VERIFY`, `AFTER_BACKUP`, etc.

4. **ROLLBACK Events** - Cleanup after failures
   - `ROLLBACK_INSTALL`, `ROLLBACK_RESTORE`, `ROLLBACK_MIGRATE`, `ROLLBACK_CLONE`

**Operations Covered**:
- Install, Verify, Backup, Restore, Deploy
- Migrate, Clone, Delete
- Enable, Disable, Lock, Unlock

---

## Specific Event Classes

### InstallEvent

**File**: `InstallEvent.php`

Fired during site installation.

**Methods**:
- `getSite(): Context` - Site being installed
- `getPlatform(): Context` - Platform hosting the site
- `getServer(): Context` - Server hosting the platform

### VerifyEvent

**File**: `VerifyEvent.php`

Fired during verify operations.

**Methods**:
- `getContext(): Context` - Context being verified (server, platform, or site)
- `getPlatform(): ?Context` - Platform (if verifying site)
- `getServer(): ?Context` - Server (if verifying platform or site)

### BackupEvent

**File**: `BackupEvent.php`

Fired during backup operations.

**Methods**:
- `getContext(): Context` - Context being backed up
- `getBackupPath(): string` - Path to backup archive

### RestoreEvent

**File**: `RestoreEvent.php`

Fired during restore operations.

**Methods**:
- `getContext(): Context` - Context being restored
- `getBackupPath(): string` - Path to backup archive being restored

### MigrateEvent

**File**: `MigrateEvent.php`

Fired during site migration between platforms.

**Methods**:
- `getSite(): Context` - Site being migrated
- `getOldPlatform(): Context` - Source platform
- `getNewPlatform(): Context` - Destination platform
- `getServer(): Context` - Server

### CloneEvent

**File**: `CloneEvent.php`

Fired during site cloning.

**Methods**:
- `getSourceSite(): Context` - Original site
- `getTargetSite(): Context` - Cloned site
- `getPlatform(): Context` - Platform
- `getServer(): Context` - Server

### DeleteEvent

**File**: `DeleteEvent.php`

Fired during context deletion.

**Methods**:
- `getContext(): Context` - Context being deleted
- `shouldDeleteDatabase(): bool` - Whether to drop database
- `shouldDeleteFiles(): bool` - Whether to remove files

### DeployEvent

**File**: `DeployEvent.php`

Fired during backup deployment to a site.

**Methods**:
- `getSite(): Context` - Target site
- `getBackupPath(): string` - Path to backup archive being deployed
- `getPlatform(): Context` - Platform
- `getServer(): Context` - Server

---

## Event Lifecycle

### Typical Event Flow

For a `provision:install` command:

1. **VALIDATE_INSTALL** - Validate inputs before starting
   - Subscriber throws exception → operation aborts
   
2. **BEFORE_INSTALL** - Prepare for installation
   - Modify event data
   - Set up prerequisites
   
3. **Main Operation** - ProvisionManager executes install logic
   
4. **AFTER_INSTALL** - Post-installation actions
   - Send notifications
   - Log completion
   - Integration callbacks

5. **ROLLBACK_INSTALL** (if exception thrown during main operation)
   - Clean up partial work
   - Remove created resources

---

## Creating Event Subscribers

**Basic Pattern**:
```php
use Aegir\Provision\Event\{ProvisionEvents, InstallEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProvisionEvents::VALIDATE_INSTALL => 'validateDomain',
            ProvisionEvents::AFTER_INSTALL => ['notifyAdmin', 10],
        ];
    }
    
    public function validateDomain(InstallEvent $event): void
    {
        $uri = $event->getSite()->get('uri');
        if (!$this->isValidDomain($uri)) {
            throw new \RuntimeException('Invalid domain: ' . $uri);
        }
    }
    
    public function notifyAdmin(InstallEvent $event): void
    {
        $uri = $event->getSite()->get('uri');
        $this->sendEmail('New site installed: ' . $uri);
    }
}
```

**Priority**: Higher numbers execute first (default: 0)

---

## Integration with ProvisionManager

Events are dispatched from ProvisionManager methods:

```php
// In ProvisionManager::install()
$event = new InstallEvent('validate', $site, $platform, $server);
$this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_INSTALL);

$event = new InstallEvent('before', $site, $platform, $server);
$this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_INSTALL);

// ... main installation logic ...

$event = new InstallEvent('after', $site, $platform, $server);
$this->dispatcher->dispatch($event, ProvisionEvents::AFTER_INSTALL);
```

---

## Related Documentation

- [Extension System Guide](../../../doc/guides/extension-system.md) - Complete guide to creating extensions
- [API Reference](../../../doc/guides/api-reference.md) - API documentation
- [Roadmap](../../../doc/roadmap.md) - Event system implementation status
- [Symfony EventDispatcher](https://symfony.com/doc/current/components/event_dispatcher.html) - Upstream documentation
