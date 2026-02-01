<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision import commands.
 */
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
  #[Command(name: 'provision:import', aliases: ['pimport'])]
  #[Argument(name: 'context', description: 'Context name')]
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
