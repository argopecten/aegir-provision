<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision install commands.
 */
#[CLI\Bootstrap(level: 0)]
final class ProvisionInstallCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Install a Drupal site.
   */
  #[CLI\Command(name: 'provision:install', aliases: ['pinstall'])]
  #[CLI\Argument(name: 'context', description: 'Site context name')]
  public function install(string $context): int {
    try {
      $this->manager->install($context);
      $this->logger()->success("Site installed: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
