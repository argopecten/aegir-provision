# Create Manager

Create a new Manager class in `src/Manager/` that implements a provision operation with the full event lifecycle.

## User Input Needed

Ask the user:
1. What operation does this manager handle? (e.g., `rename`, `audit`)
2. What context types does it operate on (server/platform/site)?
3. What infrastructure does it touch (filesystem, Apache, MySQL, ProcessRunner)?
4. Does it need rollback support?

## Steps

### 1. Create Event class (if new operation)

Path: `src/Event/{Name}Event.php`

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

final class {Name}Event extends ProvisionEvent {}
```

### 2. Add Event Constants

In `src/Event/ProvisionEvents.php`, add 4 constants for the new operation:

```php
// {Name} operation
public const {NAME}_VALIDATE = 'provision.{name}.validate';
public const {NAME}_BEFORE   = 'provision.{name}.before';
public const {NAME}_AFTER    = 'provision.{name}.after';
public const {NAME}_ROLLBACK = 'provision.{name}.rollback';
```

### 3. Create the Manager class

Path: `src/Manager/{Name}Manager.php`

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Event\{Name}Event;
use Aegir\Provision\Event\ProvisionEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class {Name}Manager
{
    public function __construct(
        private readonly ContextRepository $contexts,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ContextLoader $loader,
        private readonly PathResolver $pathResolver,
        // Add other dependencies as needed (e.g., DatabaseManager, service)
    ) {}

    public function {name}(string $contextName): void
    {
        $context = $this->contexts->load($contextName);
        $event = new {Name}Event($context);

        try {
            $this->dispatcher->dispatch($event, ProvisionEvents::{NAME}_VALIDATE);
            $this->dispatcher->dispatch($event, ProvisionEvents::{NAME}_BEFORE);

            // ── Implementation ──────────────────────────────────────────
            // Load related contexts if needed:
            // $related = $this->loader->load{Related}($context);

            // Do the work:
            // $this->filesystem->writeFile(...);

            $this->logger->success('{Name} completed for {context}.', ['context' => $contextName]);
            $this->dispatcher->dispatch($event, ProvisionEvents::{NAME}_AFTER);
        }
        catch (\Throwable $e) {
            $this->dispatcher->dispatch($event, ProvisionEvents::{NAME}_ROLLBACK);
            throw $e;
        }
    }
}
```

### 4. Wire into ProvisionManager

In `src/ProvisionManager.php`, add a lazy-loaded accessor:

```php
private ?{Name}Manager ${name}Manager = null;

public function {name}Manager(): {Name}Manager
{
    return $this->{name}Manager ??= new {Name}Manager(
        $this->contextRepository(),
        $this->filesystem(),
        $this->logger(),
        $this->eventDispatcher(),
        $this->contextLoader(),
        $this->pathResolver(),
        // Other deps available: $this->databaseManager(), etc.
    );
}
```

### 5. Create the Drush Command

Path: `src/Drush/Commands/Provision{Name}Commands.php`

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

final class Provision{Name}Commands extends DrushCommands
{
    use ProvisionAutowireTrait;

    #[CLI\Command(name: 'provision:{name}')]
    #[CLI\Argument(name: 'context', description: 'Context alias (e.g. @example.com)')]
    #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
    public function {name}(string $context): int
    {
        try {
            $this->getProvisionManager()->{name}Manager()->{name}($context);
            $this->logger()->success("provision:{name} completed: $context");
            return self::EXIT_SUCCESS;
        }
        catch (\Exception $e) {
            $this->logger()->error($e->getMessage());
            return self::EXIT_FAILURE;
        }
    }
}
```

No registration needed — Drush auto-discovers from `src/Drush/Commands/` via PSR-4.

### 6. Test

```bash
drush list --filter=provision      # Verify command appears
drush provision:{name} @server_master -v
drush provision:{name} @server_master --debug
```

## Dependency Reference (available from ProvisionManager)

| Method | Returns | Use for |
|--------|---------|---------|
| `contextRepository()` | `ContextRepository` | Load/save/delete contexts |
| `filesystem()` | `Filesystem` | File operations (ensureDir, writeFile, remove, symlink) |
| `processRunner()` | `ProcessRunner` | Shell commands |
| `contextLoader()` | `ContextLoader` | Load platform/server related to a context |
| `pathResolver()` | `PathResolver` | docroot, site path, lock path |
| `databaseManager()` | `DatabaseManager` | DB credentials and grants |
| `logger()` | `LoggerInterface` | PSR-3 logger |
| `eventDispatcher()` | `EventDispatcherInterface` | Symfony event dispatcher |
| `getHttpService($server)` | `HttpServiceInterface` | Apache/Nginx ops |
| `getDbService($server)` | `DbServiceInterface` | MySQL/Postgres ops |

## Rules

- `final` class — always
- VALIDATE → BEFORE → execute → AFTER — always in try/catch with ROLLBACK
- Use concrete `{Name}Event extends ProvisionEvent` — NEVER `new ProvisionEvent(...)` (abstract)
- Idempotent — safe to run multiple times
- No `\Drupal::` — standalone package
- Read `VerificationManager.php` before implementing to match patterns
