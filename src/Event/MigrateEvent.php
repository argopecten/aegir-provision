<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

use Aegir\Provision\Core\Context;

/**
 * Event for site migration operations.
 */
class MigrateEvent extends ProvisionEvent
{
    public function __construct(
        string $operation,
        private readonly Context $site,
        private readonly Context $oldPlatform,
        private readonly Context $newPlatform,
        private readonly Context $server,
        array $data = []
    ) {
        parent::__construct($operation, $site, $data);
    }

    public function getSite(): Context
    {
        return $this->site;
    }

    public function getOldPlatform(): Context
    {
        return $this->oldPlatform;
    }

    public function getNewPlatform(): Context
    {
        return $this->newPlatform;
    }

    public function getServer(): Context
    {
        return $this->server;
    }
}
