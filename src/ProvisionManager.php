<?php

declare(strict_types=1);

namespace Aegir\Provision;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\Filesystem;
use Aegir\Provision\Core\ProcessRunner;
use Aegir\Provision\Config\TemplateRenderer;
use Aegir\Provision\Manager\BackupRestoreManager;
use Aegir\Provision\Manager\CloneManager;
use Aegir\Provision\Manager\ContextLoader;
use Aegir\Provision\Manager\CronManager;
use Aegir\Provision\Manager\DatabaseManager;
use Aegir\Provision\Manager\DeleteManager;
use Aegir\Provision\Manager\InstallationManager;
use Aegir\Provision\Manager\LockManager;
use Aegir\Provision\Manager\MigrationManager;
use Aegir\Provision\Manager\PathResolver;
use Aegir\Provision\Manager\VerificationManager;
use Aegir\Provision\Service\ServiceRegistry;
use Aegir\Provision\Core\ValueObject\CronJobConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Main facade for Provision operations.
 *
 * Coordinates specialized managers for different operation types.
 */
final class ProvisionManager {
  private ContextRepository $contexts;
  private LoggerInterface $logger;
  private ServiceRegistry $serviceRegistry;
  private InstallationManager $installationManager;
  private VerificationManager $verificationManager;
  private DeleteManager $deleteManager;
  private BackupRestoreManager $backupRestoreManager;
  private CloneManager $cloneManager;
  private MigrationManager $migrationManager;
  private LockManager $lockManager;
  private CronManager $cronManager;

  public function __construct(
    ContextRepository $contexts,
    Filesystem $filesystem,
    ProcessRunner $runner,
    TemplateRenderer $templates,
    LoggerInterface $logger,
    EventDispatcherInterface $dispatcher,
    ServiceRegistry $serviceRegistry
  ) {
    $this->contexts = $contexts;
    $this->logger = $logger;
    $this->serviceRegistry = $serviceRegistry;

    // Initialize shared utilities
    $loader = new ContextLoader($contexts);
    $pathResolver = new PathResolver();
    $dbManager = new DatabaseManager($runner, $serviceRegistry);

    // Initialize specialized managers
    $this->installationManager = new InstallationManager(
      $contexts, $filesystem, $runner, $templates, $logger, $dispatcher,
      $loader, $pathResolver, $dbManager, $serviceRegistry
    );
    $this->verificationManager = new VerificationManager(
      $contexts, $filesystem, $runner, $templates, $logger, $dispatcher,
      $loader, $pathResolver, $dbManager, $serviceRegistry
    );
    $this->deleteManager = new DeleteManager(
      $contexts, $filesystem, $runner, $templates, $logger, $dispatcher,
      $loader, $pathResolver, $dbManager, $serviceRegistry
    );
    $this->backupRestoreManager = new BackupRestoreManager(
      $contexts, $filesystem, $runner, $logger, $dispatcher,
      $loader, $pathResolver, $dbManager, $serviceRegistry
    );
    $this->cloneManager = new CloneManager(
      $contexts, $filesystem, $runner, $templates, $logger, $dispatcher,
      $loader, $pathResolver, $dbManager, $serviceRegistry
    );
    $this->migrationManager = new MigrationManager(
      $contexts, $logger, $dispatcher, $this->verificationManager, $this->installationManager, $loader
    );
    $this->lockManager = new LockManager(
      $contexts, $filesystem, $logger, $dispatcher, $loader, $pathResolver
    );
    $this->cronManager = new CronManager(
      $logger, $dispatcher, $serviceRegistry
    );
  }

