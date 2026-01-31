<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\ContextType;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Event\DeleteEvent;
use Aegir\Provision\Event\ProvisionEvents;
use Aegir\Provision\Service\Db\MySqlService;
use Aegir\Provision\Service\DbServiceInterface;
use Aegir\Provision\Service\Http\ApacheService;
use Aegir\Provision\Service\HttpServiceInterface;
use Aegir\Provision\Service\ServiceRegistry;
use Aegir\Provision\Service\Ssl\SslManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages deletion operations.
 *
 * Supports all context types: sites, platforms, and servers.
 */
final class DeleteManager {
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

  public function delete(string $contextName, bool $deleteFiles, bool $deleteDb): void {
    $context = $this->contexts->load($contextName);

    // Dispatch VALIDATE event
    $event = new DeleteEvent('validate', $context, $deleteDb, $deleteFiles);
    $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_DELETE);

    // Dispatch BEFORE event
    $event = new DeleteEvent('before', $context, $deleteDb, $deleteFiles);
    $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_DELETE);

    if ($context->type() === ContextType::SITE) {
      $platform = $this->loader->loadPlatform($context);
      $server = $this->loader->loadServer($platform, $context);
      $paths = $this->pathResolver->buildConfigPaths($server);
      /** @var HttpServiceInterface $http */
      $http = $this->serviceRegistry->get('http', 'apache');
      $http->removeSite($server->name(), $context->name());

      if ($deleteFiles) {
        $docroot = $this->pathResolver->resolveDocroot($platform);
        $sitePath = $this->pathResolver->resolveSitePath($context, $docroot);
        $this->filesystem->remove($sitePath);
      }

      if ($deleteDb) {
        $dbServer = $this->loader->loadDbServer($context, $server);
        /** @var DbServiceInterface $mysql */
        $mysql = $this->serviceRegistry->get('db', 'mysql');
        $dbName = (string) $context->get('db_name');
        $dbUser = (string) $context->get('db_user');
        $dbHost = $this->dbManager->resolveDbGrantHost($dbServer);
        if ($dbName !== '') {
          $mysql->dropDatabase($dbServer, $dbName);
        }
        if ($dbUser !== '') {
          $mysql->dropUser($dbServer, $dbUser, $dbHost);
        }
      }
    }

    $this->contexts->delete($contextName);
    $this->logger->info('Deleted context {context}.', ['context' => $contextName]);

    // Dispatch AFTER event
    $event = new DeleteEvent('after', $context, $deleteDb, $deleteFiles);
    $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_DELETE);
  }
}
