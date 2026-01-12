<?php

declare(strict_types=1);

namespace Aegir\ProvisionD11\Core;

use Symfony\Component\Yaml\Yaml;

final class AliasStore {
  private string $defaultPath;

  public function __construct(?string $defaultPath = NULL) {
    $home = rtrim((string) getenv('HOME'), '/');
    $this->defaultPath = $defaultPath ?? ($home . '/.drush/sites/aegir');
  }

  /**
   * @return string[]
   */
  public function searchPaths(): array {
    $paths = [];
    $env = getenv('DRUSH_SITE_ALIAS_PATH');
    if ($env) {
      foreach (explode(':', $env) as $path) {
        $path = trim($path);
        if ($path !== '') {
          $paths[] = $path;
        }
      }
    }

    $paths[] = $this->defaultPath;
    return array_values(array_unique($paths));
  }

  public function readAlias(string $contextName): ?array {
    $contextName = ltrim($contextName, '@');
    $filename = $contextName . '.site.yml';

    foreach ($this->searchPaths() as $path) {
      $full = rtrim($path, '/') . '/' . $filename;
      if (is_readable($full)) {
        $data = Yaml::parseFile($full);
        if (!is_array($data)) {
          return NULL;
        }
        if (isset($data[$contextName]) && is_array($data[$contextName])) {
          return $data[$contextName];
        }
      }
    }

    return NULL;
  }

  public function writeAlias(string $contextName, array $alias): string {
    $contextName = ltrim($contextName, '@');
    $filename = $contextName . '.site.yml';
    $targetDir = $this->searchPaths()[0];

    if (!is_dir($targetDir)) {
      if (!mkdir($targetDir, 0775, TRUE) && !is_dir($targetDir)) {
        throw new \RuntimeException('Unable to create alias directory: ' . $targetDir);
      }
    }

    $data = [$contextName => $alias];
    $yaml = Yaml::dump($data, 10, 2);
    $full = rtrim($targetDir, '/') . '/' . $filename;
    if (file_put_contents($full, $yaml) === FALSE) {
      throw new \RuntimeException('Unable to write alias file: ' . $full);
    }

    return $full;
  }

  public function deleteAlias(string $contextName): void {
    $contextName = ltrim($contextName, '@');
    $filename = $contextName . '.site.yml';

    foreach ($this->searchPaths() as $path) {
      $full = rtrim($path, '/') . '/' . $filename;
      if (is_file($full)) {
        @unlink($full);
      }
    }
  }
}
