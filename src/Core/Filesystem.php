<?php

declare(strict_types=1);

namespace Aegir\ProvisionD11\Core;

final class Filesystem {
  public function ensureDir(string $path, int $mode = 0750): void {
    if (is_dir($path)) {
      return;
    }
    if (!mkdir($path, $mode, TRUE) && !is_dir($path)) {
      throw new \RuntimeException('Unable to create directory: ' . $path);
    }
  }

  public function writeFile(string $path, string $contents, int $mode = 0640): void {
    $dir = dirname($path);
    $this->ensureDir($dir, 0750);
    if (file_put_contents($path, $contents) === FALSE) {
      throw new \RuntimeException('Unable to write file: ' . $path);
    }
    chmod($path, $mode);
  }

  public function remove(string $path): void {
    if (is_link($path) || is_file($path)) {
      @unlink($path);
      return;
    }
    if (is_dir($path)) {
      $this->removeDir($path);
    }
  }

  public function removeDir(string $path): void {
    if (!is_dir($path)) {
      return;
    }
    $items = scandir($path);
    if (!is_array($items)) {
      return;
    }
    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }
      $target = $path . '/' . $item;
      if (is_dir($target) && !is_link($target)) {
        $this->removeDir($target);
      }
      else {
        @unlink($target);
      }
    }
    @rmdir($path);
  }

  public function symlink(string $target, string $link): void {
    $dir = dirname($link);
    $this->ensureDir($dir, 0750);
    if (is_link($link) || file_exists($link)) {
      @unlink($link);
    }
    if (!@symlink($target, $link)) {
      throw new \RuntimeException('Unable to create symlink: ' . $link);
    }
  }
}
