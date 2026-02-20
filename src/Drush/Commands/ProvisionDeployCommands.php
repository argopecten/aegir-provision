<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision deploy commands.
 */
#[CLI\Bootstrap(level: 0)]
final class ProvisionDeployCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Deploy a backup to a site.
   */
  #[CLI\Command(name: 'provision:deploy', aliases: ['pdeploy'])]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  #[CLI\Argument(name: 'backupFile', description: 'Backup file to deploy')]
  public function deploy(string $context, string $backupFile): int {
    try {
      $this->manager->deploy($context, $backupFile);
      $this->logger()->success("Backup deployed: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
