<?php

declare(strict_types=1);

namespace Aegir\Provision\Service;

use Aegir\Provision\Core\ValueObject\CronJobConfig;

/**
 * Interface for cron service implementations.
 *
 * Manages system crontab entries for Aegir dispatch and other scheduled tasks.
 * Implementations may use system crontab, systemd timers, or other mechanisms.
 */
interface CronServiceInterface {

  /**
   * Add or update a crontab entry.
   *
   * If an entry with the same identifier already exists, it is replaced.
   *
   * @param CronJobConfig $config Cron job configuration
   */
  public function addCron(CronJobConfig $config): void;

  /**
   * Delete a crontab entry by identifier.
   *
   * No-op if the entry does not exist.
   *
   * @param string $identifier Unique cron entry identifier
   */
  public function deleteCron(string $identifier): void;

  /**
   * Check whether a crontab entry exists for the given identifier.
   *
   * @param string $identifier Unique cron entry identifier
   * @return bool True if the entry exists
   */
  public function hasCron(string $identifier): bool;

  /**
   * List all Aegir-managed crontab entries.
   *
   * @return array<string, string> Map of identifier => crontab line
   */
  public function listCron(): array;

  /**
   * Reload the cron service to apply configuration changes.
   *
   * For system crontab this re-writes the full crontab. For systemd timers
   * this would run `systemctl daemon-reload`.
   */
  public function reload(): void;
}
