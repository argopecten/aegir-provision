<?php

declare(strict_types=1);

namespace Aegir\Provision\Core\ValueObject;

/**
 * Immutable value object representing Apache virtual host configuration.
 *
 * This replaces passing vhost config arrays throughout the codebase,
 * providing type safety and validation.
 */
final readonly class ApacheVhostConfig {
  /**
   * @param string $serverName Primary server name (domain)
   * @param string $documentRoot Document root path
   * @param int $port Port number (default: 80)
   * @param array<string> $serverAliases Additional server aliases
   * @param string|null $serverAdmin Server admin email
   * @param string|null $logPath Custom log path
   * @param string|null $sslCertPath SSL certificate path (for HTTPS)
   * @param string|null $sslKeyPath SSL private key path (for HTTPS)
   * @param string|null $sslCaPath SSL CA certificate path (optional)
   * @param array<string,mixed> $customDirectives Custom Apache directives
   */
  public function __construct(
    public string $serverName,
    public string $documentRoot,
    public int $port = 80,
    public array $serverAliases = [],
    public ?string $serverAdmin = null,
    public ?string $logPath = null,
    public ?string $sslCertPath = null,
    public ?string $sslKeyPath = null,
    public ?string $sslCaPath = null,
    public array $customDirectives = [],
  ) {
    if (empty($this->serverName)) {
      throw new \InvalidArgumentException('Server name cannot be empty');
    }
    if (empty($this->documentRoot)) {
      throw new \InvalidArgumentException('Document root cannot be empty');
    }
    if ($this->port < 1 || $this->port > 65535) {
      throw new \InvalidArgumentException("Invalid port number: {$this->port}");
    }
    if (!$this->isValidDomain($this->serverName)) {
      throw new \InvalidArgumentException("Invalid server name: {$this->serverName}");
    }
  }

  /**
   * Check if SSL is enabled.
   *
   * @return bool True if SSL certificate and key are configured
   */
  public function isSslEnabled(): bool {
    return $this->sslCertPath !== null && $this->sslKeyPath !== null;
  }

  /**
   * Get the protocol (http or https).
   *
   * @return string Protocol string
   */
  public function getProtocol(): string {
    return $this->isSslEnabled() ? 'https' : 'http';
  }

  /**
   * Get the full URL with protocol, domain, and port.
   *
   * @return string Full URL
   */
  public function getFullUrl(): string {
    $protocol = $this->getProtocol();
    $defaultPort = $protocol === 'https' ? 443 : 80;
    
    if ($this->port === $defaultPort) {
      return "{$protocol}://{$this->serverName}";
    }
    
    return "{$protocol}://{$this->serverName}:{$this->port}";
  }

  /**
   * Create new instance with SSL configuration.
   *
   * @param string $certPath SSL certificate path
   * @param string $keyPath SSL private key path
   * @param string|null $caPath SSL CA certificate path (optional)
   * @return self New instance with SSL enabled
   */
  public function withSsl(string $certPath, string $keyPath, ?string $caPath = null): self {
    return new self(
      serverName: $this->serverName,
      documentRoot: $this->documentRoot,
      port: 443, // Switch to HTTPS port
      serverAliases: $this->serverAliases,
      serverAdmin: $this->serverAdmin,
      logPath: $this->logPath,
      sslCertPath: $certPath,
      sslKeyPath: $keyPath,
      sslCaPath: $caPath,
      customDirectives: $this->customDirectives,
    );
  }

  /**
   * Create new instance with additional server aliases.
   *
   * @param array<string> $aliases Additional server aliases to add
   * @return self New instance with updated aliases
   */
  public function withServerAliases(array $aliases): self {
    return new self(
      serverName: $this->serverName,
      documentRoot: $this->documentRoot,
      port: $this->port,
      serverAliases: array_unique(array_merge($this->serverAliases, $aliases)),
      serverAdmin: $this->serverAdmin,
      logPath: $this->logPath,
      sslCertPath: $this->sslCertPath,
      sslKeyPath: $this->sslKeyPath,
      sslCaPath: $this->sslCaPath,
      customDirectives: $this->customDirectives,
    );
  }

  /**
   * Validate domain name format.
   *
   * @param string $domain Domain name to validate
   * @return bool True if valid
   */
  private function isValidDomain(string $domain): bool {
    // Allow localhost and IP addresses for development
    if ($domain === 'localhost' || filter_var($domain, FILTER_VALIDATE_IP)) {
      return true;
    }
    
    // Basic domain validation
    return (bool) preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/', $domain);
  }
}
