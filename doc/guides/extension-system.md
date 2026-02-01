# Extension System Documentation

**Version**: Aegir Provision 11.x for Drupal 11+  
**Last Updated**: February 1, 2026

---

## Overview

The Aegir Provision extension system allows third-party code to integrate with provision operations through:

1. **Event System** - React to lifecycle events (validate, before, after, rollback)
2. **Event Subscribers** - Listen to specific events and execute custom logic
3. **Extensible Architecture** - All major operations dispatch events

This replaces the D7 hook system (`hook_provision_*`) with a modern, type-safe approach using Symfony EventDispatcher.

---

## Aegir Drush Commands Library (PSR-4 Composer Package)

A reusable PSR-4 Composer library providing site-wide Drush commands for Aegir platform management, compatible with Drush 13.7+.

### What This Is

This is a **PSR-4 Composer package** that provides custom Drush commands for Aegir operations. As a library:

- **Reusable**: Can be installed in any Drupal project via `composer require aegir/drush-commands`
- **PSR-4 Compliant**: Follows PHP autoloading standards for maximum compatibility
- **Site-Wide Commands**: Commands are available globally within any project that requires this package
- **Drush 13.7+ Compatible**: Uses Drush 13 attribute-based commands on top of Symfony Console for:
  - Future compatibility with Drush 14+
  - Better IDE support and type safety
  - Standard Symfony Console patterns
  - Proper dependency injection support

### Use Cases

Use this distribution model when you want to:
- Share Drush commands across multiple Drupal projects
- Version and release commands independently from your projects
- Distribute commands via Composer/Packagist
- Maintain commands separately from site-specific code

**Alternative:** For project-specific commands that will not be reused, place them directly in your project's `drush/Commands/` directory.

### Installation

#### For Users of This Library

Add this package to your Drupal project:

```bash
composer require aegir/drush-commands
```

The commands will be automatically discovered by Drush after installation.

#### For Developers of This Library

Clone or create the library with the following structure.

### Package Directory Structure

A PSR-4 Drush commands library should have this structure:

```
aegir/drush-commands/
├── composer.json
├── README.md
├── src/
│   └── Drush/
│       └── Commands/
│           ├── PlatformInstallCommands.php
│           └── OtherCommands.php
└── tests/
    └── ...
```

**Critical Requirements:**
- Package MUST have a `composer.json` with PSR-4 autoload configuration
- Commands MUST be in `src/Drush/Commands/` directory
- Files MUST end with `Commands.php`
- Namespace MUST match your package's PSR-4 mapping + `\Drush\Commands`

### Package Composer Configuration

Your library's `composer.json` should look like this:

```json
{
  "name": "aegir/drush-commands",
  "description": "Site-wide Drush commands for Aegir platform management",
  "type": "library",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=8.1",
    "drush/drush": "^13.7",
    "symfony/console": "^6.0 || ^7.0",
    "symfony/process": "^6.0 || ^7.0"
  },
  "conflict": {
    "drush/drush": "<13.7"
  },
  "autoload": {
    "psr-4": {
      "Drupal\\Platform\\": "src/"
    }
  }
}
```

**Key Points:**
- `type: library` - Declares this as a Composer library
- `require` - Specifies dependencies (Drush 13.7+, Symfony Console)
- `conflict` - Ensures compatibility
- `autoload` - PSR-4 mapping where `Drupal\\Platform\\` maps to `src/`
  - This means `Drupal\Platform\Drush\Commands` maps to `src/Drush/Commands/`

#### After Creating/Modifying composer.json

Generate the autoloader:

```bash
cd /var/aegir/drush-commands
composer install
```

### How Drush Discovers PSR-4 Commands

Drush 13.7+ automatically discovers commands from Composer packages using:

1. **PSR-4 Autoloading**: Scans all PSR-4 namespaces for classes matching:
   - Pattern: `*\\Drush\\Commands\\*Commands`
   - Must extend `Drush\Commands\DrushCommands`

2. **Automatic Registration**: When a project runs `composer require your-vendor/drush-commands`:
   - Composer updates the autoloader to include your package
   - Drush scans the autoloader and finds your command classes
   - Commands are immediately available

**No manual registration needed** - Drush discovers PSR-4 commands automatically via Composer's autoloader.

### Command Class Structure

#### Recommended Approach: DrushCommands with Attributes (Drush 13.7+)

```php
<?php

declare(strict_types=1);

namespace Drupal\Platform\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

final class PlatformInstallCommands extends DrushCommands
{
  public function __construct()
  {
    parent::__construct();
  }

  #[Command(
    name: 'platform:install',
    description: 'Provision a Drupal platform (Composer only)'
  )]
  #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
  #[Argument(name: 'argument-name', description: 'Description of the argument')]
  #[Option(name: 'option-name', description: 'Description of the option', default: 'default-value')]
  public function install(string $argumentName, string $optionName = 'default-value'): int
  {
    $io = $this->io();

    // Get inputs
    $argument = $argumentName;
    $option = $optionName;

    // Display section header
    $io->section('Doing something');

    // Output text
    $io->text('Processing...');

    // Success message
    $io->success('Operation completed!');

    return self::EXIT_SUCCESS;
  }
}
```

**Note:** The namespace `Drupal\Platform\Drush\Commands` comes from:
- Base namespace: `Drupal\Platform\` (defined in composer.json as mapping to `src/`)
- Plus: `Drush\Commands\` (required by Drush for auto-discovery)
- File location: `src/Drush/Commands/PlatformInstallCommands.php`

### Critical Components Explained

#### 1. Namespace
```php
namespace Drupal\Platform\Drush\Commands;
```

**For PSR-4 Libraries:**
- Base namespace matches your package's PSR-4 mapping (e.g., `Drupal\Platform\`)
- MUST append `\Drush\Commands` for Drush discovery
- Full namespace = Base + `\Drush\Commands`

**Examples:**
- Package: `"My\\Package\\": "src/"` -> Commands: `My\Package\Drush\Commands`
- Package: `"Drupal\\Platform\\": "src/"` -> Commands: `Drupal\Platform\Drush\Commands`
- Package: `"Acme\\Tools\\": "src/"` -> Commands: `Acme\Tools\Drush\Commands`

**Note:** For site-specific commands in `drush/` directory (not PSR-4 packages), use `Drush\Commands` directly.

#### 2. Command Attribute
```php
#[Command(
  name: 'my:command',
  description: 'Brief description',
  aliases: ['mc']
)]
```
- `name`: Command name (use colon for grouping: `category:action`)
- `description`: Short description shown in `drush list`
- `aliases`: Optional short aliases

Apply this attribute to the command method (not the class).

#### 3. Bootstrap Attribute
```php
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
```

Available levels:
- `DrupalBootLevels::NONE` - No Drupal bootstrap (fast, use for platform-level operations)
- `DrupalBootLevels::ROOT` - Bootstrap to Drupal root
- `DrupalBootLevels::SITE` - Bootstrap to Drupal site
- `DrupalBootLevels::CONFIGURATION` - Bootstrap with configuration
- `DrupalBootLevels::DATABASE` - Bootstrap with database
- `DrupalBootLevels::FULL` - Full Drupal bootstrap

**Important:** Include the use statement:
```php
use Drush\Boot\DrupalBootLevels;
```

#### 4. Constructor
```php
public function __construct()
{
  parent::__construct();
}
```

**Guidance:** Define a constructor only when you need dependency injection. If you do, always call `parent::__construct()`.

#### 5. Dependency Injection (Advanced)

If you need Drupal or Drush services, use the constructor with type hints:

```php
use Psr\Log\LoggerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

