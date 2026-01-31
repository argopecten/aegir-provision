<?php

declare(strict_types=1);

namespace Aegir\Provision\Core\ValueObject;

/**
 * Immutable value object representing database credentials.
 *
 * This replaces passing database credential arrays throughout the codebase,
 * providing type safety and validation.
 */
final readonly class DatabaseCredentials {
  /**
   * @param string $name Database name
   * @param string $user Database username
   * @param string $password Database password
   * @param string $host Database host (default: localhost)
   * @param int $port Database port (default: 3306)
   * @param string|null $socket Optional Unix socket path
   */
  public function __construct(
    public string $name,
    public string $user,
    public string $password,
    public string $host = 'localhost',
    public int $port = 3306,
    public ?string $socket = null,
  ) {
    if (empty($this->name)) {
      throw new \InvalidArgumentException('Database name cannot be empty');
    }
    if (empty($this->user)) {
      throw new \InvalidArgumentException('Database user cannot be empty');
    }
    if ($this->port < 1 || $this->port > 65535) {
      throw new \InvalidArgumentException("Invalid port number: {$this->port}");
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $this->name)) {
      throw new \InvalidArgumentException("Invalid database name: {$this->name}");
    }
  }

  /**
   * Convert to array for template rendering and Context storage.
   *
   * @return array<string,mixed>
   */
  public function toArray(): array {
    return [
      'name' => $this->name,
      'user' => $this->user,
      'pass' => $this->password,
      'host' => $this->host,
      'port' => (string) $this->port,
      'driver' => 'mysql',
    ];
  }

  /**
   * Get DSN string for PDO connection.
   *
   * @return string PDO DSN
   */
  public function getDsn(): string {
    if ($this->socket) {
      return "mysql:unix_socket={$this->socket};dbname={$this->name}";
    }
    return "mysql:host={$this->host};port={$this->port};dbname={$this->name}";
  }

  /**
   * Get DSN without database name (for admin connections).
   *
   * @return string PDO DSN without dbname
   */
  public function getDsnWithoutDatabase(): string {
    if ($this->socket) {
      return "mysql:unix_socket={$this->socket}";
    }
    return "mysql:host={$this->host};port={$this->port}";
  }

  /**
   * Create new instance with different database name.
   *
   * @param string $newName New database name
   * @return self New instance with updated name
   */
  public function withName(string $newName): self {
    return new self(
      name: $newName,
      user: $this->user,
      password: $this->password,
      host: $this->host,
      port: $this->port,
      socket: $this->socket,
    );
  }

  /**
   * Create new instance with different user credentials.
   *
   * @param string $newUser New username
   * @param string $newPassword New password
   * @return self New instance with updated credentials
   */
  public function withCredentials(string $newUser, string $newPassword): self {
    return new self(
      name: $this->name,
      user: $newUser,
      password: $newPassword,
      host: $this->host,
      port: $this->port,
      socket: $this->socket,
    );
  }
}
