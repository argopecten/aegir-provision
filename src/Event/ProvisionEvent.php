<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

use Aegir\Provision\Core\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for all Provision events.
 */
abstract class ProvisionEvent extends Event
{
    public function __construct(
        protected readonly string $operation,
        protected readonly Context $context,
        protected array $data = []
    ) {}

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function hasData(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function getDataValue(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