public function __construct(
  private readonly LoggerInterface $logger,
  private readonly ConfigFactoryInterface $configFactory,
) {
  parent::__construct();
}
```

**Note:** This requires `DrupalBootLevels::FULL` or the appropriate bootstrap level.

### Common Input/Output Patterns

#### Working with SymfonyStyle

```php
$io = $this->io();

// Headings
$io->title('Main Title');
$io->section('Section Title');

// Output
$io->text('Normal text');
$io->writeln('Line of text');

// Lists
$io->listing(['Item 1', 'Item 2', 'Item 3']);

// Tables
$io->table(
  ['Header 1', 'Header 2'],
  [
    ['Row 1 Col 1', 'Row 1 Col 2'],
    ['Row 2 Col 1', 'Row 2 Col 2'],
  ]
);

// Status messages
$io->success('Success message');
$io->error('Error message');
$io->warning('Warning message');
$io->note('Note message');

// Progress bar
$io->progressStart(100);
for ($i = 0; $i < 100; $i++) {
  $io->progressAdvance();
  // do work
}
$io->progressFinish();

// User interaction
$answer = $io->confirm('Proceed?', true);
$input = $io->ask('Enter value', 'default');
$choice = $io->choice('Select option', ['Option 1', 'Option 2'], 'Option 1');
```

### Testing Your Command

#### For Library Developers

After creating/modifying commands in your package:

```bash
# In your package directory
composer install

# In a test project that requires your package
cd /var/aegir/test-project
composer require your-vendor/your-package:@dev

# Or if using a local path repository
composer update your-vendor/your-package
```

#### 1. Check if command is discovered

```bash
drush list | grep platform:install
```

#### 2. View command help

```bash
drush platform:install --help
```

#### 3. Test with verbose output

```bash
drush platform:install -vvv
```

#### 4. Debug discovery issues

```bash
drush list --debug 2>&1 | grep -i "platform\|error\|could not"
```

### Testing During Development

For local development, add your package as a path repository in a test project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "/var/aegir/drush-commands"
    }
  ],
  "require": {
    "aegir/drush-commands": "@dev"
  }
}
```

Then run:
```bash
composer update aegir/drush-commands
drush list | grep your-command
```

### Common Issues and Solutions

#### Issue 1: Command not found

**Symptoms:**
```
Command platform:install was not found
```

**Solutions:**
1. Verify package is properly required: `composer show aegir/drush-commands`
2. Check file is in `src/Drush/Commands/` directory (relative to package root)
3. Verify filename ends with `Commands.php`
4. Check namespace follows pattern: `YourBase\Drush\Commands`
5. Verify PSR-4 autoload mapping in package's `composer.json`
6. Run `composer dump-autoload` in your project
7. Check file for PHP syntax errors: `php -l src/Drush/Commands/YourCommands.php`

**For PSR-4 packages specifically:**
- Ensure namespace matches: PSR-4 base + `\Drush\Commands`
- Example: If PSR-4 is `"Drupal\\Platform\\": "src/"`, namespace must be `Drupal\Platform\Drush\Commands`
- File must be at: `src/Drush/Commands/YourCommands.php`

#### Issue 2: Autowiring error

**Symptoms:**
```
Could not instantiate Drush\Commands\MyCommands: Cannot autowire service "string"
```

**Solution:**
Ensure your constructor matches services that can be autowired and that `parent::__construct()` is called.

#### Issue 3: Bootstrap constant not found

**Symptoms:**
```
Undefined constant Drush\Attributes\Bootstrap::NONE
```

**Solution:**
Use `DrupalBootLevels` class instead:
```php
use Drush\Boot\DrupalBootLevels;

#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
```

#### Issue 4: Class not autoloaded

**Symptoms:**
```
Class "Drupal\Platform\Drush\Commands\PlatformInstallCommands" not found
```

**Solution:**
1. Verify PSR-4 mapping in your package's `composer.json`
2. Ensure the class file location matches the namespace
3. Run `composer dump-autoload` in the consuming project
4. If using path repository, run `composer update your-vendor/your-package`

#### Issue 5: Package not discovered

**Symptoms:**
```
Package aegir/drush-commands not found
```

**Solutions for local development:**
1. Add path repository to test project's `composer.json`:
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "/var/aegir/drush-commands"
    }
  ]
}
```
2. Require with dev stability: `composer require aegir/drush-commands:@dev`
3. For published packages, ensure package is on Packagist or a custom repository

### File Naming Conventions

**Good:**
- `PlatformInstallCommands.php` in `src/Drush/Commands/`
- `CustomCommands.php` in `src/Drush/Commands/`
- `SiteMaintenanceCommands.php` in `src/Drush/Commands/`
- `DeployCommands.php` in `src/Drush/Commands/`

**Bad:**
- `PlatformInstall.php` (missing "Commands" suffix)
- `platform-commands.php` (wrong case)
- `commands.php` (too generic)
- `MyCommand.php` (singular form)
- Files in `src/Commands/` without `Drush/` subdirectory (won't be discovered)

### Directory Structure Example

For package `aegir/drush-commands` with PSR-4 mapping `"Drupal\\Platform\\": "src/"`:

```
aegir/drush-commands/
├── composer.json
├── README.md
├── src/
│   └── Drush/
│       └── Commands/
│           ├── PlatformInstallCommands.php    # Drupal\Platform\Drush\Commands\PlatformInstallCommands
│           ├── SiteMigrateCommands.php        # Drupal\Platform\Drush\Commands\SiteMigrateCommands
│           └── BackupCommands.php             # Drupal\Platform\Drush\Commands\BackupCommands
└── tests/
    └── ...
