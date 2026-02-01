<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision deploy commands.
 */
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
  #[Command(name: 'provision:deploy', aliases: ['pdeploy'])]
  #[Argument(name: 'context', description: 'Site context name')]
  #[Argument(name: 'backup-file', description: 'Backup file to deploy')]
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
