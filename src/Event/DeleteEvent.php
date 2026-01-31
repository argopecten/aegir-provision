<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

use Aegir\Provision\Core\Context;

/**
 * Event for delete operations.
 */
class DeleteEvent extends ProvisionEvent
{
    public function __construct(
        string $operation,
        Context $context,
        private readonly bool $deleteDatabase = false,
        private readonly bool $deleteFiles = false,
        array $data = []
    ) {
        parent::__construct($operation, $context, $data);
    }

    public function shouldDeleteDatabase(): bool
    {
        return $this->deleteDatabase;
    }

    public function shouldDeleteFiles(): bool
    {
        return $this->deleteFiles;
    }
}