  /**
   * @param array<string,mixed> $data
   */
  public function saveContext(string $contextName, array $data, ?string $type = NULL, bool $delete = FALSE): void {
    if ($delete) {
      $this->contexts->delete($contextName);
      $this->logger->info('Deleted context {context}.', ['context' => $contextName]);
      return;
    }

    $contextName = ltrim($contextName, '@');
    $existing = NULL;
    try {
      $existing = $this->contexts->load($contextName);
    }
    catch (\RuntimeException) {
    }

    $contextType = $type ?: ($existing ? $existing->type() : $this->inferType($data));
    $context = new Context($contextName, $contextType, $existing ? $existing->all() : []);

    if (isset($data['provision']) && is_array($data['provision'])) {
      $data = array_merge($data['provision'], $data);
      unset($data['provision']);
    }

    foreach ($data as $key => $value) {
      $context->set($key, $value);
    }

    $this->contexts->save($context);
    $this->logger->info('Saved context {context} ({type}).', ['context' => $contextName, 'type' => $contextType]);
  }

  public function verify(string $contextName): void {
    $this->verificationManager->verify($contextName);
  }

  public function install(string $contextName): void {
    $this->installationManager->install($contextName);
  }

  public function enable(string $contextName): void {
    $this->installationManager->enable($contextName);
  }

  public function disable(string $contextName): void {
    $this->installationManager->disable($contextName);
  }

  public function delete(string $contextName, bool $deleteFiles, bool $deleteDb): void {
    $this->deleteManager->delete($contextName, $deleteFiles, $deleteDb);
  }

  public function backup(string $contextName, ?string $backupFile): string {
    return $this->backupRestoreManager->backup($contextName, $backupFile);
  }

  public function restore(string $contextName, string $backupFile): void {
    $this->backupRestoreManager->restore($contextName, $backupFile);
  }

  public function deploy(string $contextName, string $backupFile): void {
    $this->backupRestoreManager->deploy($contextName, $backupFile);
  }

  public function migrate(string $contextName, string $platformAlias): void {
    $this->migrationManager->migrate($contextName, $platformAlias);
  }

  public function cloneSite(string $contextName, string $newSite, string $platformAlias): void {
    $this->cloneManager->cloneSite($contextName, $newSite, $platformAlias, $this->installationManager);
  }

  public function importContext(string $contextName): void {
    $this->verificationManager->importContext($contextName, $this->installationManager);
  }

  public function lock(string $contextName): void {
    $this->lockManager->lock($contextName);
  }

  public function unlock(string $contextName): void {
    $this->lockManager->unlock($contextName);
  }

  public function loginReset(string $contextName): void {
    $this->installationManager->loginReset($contextName);
  }

  /**
   * Get the service registry for accessing pluggable services.
   */
  public function getServiceRegistry(): ServiceRegistry {
    return $this->serviceRegistry;
  }

  /**
   * Add or update a crontab entry.
   */
  public function addCron(CronJobConfig $config, ?string $serverContext = null): void {
    $server = null;
    if ($serverContext !== null) {
      try {
        $server = $this->contexts->load($serverContext);
      } catch (\RuntimeException) {
        // Server context not available — proceed without it.
      }
    }
    $this->cronManager->add($config, $server);
  }

  /**
   * Delete a crontab entry.
   */
  public function deleteCron(string $identifier, ?string $serverContext = null): void {
    $server = null;
    if ($serverContext !== null) {
      try {
        $server = $this->contexts->load($serverContext);
      } catch (\RuntimeException) {
        // Server context not available — proceed without it.
      }
    }
    $this->cronManager->delete($identifier, $server);
  }

  /**
   * Check whether a crontab entry exists.
   */
  public function cronStatus(string $identifier): bool {
    return $this->cronManager->status($identifier);
  }

  /**
   * List all Aegir-managed crontab entries.
   *
   * @return array<string, string>
   */
  public function cronList(): array {
    return $this->cronManager->list();
  }

  /**
   * @param array<string,mixed> $data
   */
  private function inferType(array $data): string {
    if (!empty($data['uri'])) {
      return 'site';
    }
    if (!empty($data['root'])) {
      return 'platform';
    }
    return 'server';
  }
}
