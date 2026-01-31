<?php

declare(strict_types=1);

namespace Aegir\Provision\Core;

final class ConfigPaths {
  private string $aegirRoot;
  private string $configRoot;

  public function __construct(?string $aegirRoot = NULL, ?string $configRoot = NULL) {
    if ($aegirRoot === NULL) {
      $home = rtrim((string) getenv('HOME'), '/');
      $aegirRoot = $home !== '' ? ($home . '/aegir') : '/var/aegir';
    }
    $this->aegirRoot = rtrim($aegirRoot, '/');
    $this->configRoot = rtrim($configRoot ?? ($this->aegirRoot . '/config'), '/');
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
