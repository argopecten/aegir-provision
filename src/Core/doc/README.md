# Core Package

**Location**: `src/Core/`  
**Purpose**: Foundational abstractions and utilities

---

## Overview

The Core package provides essential building blocks used throughout Aegir Provision:

- **Context system** - Immutable data structures for server/platform/site
- **Repository pattern** - Load/save contexts to Drush aliases
- **Filesystem abstraction** - Safe file operations with error handling
- **Process execution** - Shell command runner via Symfony Process
- **Path resolution** - Configuration and platform path management

---

## Classes

### Context

**File**: `Context.php`

Immutable value object representing a server, platform, or site context.

**Constructor**:
```php
public function __construct(string $name, string $type, array $data = [])
```

**Key Methods**:
- `name(): string` - Context name without @ prefix
- `alias(): string` - Full alias with @ prefix
- `type(): string` - Context type (server, platform, site)
- `get(string $key, mixed $default = null): mixed` - Get property value
- `set(string $key, mixed $value): void` - Set property value
- `all(): array` - Get all properties
- `toArray(): array` - Export as array

**Example**:
```php
$site = new Context('example.com', 'site', [
    'uri' => 'example.com',
    'platform' => 'platform_d11',
    'db_name' => 'example_com',
]);
echo $site->alias(); // @example.com
echo $site->get('uri'); // example.com
```

### ContextRepository

**File**: `ContextRepository.php`

Manages loading and saving contexts to Drush YAML site aliases.

**Key Methods**:
- `load(string $name): Context` - Load context from alias file
- `save(Context $context): void` - Save context to alias file
- `delete(string $name): void` - Delete alias file
- `exists(string $name): bool` - Check if context exists

**Storage Location**: `~/.drush/sites/`

### ContextType

**File**: `ContextType.php`

Enum-like class defining valid context types and their validation rules.

**Constants**:
- `SERVER` - Server context type
- `PLATFORM` - Platform context type
- `SITE` - Site context type

### Filesystem

**File**: `Filesystem.php`

Provides safe file system operations using Symfony Filesystem component.

**Key Methods**:
- `ensureDirectory(string $path, int $mode = 0755): void`
- `writeFile(string $path, string $content): void`
- `remove(string $path): void`
- `chmod(string $path, int $mode): void`
- `chown(string $path, string $user, ?string $group = null): void`

### ProcessRunner

**File**: `ProcessRunner.php`

Executes shell commands via Symfony Process component with error handling.

**Key Methods**:
- `run(array $command, ?string $cwd = null): string` - Run command, return output
- `mustRun(array $command, ?string $cwd = null): void` - Run command, throw on error

**Example**:
```php
$runner = new ProcessRunner();
$output = $runner->run(['drush', 'status', '--format=json']);
```

### ConfigPaths

**File**: `ConfigPaths.php`

Resolves configuration and platform paths from context data.

**Key Methods**:
- `getAegirRoot(Context $server): string`
- `getConfigPath(Context $server): string`
- `getPlatformRoot(Context $platform): string`

### PlatformRoot

**File**: `PlatformRoot.php`

Detects Drupal docroot within Composer-based platforms.

**Key Methods**:
- `findDocroot(string $platformPath): string` - Find `/web`, `/docroot`, or `/html`

### AliasStore

**File**: `AliasStore.php`

Interface to Drush's site alias system for context persistence.

---

## Context System Architecture

### Three Context Types

1. **Server Context** (`@server_master`)
   - `aegir_root`: Base directory (e.g., `/var/aegir`)
   - `config_path`: Configuration directory
   - `web_group`: Web server user/group

2. **Platform Context** (`@platform_d11`)
   - `root`: Platform path
   - `server`: Server context name
   - `webserver`: HTTP service type

3. **Site Context** (`@example.com`)
   - `uri`: Site domain
   - `platform`: Platform context name
   - `db_name`, `db_user`, `db_passwd`: Database credentials

### Storage Format

Contexts are stored as Drush YAML aliases in `~/.drush/sites/*.site.yml`:

```yaml
example.com:
  provision:
    uri: example.com
    platform: platform_d11
    db_name: example_com
    db_user: aegir
```

---

## Related Documentation

- [Core Concepts](../../../doc/guides/architecture.md) - Context system explained
- [D11 Architecture](../../../doc/provision-d11.md) - Detailed architecture
- [API Reference](../../../doc/guides/api-reference.md) - Complete API documentation
- [Quick Start](../../../doc/Home.md) - Context usage examples