```

Each command is a separate file for clarity and maintainability.

### Version Compatibility

Your package's `composer.json` should declare Drush compatibility:

```json
{
  "require": {
    "drush/drush": "^13.7"
  },
  "conflict": {
    "drush/drush": "<13.7"
  }
}
```

This ensures your commands only run with compatible Drush versions and prevents installation in incompatible environments.

### Publishing Your Package

#### To Packagist (Public)

1. Create a GitHub/GitLab repository for your package
2. Tag a release: `git tag -a v1.0.0 -m "First release"`
3. Push tags: `git push --tags`
4. Submit to Packagist.org

Users can then install with:
```bash
composer require aegir/drush-commands
```

#### Private Repository

For private packages, configure Composer repository in consuming projects:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/yourorg/drush-commands.git"
    }
  ]
}
```

#### Development Workflow

Use path repositories for local development:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "/var/aegir/drush-commands",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "aegir/drush-commands": "@dev"
  }
}
```

This creates a symlink, so changes in the package are immediately available.

### Best Practices

1. **Bootstrap Level**: Use the minimum required bootstrap level for performance
   - Platform operations: `NONE`
   - File operations: `ROOT`
   - Database queries: `DATABASE`
   - Entity operations: `FULL`

2. **Error Handling**: Always return proper exit codes
   - Success: `self::EXIT_SUCCESS` (0)
   - Failure: `self::EXIT_FAILURE` (1)
   - Invalid: `self::EXIT_INVALID` (2)

3. **User Feedback**: Provide clear, actionable messages
   - Use `$this->io()->section()` for major steps
   - Use `$this->io()->text()` for progress updates
   - Use `$this->io()->success()` or `$this->io()->error()` for final status

4. **Validation**: Validate inputs early in the command method

5. **Help Text**: Provide comprehensive help text and usage examples

### Complete Working Example

This is a `PlatformInstallCommands.php` example for the `aegir/drush-commands` package:

**File:** `src/Drush/Commands/PlatformInstallCommands.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\Platform\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Symfony\Component\Process\Process;

final class PlatformInstallCommands extends DrushCommands
{
  public function __construct()
  {
    parent::__construct();
  }

