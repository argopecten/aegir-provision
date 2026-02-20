<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision backup commands.
 */
#[CLI\Bootstrap(level: 0)]
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
  #[CLI\Command(name: 'provision:backup', aliases: ['pbackup'])]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  #[CLI\Argument(name: 'backupFile', description: 'Optional backup file path')]
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
