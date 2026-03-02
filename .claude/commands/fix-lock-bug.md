# Fix LockManager Known Bug

Fix the PHP Fatal Error in `LockManager.php` caused by instantiating the abstract `ProvisionEvent` class directly.

## The Bug

`src/Manager/LockManager.php` calls `new ProvisionEvent(...)` but `ProvisionEvent` is declared `abstract`.
This causes a **PHP Fatal Error at runtime** whenever `provision:lock` or `provision:unlock` is invoked.

```php
// ❌ CURRENT (broken) — ProvisionEvent is abstract
$event = new ProvisionEvent('validate', $context);
$this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_LOCK);
```

## The Fix

### Step 1: Create LockEvent class

Create `src/Event/LockEvent.php`:

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

final class LockEvent extends ProvisionEvent {}
```

Create `src/Event/UnlockEvent.php`:

```php
<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

final class UnlockEvent extends ProvisionEvent {}
```

### Step 2: Update LockManager

In `src/Manager/LockManager.php`, replace all `new ProvisionEvent(...)` calls:

```php
// ✅ FIXED — use concrete event classes

// In lock():
$event = new LockEvent($context);                         // replaces: new ProvisionEvent('validate', $context)
$this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_LOCK);

$event = new LockEvent($context);
$this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_LOCK);

// ... (actual lock logic) ...

$event = new LockEvent($context);
$this->dispatcher->dispatch($event, ProvisionEvents::AFTER_LOCK);

// In unlock():
$event = new UnlockEvent($context);
$this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_UNLOCK);

$event = new UnlockEvent($context);
$this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_UNLOCK);

// ... (actual unlock logic) ...

$event = new UnlockEvent($context);
$this->dispatcher->dispatch($event, ProvisionEvents::AFTER_UNLOCK);
```

### Step 3: Add the use statement

At the top of `LockManager.php`, add:

```php
use Aegir\Provision\Event\LockEvent;
use Aegir\Provision\Event\UnlockEvent;
```

Remove the existing: `use Aegir\Provision\Event\ProvisionEvent;` (no longer needed directly).

### Step 4: Verify

```bash
# Test lock command (will throw Fatal Error before fix, succeed after)
drush provision:lock @server_master -v

# Verify no abstract class instantiation remains
grep -rn "new ProvisionEvent" src/
```

### Step 5: Add tests

After fixing, add tests in `tests/Unit/Manager/LockManagerTest.php` that verify:
1. `lock()` fires VALIDATE_LOCK, BEFORE_LOCK, AFTER_LOCK events in order
2. `unlock()` fires VALIDATE_UNLOCK, BEFORE_UNLOCK, AFTER_UNLOCK events
3. No PHP errors are thrown

## Check for Similar Bugs Elsewhere

```bash
# Find any other abstract ProvisionEvent instantiation
grep -rn "new ProvisionEvent" src/
```

All managers should use concrete event subclasses: `VerifyEvent`, `InstallEvent`, `DeleteEvent`, `BackupEvent`, `RestoreEvent`, `DeployEvent`, `MigrateEvent`, `CloneEvent`, `CronEvent`, `LockEvent`, `UnlockEvent`.

Read `src/Manager/LockManager.php` and `src/Event/ProvisionEvent.php` before making changes.