  #[Command(
    name: 'platform:install',
    description: 'Provision a Drupal platform (Composer only)',
    aliases: ['pfi']
  )]
  #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
  #[Option(
    name: 'working-dir',
    description: 'The working directory containing composer.json',
    default: '.'
  )]
  #[Option(
    name: 'no-dev',
    type: 'boolean',
    description: 'Skip dev dependencies'
  )]
  public function install(string $workingDir = '.', bool $noDev = false): int
  {
    $io = $this->io();

    $io->section('Provisioning Drupal platform');

    // Validate
    if (!file_exists($workingDir . '/composer.json')) {
      $io->error('composer.json not found in ' . $workingDir);
      return self::EXIT_FAILURE;
    }

    // Execute Composer
    $io->text('Installing Composer dependencies');
    $command = ['composer', 'install', '--optimize-autoloader', '--no-interaction'];
    if ($noDev) {
      $command[] = '--no-dev';
    }

    $process = new Process($command, $workingDir);
    $process->setTimeout(null);

    try {
      $process->mustRun(function ($type, $buffer) use ($io) {
        $io->write($buffer);
      });
    } catch (\Exception $e) {
      $io->error('Composer install failed: ' . $e->getMessage());
      return self::EXIT_FAILURE;
    }

    // Verify
    if (!is_dir($workingDir . '/web/core')) {
      $io->error('Drupal core not found after Composer install.');
      return self::EXIT_FAILURE;
    }

    // Prepare directories
    $io->text('Preparing writable directories');
    $dirs = ['web/sites/default/files'];
    foreach ($dirs as $dir) {
      $path = $workingDir . '/' . $dir;
      if (!is_dir($path)) {
        mkdir($path, 0775, true);
      }
    }

    $io->success('Drupal platform provisioned (no site installed).');
    return self::EXIT_SUCCESS;
  }
}
```

### Quick Checklist

#### For Package Developers

Before releasing your Drush commands library:

- [ ] Package has `composer.json` with correct PSR-4 autoload mapping
- [ ] Commands in `src/Drush/Commands/` directory
- [ ] Filenames end with `Commands.php`
- [ ] Namespace follows pattern: `YourBase\Drush\Commands`
- [ ] Each command extends `Drush\Commands\DrushCommands`
- [ ] Constructor calls `parent::__construct()` (when defined)
- [ ] Uses `DrupalBootLevels` for bootstrap attribute
- [ ] Imports include `use Drush\Boot\DrupalBootLevels;`
- [ ] Commands return proper exit codes
- [ ] PHP syntax is valid: `php -l src/Drush/Commands/*.php`
- [ ] Drush version requirement in `require` and `conflict`
- [ ] Tested with path repository in a real project
- [ ] README.md with installation and usage instructions
- [ ] Tagged release if publishing to Packagist

#### For Package Users

Before using a Drush commands library:

- [ ] Added package via `composer require vendor/package`
- [ ] Ran `composer install` or `composer update`
- [ ] Verified package installed: `composer show vendor/package`
- [ ] Commands appear in `drush list`
- [ ] Command help works: `drush command:name --help`

### Creating Your Own Library

#### Step-by-Step Guide

1. **Create package directory structure:**
```bash
mkdir -p /var/aegir/my-drush-commands/src/Drush/Commands
cd /var/aegir/my-drush-commands
```

2. **Create composer.json:**
```json
{
  "name": "myvendor/my-drush-commands",
  "description": "Custom Drush commands for my needs",
  "type": "library",
  "license": "MIT",
  "require": {
    "php": ">=8.1",
    "drush/drush": "^13.7",
    "symfony/console": "^6.0 || ^7.0"
  },
  "conflict": {
    "drush/drush": "<13.7"
  },
  "autoload": {
    "psr-4": {
      "MyVendor\\DrushCommands\\": "src/"
    }
  }
}
```

3. **Create your first command:**
`src/Drush/Commands/HelloCommands.php`:
```php
<?php

declare(strict_types=1);

namespace MyVendor\DrushCommands\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Attributes\Command;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

#[Command(
  name: 'hello:world',
  description: 'A simple hello world command',
  aliases: ['hw']
)]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class HelloCommands extends DrushCommands
{
  public function __construct()
  {
    parent::__construct();
  }

  public function hello(): int
  {
    $this->io()->success('Hello, World!');
    return self::EXIT_SUCCESS;
  }
}
```

4. **Test locally:**
In a Drupal project, add to `composer.json`:
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "/var/aegir/my-drush-commands"
    }
  ],
  "require": {
    "myvendor/my-drush-commands": "@dev"
  }
}
```

Run:
```bash
composer update myvendor/my-drush-commands
drush hello:world
```

5. **Publish:**
```bash
git init
git add .
git commit -m "Initial commit"
git tag v1.0.0
git remote add origin https://github.com/myvendor/my-drush-commands.git
git push -u origin main --tags
```

Then submit to Packagist.org.

### References

- [Drush 13 Commands Documentation](https://www.drush.org/13.x/commands/)
- [Symfony Console Component](https://symfony.com/doc/current/console.html)
- [Drush Attributes](https://github.com/drush-ops/drush/tree/13.x/src/Attributes)
- [Composer Documentation](https://getcomposer.org/doc/)
- [PSR-4 Autoloading Standard](https://www.php-fig.org/psr/psr-4/)
- [Packagist.org](https://packagist.org) - PHP package repository

### Contributing to This Library

To contribute to `aegir/drush-commands`:

1. Fork the repository
2. Create a feature branch
3. Add your command following the patterns above
4. Test with a real Drupal project using a path repository
5. Submit a pull request

### Support

For issues specific to this library, please file issues on the project's repository.

For general Drush command development questions, consult the Drush documentation.

---


## Event System

### Available Events

All events are defined in `Aegir\Provision\Event\ProvisionEvents`:

#### Validation Events
Fired before any changes. Throw exceptions to prevent the operation.

- `VALIDATE_INSTALL` - Before site installation
- `VALIDATE_VERIFY` - Before verify operation
- `VALIDATE_BACKUP` - Before creating backup
- `VALIDATE_RESTORE` - Before restoring from backup
- `VALIDATE_MIGRATE` - Before migrating site
- `VALIDATE_CLONE` - Before cloning site
- `VALIDATE_DELETE` - Before deleting context
- `VALIDATE_DEPLOY` - Before deploying backup
- `VALIDATE_ENABLE` - Before enabling site
- `VALIDATE_DISABLE` - Before disabling site
- `VALIDATE_LOCK` - Before locking site
- `VALIDATE_UNLOCK` - Before unlocking site

#### Before Events
Fired immediately before the main operation. Can modify event data.

- `BEFORE_INSTALL`
- `BEFORE_VERIFY`
- `BEFORE_BACKUP`
- `BEFORE_RESTORE`
- `BEFORE_MIGRATE`
- `BEFORE_CLONE`
- `BEFORE_DELETE`
- `BEFORE_DEPLOY`
- `BEFORE_ENABLE`
- `BEFORE_DISABLE`
- `BEFORE_LOCK`
- `BEFORE_UNLOCK`

#### After Events
Fired after successful completion. Use for notifications, cleanup, etc.

- `AFTER_INSTALL`
- `AFTER_VERIFY`
- `AFTER_BACKUP`
- `AFTER_RESTORE`
- `AFTER_MIGRATE`
- `AFTER_CLONE`
- `AFTER_DELETE`
- `AFTER_DEPLOY`
- `AFTER_ENABLE`
- `AFTER_DISABLE`
- `AFTER_LOCK`
- `AFTER_UNLOCK`

#### Rollback Events
Fired when an operation fails. Use for cleanup.

- `ROLLBACK_INSTALL`
- `ROLLBACK_RESTORE`
- `ROLLBACK_MIGRATE`
- `ROLLBACK_CLONE`

---

## Event Classes

Each event type has a dedicated class with relevant context:

### InstallEvent
```php
use Aegir\Provision\Event\InstallEvent;

public function onInstall(InstallEvent $event): void {
    $site = $event->getSite();        // Site context
    $platform = $event->getPlatform(); // Platform context
    $server = $event->getServer();     // Server context
    $operation = $event->getOperation(); // 'validate', 'before', 'after', 'rollback'
}
```

### VerifyEvent
```php
use Aegir\Provision\Event\VerifyEvent;

public function onVerify(VerifyEvent $event): void {
    $context = $event->getContext();   // Context being verified
    $platform = $event->getPlatform(); // Platform (may be null)
    $server = $event->getServer();     // Server (may be null)
}
```

### BackupEvent / RestoreEvent / DeployEvent
```php
use Aegir\Provision\Event\BackupEvent;

public function onBackup(BackupEvent $event): void {
    $context = $event->getContext();
    $backupPath = $event->getBackupPath();
}
```

### MigrateEvent
```php
use Aegir\Provision\Event\MigrateEvent;

public function onMigrate(MigrateEvent $event): void {
    $site = $event->getSite();
    $oldPlatform = $event->getOldPlatform();
    $newPlatform = $event->getNewPlatform();
    $server = $event->getServer();
}
```

### CloneEvent
```php
use Aegir\Provision\Event\CloneEvent;

public function onClone(CloneEvent $event): void {
    $source = $event->getSourceSite();
    $target = $event->getTargetSite();
    $platform = $event->getPlatform();
    $server = $event->getServer();
}
```

### DeleteEvent
```php
use Aegir\Provision\Event\DeleteEvent;

public function onDelete(DeleteEvent $event): void {
    $context = $event->getContext();
    $deleteDb = $event->shouldDeleteDatabase();
    $deleteFiles = $event->shouldDeleteFiles();
}
```

---

## Creating Event Subscribers

### Basic Subscriber

Create a class implementing `EventSubscriberInterface`:

```php
<?php

declare(strict_types=1);

namespace YourNamespace\EventSubscriber;

use Aegir\Provision\Event\{ProvisionEvents, InstallEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProvisionEvents::VALIDATE_INSTALL => 'validateInstall',
            ProvisionEvents::AFTER_INSTALL => ['notifyInstall', 10],
        ];
    }

    public function validateInstall(InstallEvent $event): void
    {
        // Validation logic - throw exception to prevent install
        $site = $event->getSite();
        if (/* some condition */) {
            throw new \RuntimeException('Installation prevented');
        }
    }

    public function notifyInstall(InstallEvent $event): void
    {
        // Post-install actions
        $site = $event->getSite();
        // Send notification, log event, etc.
    }
}
```

### Event Priority

Control execution order with priority (higher = earlier):

```php
public static function getSubscribedEvents(): array
{
    return [
        // These run in order: highest priority first
        ProvisionEvents::VALIDATE_INSTALL => ['earlyValidation', 100],
        ProvisionEvents::VALIDATE_INSTALL => ['normalValidation', 0],
        ProvisionEvents::VALIDATE_INSTALL => ['lateValidation', -100],
    ];
}
```

---

## Registering Subscribers

### Option 1: Direct Registration

In your Drush command or initialization code:

```php
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

$dispatcher = $container->get(EventDispatcherInterface::class);
$dispatcher->addSubscriber(new MySubscriber());
```

### Option 2: Via Drush Container (Recommended)

Create a service registry for your extension:

```php
<?php

namespace YourNamespace\Drush;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use YourNamespace\EventSubscriber\MySubscriber;

