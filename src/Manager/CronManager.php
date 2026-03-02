<?php

declare(strict_types=1);

namespace Aegir\Provision\Manager;

use Aegir\Provision\Core\Context;
use Aegir\Provision\Core\ContextType;
use Aegir\Provision\Core\ValueObject\CronJobConfig;
use Aegir\Provision\Event\CronEvent;
use Aegir\Provision\Event\ProvisionEvents;
use Aegir\Provision\Service\CronServiceInterface;
use Aegir\Provision\Service\ServiceRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages cron operations (add, delete, status).
 *
 * Follows the same event lifecycle pattern as other managers:
 * VALIDATE → BEFORE → operation → reload → AFTER (or ROLLBACK on failure).
 */
final class CronManager {

  public function __construct(
    private readonly LoggerInterface $logger,
    private readonly EventDispatcherInterface $dispatcher,
    private readonly ServiceRegistry $serviceRegistry,
  ) {}

  /**
   * Add or update a crontab entry.
   *
   * @param CronJobConfig $config Cron job configuration
   * @param Context|null $server Optional server context for event listeners
   */
  public function add(CronJobConfig $config, ?Context $server = null): void {
    $server ??= $this->placeholderContext();
    try {
      // Dispatch VALIDATE event.
      $event = new CronEvent('validate', $server, $config);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_CRON_ADD);

      // Dispatch BEFORE event.
      $event = new CronEvent('before', $server, $config);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_CRON_ADD);

      /** @var CronServiceInterface $cron */
      $cron = $this->serviceRegistry->get('cron');
      $cron->addCron($config);
      $cron->reload();

      $this->logger->info('Added cron entry for {identifier} (every {frequency}s).', [
        'identifier' => $config->identifier,
        'frequency' => $config->frequency,
      ]);

      // Dispatch AFTER event.
      $event = new CronEvent('after', $server, $config);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_CRON_ADD);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event.
      $event = new CronEvent('rollback', $server, $config, ['exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_CRON_ADD);
      throw $e;
    }
  }

  /**
   * Delete a crontab entry.
   *
   * @param string $identifier Unique cron entry identifier
   * @param Context|null $server Optional server context for event listeners
   */
  public function delete(string $identifier, ?Context $server = null): void {
    $server ??= $this->placeholderContext();
    try {
      // Dispatch VALIDATE event.
      $event = new CronEvent('validate', $server, data: ['identifier' => $identifier]);
      $this->dispatcher->dispatch($event, ProvisionEvents::VALIDATE_CRON_DELETE);

      // Dispatch BEFORE event.
      $event = new CronEvent('before', $server, data: ['identifier' => $identifier]);
      $this->dispatcher->dispatch($event, ProvisionEvents::BEFORE_CRON_DELETE);

      /** @var CronServiceInterface $cron */
      $cron = $this->serviceRegistry->get('cron');
      $cron->deleteCron($identifier);
      $cron->reload();

      $this->logger->info('Deleted cron entry for {identifier}.', [
        'identifier' => $identifier,
      ]);

      // Dispatch AFTER event.
      $event = new CronEvent('after', $server, data: ['identifier' => $identifier]);
      $this->dispatcher->dispatch($event, ProvisionEvents::AFTER_CRON_DELETE);

    } catch (\Exception $e) {
      // Dispatch ROLLBACK event.
      $event = new CronEvent('rollback', $server, data: ['identifier' => $identifier, 'exception' => $e]);
      $this->dispatcher->dispatch($event, ProvisionEvents::ROLLBACK_CRON_DELETE);
      throw $e;
    }
  }

  /**
   * Check whether a crontab entry exists.
   *
   * @param string $identifier Unique cron entry identifier
   * @return bool True if the entry exists
   */
  public function status(string $identifier): bool {
    /** @var CronServiceInterface $cron */
    $cron = $this->serviceRegistry->get('cron');
    return $cron->hasCron($identifier);
  }

  /**
   * List all Aegir-managed crontab entries.
   *
   * @return array<string, string> Map of identifier => crontab line
   */
  public function list(): array {
    /** @var CronServiceInterface $cron */
    $cron = $this->serviceRegistry->get('cron');
    return $cron->listCron();
  }

  /**
   * Create a minimal placeholder context for event dispatching.
   */
  private function placeholderContext(): Context {
    return new Context('localhost', ContextType::SERVER, []);
  }
}
