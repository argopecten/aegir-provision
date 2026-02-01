<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision unlock commands.
 */
final class ProvisionUnlockCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Unlock a site or context.
   */
  #[Command(name: 'provision:unlock', aliases: ['punlock'])]
  #[Argument(name: 'context', description: 'Context name')]
  public function unlock(string $context): int {
    try {
      $this->manager->unlock($context);
      $this->logger()->success("Context unlocked: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