class YourServiceRegistry
{
    public static function register(ContainerInterface $container): void
    {
        $dispatcher = $container->get(EventDispatcherInterface::class);
        $dispatcher->addSubscriber(new MySubscriber());
    }
}
```

Then call it from your command's `__construct()` or via Drush bootstrap.

---

## Use Cases

### 1. Domain Validation

Prevent installation on unauthorized domains:

```php
public function validateDomain(InstallEvent $event): void
{
    $site = $event->getSite();
    $uri = $site->get('uri');
    
    if (!$this->isDomainAllowed($uri)) {
        throw new \RuntimeException("Domain $uri not authorized");
    }
}
```

### 2. Custom DNS Updates

Update DNS records after site creation:

```php
public function updateDNS(InstallEvent $event): void
{
    $site = $event->getSite();
    $uri = $site->get('uri');
    $server = $event->getServer();
    
    $this->dnsProvider->createRecord($uri, $server->get('ip_address'));
}
```

### 3. Backup Notifications

Alert administrators of backup completion:

```php
public function notifyBackup(BackupEvent $event): void
{
    $context = $event->getContext();
    $backupPath = $event->getBackupPath();
    
    mail(
        'admin@example.com',
        'Backup Complete',
        "Backup created: $backupPath"
    );
}
```

### 4. Compliance Logging

Log all operations for audit trails:

```php
public function logOperation(ProvisionEvent $event): void
{
    $operation = $event->getOperation();
    $context = $event->getContext();
    
    $this->auditLogger->log([
        'timestamp' => time(),
        'operation' => $operation,
        'context' => $context->name(),
        'user' => posix_getuid(),
    ]);
}
```

### 5. Prevent Accidental Deletion

Add confirmation for production sites:

```php
public function confirmDeletion(DeleteEvent $event): void
{
    $context = $event->getContext();
    
    if ($this->isProduction($context)) {
        throw new \RuntimeException(
            'Cannot delete production site without confirmation'
        );
    }
}
```

---

## Event Data

Events support arbitrary data storage:

```php
// Set data in BEFORE event
public function beforeInstall(InstallEvent $event): void
{
    $event->setData('custom_id', $this->generateId());
    $event->setData('start_time', time());
}

// Read data in AFTER event
public function afterInstall(InstallEvent $event): void
{
    $customId = $event->getDataValue('custom_id');
    $duration = time() - $event->getDataValue('start_time', 0);
    
    $this->logger->info("Install $customId completed in {$duration}s");
}
```

---

## Best Practices

### 1. Validate Early

Use `VALIDATE_*` events for all pre-flight checks:

```php
public function validateInstall(InstallEvent $event): void
{
    // Check all conditions before any changes
    $this->checkDiskSpace();
    $this->checkDatabaseConnection();
    $this->checkPermissions();
}
```

### 2. Keep Subscribers Focused

One subscriber per concern (validation, logging, notifications, etc.):

```php
// Good: Focused subscribers
class ValidationSubscriber { /* ... */ }
class NotificationSubscriber { /* ... */ }
class AuditLogSubscriber { /* ... */ }

// Avoid: One subscriber doing everything
class EverythingSubscriber { /* ... */ }
```

### 3. Handle Errors Gracefully

```php
public function afterInstall(InstallEvent $event): void
{
    try {
        $this->sendNotification($event);
    } catch (\Exception $e) {
        // Don't fail the operation if notification fails
        $this->logger->error('Notification failed: ' . $e->getMessage());
    }
}
```

### 4. Use Appropriate Events

- **VALIDATE**: Pre-flight checks, throw exceptions to prevent
- **BEFORE**: Last-minute preparation, modify data
- **AFTER**: Notifications, cleanup, logging
- **ROLLBACK**: Undo changes, cleanup after failure

### 5. Document Your Events

```php
/**
 * Validates domain ownership before installation.
 *
 * Checks DNS records to ensure the domain is properly delegated
 * to this hosting platform. Prevents typosquatting and unauthorized
 * site creation.
 *
 * @throws \RuntimeException if domain validation fails
 */
public function validateDomain(InstallEvent $event): void
{
    // ...
}
```

---

## Testing Event Subscribers

### Unit Test Example

```php
use PHPUnit\Framework\TestCase;
use Aegir\Provision\Event\InstallEvent;
use Aegir\Provision\Core\Context;

class MySubscriberTest extends TestCase
{
    public function testValidateDomain(): void
    {
        $subscriber = new MySubscriber();
        
        $site = $this->createMock(Context::class);
        $site->method('get')->willReturn('example.com');
        
        $event = new InstallEvent('validate', $site, $platform, $server);
        
        // Should not throw
        $subscriber->validateDomain($event);
    }
    
    public function testValidateDomainFails(): void
    {
        $this->expectException(\RuntimeException::class);
        
        $subscriber = new MySubscriber();
        $site = $this->createMock(Context::class);
        $site->method('get')->willReturn('unauthorized.com');
        
        $event = new InstallEvent('validate', $site, $platform, $server);
        $subscriber->validateDomain($event);
    }
}
```

---

## Implementation Status

### Phase 1: Event System (COMPLETED)

✅ **Completed**:
- Event base class (`ProvisionEvent.php`)
- Event constants (`ProvisionEvents.php`) - 52 events defined
- Specific event classes (InstallEvent, VerifyEvent, BackupEvent, RestoreEvent, MigrateEvent, CloneEvent, DeleteEvent, DeployEvent)
- EventDispatcher integration in ProvisionManager
- Service registration in Drush container
- Event dispatching in `install()` and `delete()` methods

⏳ **In Progress**:
- Event dispatching in remaining operations (verify, backup, restore, migrate, clone, deploy, enable, disable, lock, unlock)

### Service Plugin System (Phase 2)

**Status**: ✅ IMPLEMENTED (January 31, 2026)

The service plugin system allows alternative implementations of core services (HTTP, database, SSL).

#### Service Interfaces

Three standard interfaces define service contracts:

**HttpServiceInterface** - Web server implementations (Apache, Nginx, etc.)
```php
namespace Aegir\Provision\Service;

use Aegir\Provision\Core\ValueObject\ApacheVhostConfig;

