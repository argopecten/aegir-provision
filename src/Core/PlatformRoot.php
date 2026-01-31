<?php

declare(strict_types=1);

namespace Aegir\Provision\Core;

final class PlatformRoot {
  public static function resolve(string $path): string {
    $path = rtrim($path, '/');
    if (is_file($path . '/index.php')) {
      return $path;
    }

    foreach (['/web', '/docroot', '/html'] as $suffix) {
      if (is_file($path . $suffix . '/index.php')) {
        return $path . $suffix;
      }
    }

    return $path;
  }
}
