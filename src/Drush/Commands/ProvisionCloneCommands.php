<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision clone commands.
 */
#[CLI\Bootstrap(level: 0)]
final class ProvisionCloneCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Clone a site to a new context.
   */
  #[CLI\Command(name: 'provision:clone', aliases: ['pclone'])]
  #[CLI\Argument(name: 'context', description: 'Source site context name')]
  #[CLI\Argument(name: 'newSite', description: 'New site alias')]
  #[CLI\Option(name: 'platform', description: 'Target platform alias')]
  public function cloneSite(string $context, string $newSite, array $options = ['platform' => null]): int {
    try {
      $this->manager->cloneSite($context, $newSite, $options['platform'] ?? '');
      $this->logger()->success("Site cloned: $context to $newSite");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