interface HttpServiceInterface
{
    public function ensureServerLayout(string $serverName): array;
    public function enableSite(Context $site, Context $platform, Context $server, ApacheVhostConfig $config): array;
    public function disableSite(string $serverName, string $siteName): void;
    public function removeSite(string $serverName, string $siteName): void;
    public function reload(?string $restartCmd): void;
}
```

**DbServiceInterface** - Database implementations (MySQL, PostgreSQL, etc.)
```php
interface DbServiceInterface
{
    public function ensureDatabase(Context $server, string $dbName): void;
    public function ensureUser(Context $server, string $dbUser, string $dbPass, string $dbHost): void;
    public function grant(Context $server, string $dbName, string $dbUser, string $dbHost): void;
    public function dropDatabase(Context $server, string $dbName): void;
    public function dropUser(Context $server, string $dbUser, string $dbHost): void;
    public function dump(Context $server, string $dbName, string $targetFile, bool $gzip = false): string;
    public function import(Context $server, string $dbName, string $sourceFile): void;
    public function testConnection(Context $server): void;
}
```

**SslServiceInterface** - SSL certificate management
```php
interface SslServiceInterface
{
    public function resolve(string $serverName, string $domain, array $contextData): array;
}
```

#### ServiceRegistry

The `ServiceRegistry` manages all service implementations:

```php
use Aegir\Provision\Service\ServiceRegistry;

// Get the registry from ProvisionManager
$registry = $provisionManager->getServiceRegistry();

// Register a custom service
$nginxService = new NginxService(...);
$registry->register('http', 'nginx', $nginxService);

// Set as default for all servers
$registry->setDefault('http', 'nginx');

// Get a specific service
$httpService = $registry->get('http', 'nginx');

// Get the default service
$httpService = $registry->get('http'); // Uses default
```

#### Registering Custom Services

**Method 1: Via Drush Service Provider**

Create a custom Drush service provider that registers your service:

```php
<?php
// In your extension: src/Drush/MyExtensionServiceProvider.php

namespace MyExtension\Drush;

use Aegir\Provision\Examples\NginxService;
use Aegir\Provision\Service\ServiceRegistry;
use Psr\Container\ContainerInterface;

class MyExtensionServiceProvider
{
    public static function register(ContainerInterface $container): void
    {
        // Get the service registry
        $registry = $container->get(ServiceRegistry::class);
        
        // Create and register your custom service
        $nginx = new NginxService(
            $container->get(ConfigPaths::class),
            $container->get(Filesystem::class),
            $container->get(TemplateRenderer::class),
            $container->get(ProcessRunner::class),
            $container->get(SslManager::class)
        );
        
        $registry->register('http', 'nginx', $nginx);
        
        // Optionally set as default
        // $registry->setDefault('http', 'nginx');
    }
}
```

**Method 2: Via Event Subscriber**

Register services dynamically in response to events:

```php
<?php

namespace MyExtension\EventSubscriber;

use Aegir\Provision\Event\ProvisionEvents;
use Aegir\Provision\Event\VerifyEvent;
use Aegir\Provision\Service\ServiceRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ServiceRegistrationSubscriber implements EventSubscriberInterface
{
    private ServiceRegistry $registry;
    
    public function __construct(ServiceRegistry $registry)
    {
        $this->registry = $registry;
    }
    
    public static function getSubscribedEvents(): array
    {
        // Register before any verify operations
        return [
            ProvisionEvents::VALIDATE_VERIFY => ['registerServices', 1000],
        ];
    }
    
    public function registerServices(VerifyEvent $event): void
    {
        if (!$this->registry->has('http', 'nginx')) {
            $nginx = new NginxService(...);
            $this->registry->register('http', 'nginx', $nginx);
        }
    }
}
```

#### Creating Custom Service Implementations

**Example: Nginx HTTP Service**

See [examples/NginxService.php](../../examples/NginxService.php) for a complete implementation.

Key requirements:
1. Implement the appropriate interface (`HttpServiceInterface`)
2. Follow the same method signatures
3. Handle vhost generation, SSL, reload operations
4. Return expected data structures

```php
<?php

namespace MyExtension\Service;

use Aegir\Provision\Service\HttpServiceInterface;
use Aegir\Provision\Core\ValueObject\ApacheVhostConfig;
use Aegir\Provision\Core\Context;

class NginxService implements HttpServiceInterface
{
    public function enableSite(Context $site, Context $platform, Context $server, ApacheVhostConfig $config): array
    {
        // Generate Nginx vhost configuration using value object
        $vhostConfig = $this->templates->render('nginx/vhost.tpl.php', [
            'server_name' => $config->serverName,
            'server_aliases' => $config->serverAliases,
            'docroot' => $config->documentRoot,
            'port' => $config->port,
            'ssl_cert' => $config->sslCertPath,
            'ssl_key' => $config->sslKeyPath,
        ]);
        
        // Write to appropriate location
        $vhostPath = $this->getVhostPath($server->name(), $site->name());
        $this->filesystem->writeFile($vhostPath, $vhostConfig);
        
        return ['vhost' => $vhostPath];
    }
    
    // Implement other interface methods...
}
```

#### Using Custom Services

Once registered, services are automatically used based on server configuration:

**Option 1: Set per-server**
```bash
# Configure server to use Nginx
drush provision:save @server_master http_service_type=nginx

# All sites on this server will use Nginx
drush provision:verify @server_master
```

**Option 2: Set globally as default**
```php
$registry->setDefault('http', 'nginx');
```

**Option 3: Access directly in code**
```php
// In a manager or custom code
$httpService = $this->serviceRegistry->get('http', 'nginx');
$httpService->enableSite($site, $platform, $server);
```

#### Built-in Services

Default services registered automatically:

- **HTTP**: `apache` (ApacheService) - Default
- **Database**: `mysql` (MySqlService) - Default  
- **SSL**: `default` (SslManager) - Default

#### Service Discovery

Services can be discovered at runtime:

```php
// Check if a service exists
if ($registry->has('http', 'nginx')) {
    $nginx = $registry->get('http', 'nginx');
}

// Get all HTTP services
$httpServices = $registry->getAll('http');
// Returns: ['apache' => ApacheService, 'nginx' => NginxService]

// Get default service name
$defaultHttp = $registry->getDefault('http'); // 'apache'
```

#### Validation

The ServiceRegistry validates that implementations match their interface:

```php
// This will throw InvalidArgumentException if NginxService 
// doesn't implement HttpServiceInterface
$registry->register('http', 'nginx', $nginxService);
```

### Template Override System (Phase 3)

**Status**: ✅ IMPLEMENTED (January 31, 2026)

The template override system allows extensions to provide custom templates for vhosts, Drupal settings, and other configuration files. Templates are resolved using a priority-based search system.

#### Template Resolution

Templates are searched in priority order (highest first) until found:

1. **Custom directories** (priority 200+) - Development/testing overrides
2. **User templates** (priority 100) - Site-specific customizations
3. **Extension templates** (priority 10-99) - Extension-provided templates
4. **Core templates** (priority 0) - Built-in fallback templates

#### TemplateRenderer API

The `TemplateRenderer` class provides the template override system:

```php
use Aegir\Provision\Config\TemplateRenderer;

// Register a custom template directory with priority
$templates->registerTemplatePath('/path/to/custom/templates', 100);

