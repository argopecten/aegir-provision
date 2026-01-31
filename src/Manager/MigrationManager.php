<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Core\ContextRepository;
use Aegir\Provision\Core\ContextType;
use Aegir\Provision\Event\MigrateEvent;
use Aegir\Provision\Event\ProvisionEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages migration (move and upgrade) operations.
 *
 * Note: Currently only supports site migration between platforms.
 */
final class MigrationManager {
  private ContextRepository $contexts;
  private LoggerInterface $logger;
  private EventDispatcherInterface $dispatcher;
  private VerificationManager $verifyManager;
  private InstallationManager $installManager;
  private ContextLoader $loader;

  public function __construct(
    ContextRepository $contexts,
    LoggerInterface $logger,
    EventDispatcherInterface $dispatcher,
    VerificationManager $verifyManager,
    InstallationManager $installManager,
    ContextLoader $loader
  ) {
    $this->contexts = $contexts;
    $this->logger = $logger;
    $this->dispatcher = $dispatcher;
    $this->verifyManager = $verifyManager;
    $this->installManager = $installManager;
    $this->loader = $loader;
  }

  public function migrate(string $contextName, string $platformAlias): void {
    $site = $this->contexts->load($contextName);
    if ($site->type() !== ContextType::SITE) {
      throw new \RuntimeException('Migrate only supports site contexts.');
    }

    $oldPlatform = $this->loader->loadPlatform($site);
    $platform = $this->contexts->load($platformAlias);
    if ($platform->type() !== ContextType::PLATFORM) {
      throw new \RuntimeException('Target platform must be a platform context.');
    }

    try {
      $server = $this->loader->loadServer($platform, $site);

      // Dispatch VALIDATE event
      $event = new MigrateEvent('validate', $site, $oldPlatform, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_MIGRATE);

      // Dispatch BEFORE event
      $event = new MigrateEvent('before', $site, $oldPlatform, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_MIGRATE);

      $site->set('platform', $platform->name());
      $this->contexts->save($site);
      $this->verifyManager->verify($site->name());
      $this->installManager->enable($site->name());
      $this->logger->info('Migrated site {context} to platform {platform}.', ['context' => $site->name(), 'platform' => $platform->name()]);

      // Dispatch AFTER event
      $event = new MigrateEvent('after', $site, $oldPlatform, $platform, $server);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_MIGRATE);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event
      $event = new MigrateEvent('rollback', $site, $oldPlatform ?? $platform, $platform, $server ?? $oldPlatform, ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_MIGRATE);
      throw $e;
    }
  }
}
