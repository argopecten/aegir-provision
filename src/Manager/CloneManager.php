<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\ContextType;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Event\CloneEvent;
use Aegir\Provision\Event\ProvisionEvents;
use Aegir\Provision\Service\Db\MySqlService;
use Aegir\Provision\Service\DbServiceInterface;
use Aegir\Provision\Service\Drupal\SettingsWriter;
use Aegir\Provision\Service\ServiceRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages cloning (copying) operations.
 *
 * Note: Currently only supports cloning sites. Platform cloning could be added in the future.
 */
final class CloneManager {
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

  public function cloneSite(string $contextName, string $newSite, string $platformAlias, InstallationManager $installManager): void {
    $source = $this->contexts->load($contextName);
    if ($source->type() !== ContextType::SITE) {
      throw new \RuntimeException('Clone only supports site contexts.');
    }

    $platform = $platformAlias ? $this->contexts->load($platformAlias) : $this->loader->loadPlatform($source);
    if ($platform->type() !== ContextType::PLATFORM) {
      throw new \RuntimeException('Target platform must be a platform context.');
    }

    $copy = new Context($newSite, ContextType::SITE, $source->all());
    $copy->set('uri', $newSite);
    $copy->set('platform', $platform->name());
    $copy->set('aliases', []);
    $copy->set('db_name', NULL);
    $copy->set('db_user', NULL);
    $copy->set('db_passwd', NULL);

    try {
      $server = $this->loader->loadServer($platform, $copy);

      // Dispatch VALIDATE event
      $event = new CloneEvent('validate', $source, $copy, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_CLONE);

      // Dispatch BEFORE event
      $event = new CloneEvent('before', $source, $copy, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_CLONE);

      $dbServer = $this->loader->loadDbServer($copy, $server);
    $db = $this->dbManager->ensureSiteDatabase($copy, $dbServer);
    $this->contexts->save($copy);

    /** @var DbServiceInterface $mysql */
    $mysql = $this->serviceRegistry->get('db', 'mysql');
    $sourceDb = (string) $source->get('db_name');
    $sourceDbServer = $this->loader->loadDbServer($source, $server);
    if ($sourceDb !== '') {
      $tmp = sys_get_temp_dir() . '/aegir-clone-' . uniqid('', TRUE) . '.sql';
      $mysql->dump($sourceDbServer, $sourceDb, $tmp, FALSE);
      $mysql->import($dbServer, $db->name, $tmp);
      @unlink($tmp);
    }

    $docroot = $this->pathResolver->resolveDocroot($platform);
    $sourcePath = $this->pathResolver->resolveSitePath($source, $docroot);
    $targetPath = $this->pathResolver->resolveSitePath($copy, $docroot);
    $this->copyFiles($sourcePath . '/files', $targetPath . '/files');

    $settingsWriter = new SettingsWriter($this->filesystem, $this->templates);
    $settingsWriter->write($docroot, $targetPath, $db, [
      'trusted_host' => $copy->get('uri'),
    ]);

    $installManager->enable($copy->name());
    $this->logger->info('Cloned site {source} to {target}.', ['source' => $source->name(), 'target' => $copy->name()]);

      // Dispatch AFTER event
      $event = new CloneEvent('after', $source, $copy, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_CLONE);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new CloneEvent('rollback', $source, $copy ?? $source, $platform, $server ?? $platform, ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_CLONE);
      throw $e;
    }
  }

  private function copyFiles(string $source, string $destination): void {
    if (!is_dir($source)) {
      return;
    }
    $this->filesystem->ensureDir($destination, 0775);
    $items = scandir($source);
    if (!is_array($items)) {
      return;
    }
    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }
      $src = $source . '/' . $item;
      $dst = $destination . '/' . $item;
      if (is_dir($src) && !is_link($src)) {
        $this->copyFiles($src, $dst);
      }
      else {
        copy($src, $dst);
      }
    }
  }
}
