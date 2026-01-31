<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\ContextType;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Event\ProvisionEvents;
use Aegir\Provision\Event\VerifyEvent;
use Aegir\Provision\Service\Db\MySqlService;
use Aegir\Provision\Service\DbServiceInterface;
use Aegir\Provision\Service\Drupal\SettingsWriter;
use Aegir\Provision\Service\Http\ApacheService;
use Aegir\Provision\Service\HttpServiceInterface;
use Aegir\Provision\Service\ServiceRegistry;
use Aegir\Provision\Service\Ssl\SslManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages verification operations.
 *
 * Supports all context types: sites, platforms, and servers.
 */
final class VerificationManager {
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

  public function verify(string $contextName): void {
    $context = $this->contexts->load($contextName);
    if ($context->type() === ContextType::SERVER) {
      $this->verifyServer($context);
      return;
    }
    if ($context->type() === ContextType::PLATFORM) {
      $this->verifyPlatform($context);
      return;
    }
    if ($context->type() === ContextType::SITE) {
      $this->verifySite($context);
      return;
    }
    throw new \RuntimeException('Unknown context type: ' . $context->type());
  }

  public function importContext(string $contextName, InstallationManager $installManager): void {
    $this->verify($contextName);
    $context = $this->contexts->load($contextName);
    if ($context->type() === ContextType::SITE) {
      $installManager->enable($contextName);
    }
    $this->logger->info('Imported context {context}.', ['context' => $contextName]);
  }

  private function verifyServer(Context $server): void {
    try {
      // Dispatch VALIDATE event
      $event = new VerifyEvent('validate', $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_VERIFY);

      // Dispatch BEFORE event
      $event = new VerifyEvent('before', $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_VERIFY);

      $paths = $this->pathResolver->resolveServerPaths($server);
      $paths->ensureDirectoriesExist(0750);

    $httpType = (string) $server->get('http_service_type', 'apache');
    if ($httpType !== 'apache') {
      throw new \RuntimeException('Unsupported http service type: ' . $httpType);
    }

    $pathsObj = $this->pathResolver->buildConfigPaths($server);
    /** @var HttpServiceInterface $http */
    $http = $this->serviceRegistry->get('http', 'apache');
    $http->ensureServerLayout($server->name());

    $dbType = (string) $server->get('db_service_type', 'mysql');
    if ($dbType !== 'mysql') {
      throw new \RuntimeException('Unsupported db service type: ' . $dbType);
    }

    /** @var DbServiceInterface $mysql */
    $mysql = $this->serviceRegistry->get('db', $dbType);
    $mysql->testConnection($server);

    $this->logger->info('Verified server {context}.', ['context' => $server->name()]);

      // Dispatch AFTER event
      $event = new VerifyEvent('after', $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_VERIFY);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new VerifyEvent('rollback', $server, null, null, ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_VERIFY);
      throw $e;
    }
  }

  private function verifyPlatform(Context $platform): void {
    try {
      $server = $this->loader->loadServer($platform);

      // Dispatch VALIDATE event
      $event = new VerifyEvent('validate', $platform, null, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_VERIFY);

      // Dispatch BEFORE event
      $event = new VerifyEvent('before', $platform, null, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_VERIFY);

      $docroot = $this->pathResolver->resolveDocroot($platform);
      if (!is_dir($docroot)) {
        throw new \RuntimeException('Platform docroot not found: ' . $docroot);
      }
    if (!is_file($docroot . '/index.php')) {
      throw new \RuntimeException('Platform index.php not found: ' . $docroot);
    }

    $version = $this->pathResolver->detectDrupalVersion($docroot);
    if ($version !== NULL) {
      $platform->set('drupal_version', $version);
      $platform->set('drupal_major', (int) explode('.', $version)[0]);
    }
    $platform->set('root', $docroot);
    $this->contexts->save($platform);

    $this->logger->info('Verified platform {context}.', ['context' => $platform->name()]);

      // Dispatch AFTER event
      $event = new VerifyEvent('after', $platform, null, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_VERIFY);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new VerifyEvent('rollback', $platform, null, $server ?? null, ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_VERIFY);
      throw $e;
    }
  }

  private function verifySite(Context $site): void {
    try {
      $platform = $this->loader->loadPlatform($site);
      $server = $this->loader->loadServer($platform, $site);

      // Dispatch VALIDATE event
      $event = new VerifyEvent('validate', $site, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_VERIFY);

      // Dispatch BEFORE event
      $event = new VerifyEvent('before', $site, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_VERIFY);

      $docroot = $this->pathResolver->resolveDocroot($platform);
      if (!$site->get('uri')) {
        $site->set('uri', $site->name());
      }
    $sitePath = $this->pathResolver->resolveSitePath($site, $docroot);
    $this->filesystem->ensureDir($sitePath, 0755);

    $dbServer = $this->loader->loadDbServer($site, $this->loader->loadServer($platform, $site));
    $this->dbManager->ensureSiteDatabase($site, $dbServer);
    $site->set('root', $docroot);
    $this->contexts->save($site);

    $this->logger->info('Verified site {context}.', ['context' => $site->name()]);

      // Dispatch AFTER event
      $event = new VerifyEvent('after', $site, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_VERIFY);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new VerifyEvent('rollback', $site, $platform ?? null, $server ?? null, ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_VERIFY);
      throw $e;
    }
  }
}
