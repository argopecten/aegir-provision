# AI Skills — aegir-provision (Backend)

> Actionable instruction sets for performing specialized tasks in the provision backend.
> Each skill is a step-by-step procedure an AI agent can follow to completion.

---

## Skill 1: Create a New Drush Command

**When**: You need to add a new `provision:*` command (e.g., `provision:rename`).

### Steps

1. **Create command class** at `src/Drush/Commands/Provision{Name}Commands.php`:
   ```php
   <?php

   declare(strict_types=1);

   namespace Aegir\Provision\Drush\Commands;

   use Drush\Commands\DrushCommands;
   use Drush\Attributes as CLI;
   use Aegir\Provision\Drush\Commands\ProvisionAutowireTrait;

   final class Provision{Name}Commands extends DrushCommands
   {
       use ProvisionAutowireTrait;

       #[CLI\Command(name: 'provision:{name}')]
       #[CLI\Argument(name: 'context', description: 'Context name')]
       #[CLI\Usage(name: 'provision:{name} @site.example', description: 'Description')]
       public function {name}(string $context): void
       {
           $pm = $this->getProvisionManager();
           $ctx = $pm->contextRepository()->load($context);

           // Implementation here
       }
   }
   ```

2. **Follow naming conventions**:
   - Class: `Provision{Name}Commands` (PascalCase)
   - Command name: `provision:{name}` (lowercase, colon separator, NOT dash)
   - File: `src/Drush/Commands/Provision{Name}Commands.php`

3. **Use `ProvisionAutowireTrait`** — it injects `ProvisionManager` via `$this->getProvisionManager()`

4. **If firing events**, create event constants in `ProvisionEvents.php`:
   ```php
   public const {NAME}_VALIDATE = 'provision.{name}.validate';
   public const {NAME}_BEFORE = 'provision.{name}.before';
   public const {NAME}_AFTER = 'provision.{name}.after';
   public const {NAME}_ROLLBACK = 'provision.{name}.rollback';
   ```

5. **If firing events**, create a concrete event class at `src/Event/{Name}Event.php`:
   ```php
   final class {Name}Event extends ProvisionEvent {}
   ```

6. **Register discovery**: No manual registration needed — Drush auto-discovers from `src/Drush/Commands/` via PSR-4.

7. **Test manually**: `drush provision:{name} @context_name --debug`