// Render a template (searches all registered paths by priority)
$output = $templates->render('apache/vhost.tpl.php', [
    'server_name' => 'example.com',
    'docroot' => '/var/www/example',
    'http_port' => 80,
]);

// Check which template will be used
$path = $templates->getTemplatePath('apache/vhost.tpl.php');
// Returns: '/path/to/custom/templates/apache/vhost.tpl.php' (if exists)
// Falls back to core template if not found

// Check if template exists in any registered path
if ($templates->templateExists('apache/vhost.tpl.php')) {
    // Template is available
}

// Get all registered template paths (in priority order)
$paths = $templates->getTemplatePaths();
// Returns: ['/custom/templates', '/extension/templates', '/core/templates']

// Remove a template directory
$templates->unregisterTemplatePath('/path/to/custom/templates');

// Clear all custom paths (keeps only core templates)
$templates->clearCustomPaths();
```

#### Registering Custom Template Directories

**Method 1: Via Event Subscriber**

Register templates automatically at the start of operations:

```php
<?php

namespace MyExtension\EventSubscriber;

use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Event\ProvisionEvents;
use Aegir\Provision\Event\ProvisionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomTemplateSubscriber implements EventSubscriberInterface
{
    private TemplateRenderer $templates;
    private string $templatePath;

    public function __construct(TemplateRenderer $templates, string $templatePath)
    {
        $this->templates = $templates;
        $this->templatePath = $templatePath;
    }

    public static function getSubscribedEvents(): array
    {
        // Register early with high priority to ensure templates available
        return [
            ProvisionEvents::VALIDATE_INSTALL => ['registerTemplates', 1000],
            ProvisionEvents::VALIDATE_VERIFY => ['registerTemplates', 1000],
            ProvisionEvents::VALIDATE_ENABLE => ['registerTemplates', 1000],
        ];
    }

    public function registerTemplates(ProvisionEvent $event): void
    {
        // Priority 50 = higher than core (0) but lower than user overrides (100)
        $this->templates->registerTemplatePath($this->templatePath, 50);
    }
}
```

**Method 2: Via Service Provider**

Register templates during container initialization:

```php
<?php

namespace MyExtension\Drush;

use Aegir\Provision\Config\TemplateRenderer;
use Psr\Container\ContainerInterface;

class MyExtensionServiceProvider
{
    public static function register(ContainerInterface $container): void
    {
        $templates = $container->get(TemplateRenderer::class);
        
        // Register extension's template directory
        $extensionPath = dirname(__DIR__);
        $templatePath = $extensionPath . '/templates';
        
        if (is_dir($templatePath)) {
            $templates->registerTemplatePath($templatePath, 50);
        }
    }
}
```

**Method 3: Programmatic Registration**

Register templates directly in code:

```php
// Register multiple directories with different priorities
$templates->registerTemplatePath('/var/aegir/custom-extension/templates', 50);
$templates->registerTemplatePath('/var/aegir/site-templates', 100);

// Development override (highest priority)
if (getenv('AEGIR_DEV_MODE') === 'true') {
    $templates->registerTemplatePath('/tmp/aegir-dev-templates', 200);
}
```

#### Priority Levels Guide

| Priority | Purpose | Use Case |
|----------|---------|----------|
| 0 | Core templates | Built-in fallback templates (default) |
| 10-49 | Package defaults | Shared templates from packages |
| 50-99 | Extension templates | Extension-provided templates |
| 100-199 | User customizations | Site or organization-specific templates |
| 200+ | Development/debug | Temporary testing overrides |

#### Creating Custom Templates

Custom templates follow the same structure as core templates:

**Directory Structure**:
```
my-extension/templates/
├── apache/
│   ├── vhost.tpl.php          # HTTP vhost
│   └── vhost_ssl.tpl.php      # HTTPS vhost
├── nginx/
│   ├── vhost.tpl.php
│   └── vhost_ssl.tpl.php
└── drupal/
    └── settings.php.tpl.php
```

**Example Custom Apache Vhost**:

See [examples/templates/apache/vhost.tpl.php](../../examples/templates/apache/vhost.tpl.php) for a complete example with:
- Per-site logging
- Security headers
- Performance optimization (caching, compression)
- Custom error documents
- Enhanced SSL redirects

```php
<?php
/**
 * Custom Apache vhost template with enhanced features.
 * 
 * Available variables:
 * - $server_name: Primary domain name
 * - $server_aliases: Array of alias domains
 * - $http_port: HTTP port number
 * - $docroot: Document root path
 * - $site_path: Site directory path
 * - $ssl_redirect: Boolean for HTTPS redirect
 * - $canonical_host: Canonical hostname
 * - $extra_config: Additional Apache directives
 */
?>
<VirtualHost *:<?php print (int) $http_port; ?>>
  ServerName <?php print $server_name; ?>
  
  # Custom per-site logging
  ErrorLog ${APACHE_LOG_DIR}/<?php print str_replace('.', '_', $server_name); ?>-error.log
  CustomLog ${APACHE_LOG_DIR}/<?php print str_replace('.', '_', $server_name); ?>-access.log combined
  
  # Security headers
  <IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
  </IfModule>
  
  # ... rest of configuration ...
</VirtualHost>
```

#### Available Template Variables

**Apache vhost.tpl.php**:
- `$server_name` - Primary domain name
- `$server_aliases` - Array of alias domains
- `$http_port` - HTTP port number (default: 80)
- `$docroot` - Document root path
- `$site_path` - Site-specific directory
- `$ssl_redirect` - Boolean: redirect HTTP to HTTPS
- `$canonical_host` - Canonical hostname for redirects
- `$extra_config` - Additional Apache directives

**Apache vhost_ssl.tpl.php** (includes all vhost.tpl.php variables plus):
- `$ssl_cert` - Path to SSL certificate file
- `$ssl_key` - Path to SSL private key file
- `$ssl_chain` - Path to SSL certificate chain (optional)
- `$http_ssl_port` - HTTPS port number (default: 443)

**Drupal settings.php.tpl.php**:
- `$db_host` - Database hostname
- `$db_port` - Database port
- `$db_name` - Database name
- `$db_user` - Database username
- `$db_password` - Database password
- `$trusted_host` - Trusted host pattern
- Custom variables via options array

#### Template Debugging

Debug which template will be used for rendering:

```php
// Find which file will be loaded
$path = $templates->getTemplatePath('apache/vhost.tpl.php');
if ($path) {
    echo "Template will be loaded from: {$path}\n";
} else {
    echo "Template not found!\n";
}

// List all search paths (in priority order)
echo "Template search paths:\n";
foreach ($templates->getTemplatePaths() as $dir) {
    echo "  - {$dir}\n";
}

