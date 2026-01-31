<?php

declare(strict_types=1);

namespace Aegir\Provision\Service\Http;

use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Core\ConfigPaths;
use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Core\ValueObject\ApacheVhostConfig;
use Aegir\Provision\Service\HttpServiceInterface;
use Aegir\Provision\Service\Ssl\SslManager;

final class ApacheService implements HttpServiceInterface {
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
  public function enableSite(Context $site, Context $platform, Context $server, ApacheVhostConfig $config): array {
    $dirs = $this->ensureServerLayout($server->name());
    $filename = $this->sanitizeFileName($site->name()) . '.conf';

    $sslRedirect = (bool) ($site->get('ssl_redirect', FALSE) || $site->get('ssl_redirection', FALSE));

    $vars = [
      'server_name' => $config->serverName,
      'server_aliases' => $config->serverAliases,
      'docroot' => $config->documentRoot,
      'site_path' => dirname($config->documentRoot) . '/sites/' . $config->serverName,
      'http_port' => $config->port,
      'ssl_redirect' => $sslRedirect,
      'canonical_host' => $site->get('redirection'),
      'extra_config' => implode("\n", array_values($config->customDirectives)),
    ];

    $vhost = $this->templates->render('apache/vhost.tpl.php', $vars);
    $vhostPath = $dirs['vhost'] . '/' . $filename;
    $this->filesystem->writeFile($vhostPath, $vhost, 0644);

    $sslPath = '';
    if ($config->isSslEnabled()) {
      $ssl = $this->sslManager->resolve($server->name(), $config->serverName, array_merge($server->all(), $site->all()));
      $sslVars = $vars + [
        'http_ssl_port' => $config->isSslEnabled() ? 443 : $config->port,
        'ssl_cert' => $config->sslCertPath ?? $ssl['cert'],
        'ssl_key' => $config->sslKeyPath ?? $ssl['key'],
        'ssl_chain' => $config->sslCaPath ?? $ssl['chain'],
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
