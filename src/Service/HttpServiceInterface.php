<?php

declare(strict_types=1);

namespace Aegir\Provision\Service;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ValueObject\ApacheVhostConfig;

/**
 * Interface for HTTP service implementations (Apache, Nginx, etc.).
 */
interface HttpServiceInterface
{
    /**
     * Ensure server directory layout exists.
     *
     * @return array<string,string> Map of directory names to paths
     */
    public function ensureServerLayout(string $serverName): array;

    /**
     * Enable a site by generating vhost configuration.
     *
     * @return array<string,string> Map of generated file types to paths
     */
    public function enableSite(Context $site, Context $platform, Context $server, ApacheVhostConfig $config): array;

    /**
     * Disable a site by moving vhost to disabled directory.
     */
    public function disableSite(string $serverName, string $siteName): void;

    /**
     * Remove a site's vhost configuration completely.
     */
    public function removeSite(string $serverName, string $siteName): void;

    /**
     * Reload the web server to apply configuration changes.
     */
    public function reload(?string $restartCmd): void;
}
