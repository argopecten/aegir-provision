<?php

declare(strict_types=1);

namespace Aegir\ProvisionD11\Service\Http;

use Aegir\ProvisionD11\Config\TemplateRenderer;
use Aegir\ProvisionD11\Core\ConfigPaths;
use Aegir\ProvisionD11\Core\Context;
use Aegir\ProvisionD11\Core\Filesystem;
use Aegir\ProvisionD11\Core\ProcessRunner;
use Aegir\ProvisionD11\Service\Ssl\SslManager;

final class ApacheService {
  private ConfigPaths $paths;
  private Filesystem $filesystem;
  private TemplateRenderer $templates;
  private ProcessRunner $runner;
  private SslManager $sslManager;

  public function __construct(ConfigPaths $paths, Filesystem $filesystem, TemplateRenderer $templates, ProcessRunner $runner, SslManager $sslManager) {
    $this->paths = $paths;
    $this->filesystem = $filesystem;
    $this->templates = $templates;
    $this->runner = $runner;
    $this->sslManager = $sslManager;
  }

  /**
   * @return array<string,string>
   */
  public function ensureServerLayout(string $serverName): array {
    $base = $this->paths->serverConfigPath($serverName) . '/apache';
    $dirs = [
      'base' => $base,
      'pre' => $base . '/pre.d',
      'post' => $base . '/post.d',
      'platform' => $base . '/platform.d',
      'vhost' => $base . '/vhost.d',
      'vhost_ssl' => $base . '/vhost_ssl.d',
      'disabled' => $base . '/disabled.d',
    ];

    foreach ($dirs as $dir) {
      $this->filesystem->ensureDir($dir, 0750);
    }

    return $dirs;
  }

  /**
   * @return array<string,string>
   */
  public function enableSite(Context $site, Context $platform, Context $server, array $options = []): array {
    $dirs = $this->ensureServerLayout($server->name());
    $filename = $this->sanitizeFileName($site->name()) . '.conf';

    $docroot = $options['docroot'] ?? $platform->get('root');
    $sitePath = $options['site_path'] ?? $docroot . '/sites/' . $site->get('uri');

    $httpPort = (int) ($options['http_port'] ?? $server->get('http_port', 80));
    $sslPort = (int) ($options['http_ssl_port'] ?? $server->get('http_ssl_port', 443));

    $sslEnabled = (bool) ($site->get('ssl_enabled', FALSE) || $site->get('ssl', FALSE));
    $sslRedirect = (bool) ($site->get('ssl_redirect', FALSE) || $site->get('ssl_redirection', FALSE));

    $vars = [
      'server_name' => $site->get('uri'),
      'server_aliases' => (array) ($site->get('aliases', []) ?: []),
      'docroot' => $docroot,
      'site_path' => $sitePath,
      'http_port' => $httpPort,
      'ssl_redirect' => $sslRedirect,
      'canonical_host' => $site->get('redirection'),
      'extra_config' => (string) ($site->get('http_extra_config', '') ?: $site->get('apache_extra_config', '')),
    ];

    $vhost = $this->templates->render('apache/vhost.tpl.php', $vars);
    $vhostPath = $dirs['vhost'] . '/' . $filename;
    $this->filesystem->writeFile($vhostPath, $vhost, 0644);

    $sslPath = '';
    if ($sslEnabled) {
      $ssl = $this->sslManager->resolve($server->name(), $site->get('uri'), array_merge($server->all(), $site->all()));
      $sslVars = $vars + [
        'http_ssl_port' => $sslPort,
        'ssl_cert' => $ssl['cert'],
        'ssl_key' => $ssl['key'],
        'ssl_chain' => $ssl['chain'],
      ];
      $vhostSsl = $this->templates->render('apache/vhost_ssl.tpl.php', $sslVars);
      $sslPath = $dirs['vhost_ssl'] . '/' . $filename;
      $this->filesystem->writeFile($sslPath, $vhostSsl, 0644);
    }
    else {
      $this->filesystem->remove($dirs['vhost_ssl'] . '/' . $filename);
    }

    $this->filesystem->remove($dirs['disabled'] . '/' . $filename);

    return [
      'http' => $vhostPath,
      'https' => $sslPath,
    ];
  }

  public function disableSite(string $serverName, string $siteName): void {
    $dirs = $this->ensureServerLayout($serverName);
    $filename = $this->sanitizeFileName($siteName) . '.conf';

    $enabled = [
      $dirs['vhost'] . '/' . $filename,
      $dirs['vhost_ssl'] . '/' . $filename,
    ];

    foreach ($enabled as $source) {
      if (is_file($source)) {
        $target = $dirs['disabled'] . '/' . basename($source);
        @rename($source, $target);
      }
    }
  }

  public function removeSite(string $serverName, string $siteName): void {
    $dirs = $this->ensureServerLayout($serverName);
    $filename = $this->sanitizeFileName($siteName) . '.conf';

    $targets = [
      $dirs['vhost'] . '/' . $filename,
      $dirs['vhost_ssl'] . '/' . $filename,
      $dirs['disabled'] . '/' . $filename,
    ];

    foreach ($targets as $target) {
      $this->filesystem->remove($target);
    }
  }

  public function reload(?string $restartCmd): void {
    if ($restartCmd === NULL || trim($restartCmd) === '') {
      return;
    }

    $command = preg_split('/\s+/', trim($restartCmd));
    if (!$command) {
      return;
    }
    $this->runner->run($command);
  }

  private function sanitizeFileName(string $name): string {
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9._-]+/', '_', $name);
    return $name === '' ? 'site' : $name;
  }
}
