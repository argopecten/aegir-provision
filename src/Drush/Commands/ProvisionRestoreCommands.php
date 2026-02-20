<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision restore commands.
 */
#[CLI\Bootstrap(level: 0)]
final class ProvisionRestoreCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Restore a site from backup.
   */
  #[CLI\Command(name: 'provision:restore', aliases: ['prestore'])]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  #[CLI\Argument(name: 'backupFile', description: 'Backup file to restore')]
  public function restore(string $context, string $backupFile): int {
    try {
      $this->manager->restore($context, $backupFile);
      $this->logger()->success("Site restored: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
