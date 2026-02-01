<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Provision install commands.
 */
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
  #[Command(name: 'provision:install', aliases: ['pinstall'])]
  #[Argument(name: 'context', description: 'Site context name')]
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
