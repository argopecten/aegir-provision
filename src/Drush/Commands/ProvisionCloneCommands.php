<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision clone commands.
 */
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
  #[Command(name: 'provision:clone', aliases: ['pclone'])]
  #[Argument(name: 'context', description: 'Source site context name')]
  #[Argument(name: 'new-site', description: 'New site alias')]
  #[Argument(name: 'platform', description: 'Target platform alias')]
  public function cloneSite(string $context, string $newSite, ?string $platform = null): int {
    try {
      $this->manager->cloneSite($context, $newSite, $platform ?? '');
      $this->logger()->success("Site cloned: $context to $newSite");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
