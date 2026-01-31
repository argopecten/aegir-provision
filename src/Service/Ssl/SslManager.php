<?php

declare(strict_types=1);

namespace Aegir\Provision\Service\Ssl;

use Aegir\Provision\Core\ConfigPaths;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Service\SslServiceInterface;

final class SslManager implements SslServiceInterface {
  private ConfigPaths $paths;
  private Filesystem $filesystem;
  private ProcessRunner $runner;

  public function __construct(ConfigPaths $paths, Filesystem $filesystem, ProcessRunner $runner) {
    $this->paths = $paths;
    $this->filesystem = $filesystem;
    $this->runner = $runner;
  }

  /**
   * @return array{cert:string,key:string,chain:?string}
   */
  public function resolve(string $serverName, string $domain, array $options = []): array {
    $provider = $options['ssl_provider'] ?? 'self-signed';
    $sslKey = $options['ssl_key'] ?? $domain;

    if ($provider === 'letsencrypt') {
      $result = $this->resolveLetsEncrypt($domain);
      if ($result !== NULL) {
        return $result;
      }
      $provider = 'self-signed';
    }

    if ($provider === 'cloudflare') {
      $result = $this->resolveCloudflare($domain, $options);
      if ($result !== NULL) {
        return $result;
      }
      $provider = 'self-signed';
    }

    return $this->resolveSelfSigned($serverName, $sslKey, $domain);
  }

  private function resolveLetsEncrypt(string $domain): ?array {
    $base = '/etc/letsencrypt/live/' . $domain;
    $cert = $base . '/fullchain.pem';
    $key = $base . '/privkey.pem';
    $chain = $base . '/chain.pem';
    if (is_readable($cert) && is_readable($key)) {
      return [
        'cert' => $cert,
        'key' => $key,
        'chain' => is_readable($chain) ? $chain : NULL,
      ];
    }
    return NULL;
  }

  private function resolveCloudflare(string $domain, array $options): ?array {
    $cert = $options['ssl_cert_source'] ?? ('/etc/ssl/cloudflare/' . $domain . '.crt');
    $key = $options['ssl_key_source'] ?? ('/etc/ssl/cloudflare/' . $domain . '.key');
    $chain = $options['ssl_chain_source'] ?? NULL;
    if (is_readable($cert) && is_readable($key)) {
      return [
        'cert' => $cert,
        'key' => $key,
        'chain' => $chain && is_readable($chain) ? $chain : NULL,
      ];
    }
    return NULL;
  }

  private function resolveSelfSigned(string $serverName, string $sslKey, string $domain): array {
    $source = $this->paths->sslRoot() . '/' . $sslKey;
    $serverTarget = $this->paths->serverSslPath($serverName) . '/' . $sslKey;

    $this->filesystem->ensureDir($source, 0700);
    $this->filesystem->ensureDir($serverTarget, 0700);

    $keyPath = $source . '/openssl.key';
    $certPath = $source . '/openssl.crt';

    if (!is_file($keyPath) || !is_file($certPath)) {
      $this->generateSelfSigned($source, $domain);
    }

    $this->filesystem->symlink($keyPath, $serverTarget . '/openssl.key');
    $this->filesystem->symlink($certPath, $serverTarget . '/openssl.crt');

    return [
      'cert' => $serverTarget . '/openssl.crt',
      'key' => $serverTarget . '/openssl.key',
      'chain' => NULL,
    ];
  }

  private function generateSelfSigned(string $path, string $domain): void {
    $key = $path . '/openssl.key';
    $csr = $path . '/openssl.csr';
    $crt = $path . '/openssl.crt';
    $ident = '/CN=' . $domain . '/emailAddress=abuse@' . $domain;

    $this->runner->run(['openssl', 'genrsa', '-out', $key, '2048']);
    $this->runner->run(['openssl', 'req', '-new', '-subj', $ident, '-key', $key, '-out', $csr, '-batch']);
    $this->runner->run(['openssl', 'x509', '-req', '-days', '365', '-in', $csr, '-signkey', $key, '-out', $crt]);
  }
}
