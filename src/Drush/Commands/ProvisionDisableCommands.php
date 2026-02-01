<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision disable commands.
 */
final class ProvisionDisableCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Disable a site.
   */
  #[Command(name: 'provision:disable', aliases: ['pdisable'])]
  #[Argument(name: 'context', description: 'Site context name')]
  public function disable(string $context): int {
    try {
      $this->manager->disable($context);
      $this->logger()->success("Site disabled: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
