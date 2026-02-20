<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision login reset commands.
 */
#[CLI\Bootstrap(level: 0)]
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
  #[CLI\Command(name: 'provision:login-reset', aliases: ['plogin-reset'])]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
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
