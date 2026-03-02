<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\Core\ConfigPaths;
use Aegir\Provision\Core\ValueObject\CronJobConfig;
use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision cron management commands.
 */
#[CLI\Bootstrap(level: 0)]
final class ProvisionCronCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager,
  ) {
    parent::__construct();
  }

  /**
   * Add or update a crontab entry for Aegir dispatch.
   */
  #[CLI\Command(name: 'provision:cron-add')]
  #[CLI\Option(name: 'server', description: 'Server context name (default: server_master)')]
  #[CLI\Option(name: 'drupal-root', description: 'Absolute path to the Drupal installation')]
  #[CLI\Option(name: 'drush-path', description: 'Absolute path to the drush binary')]
  #[CLI\Option(name: 'command', description: 'Drush command to schedule (default: hosting:dispatch)')]
  #[CLI\Option(name: 'frequency', description: 'Interval in seconds between runs (default: 300)')]
  public function cronAdd(
    array $options = [
      'server' => '',
      'drupal-root' => '',
      'drush-path' => '',
      'command' => 'hosting:dispatch',
      'frequency' => '300',
    ]
  ): int {
    try {
      $projectRoot = ConfigPaths::projectRoot();
      $drupalRoot = $options['drupal-root'] !== '' ? $options['drupal-root'] : $projectRoot;
      $drushPath = $options['drush-path'] !== '' ? $options['drush-path'] : $projectRoot . '/vendor/bin/drush';

      $config = new CronJobConfig(
        identifier: $drupalRoot,
        drupalRoot: $drupalRoot,
        drushPath: $drushPath,
        command: $options['command'],
        frequency: (int) $options['frequency'],
      );

      $server = $options['server'] !== '' ? $options['server'] : null;
      $this->manager->addCron($config, $server);
      $this->logger()->success(sprintf(
        'Cron entry added: %s (every %ds)',
        $config->toCronExpression() . ' ' . $config->command,
        $config->frequency,
      ));
      return self::EXIT_SUCCESS;
    } catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Delete a crontab entry.
   */
  #[CLI\Command(name: 'provision:cron-delete')]
  #[CLI\Option(name: 'server', description: 'Server context name (default: server_master)')]
  #[CLI\Option(name: 'identifier', description: 'Cron entry identifier (typically the Drupal root path)')]
  public function cronDelete(
    array $options = [
      'server' => '',
      'identifier' => self::REQ,
    ]
  ): int {
    try {
      $server = $options['server'] !== '' ? $options['server'] : null;
      $this->manager->deleteCron($options['identifier'], $server);
      $this->logger()->success(sprintf('Cron entry deleted: %s', $options['identifier']));
      return self::EXIT_SUCCESS;
    } catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Show crontab entry status.
   */
  #[CLI\Command(name: 'provision:cron-status')]
  #[CLI\Option(name: 'identifier', description: 'Cron entry identifier (typically the Drupal root path). If omitted, lists all entries.')]
  public function cronStatus(
    array $options = [
      'identifier' => '',
    ]
  ): int {
    try {
      if (!empty($options['identifier'])) {
        $exists = $this->manager->cronStatus($options['identifier']);
        if ($exists) {
          $this->logger()->success(sprintf('Cron entry exists: %s', $options['identifier']));
        } else {
          $this->logger()->notice(sprintf('No cron entry found: %s', $options['identifier']));
        }
        return self::EXIT_SUCCESS;
      }

      // List all entries.
      $entries = $this->manager->cronList();
      if (empty($entries)) {
        $this->logger()->notice('No Aegir cron entries found.');
        return self::EXIT_SUCCESS;
      }

      foreach ($entries as $identifier => $line) {
        $this->io()->writeln(sprintf('  %s: %s', $identifier, $line));
      }
      return self::EXIT_SUCCESS;
    } catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
