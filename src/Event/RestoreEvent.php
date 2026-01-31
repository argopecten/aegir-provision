<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

use Aegir\Provision\Core\Context;

/**
 * Event for restore operations.
 */
class RestoreEvent extends ProvisionEvent
{
    public function __construct(
        string $operation,
        Context $context,
        private readonly string $backupPath,
        array $data = []
    ) {
        parent::__construct($operation, $context, $data);
    }

    public function getBackupPath(): string
    {
        return $this->backupPath;
    }
}
