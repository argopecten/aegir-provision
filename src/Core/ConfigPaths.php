<?php

declare(strict_types=1);

namespace Aegir\Provision\Core;

final class ConfigPaths {
  /**
   * Cached project root, detected once per process.
   */
  private static ?string $projectRoot = null;

  private string $aegirRoot;
  private string $configRoot;

  /**
   * @param string|null $configRoot Override the default config directory.
   *   If null, defaults to {projectRoot}/config.
   */
  public function __construct(?string $configRoot = null) {
    $this->aegirRoot = self::projectRoot();
    $this->configRoot = rtrim($configRoot ?? ($this->aegirRoot . '/config'), '/');
  }

  /**
   * Return the project root (Aegir installation root).
   *
   * Detected once from this package's location inside vendor/. The project
   * root is the nearest ancestor directory that contains both composer.json
   * and a vendor/ directory. The value is determined at install time and
   * is not configurable.
   *
   * @throws \RuntimeException If the project root cannot be detected.
   */
  public static function projectRoot(): string {
    if (self::$projectRoot === null) {
      self::$projectRoot = self::detect();
    }
    return self::$projectRoot;
  }

  /**
   * Walk up from this file's directory to locate the project root.
   */
  private static function detect(): string {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
      $dir = dirname($dir);
      if (is_file($dir . '/composer.json') && is_dir($dir . '/vendor')) {
        return $dir;
      }
      if ($dir === '/' || $dir === '') {
        break;
      }
    }
    throw new \RuntimeException(
      'Unable to detect Aegir project root from ' . __DIR__
    );
  }

  public function aegirRoot(): string {
    return $this->aegirRoot;
  }

  public function configRoot(): string {
    return $this->configRoot;
  }

  public function includePath(): string {
    return $this->configRoot() . '/includes';
  }

  public function serverConfigPath(string $serverName): string {
    $serverName = ltrim($serverName, '@');
    return $this->configRoot() . '/' . $serverName;
  }

  public function backupPath(): string {
    return $this->aegirRoot . '/backups';
  }

  public function clientsPath(): string {
    return $this->aegirRoot . '/clients';
  }

  public function platformsPath(): string {
    return $this->aegirRoot . '/platforms';
  }

  public function httpRoot(string $serverName, string $service): string {
    return $this->serverConfigPath($serverName) . '/' . $service;
  }

  public function sslRoot(): string {
    return $this->configRoot() . '/ssl.d';
  }

  public function serverSslPath(string $serverName): string {
    return $this->serverConfigPath($serverName) . '/ssl.d';
  }
}
