<?php

/**
 * @file
 * Example custom Nginx HTTP service implementation.
 *
 * This demonstrates how to create a custom service plugin for Aegir Provision.
 * To use this service:
 *
 * 1. Register it in your Drush service provider:
 *
 * ```php
 * use Aegir\Provision\Service\ServiceRegistry;
 *
 * public static function register(ContainerInterface $container): void {
 *   $registry = $container->get(ServiceRegistry::class);
 *   $nginx = new NginxService(...);
 *   $registry->register('http', 'nginx', $nginx);
 *   $registry->setDefault('http', 'nginx'); // Make it default
 * }
 * ```
 *
 * 2. Or specify it per-server context:
 *
 * ```bash
 * drush provision:save @server_master http_service_type=nginx
 * ```
 */

declare(strict_types=1);

namespace Aegir\Provision\Examples;

use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Core\ConfigPaths;
use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Service\HttpServiceInterface;
use Aegir\Provision\Service\Ssl\SslManager;

/**
 * Nginx HTTP service implementation.
 *
 * Provides vhost management using Nginx instead of Apache.
 */
final class NginxService implements HttpServiceInterface
{
    private ConfigPaths $paths;
    private Filesystem $filesystem;
    private TemplateRenderer $templates;
    private ProcessRunner $runner;
    private SslManager $sslManager;

    public function __construct(
        ConfigPaths $paths,
        Filesystem $filesystem,
        TemplateRenderer $templates,
        ProcessRunner $runner,
        SslManager $sslManager
    ) {
        $this->paths = $paths;
        $this->filesystem = $filesystem;
        $this->templates = $templates;
        $this->runner = $runner;
        $this->sslManager = $sslManager;
    }

