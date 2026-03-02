# Write PHPUnit Tests

Write unit tests for an aegir-provision class. This is a standalone package — pure PHPUnit, no Drupal test base classes.

## User Input Needed

Ask the user which class to test (or ask them to describe what to test).

## Setup (First Time Only)

If `phpunit.xml` doesn't exist at repo root:

```bash
composer require --dev phpunit/phpunit:^11
```

Create `phpunit.xml`:
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

Create `tests/Unit/` directory matching `src/` structure.

## Process

1. **Read the source file** being tested — understand constructor, public methods, throw conditions
2. **Identify dependencies** — which can be mocked, which are value objects (no mock needed)
3. **Write test class** with `setUp()` creating mocks, then methods for each scenario
4. **Verify** with `./vendor/bin/phpunit tests/Unit/Path/To/ClassTest.php`

## Mock Reference

```php
// PHPUnit mocks — for classes you own
$mock = $this->createMock(Filesystem::class);
$mock->expects(self::once())->method('writeFile')->with('/path', 'content', 0644);

// NullLogger — never mock Psr\Log\LoggerInterface
use Psr\Log\NullLogger;
$logger = new NullLogger();

// Real EventDispatcher — track events with addListener()
use Symfony\Component\EventDispatcher\EventDispatcher;
$dispatcher = new EventDispatcher();
$fired = [];
$dispatcher->addListener(ProvisionEvents::AFTER_LOCK, static function () use (&$fired) {
    $fired[] = 'after';
});

// Context — use real class (no mock needed), it's a simple data bag
use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextType;
$ctx = new Context(name: 'example.com', type: ContextType::SITE);
$ctx->set('uri', 'example.com');
```

## Test Class Template

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Tests\Unit\{Namespace};

use Aegir\Provision\{ClassName};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class {ClassName}Test extends TestCase
{
    // Declare mocks as typed properties
    private SomeDep&MockObject $dep;
    private {ClassName} $subject;

    protected function setUp(): void
    {
        $this->dep = $this->createMock(SomeDep::class);
        $this->subject = new {ClassName}($this->dep);
    }

    public function test{HappyPath}(): void
    {
        $this->dep->method('someMethod')->willReturn('expected');

        $result = $this->subject->doSomething('input');

        self::assertSame('expected', $result);
    }

    public function test{ExceptionPath}(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('specific message');

        $this->subject->doSomethingBad('');
    }

    public function test{EventFiring}(): void
    {
        // Use real EventDispatcher and track fired events
    }
}
```

## Running Tests

```bash
# All tests
./vendor/bin/phpunit

# Single file
./vendor/bin/phpunit tests/Unit/Manager/LockManagerTest.php

# Filtered by method name
./vendor/bin/phpunit --filter=testLockWritesLockFile

# With coverage (requires Xdebug)
./vendor/bin/phpunit --coverage-text --coverage-html=coverage/
```

## Conventions

- File: `tests/Unit/{Namespace}/{ClassName}Test.php`
- PHP namespace: `Aegir\Provision\Tests\Unit\{Namespace}`
- Use `self::assert*()` — static call (PHPUnit 10+ style)
- Method names: `test{WhatItDoes}` — behavior not implementation
- `setUp()` builds the subject under test fresh for each test
- Prefer `expects(self::once())` over `method()` when the call count matters

## Priority Targets (0% coverage today)

1. `Core/ValueObject/DatabaseCredentials` — pure logic, no mocks, easy wins
2. `Core/ValueObject/ApacheVhostConfig` — same
3. `Core/Context` — foundational data bag
4. `Manager/LockManager` — known bug, tests will expose it
5. `Manager/CronManager` — crontab manipulation
6. `Service/Http/ApacheService` — vhost generation

Read the source file first. Write tests covering: valid input (happy path), invalid input (exception paths), boundary values, and event lifecycle (VALIDATE → BEFORE → execute → AFTER).
