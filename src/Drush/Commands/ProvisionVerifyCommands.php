<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision verify commands.
 */
final class ProvisionVerifyCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Verify server, platform, or site context.
   */
  #[Command(name: 'provision:verify', aliases: ['pv', 'verify'])]
  #[Argument(name: 'context', description: 'Context name, e.g. @example.com')]
  public function verify(string $context): int {
    try {
      $this->manager->verify($context);
      $this->logger()->success("Context verified: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
