<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

use Aegir\Provision\Core\Context;

/**
 * Event for site clone operations.
 */
class CloneEvent extends ProvisionEvent
{
    public function __construct(
        string $operation,
        private readonly Context $sourceSite,
        private readonly Context $targetSite,
        private readonly Context $platform,
        private readonly Context $server,
        array $data = []
    ) {
        parent::__construct($operation, $targetSite, $data);
    }

    public function getSourceSite(): Context
    {
        return $this->sourceSite;
    }

    public function getTargetSite(): Context
    {
        return $this->targetSite;
    }

    public function getPlatform(): Context
    {
        return $this->platform;
    }

    public function getServer(): Context
    {
        return $this->server;
    }
}
