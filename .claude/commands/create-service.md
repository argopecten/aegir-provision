# Create Service Implementation

Create a new service type or implementation in aegir-provision.

## User Input Needed

Ask the user:
1. Is this a **new implementation** of an existing type (e.g., Nginx for `http`) or a **new service type** from scratch?
2. What is the provider name (e.g., `nginx`, `postgres`)?
3. What context types use it (`server` only, or also `platform`/`site`)?

---

## Option A: New Implementation of Existing Type

### 1. Create the implementation class

Path: `src/Service/{Http|Db|Ssl|Cron}/{ProviderName}Service.php`

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Service\Http;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Core\ValueObject\ApacheVhostConfig;
use Aegir\Provision\Service\HttpServiceInterface;

final class {ProviderName}Service implements HttpServiceInterface
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ProcessRunner $runner,
        // Add other deps as needed: ConfigPaths, TemplateRenderer, etc.
    ) {}

    public function ensureServerLayout(string $serverName): array
    {
        // Create required directories for this server type
        // Return associative array of created paths
    }

    public function enableSite(Context $site, Context $platform, Context $server, ApacheVhostConfig $config): array
    {
        // Write config file, reload server
        // Return array of file paths written
    }

    public function disableSite(Context $site, Context $server): void
    {
        // Move config to disabled dir, reload server
    }

    public function removeSite(Context $site, Context $server): void
    {
        // Delete all config files for this site, reload server
    }

    public function reload(string $serverName): void
    {
        // Reload/restart the HTTP server process
        // Use $this->runner->run([...]) — never raw shell_exec()
    }
}
```

### 2. Register in ServiceRegistry

In `src/Drush/ProvisionServiceRegistry.php`, inside `register()`:

```php
use Aegir\Provision\Service\Http\{ProviderName}Service;

// Add alongside existing factories:
$registry->addFactory('http.{provider}', static fn() => new {ProviderName}Service(
    $filesystem,
    $runner,
    // other dependencies already constructed above
));
```

### 3. Add template (if needed)

Path: `resources/templates/{type}/{provider}/vhost.tpl.php`

Templates use PHP variable extraction — see `resources/templates/apache/vhost.tpl.php`.

---

## Option B: New Service Type from Scratch

### 1. Create the interface

Path: `src/Service/{TypeName}ServiceInterface.php`

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Service;

interface {TypeName}ServiceInterface
{
    // Define methods — ALL must be idempotent
}
```

### 2. Create default implementation

Path: `src/Service/{TypeName}/{Default}Service.php`

Implements `{TypeName}ServiceInterface` with `final` class.

### 3. Register in ServiceRegistry

In `src/Service/ServiceRegistry.php`, add the new type to the supported types.
In `src/Drush/ProvisionServiceRegistry.php`, register the default factory.

### 4. Expose via ProvisionManager

In `src/ProvisionManager.php`, add:

```php
public function get{TypeName}Service(Context $server): {TypeName}ServiceInterface
{
    $providerKey = $server->get('{type}_service_type', '{default}');
    return $this->serviceRegistry->get('{type}', $providerKey);
}
```

---

## Rules

- `final` class — no inheritance from other implementations
- All methods must be **idempotent** (safe to run multiple times)
- Use `Filesystem` for all file ops — never raw `file_put_contents()`
- Use `ProcessRunner` for all shell commands — never `shell_exec()`
- No `\Drupal::` — standalone package, no Drupal bootstrap
- Read `ApacheService.php` and `MySqlService.php` before implementing to match patterns