    /**
     * {@inheritdoc}
     */
    public function ensureServerLayout(string $serverName): array
    {
        $base = $this->paths->serverConfigPath($serverName) . '/nginx';
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
     * {@inheritdoc}
     */
    public function enableSite(Context $site, Context $platform, Context $server, array $options = []): array
    {
        $dirs = $this->ensureServerLayout($server->name());
        $filename = $this->sanitizeFileName($site->name()) . '.conf';

        $docroot = $options['docroot'] ?? $platform->get('root');
        $sitePath = $options['site_path'] ?? $docroot . '/sites/' . $site->get('uri');

        $httpPort = (int) ($options['http_port'] ?? $server->get('http_port', 80));
        $sslPort = (int) ($options['http_ssl_port'] ?? $server->get('http_ssl_port', 443));

        $sslEnabled = (bool) ($site->get('ssl_enabled', false) || $site->get('ssl', false));
        $sslRedirect = (bool) ($site->get('ssl_redirect', false));

        $vars = [
            'server_name' => $site->get('uri'),
            'server_aliases' => (array) ($site->get('aliases', []) ?: []),
            'docroot' => $docroot,
            'site_path' => $sitePath,
            'http_port' => $httpPort,
            'ssl_port' => $sslPort,
            'ssl_redirect' => $sslRedirect,
            'php_fpm_socket' => $server->get('php_fpm_socket', '/var/run/php/php-fpm.sock'),
            'extra_config' => (string) ($site->get('http_extra_config', '') ?: $site->get('nginx_extra_config', '')),
        ];

        // HTTP vhost
        $vhost = $this->renderNginxVhost($vars);
        $vhostPath = $dirs['vhost'] . '/' . $filename;
        $this->filesystem->writeFile($vhostPath, $vhost, 0644);

        $result = ['vhost' => $vhostPath];

        // SSL vhost if enabled
        if ($sslEnabled) {
            $ssl = $this->sslManager->resolve($server->name(), (string) $site->get('uri'), $site->all());
            $vars['ssl_cert'] = $ssl['cert'];
            $vars['ssl_key'] = $ssl['key'];
            $vars['ssl_chain'] = $ssl['chain'];

            $sslVhost = $this->renderNginxSslVhost($vars);
            $sslVhostPath = $dirs['vhost_ssl'] . '/' . $filename;
            $this->filesystem->writeFile($sslVhostPath, $sslVhost, 0644);
            $result['vhost_ssl'] = $sslVhostPath;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function disableSite(string $serverName, string $siteName): void
    {
        $dirs = $this->ensureServerLayout($serverName);
        $filename = $this->sanitizeFileName($siteName) . '.conf';

        $vhostPath = $dirs['vhost'] . '/' . $filename;
        $sslVhostPath = $dirs['vhost_ssl'] . '/' . $filename;
        $disabledPath = $dirs['disabled'] . '/' . $filename;

        if (is_file($vhostPath)) {
            rename($vhostPath, $disabledPath);
        }
        if (is_file($sslVhostPath)) {
            $this->filesystem->remove($sslVhostPath);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeSite(string $serverName, string $siteName): void
    {
        $dirs = $this->ensureServerLayout($serverName);
        $filename = $this->sanitizeFileName($siteName) . '.conf';

        $paths = [
            $dirs['vhost'] . '/' . $filename,
            $dirs['vhost_ssl'] . '/' . $filename,
            $dirs['disabled'] . '/' . $filename,
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $this->filesystem->remove($path);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reload(?string $restartCmd): void
    {
        $cmd = $restartCmd ?: 'sudo nginx -s reload';
        $result = $this->runner->run(explode(' ', $cmd));

        if ($result['exit_code'] !== 0) {
            throw new \RuntimeException('Nginx reload failed: ' . $result['error']);
        }
    }

    /**
     * Sanitize a site name for use in filenames.
     */
    private function sanitizeFileName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    }

    /**
     * Render Nginx HTTP vhost configuration.
     */
    private function renderNginxVhost(array $vars): string
    {
        // Basic Nginx vhost template
        $aliases = !empty($vars['server_aliases']) ? ' ' . implode(' ', $vars['server_aliases']) : '';
        
        return <<<NGINX
server {
    listen {$vars['http_port']};
    server_name {$vars['server_name']}{$aliases};

    root {$vars['docroot']};
    index index.php index.html;

    # Drupal-specific configuration
    location = /favicon.ico {
        log_not_found off;
        access_log off;
    }

    location = /robots.txt {
        allow all;
        log_not_found off;
        access_log off;
    }

    location ~ \\..*/.*\\.php\$ {
        return 403;
    }

    location ~ ^/sites/.*/private/ {
        return 403;
    }

    location ~ ^/sites/[^/]+/files/.*\\.php\$ {
        deny all;
    }

    location ~* ^/.well-known/ {
        allow all;
    }

    location ~ (^|/)\\.  {
        return 403;
    }

    location / {
        try_files \$uri /index.php?\$query_string;
    }

    location @rewrite {
        rewrite ^ /index.php;
    }

    location ~ '\\.php\$|^/update.php' {
        fastcgi_split_path_info ^(.+?\\.php)(|/.*)\$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_param QUERY_STRING \$query_string;
        fastcgi_intercept_errors on;
        fastcgi_pass unix:{$vars['php_fpm_socket']};
    }

    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg)\$ {
        try_files \$uri @rewrite;
        expires max;
        log_not_found off;
    }

    {$vars['extra_config']}
}
NGINX;
    }

    /**
     * Render Nginx SSL vhost configuration.
     */
    private function renderNginxSslVhost(array $vars): string
    {
        $aliases = !empty($vars['server_aliases']) ? ' ' . implode(' ', $vars['server_aliases']) : '';
        $chain = isset($vars['ssl_chain']) ? "\n    ssl_trusted_certificate {$vars['ssl_chain']};" : '';
        
        return <<<NGINX
server {
    listen {$vars['ssl_port']} ssl http2;
    server_name {$vars['server_name']}{$aliases};

    root {$vars['docroot']};
    index index.php index.html;

    ssl_certificate {$vars['ssl_cert']};
    ssl_certificate_key {$vars['ssl_key']};{$chain}

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Same Drupal config as HTTP vhost
    location = /favicon.ico {
        log_not_found off;
        access_log off;
    }

    location = /robots.txt {
        allow all;
        log_not_found off;
        access_log off;
    }

    location ~ \\..*/.*\\.php\$ {
        return 403;
    }

    location ~ ^/sites/.*/private/ {
        return 403;
    }

    location ~ ^/sites/[^/]+/files/.*\\.php\$ {
        deny all;
    }

    location ~* ^/.well-known/ {
        allow all;
    }

    location ~ (^|/)\\.  {
        return 403;
    }

    location / {
        try_files \$uri /index.php?\$query_string;
    }

    location @rewrite {
        rewrite ^ /index.php;
    }

    location ~ '\\.php\$|^/update.php' {
        fastcgi_split_path_info ^(.+?\\.php)(|/.*)\$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_param QUERY_STRING \$query_string;
        fastcgi_param HTTPS on;
        fastcgi_intercept_errors on;
        fastcgi_pass unix:{$vars['php_fpm_socket']};
    }

    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg)\$ {
        try_files \$uri @rewrite;
        expires max;
        log_not_found off;
    }

    {$vars['extra_config']}
}
NGINX;
    }
}
