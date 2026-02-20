<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision verify commands.
 */
#[CLI\Bootstrap(level: 0)]
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
  #[CLI\Command(name: 'provision:verify', aliases: ['pv', 'verify'])]
  #[CLI\Argument(name: 'context', description: 'Context name, e.g. @example.com')]
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
