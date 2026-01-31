<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\ContextType;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Core\ValueObject\ApacheVhostConfig;
use Aegir\Provision\Event\InstallEvent;
use Aegir\Provision\Event\ProvisionEvents;
use Aegir\Provision\Service\Drupal\SettingsWriter;
use Aegir\Provision\Service\Http\ApacheService;
use Aegir\Provision\Service\HttpServiceInterface;
use Aegir\Provision\Service\ServiceRegistry;
use Aegir\Provision\Service\Ssl\SslManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages installation operations.
 *
 * Supports all context types: sites, platforms, and servers.
 * Note: enable(), disable(), and loginReset() are site-specific operations.
 */
final class InstallationManager {
  private ContextRepository $contexts;
  private Filesystem $filesystem;
  private ProcessRunner $runner;
  private TemplateRenderer $templates;
  private LoggerInterface $logger;
  private EventDispatcherInterface $dispatcher;
  private ContextLoader $loader;
  private PathResolver $pathResolver;
  private DatabaseManager $dbManager;
  private ServiceRegistry $serviceRegistry;

  public function __construct(
    ContextRepository $contexts,
    Filesystem $filesystem,
    ProcessRunner $runner,
    TemplateRenderer $templates,
    LoggerInterface $logger,
    EventDispatcherInterface $dispatcher,
    ContextLoader $loader,
    PathResolver $pathResolver,
    DatabaseManager $dbManager,
    ServiceRegistry $serviceRegistry
  ) {
    $this->contexts = $contexts;
    $this->filesystem = $filesystem;
    $this->runner = $runner;
    $this->templates = $templates;
    $this->logger = $logger;
    $this->dispatcher = $dispatcher;
    $this->loader = $loader;
    $this->pathResolver = $pathResolver;
    $this->dbManager = $dbManager;
    $this->serviceRegistry = $serviceRegistry;
  }

  public function install(string $contextName): void {
    $context = $this->contexts->load($contextName);

    // Route to appropriate installation method based on context type
    if ($context->type() === ContextType::SITE) {
      $this->installSite($context);
      return;
    }
    if ($context->type() === ContextType::PLATFORM) {
      $this->installPlatform($context);
      return;
    }
    if ($context->type() === ContextType::SERVER) {
      $this->installServer($context);
      return;
    }
    throw new \RuntimeException('Unknown context type: ' . $context->type());
  }

  private function installSite(Context $site): void {
    $platform = $this->loader->loadPlatform($site);
    $server = $this->loader->loadServer($platform, $site);

    try {
      // Dispatch VALIDATE event
      $event = new InstallEvent('validate', $site, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_INSTALL);

      // Dispatch BEFORE event
      $event = new InstallEvent('before', $site, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_INSTALL);

      if (!$site->get('uri')) {
        $site->set('uri', $site->name());
      }
      $docroot = $this->pathResolver->resolveDocroot($platform);

      $dbServer = $this->loader->loadDbServer($site, $server);
      $db = $this->dbManager->ensureSiteDatabase($site, $dbServer);
      $site->set('root', $docroot);
      $this->contexts->save($site);

      $settingsWriter = new SettingsWriter($this->filesystem, $this->templates);
      $sitePath = $this->pathResolver->resolveSitePath($site, $docroot);
      $settingsWriter->write($docroot, $sitePath, $db, [
        'trusted_host' => $site->get('uri'),
      ]);

      $this->runDrushInstall($site, $docroot);
      $this->enable($site->name());
      $this->logger->info('Installed site {context}.', ['context' => $site->name()]);

      // Dispatch AFTER event
      $event = new InstallEvent('after', $site, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_INSTALL);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new InstallEvent('rollback', $site, $platform, $server, ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_INSTALL);
      throw $e;
    }
  }

  public function enable(string $contextName): void {
    $site = $this->contexts->load($contextName);
    if ($site->type() !== ContextType::SITE) {
      throw new \RuntimeException('Enable only supports site contexts.');
    }

    $platform = $this->loader->loadPlatform($site);
    $server = $this->loader->loadServer($platform, $site);

    // Dispatch VALIDATE event
    $event = new InstallEvent('validate', $site, $platform, $server);
    $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_ENABLE);