### Anti-patterns
- ❌ Do NOT use `#[CLI\Command(name: 'provision-{name}')]` (dash syntax is legacy)
- ❌ Do NOT extend `DrushCommands` without `ProvisionAutowireTrait`
- ❌ Do NOT instantiate `ProvisionEvent` directly (it's abstract)
- ❌ Do NOT use `drush.services.yml` — services are registered in `ProvisionServiceRegistry`

---

## Skill 2: Add a New Service Implementation

**When**: You need a new backend service (e.g., Nginx, PostgreSQL, Certbot).

### Steps

1. **Identify the service interface** from `src/Service/`:
   - HTTP → `HttpServiceInterface`
   - Database → `DbServiceInterface`
   - SSL → `SslServiceInterface`
   - Cron → `CronServiceInterface`

2. **Create implementation** at `src/Service/{Type}/{Name}Service.php`:
   ```php
   <?php

   declare(strict_types=1);

   namespace Aegir\Provision\Service\{Type};

   use Aegir\Provision\Service\{Type}ServiceInterface;

   final class {Name}Service implements {Type}ServiceInterface
   {
       // Implement ALL interface methods
   }
   ```

3. **Register in `ProvisionServiceRegistry::register()`**:
   ```php
   $container->add('{type}.{name}', \Aegir\Provision\Service\{Type}\{Name}Service::class);
   ```

4. **Create templates** if needed:
   - Apache templates live in `templates/` directory
   - Use `TemplateRenderer::render()` with priority-based resolution

5. **Update `ServiceRegistry`** to recognize the new service name in factory resolution.

### Interface Contracts

| Interface | Required Methods |
|-----------|-----------------|
| `HttpServiceInterface` | `ensureServerLayout()`, `enableSite()`, `disableSite()`, `removeSite()`, `reload()` |
| `DbServiceInterface` | `ensureDatabase()`, `ensureUser()`, `grant()`, `dropDatabase()`, `dump()`, `import()` |
| `SslServiceInterface` | `resolve()` |
| `CronServiceInterface` | `addCron()`, `deleteCron()`, `hasCron()`, `listCron()`, `reload()` |

---

## Skill 3: Work with the Context System

**When**: You need to create, load, modify, or delete a server/platform/site context.

### Loading a Context

```php
$pm = $this->getProvisionManager();
$ctx = $pm->contextRepository()->load('@server_master');
$type = $ctx->type();    // ContextType::SERVER
$ip = $ctx->get('ip');   // value or null
```

### Creating a Context

```php
use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextType;

$ctx = new Context(
    name: 'server_web2',
    type: ContextType::SERVER,
);
$ctx->set('ip', '10.0.0.2');
$ctx->set('http_service', 'apache');
$pm->contextRepository()->save($ctx);
```

### YAML Alias Structure

Contexts serialize to `drush/sites/aegir/{name}.site.yml`:

```yaml
# Server context
provision:
  type: server
  ip: 10.0.0.2
  http_service: apache
  db_service: mysql
  ssl_service: default
  cron_service: system

# Platform context
provision:
  type: platform
  server: "@server_master"
  root: /var/aegir/platforms/drupal11
  makefile: null
  web_server: "@server_master"

# Site context
provision:
  type: site
  platform: "@platform_drupal11"
  db_server: "@server_master"
  uri: site.example.com
  profile: standard
  language: en
root: /var/aegir/platforms/drupal11
uri: site.example.com
```

### Context Hierarchy

- **Site** → references **Platform** (via `platform` key) + **Server** (via `db_server` key)
- **Platform** → references **Server** (via `server` and `web_server` keys)
- **Server** → standalone, hosts services

### Key Rules
- Context identity (`name`, `type`) is **immutable** — set at construction, no setters
- Context properties are **mutable** — use `$ctx->set()` to modify, then `save()` to persist
- Some properties (e.g., `root`, `server`) are fixed by convention once set
- Names follow pattern: `server_*`, `platform_*`, `site_*`
- References use `@` prefix: `"@server_master"`
- All YAML files live in `drush/sites/aegir/`

---

## Skill 4: Create a New Manager

**When**: You need a new orchestration layer for a group of related operations.

### Steps

1. **Create class** at `src/Manager/{Name}Manager.php`:
   ```php
   <?php

   declare(strict_types=1);

   namespace Aegir\Provision\Manager;

   use Aegir\Provision\Core\ContextRepository;
   use Aegir\Provision\Core\Filesystem;

   final class {Name}Manager
   {
       public function __construct(
           private readonly ContextRepository $contextRepository,
           private readonly Filesystem $filesystem,
       ) {}
   }
   ```

2. **Inject into `ProvisionManager`** — add property + getter. Example pattern:
   ```php
   public function {name}Manager(): {Name}Manager
   {
       return $this->{name}Manager ??= new {Name}Manager(
           $this->contextRepository(),
           $this->filesystem(),
       );
   }
   ```

3. **Wire into commands** via `$this->getProvisionManager()->{name}Manager()`

4. **Fire events** using the event dispatcher if the operation modifies state:
   ```php
   $event = new {Name}Event($context);
   $this->eventDispatcher->dispatch($event, ProvisionEvents::{NAME}_BEFORE);
   ```

---

## Skill 5: Extend the Event System

**When**: You need to add event hooks for a new operation or extend existing ones.

### Adding Events for a New Operation

1. **Add constants** to `src/Event/ProvisionEvents.php`:
   ```php
   public const RENAME_VALIDATE = 'provision.rename.validate';
   public const RENAME_BEFORE   = 'provision.rename.before';
   public const RENAME_AFTER    = 'provision.rename.after';
   public const RENAME_ROLLBACK = 'provision.rename.rollback';
   ```

2. **Create event class** at `src/Event/RenameEvent.php`:
   ```php
   <?php

   declare(strict_types=1);

   namespace Aegir\Provision\Event;

   final class RenameEvent extends ProvisionEvent
   {
       public function __construct(
           \Aegir\Provision\Core\Context $context,
           public readonly string $oldName,
           public readonly string $newName,
       ) {
           parent::__construct($context);
       }
   }
   ```

3. **Dispatch in order**: VALIDATE → BEFORE → (execute) → AFTER, with try/catch dispatching ROLLBACK on exception.

### Event Lifecycle Pattern

```php
try {
    $event = new RenameEvent($context, $oldName, $newName);
    $this->dispatcher->dispatch($event, ProvisionEvents::RENAME_VALIDATE);
    $this->dispatcher->dispatch($event, ProvisionEvents::RENAME_BEFORE);

    // Execute actual operation

    $this->dispatcher->dispatch($event, ProvisionEvents::RENAME_AFTER);
} catch (\Throwable $e) {
    $this->dispatcher->dispatch($event, ProvisionEvents::RENAME_ROLLBACK);
    throw $e;
}
```

---

## Skill 6: Add or Modify Templates

**When**: You need to create/edit Apache vhost, SSL vhost, or settings.php templates.

### Template System

- Templates live in `templates/` directory
- `TemplateRenderer` uses priority-based resolution (custom > default)
- Templates are PHP files that receive variables via `extract()`

### Steps

1. **Create template** in `templates/`:
   ```php
   # templates/{name}.tpl.php
   <?php
   /** @var string $variable_name */
   ?>
   # Generated by Aegir Provision
   <?= $variable_name ?>
   ```

2. **Render via `TemplateRenderer`**:
   ```php
   $renderer = $pm->templateRenderer();
   $output = $renderer->render('{name}', [
       'variable_name' => $value,
   ]);
   ```

3. **Write output** using `Filesystem::writeFile()`:
   ```php
   $pm->filesystem()->writeFile($targetPath, $output);
   ```

### Existing Templates

| Template | Purpose | Key Variables |
|----------|---------|---------------|
| `vhost.tpl.php` | Apache HTTP vhost | `ApacheVhostConfig` VO |
| `vhost_ssl.tpl.php` | Apache HTTPS vhost | `ApacheVhostConfig` VO + SSL paths |
| `settings.php.tpl.php` | Drupal settings | `DatabaseCredentials` VO |

---

## Skill 7: Refactor Legacy Code to Drush 13

**When**: Converting annotation-based or legacy Drush 9/10 commands to Drush 13 Attribute-based syntax.

### Checklist

1. **Replace annotations with PHP 8 Attributes**:
   ```php
   // OLD (annotation):
   /**
    * @command provision:save
    * @param string $context Context name
    */

   // NEW (attribute):
   #[CLI\Command(name: 'provision:save')]
   #[CLI\Argument(name: 'context', description: 'Context name')]
   ```

2. **Add return types** to all methods — `void`, `int`, or `CommandResult`

3. **Use typed properties** — all constructor params typed, no `@var` annotations

4. **Replace `$this->io()` calls**:
   - `$this->io()->success()` → `$this->logger()->success()`
   - `$this->io()->writeln()` → `$this->output()->writeln()`

5. **Replace `drush_set_error()`** → throw typed exceptions

6. **Mark class `final`** — all new command classes must be `final`

7. **Attribute reference**:
   | Attribute | Purpose |
   |-----------|---------|
   | `#[CLI\Command(name: '')]` | Command definition |
   | `#[CLI\Argument(name: '', description: '')]` | Positional argument |
   | `#[CLI\Option(name: '', description: '')]` | Named option |
   | `#[CLI\Usage(name: '', description: '')]` | Usage example |
   | `#[CLI\Help(description: '')]` | Extended help text |
   | `#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]` | Bootstrap level |
   | `#[CLI\Aliases(['alias'])]` | Command aliases |

---

## Skill 8: Debug a Provision Command

**When**: A `provision:*` command fails and you need to diagnose the issue.

### Steps

1. **Run with debug output**:
   ```bash
   drush provision:verify @server_master --debug -vvv 2>&1 | tee /tmp/provision-debug.log
   ```

2. **Check the event chain** — grep the log for `provision.verify.*` events to see which phase failed.

3. **Inspect context YAML**:
   ```bash
   cat drush/sites/aegir/server_master.site.yml
   ```

4. **Verify service registry** — confirm the server context has the required service keys:
   ```yaml
   provision:
     http_service: apache
     db_service: mysql
   ```

5. **Check generated configs**:
   - Apache vhosts: `/var/aegir/config/server_master/apache/`
   - SSL certs: `/var/aegir/config/ssl.d/`
   - Settings.php: `/var/aegir/platforms/{platform}/sites/{uri}/settings.php`

6. **Common failure points**:
   | Symptom | Cause | Fix |
   |---------|-------|-----|
   | "Context not found" | Missing YAML alias | Run `provision:save` first |
   | "Service not registered" | Missing service key in context | Add `http_service`, `db_service` to YAML |
   | Apache won't reload | Syntax error in vhost | `apachectl configtest` to diagnose |
   | DB connection refused | Wrong credentials in context | Check `db_host`, `db_port`, `db_user` keys |
   | Permission denied | File ownership | Check `aegir` user owns paths, `www-data` group |

7. **For LockManager crashes** — this is a known bug. `LockManager.php` instantiates abstract `ProvisionEvent` directly. Create concrete `LockEvent`/`UnlockEvent` classes to fix.

---

## Coding Standards

- PHP 8.3+ strict: `declare(strict_types=1);` in every file
- All classes `final` — no inheritance hierarchies
- Value objects `final readonly` with constructor promotion
- PSR-4 namespace: `Aegir\Provision\`
- No Drupal APIs, no `\Drupal::` calls, no hooks
- Snake_case for YAML keys, camelCase for PHP properties/methods
- Colon-separated command names: `provision:save` not `provision-save`
