<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision migrate commands.
 */
final class ProvisionMigrateCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Migrate a site to a different platform.
   */
  #[Command(name: 'provision:migrate', aliases: ['pmigrate'])]
  #[Argument(name: 'context', description: 'Site context name')]
  #[Argument(name: 'platform', description: 'Target platform alias')]
  public function migrate(string $context, string $platform): int {
    try {
      $this->manager->migrate($context, $platform);
      $this->logger()->success("Site migrated: $context to $platform");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
