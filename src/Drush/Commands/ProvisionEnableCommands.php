<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision enable commands.
 */
#[CLI\Bootstrap(level: 0)]
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
  #[CLI\Command(name: 'provision:enable', aliases: ['penable'])]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
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