    // Dispatch BEFORE event
    $event = new InstallEvent('before', $site, $platform, $server);
    $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_ENABLE);

    $docroot = $this->pathResolver->resolveDocroot($platform);
    $sitePath = $this->pathResolver->resolveSitePath($site, $docroot);

    $paths = $this->pathResolver->buildConfigPaths($server);
    
    $sslEnabled = (bool) ($site->get('ssl_enabled', FALSE) || $site->get('ssl', FALSE));
    $extraConfig = (string) ($site->get('http_extra_config', '') ?: $site->get('apache_extra_config', ''));
    $customDirectives = $extraConfig ? ['custom' => $extraConfig] : [];
    
    $config = new ApacheVhostConfig(
      serverName: $site->get('uri'),
      documentRoot: $docroot,
      port: (int) $server->get('http_port', 80),
      serverAliases: (array) ($site->get('aliases', []) ?: []),
      sslCertPath: $sslEnabled ? null : null,
      sslKeyPath: $sslEnabled ? null : null,
      sslCaPath: $sslEnabled ? null : null,
      customDirectives: $customDirectives,
    );
    
    /** @var HttpServiceInterface $http */
    $http = $this->serviceRegistry->get('http', 'apache');
    $http->enableSite($site, $platform, $server, $config);
    $http->reload($server->get('http_restart_cmd'));

    $this->logger->info('Enabled site {context}.', ['context' => $site->name()]);

    // Dispatch AFTER event
    $event = new InstallEvent('after', $site, $platform, $server);
    $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_ENABLE);
  }

  public function disable(string $contextName): void {
    $site = $this->contexts->load($contextName);
    if ($site->type() !== ContextType::SITE) {
      throw new \RuntimeException('Disable only supports site contexts.');
    }

    $platform = $this->loader->loadPlatform($site);
    $server = $this->loader->loadServer($platform, $site);

    // Dispatch VALIDATE event
    $event = new InstallEvent('validate', $site, $platform, $server);
    $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_DISABLE);

    // Dispatch BEFORE event
    $event = new InstallEvent('before', $site, $platform, $server);
    $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_DISABLE);

    $paths = $this->pathResolver->buildConfigPaths($server);
    /** @var HttpServiceInterface $http */
    $http = $this->serviceRegistry->get('http', 'apache');
    $http->disableSite($server->name(), $site->name());
    $http->reload($server->get('http_restart_cmd'));
    $this->logger->info('Disabled site {context}.', ['context' => $site->name()]);

    // Dispatch AFTER event
    $event = new InstallEvent('after', $site, $platform, $server);
    $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_DISABLE);
  }

  public function loginReset(string $contextName): void {
    $site = $this->contexts->load($contextName);
    if ($site->type() !== ContextType::SITE) {
      throw new \RuntimeException('Login reset only supports site contexts.');
    }

    $platform = $this->loader->loadPlatform($site);
    $docroot = $this->pathResolver->resolveDocroot($platform);
    $password = bin2hex(random_bytes(8));
    $command = $this->drushBaseCommand($docroot, (string) $site->get('uri'));
    $command[] = 'user:password';
    $command[] = '1';
    $command[] = $password;
    $result = $this->runner->run($command);
    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException('Drush password reset failed: ' . $result['error']);
    }
    $this->logger->info('Reset uid 1 password for {context}: {password}', [
      'context' => $contextName,
      'password' => $password,
    ]);
  }

  private function runDrushInstall(Context $site, string $docroot): void {
    $installMethod = (string) $site->get('install_method', 'drush');
    if ($installMethod !== 'drush') {
      $this->logger->info('Skipping Drush install for {context} (install_method={method}).', [
        'context' => $site->name(),
        'method' => $installMethod,
      ]);
      return;
    }

    $profile = (string) $site->get('profile', 'standard');
    $uri = (string) $site->get('uri');
    $siteName = (string) $site->get('site_name', $uri);
    $accountName = (string) $site->get('account_name', 'admin');
    $accountPass = (string) $site->get('account_pass', bin2hex(random_bytes(8)));
    $accountMail = (string) $site->get('account_mail', ('admin@' . $uri));

    $command = $this->drushBaseCommand($docroot, $uri);
    $command[] = 'site:install';
    $command[] = $profile;
    $command[] = '--site-name=' . $siteName;
    $command[] = '--account-name=' . $accountName;
    $command[] = '--account-pass=' . $accountPass;
    $command[] = '--account-mail=' . $accountMail;

    $result = $this->runner->run($command);
    if ($result['exit_code'] !== 0) {
      throw new \RuntimeException('Drush site install failed: ' . $result['error']);
    }
  }

  /**
   * @return string[]
   */
  private function drushBaseCommand(string $docroot, string $uri): array {
    $drush = (string) (getenv('DRUSH_BIN') ?: 'drush');
    return [$drush, '-y', '-r', $docroot, '--uri=' . $uri];
  }

  private function installPlatform(Context $platform): void {
    $server = $this->loader->loadServer($platform);
    $docroot = $this->pathResolver->resolveDocroot($platform);

    // Verify the platform exists and is accessible
    if (!is_dir($docroot)) {
      throw new \RuntimeException('Platform docroot not found: ' . $docroot);
    }
    if (!is_file($docroot . '/index.php')) {
      throw new \RuntimeException('Platform index.php not found: ' . $docroot);
    }

    // Set up platform configuration
    $version = $this->pathResolver->detectDrupalVersion($docroot);
    if ($version !== NULL) {
      $platform->set('drupal_version', $version);
      $platform->set('drupal_major', (int) explode('.', $version)[0]);
    }
    $platform->set('root', $docroot);
    $this->contexts->save($platform);

    $this->logger->info('Installed platform {context}.', ['context' => $platform->name()]);
  }

  private function installServer(Context $server): void {
    // Create required directory structure
    $paths = $this->pathResolver->resolveServerPaths($server);
    $paths->ensureDirectoriesExist(0750);

    // Initialize HTTP service
    $httpType = (string) $server->get('http_service_type', 'apache');
    if ($httpType !== 'apache') {
      throw new \RuntimeException('Unsupported http service type: ' . $httpType);
    }

    $pathsObj = $this->pathResolver->buildConfigPaths($server);
    /** @var HttpServiceInterface $http */
    $http = $this->serviceRegistry->get('http', 'apache');
    $http->ensureServerLayout($server->name());

    // Test database connectivity
    $dbType = (string) $server->get('db_service_type', 'mysql');
    if ($dbType !== 'mysql') {
      throw new \RuntimeException('Unsupported db service type: ' . $dbType);
    }

    $this->contexts->save($server);
    $this->logger->info('Installed server {context}.', ['context' => $server->name()]);
  }
}
