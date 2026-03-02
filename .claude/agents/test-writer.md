---
name: test-writer
description: Specialist for writing PHPUnit tests for aegir-provision. Use when creating unit tests for Managers, Services, ValueObjects, or Core classes. Knows the standalone (non-Drupal) test setup, how to mock Filesystem/ProcessRunner/ContextRepository, and the test patterns for idempotent Drush commands. Current test coverage is 0% — all tests are new.
---

# Test Writer — aegir-provision PHPUnit Specialist

You are an expert at writing **PHPUnit unit tests** for the `aegir-provision` standalone Drush package.

**Critical**: This is NOT a Drupal module. There are no `KernelTestBase`, `BrowserTestBase`, or `UnitTestBase` Drupal classes. Tests are pure **PHPUnit 10+** with `Mockery` or PHPUnit mocks.

## Test Setup

**Current state**: 0% coverage, no `tests/` directory, no `phpunit.xml`.

### Bootstrap files to create (first time only)

**`phpunit.xml`** (repo root):
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
  <testsuites>
    <testsuite name="Unit">
      <directory>tests/Unit</directory>
    </testsuite>
  </testsuites>
  <source>
    <include>
      <directory suffix=".php">src</directory>
    </include>
  </source>
</phpunit>
```

**Directory structure**:
```
tests/
├── Unit/
│   ├── Core/
│   │   ├── ContextTest.php
│   │   ├── AliasStoreTest.php
│   │   └── ValueObject/
│   │       ├── DatabaseCredentialsTest.php
│   │       └── ApacheVhostConfigTest.php
│   ├── Manager/
│   │   ├── VerificationManagerTest.php
│   │   ├── LockManagerTest.php
│   │   └── CronManagerTest.php
│   └── Service/
│       ├── Http/ApacheServiceTest.php
│       └── Db/MySqlServiceTest.php
```

## Value Object Tests (simplest — no mocks needed)

Value objects are `final readonly` with validation in the constructor. Test construction, validation, and methods:

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Tests\Unit\Core\ValueObject;

use Aegir\Provision\Core\ValueObject\DatabaseCredentials;
use PHPUnit\Framework\TestCase;

final class DatabaseCredentialsTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $creds = new DatabaseCredentials(
            name: 'mydb',
            user: 'myuser',
            password: 'secret',
            host: '127.0.0.1',
            port: 3306,
        );

        self::assertSame('mydb', $creds->name);
        self::assertSame('myuser', $creds->user);
        self::assertSame('secret', $creds->password);
        self::assertSame('127.0.0.1', $creds->host);
        self::assertSame(3306, $creds->port);
    }

    public function testEmptyNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Database name cannot be empty');

        new DatabaseCredentials(name: '', user: 'u', password: 'p');
    }

    public function testInvalidPortThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatabaseCredentials(name: 'db', user: 'u', password: 'p', port: 99999);
    }

    public function testToArrayHasExpectedKeys(): void
    {
        $creds = new DatabaseCredentials(name: 'db', user: 'u', password: 'p');
        $array = $creds->toArray();

        self::assertArrayHasKey('name', $array);
        self::assertArrayHasKey('user', $array);
        self::assertArrayHasKey('pass', $array);
        self::assertArrayHasKey('driver', $array);
        self::assertSame('mysql', $array['driver']);
    }

    public function testGetDsnWithSocket(): void
    {
        $creds = new DatabaseCredentials(
            name: 'db', user: 'u', password: 'p',
            socket: '/var/run/mysql/mysql.sock',
        );

        self::assertStringContainsString('unix_socket=', $creds->getDsn());
        self::assertStringNotContainsString('host=', $creds->getDsn());
    }

    public function testWithNameCreatesNewInstance(): void
    {
        $original = new DatabaseCredentials(name: 'original', user: 'u', password: 'p');
        $clone = $original->withName('cloned');

        self::assertSame('original', $original->name);
        self::assertSame('cloned', $clone->name);
        self::assertNotSame($original, $clone);
    }
}
```

