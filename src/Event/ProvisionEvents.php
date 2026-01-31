<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

/**
 * Defines events for the Provision system.
 */
final class ProvisionEvents
{
    // Validation events (before any changes)
    public const VALIDATE_INSTALL = 'provision.validate.install';
    public const VALIDATE_VERIFY = 'provision.validate.verify';
    public const VALIDATE_BACKUP = 'provision.validate.backup';
    public const VALIDATE_RESTORE = 'provision.validate.restore';
    public const VALIDATE_MIGRATE = 'provision.validate.migrate';
    public const VALIDATE_CLONE = 'provision.validate.clone';
    public const VALIDATE_DELETE = 'provision.validate.delete';
    public const VALIDATE_DEPLOY = 'provision.validate.deploy';
    public const VALIDATE_ENABLE = 'provision.validate.enable';
    public const VALIDATE_DISABLE = 'provision.validate.disable';
    public const VALIDATE_LOCK = 'provision.validate.lock';
    public const VALIDATE_UNLOCK = 'provision.validate.unlock';

    // Before events (pre-execution)
    public const BEFORE_INSTALL = 'provision.before.install';
    public const BEFORE_VERIFY = 'provision.before.verify';
    public const BEFORE_BACKUP = 'provision.before.backup';
    public const BEFORE_RESTORE = 'provision.before.restore';
    public const BEFORE_MIGRATE = 'provision.before.migrate';
    public const BEFORE_CLONE = 'provision.before.clone';
    public const BEFORE_DELETE = 'provision.before.delete';
    public const BEFORE_DEPLOY = 'provision.before.deploy';
    public const BEFORE_ENABLE = 'provision.before.enable';
    public const BEFORE_DISABLE = 'provision.before.disable';
    public const BEFORE_LOCK = 'provision.before.lock';
    public const BEFORE_UNLOCK = 'provision.before.unlock';

    // After events (post-execution)
    public const AFTER_INSTALL = 'provision.after.install';
    public const AFTER_VERIFY = 'provision.after.verify';
    public const AFTER_BACKUP = 'provision.after.backup';
    public const AFTER_RESTORE = 'provision.after.restore';
    public const AFTER_MIGRATE = 'provision.after.migrate';
    public const AFTER_CLONE = 'provision.after.clone';
    public const AFTER_DELETE = 'provision.after.delete';
    public const AFTER_DEPLOY = 'provision.after.deploy';
    public const AFTER_ENABLE = 'provision.after.enable';
    public const AFTER_DISABLE = 'provision.after.disable';
    public const AFTER_LOCK = 'provision.after.lock';
    public const AFTER_UNLOCK = 'provision.after.unlock';

    // Rollback events (on failure)
    public const ROLLBACK_INSTALL = 'provision.rollback.install';
    public const ROLLBACK_VERIFY = 'provision.rollback.verify';
    public const ROLLBACK_BACKUP = 'provision.rollback.backup';
    public const ROLLBACK_RESTORE = 'provision.rollback.restore';
    public const ROLLBACK_MIGRATE = 'provision.rollback.migrate';
    public const ROLLBACK_CLONE = 'provision.rollback.clone';
    public const ROLLBACK_DEPLOY = 'provision.rollback.deploy';
}
