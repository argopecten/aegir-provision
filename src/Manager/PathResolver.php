<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Core\ConfigPaths;
use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\PlatformRoot;
use Aegir\Provision\Core\ValueObject\ServerPaths;

/**
 * Resolves filesystem paths for contexts.
 */
final class PathResolver {
  public function resolveDocroot(Context $platform): string {
    $root = (string) $platform->get('root');
    if ($root === '') {
      throw new \RuntimeException('Platform context missing root.');
    }
    return PlatformRoot::resolve($root);
  }

  public function resolveSitePath(Context $site, string $docroot): string {
    $sitePath = $site->get('site_path');
    if ($sitePath) {
      return (string) $sitePath;
    }
    $uri = (string) ($site->get('uri') ?: $site->name());
    return $docroot . '/sites/' . $uri;
  }

  /**
   * Resolve all server paths as a value object.
   */
  public function resolveServerPaths(Context $server): ServerPaths {
    $defaultPaths = new ConfigPaths();
    $aegirRoot = (string) $server->get('aegir_root', $defaultPaths->aegirRoot());
    $configPath = (string) $server->get('config_path', $aegirRoot . '/config');
    $backupPath = (string) $server->get('backup_path', $aegirRoot . '/backups');
    $sslPath = $server->get('ssl_path') ? (string) $server->get('ssl_path') : null;
    $tempPath = $server->get('temp_path') ? (string) $server->get('temp_path') : null;
    
    $logPath = (string) $server->get('log_path', $aegirRoot . '/logs');

    return new ServerPaths(
      root: $aegirRoot,
      configPath: $configPath,
      logPath: $logPath,
      backupPath: $backupPath,
      sslPath: $sslPath,
      tempPath: $tempPath,
    );
  }

  public function buildConfigPaths(Context $server): ConfigPaths {
    $paths = $this->resolveServerPaths($server);
    return new ConfigPaths($paths->root, $paths->configPath);
  }

  public function detectDrupalVersion(string $docroot): ?string {
    $candidates = [
      $docroot . '/core/lib/Drupal.php',
      $docroot . '/core/lib/Drupal/Core/DrupalKernel.php',
      $docroot . '/core/composer.json',
    ];

    foreach ($candidates as $path) {
      if (!is_readable($path)) {
        continue;
      }
      $contents = file_get_contents($path);
      if ($contents === FALSE) {
        continue;
      }

      if (preg_match("/const VERSION = '([^']+)'/", $contents, $matches)) {
        return $matches[1];
      }

      if (preg_match('/"version"\s*:\s*"([^"]+)"/', $contents, $matches)) {
        return $matches[1];
      }
    }

    return NULL;
  }

  public function lockPath(Context $context, ContextLoader $loader): string {
    if ($context->type() === 'platform') {
      return rtrim((string) $context->get('root'), '/') . '/.aegir.lock';
    }
    if ($context->type() === 'site') {
      $platform = $loader->loadPlatform($context);
      $docroot = $this->resolveDocroot($platform);
      $sitePath = $this->resolveSitePath($context, $docroot);
      return $sitePath . '/.aegir.lock';
    }

    $paths = $this->resolveServerPaths($context);
    return $paths->configPath . '/.aegir.lock';
  }
}
