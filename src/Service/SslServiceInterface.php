<?php

declare(strict_types=1);

namespace Aegir\Provision\Service;

/**
 * Interface for SSL/TLS certificate management implementations.
 */
interface SslServiceInterface
{
    /**
     * Resolve SSL certificate paths for a domain.
     *
     * @param array<string,mixed> $options SSL provider options (ssl_provider, ssl_key, etc.)
     * @return array{cert:string,key:string,chain:?string} Certificate paths
     */
    public function resolve(string $serverName, string $domain, array $options = []): array;
}
