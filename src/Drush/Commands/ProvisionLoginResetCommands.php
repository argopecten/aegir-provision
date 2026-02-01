<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision login reset commands.
 */
final class ProvisionLoginResetCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Reset admin login for a site.
   */
  #[Command(name: 'provision:login-reset', aliases: ['plogin-reset'])]
  #[Argument(name: 'context', description: 'Site context name')]
  public function loginReset(string $context): int {
    try {
      $this->manager->loginReset($context);
      $this->logger()->success("Admin login reset: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
