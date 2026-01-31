<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

use Aegir\Provision\Core\Context;

/**
 * Event for verify operations (server, platform, or site).
 */
class VerifyEvent extends ProvisionEvent
{
    public function __construct(
        string $operation,
        Context $context,
        private readonly ?Context $platform = null,
        private readonly ?Context $server = null,
        array $data = []
    ) {
        parent::__construct($operation, $context, $data);
    }

    public function getPlatform(): ?Context
    {
        return $this->platform;
    }

    public function getServer(): ?Context
    {
        return $this->server;
    }
}
