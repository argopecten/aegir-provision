<?php

declare(strict_types=1);

namespace Aegir\Provision\Core\ValueObject;

/**
 * Immutable value object representing server paths configuration.
 *
 * This replaces passing path arrays throughout the codebase,
 * providing type safety and validation.
 */
final readonly class ServerPaths {
  /**
   * @param string $root Server root directory
   * @param string $configPath Configuration files directory
   * @param string $logPath Log files directory
   * @param string $backupPath Backup storage directory
   * @param string|null $sslPath SSL certificates directory (optional)
   * @param string|null $tempPath Temporary files directory (optional)
   */
  public function __construct(
    public string $root,
    public string $configPath,
    public string $logPath,
    public string $backupPath,
    public ?string $sslPath = null,
    public ?string $tempPath = null,
  ) {
    if (empty($this->root)) {
      throw new \InvalidArgumentException('Server root path cannot be empty');
    }
    if (empty($this->configPath)) {
      throw new \InvalidArgumentException('Config path cannot be empty');
    }
    if (empty($this->logPath)) {
      throw new \InvalidArgumentException('Log path cannot be empty');
    }
    if (empty($this->backupPath)) {
      throw new \InvalidArgumentException('Backup path cannot be empty');
    }
  }

  /**
   * Ensure all directories exist with proper permissions.
   *
   * @param int $mode Directory permissions (default: 0755)
   * @throws \RuntimeException If directory creation fails
   */
  public function ensureDirectoriesExist(int $mode = 0755): void {
    $paths = [
      $this->root,
      $this->configPath,
      $this->logPath,
      $this->backupPath,
    ];

    if ($this->sslPath !== null) {
      $paths[] = $this->sslPath;
    }
    if ($this->tempPath !== null) {
      $paths[] = $this->tempPath;
    }

    foreach ($paths as $path) {
      if (!is_dir($path) && !mkdir($path, $mode, true) && !is_dir($path)) {
        throw new \RuntimeException("Failed to create directory: {$path}");
      }
    }
  }

  /**
   * Create new instance with different root.
   *
   * @param string $newRoot New root directory
   * @return self New instance with updated root
   */
  public function withRoot(string $newRoot): self {
    return new self(
      root: $newRoot,
      configPath: $this->configPath,
      logPath: $this->logPath,
      backupPath: $this->backupPath,
      sslPath: $this->sslPath,
      tempPath: $this->tempPath,
    );
  }

  /**
   * Create new instance with different backup path.
   *
   * @param string $newBackupPath New backup directory
   * @return self New instance with updated backup path
   */
  public function withBackupPath(string $newBackupPath): self {
    return new self(
      root: $this->root,
      configPath: $this->configPath,
      logPath: $this->logPath,
      backupPath: $newBackupPath,
      sslPath: $this->sslPath,
      tempPath: $this->tempPath,
    );
  }
}
