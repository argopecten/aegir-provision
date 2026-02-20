<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision migrate commands.
 */
#[CLI\Bootstrap(level: 0)]
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
  #[CLI\Command(name: 'provision:migrate', aliases: ['pmigrate'])]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  #[CLI\Argument(name: 'platform', description: 'Target platform alias')]
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
