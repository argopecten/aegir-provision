<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Provision delete commands.
 */
#[CLI\Bootstrap(level: 0)]
final class ProvisionDeleteCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  /**
   * Delete a context.
   */
  #[CLI\Command(name: 'provision:delete', aliases: ['pdelete'])]
  #[CLI\Argument(name: 'context', description: 'Context name')]
  #[CLI\Option(name: 'delete-files', description: 'Remove site files on disk')]
  #[CLI\Option(name: 'delete-db', description: 'Drop site database and db user')]
  public function delete(
    string $context,
    array $options = ['delete-files' => false, 'delete-db' => false]
  ): int {
    try {
      $this->manager->delete(
        $context,
        (bool) $options['delete-files'],
        (bool) $options['delete-db']
      );
      $this->logger()->success("Context deleted: $context");
      return self::EXIT_SUCCESS;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }
}
