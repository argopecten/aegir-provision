<?php

declare(strict_types=1);

namespace Aegir\Provision\Event;

use Aegir\Provision\Core\Context;

/**
 * Event for site installation operations.
 */
class InstallEvent extends ProvisionEvent
{
    public function __construct(
        string $operation,
        private readonly Context $site,
        private readonly Context $platform,
        private readonly Context $server,
        array $data = []
    ) {
        parent::__construct($operation, $site, $data);
    }

    public function getSite(): Context
    {
        return $this->site;
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
