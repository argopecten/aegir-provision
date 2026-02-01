<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision backup commands.
 */
final class ProvisionBackupCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Create a site backup.
   */
  #[Command(name: 'provision:backup', aliases: ['pbackup'])]
  #[Argument(name: 'context', description: 'Site context name')]
  #[Argument(name: 'backup-file', description: 'Optional backup file path')]
  public function backup(string $context, ?string $backupFile = null): int {
    try {
      $file = $this->manager->backup($context, $backupFile);
      $this->logger()->success("Backup created: $file");
      $this->io()->writeln($file);
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
