<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Aegir\Provision\ProvisionManager;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Commands\DrushCommands;

/**
 * Provision delete commands.
 */
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
  #[Command(name: 'provision:delete', aliases: ['pdelete'])]
  #[Argument(name: 'context', description: 'Context name')]
  #[Option(name: 'delete-files', description: 'Remove site files on disk', type: 'boolean')]
  #[Option(name: 'delete-db', description: 'Drop site database and db user', type: 'boolean')]
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