// Check if specific template exists
if ($templates->templateExists('apache/vhost.tpl.php')) {
    echo "Template is available\n";
}
```

#### Testing Template Overrides

Temporarily register templates for testing:

```php
function withCustomTemplates(TemplateRenderer $templates, string $tempPath, callable $callback): mixed
{
    // Register temporary template path with very high priority
    $templates->registerTemplatePath($tempPath, 999);
    
    try {
        // Run code with custom templates
        return $callback();
    } finally {
        // Clean up
        $templates->unregisterTemplatePath($tempPath);
    }
}

// Usage
$result = withCustomTemplates($templates, '/tmp/test-templates', function() use ($templates) {
    return $templates->render('apache/vhost.tpl.php', [...]);
});
```

#### Integration with Services

HTTP services (Apache, Nginx) automatically use TemplateRenderer:

```php
use Aegir\Provision\Core\ValueObject\ApacheVhostConfig;

class ApacheService implements HttpServiceInterface
{
    private TemplateRenderer $templates;
    
    public function enableSite(Context $site, Context $platform, Context $server, ApacheVhostConfig $config): array
    {
        // TemplateRenderer automatically searches custom paths
        $vhostContent = $this->templates->render('apache/vhost.tpl.php', [
            'server_name' => $config->serverName,
            'docroot' => $config->documentRoot,
            'port' => $config->port,
            'ssl_cert' => $config->sslCertPath,
            // ... other variables from value object
        ]);
        
        // Write vhost file
        $this->filesystem->writeFile($vhostPath, $vhostContent);
        
        return ['http' => $vhostPath];
    }
}
```

#### Examples

See comprehensive examples:
- [examples/templates/apache/vhost.tpl.php](../../examples/templates/apache/vhost.tpl.php) - Enhanced Apache vhost
- [examples/CustomTemplateRegistration.php](../../examples/CustomTemplateRegistration.php) - Registration patterns
- [examples/templates/README.md](../../examples/templates/README.md) - Complete usage guide

---

## Implementation Details

### Adding Events to Operations

Pattern for integrating events into ProvisionManager methods:

```php
public function operationName(string $contextName): void
{
    $context = $this->contexts->load($contextName);
    // Load related contexts as needed
    
    try {
        // 1. VALIDATE event - pre-flight checks
        $event = new OperationEvent('validate', $context, ...);
        $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_OPERATION);
        
        // 2. BEFORE event - preparation
        $event = new OperationEvent('before', $context, ...);
        $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_OPERATION);
        
        // 3. Main operation logic
        // ... perform the actual work ...
        
        // 4. AFTER event - post-processing
        $event = new OperationEvent('after', $context, ...);
        $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_OPERATION);
        
    } catch (\Exception $e) {
        // 5. ROLLBACK event - cleanup on failure
        $event = new OperationEvent('rollback', $context, ['exception' => $e]);
        $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_OPERATION);
        
        throw $e;
    }
}
```

### Complete Event List

All events are defined in `Aegir\Provision\Event\ProvisionEvents`:

**Validation Events** (52 total covering all operations):
- VALIDATE_INSTALL, VALIDATE_VERIFY, VALIDATE_BACKUP, VALIDATE_RESTORE
- VALIDATE_MIGRATE, VALIDATE_CLONE, VALIDATE_DELETE, VALIDATE_DEPLOY
- VALIDATE_ENABLE, VALIDATE_DISABLE, VALIDATE_LOCK, VALIDATE_UNLOCK

**Before Events**:
- BEFORE_INSTALL, BEFORE_VERIFY, BEFORE_BACKUP, BEFORE_RESTORE
- BEFORE_MIGRATE, BEFORE_CLONE, BEFORE_DELETE, BEFORE_DEPLOY
- BEFORE_ENABLE, BEFORE_DISABLE, BEFORE_LOCK, BEFORE_UNLOCK

**After Events**:
- AFTER_INSTALL, AFTER_VERIFY, AFTER_BACKUP, AFTER_RESTORE
- AFTER_MIGRATE, AFTER_CLONE, AFTER_DELETE, AFTER_DEPLOY
- AFTER_ENABLE, AFTER_DISABLE, AFTER_LOCK, AFTER_UNLOCK

**Rollback Events** (only for destructive operations):
- ROLLBACK_INSTALL, ROLLBACK_RESTORE, ROLLBACK_MIGRATE, ROLLBACK_CLONE

---

## Migration from D7 Hooks

### D7 to D11 Mapping

| D7 Hook | D11 Event |
|---------|-----------|
| `hook_provision_install_validate()` | `ProvisionEvents::VALIDATE_INSTALL` |
| `hook_pre_provision_install()` | `ProvisionEvents::BEFORE_INSTALL` |
| `hook_post_provision_install()` | `ProvisionEvents::AFTER_INSTALL` |
| `hook_provision_backup()` | `ProvisionEvents::AFTER_BACKUP` |
| `hook_provision_delete()` | `ProvisionEvents::BEFORE_DELETE` |

### Example Migration

**D7 Code**:
```php
function mymodule_provision_install_validate() {
  if (!valid_domain(drush_get_option('uri'))) {
    return drush_set_error('INVALID_DOMAIN', 'Domain not allowed');
  }
}
```

**D11 Code**:
```php
class ValidationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProvisionEvents::VALIDATE_INSTALL => 'validateDomain',
        ];
    }
    
    public function validateDomain(InstallEvent $event): void
    {
        $uri = $event->getSite()->get('uri');
        if (!$this->isValidDomain($uri)) {
            throw new \RuntimeException('Domain not allowed');
        }
    }
}
```

---

## Troubleshooting

### Subscriber Not Called

1. Verify subscriber is registered with dispatcher
2. Check event name matches constant from `ProvisionEvents`
3. Ensure `getSubscribedEvents()` returns correct array format

### Events Fire in Wrong Order

- Set explicit priorities (higher number = earlier execution)
- Default priority is 0

### Exception Handling

- Exceptions in VALIDATE/BEFORE prevent operation
- Exceptions in AFTER don't rollback (operation already complete)
- Use ROLLBACK events for cleanup after failures

---

## Examples

See `examples/CustomValidationSubscriber.php` for a complete, documented example covering:
- Domain validation
- Post-install notifications
- Production deletion prevention
- Webhook integration patterns

---

## Further Reading

- [Symfony EventDispatcher Documentation](https://symfony.com/doc/current/components/event_dispatcher.html)
- [D11 Architecture](../provision-d11.md) - Complete architecture overview
- [Roadmap](../roadmap.md) - Extension system status and future plans
- [API Reference](api-reference.md) - Core classes and their methods

---

**Questions or contributions?** Open an issue at https://github.com/argopecten/aegir-provision/issues
