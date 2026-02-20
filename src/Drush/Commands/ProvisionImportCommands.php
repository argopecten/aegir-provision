<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision import commands.
 */
#[CLI\Bootstrap(level: 0)]
final class ProvisionImportCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Import an existing site into Aegir.
   */
  #[CLI\Command(name: 'provision:import', aliases: ['pimport'])]
  #[CLI\Argument(name: 'context', description: 'Context name')]
  public function importContext(string $context): int {
    try {
      $this->manager->importContext($context);
      $this->logger()->success("Context imported: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