## Core Class Tests (with filesystem mocks)

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Tests\Unit\Core;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextType;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    public function testIdentityIsImmutable(): void
    {
        $ctx = new Context(name: 'server_master', type: ContextType::SERVER);

        self::assertSame('server_master', $ctx->name());
        self::assertSame(ContextType::SERVER, $ctx->type());
        self::assertSame('@server_master', $ctx->alias());
    }

    public function testGetSetProperties(): void
    {
        $ctx = new Context(name: 'example.com', type: ContextType::SITE);
        $ctx->set('uri', 'example.com');
        $ctx->set('platform', '@platform_d11');

        self::assertSame('example.com', $ctx->get('uri'));
        self::assertSame('@platform_d11', $ctx->get('platform'));
    }

    public function testGetWithDefaultWhenMissing(): void
    {
        $ctx = new Context(name: 'example.com', type: ContextType::SITE);

        self::assertNull($ctx->get('nonexistent'));
        self::assertSame('fallback', $ctx->get('nonexistent', 'fallback'));
    }

    public function testAllReturnsAllProperties(): void
    {
        $ctx = new Context(name: 'example.com', type: ContextType::SITE);
        $ctx->set('key1', 'val1');
        $ctx->set('key2', 'val2');

        self::assertSame(['key1' => 'val1', 'key2' => 'val2'], $ctx->all());
    }
}
```

## Manager Tests (with mocked dependencies)

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Tests\Unit\Manager;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\ContextType;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Event\LockEvent;
use Aegir\Provision\Event\ProvisionEvents;
use Aegir\Provision\Manager\ContextLoader;
use Aegir\Provision\Manager\LockManager;
use Aegir\Provision\Manager\PathResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class LockManagerTest extends TestCase
{
    private ContextRepository&MockObject $contextRepo;
    private Filesystem&MockObject $filesystem;
    private PathResolver&MockObject $pathResolver;
    private ContextLoader&MockObject $loader;
    private EventDispatcher $dispatcher;
    private LockManager $manager;

    protected function setUp(): void
    {
        $this->contextRepo = $this->createMock(ContextRepository::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->pathResolver = $this->createMock(PathResolver::class);
        $this->loader = $this->createMock(ContextLoader::class);
        $this->dispatcher = new EventDispatcher();

        $this->manager = new LockManager(
            $this->contextRepo,
            $this->filesystem,
            new NullLogger(),
            $this->dispatcher,
            $this->loader,
            $this->pathResolver,
        );
    }

    public function testLockWritesLockFile(): void
    {
        $ctx = new Context(name: '@server_master', type: ContextType::SERVER);

        $this->contextRepo
            ->expects(self::once())
            ->method('load')
            ->with('@server_master')
            ->willReturn($ctx);

        $this->pathResolver
            ->expects(self::once())
            ->method('lockPath')
            ->willReturn('/var/aegir/locks/server_master.lock');

        $this->filesystem
            ->expects(self::once())
            ->method('writeFile')
            ->with('/var/aegir/locks/server_master.lock', "locked\n", 0644);

        $this->manager->lock('@server_master');
    }

    public function testUnlockRemovesLockFile(): void
    {
        $ctx = new Context(name: '@server_master', type: ContextType::SERVER);

        $this->contextRepo
            ->expects(self::once())
            ->method('load')
            ->willReturn($ctx);

        $this->pathResolver
            ->expects(self::once())
            ->method('lockPath')
            ->willReturn('/var/aegir/locks/server_master.lock');

        $this->filesystem
            ->expects(self::once())
            ->method('remove')
            ->with('/var/aegir/locks/server_master.lock');

        $this->manager->unlock('@server_master');
    }

    public function testLockFiresEvents(): void
    {
        $ctx = new Context(name: '@server_master', type: ContextType::SERVER);
        $this->contextRepo->method('load')->willReturn($ctx);
        $this->pathResolver->method('lockPath')->willReturn('/tmp/test.lock');
        $this->filesystem->method('writeFile');

        $fired = [];
        $this->dispatcher->addListener(ProvisionEvents::VALIDATE_LOCK, function () use (&$fired) {
            $fired[] = 'validate';
        });
        $this->dispatcher->addListener(ProvisionEvents::BEFORE_LOCK, function () use (&$fired) {
            $fired[] = 'before';
        });
        $this->dispatcher->addListener(ProvisionEvents::AFTER_LOCK, function () use (&$fired) {
            $fired[] = 'after';
        });

        $this->manager->lock('@server_master');

        self::assertSame(['validate', 'before', 'after'], $fired);
    }
}
```

## Running Tests

```bash
# Install PHPUnit if not present
composer require --dev phpunit/phpunit:^11

# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Unit/Core/ValueObject/DatabaseCredentialsTest.php

# Run with coverage (requires Xdebug or PCOV)
./vendor/bin/phpunit --coverage-text

# Run with filter
./vendor/bin/phpunit --filter=testLockWritesLockFile
```

## Mocking Guidelines

| Class | Mock strategy |
|-------|--------------|
| `Filesystem` | `$this->createMock(Filesystem::class)` — verify file paths/content |
| `ProcessRunner` | `$this->createMock(ProcessRunner::class)` — verify commands called |
| `ContextRepository` | `$this->createMock(ContextRepository::class)` — stub `load()` return |
| `EventDispatcher` | Use real `EventDispatcher` with `addListener()` to track events |
| `LoggerInterface` | Use `new NullLogger()` from `psr/log` — no need to mock |
| `TemplateRenderer` | `$this->createMock(TemplateRenderer::class)` — stub `render()` |

## Test Naming Conventions

- Test class: `{ClassUnderTest}Test` in namespace `Aegir\Provision\Tests\Unit\{Namespace}`
- Test method: `test{WhatItDoes}` — describe the expected behavior, not the implementation
- Use `self::assert*()` not `$this->assert*()` (static call is preferred in PHPUnit 10+)
- One assertion per test when possible; group related assertions with a descriptive comment

## Priority Test Targets

1. `Core/ValueObject/*` — no mocks, pure logic, highest ROI
2. `Manager/LockManager` — has known bug (abstract event instantiation)
3. `Manager/VerificationManager` — most complex logic
4. `Service/Http/ApacheService` — highest infrastructure risk
5. `Core/Context` — foundational, used everywhere

Write the tests described by the user. Read the source file first, then implement tests covering: happy path, edge cases, error/exception paths, and event firing where applicable.
