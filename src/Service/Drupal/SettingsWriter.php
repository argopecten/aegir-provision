<?php

declare(strict_types=1);

namespace Aegir\ProvisionD11\Service\Drupal;

use Aegir\ProvisionD11\Config\TemplateRenderer;
use Aegir\ProvisionD11\Core\Filesystem;

final class SettingsWriter {
  private Filesystem $filesystem;
  private TemplateRenderer $templates;

  public function __construct(Filesystem $filesystem, TemplateRenderer $templates) {
    $this->filesystem = $filesystem;
    $this->templates = $templates;
  }

  /**
   * @param array<string,mixed> $db
   * @param array<string,mixed> $options
   */
  public function write(string $docroot, string $sitePath, array $db, array $options = []): void {
    $this->filesystem->ensureDir($sitePath, 0755);
    $filesPath = $sitePath . '/files';
    $privatePath = $options['private_path'] ?? $sitePath . '/private';
    $this->filesystem->ensureDir($filesPath, 0775);
    $this->filesystem->ensureDir($privatePath, 0770);

    $hashSalt = (string) ($options['hash_salt'] ?? bin2hex(random_bytes(16)));
    $trustedHosts = $options['trusted_host_patterns'] ?? [];
    if (!$trustedHosts && !empty($options['trusted_host'])) {
      $trustedHosts = ['/^' . preg_quote((string) $options['trusted_host'], '/') . '$/'];
    }

    $configSync = (string) ($options['config_sync_directory'] ?? ($docroot . '/../config/sync'));

    $vars = [
      'db' => $db,
      'hash_salt' => $hashSalt,
      'private_path' => $privatePath,
      'config_sync_directory' => $configSync,
      'trusted_host_patterns' => $trustedHosts,
    ];

    $settings = $this->templates->render('drupal/settings.php.tpl.php', $vars);
    $this->filesystem->writeFile($sitePath . '/settings.php', $settings, 0664);
  }
}
