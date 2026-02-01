<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision enable commands.
 */
final class ProvisionEnableCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Enable a site.
   */
  #[Command(name: 'provision:enable', aliases: ['penable'])]
  #[Argument(name: 'context', description: 'Site context name')]
  public function enable(string $context): int {
    try {
      $this->manager->enable($context);
      $this->logger()->success("Site enabled: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
