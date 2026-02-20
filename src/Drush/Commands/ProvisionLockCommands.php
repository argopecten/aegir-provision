<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision lock commands.
 */
#[CLI\Bootstrap(level: 0)]
final class ProvisionLockCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Lock a site or context.
   */
  #[CLI\Command(name: 'provision:lock', aliases: ['plock'])]
  #[CLI\Argument(name: 'context', description: 'Context name')]
  public function lock(string $context): int {
    try {
      $this->manager->lock($context);
      $this->logger()->success("Context locked: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
