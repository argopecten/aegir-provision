<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision restore commands.
 */
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
  #[Command(name: 'provision:restore', aliases: ['prestore'])]
  #[Argument(name: 'context', description: 'Site context name')]
  #[Argument(name: 'backup-file', description: 'Backup file to restore')]
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
