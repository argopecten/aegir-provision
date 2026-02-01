<?php

declare(strict_types=1);

namespace Drush\Commands\provision;

use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Backend parse commands.
 */
final class BackendParseCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct() {
    parent::__construct();
  }

  /**
   * Parse backend command output.
   */
  #[Command(name: 'backend:parse')]
  public function parse(): int {
    $data = stream_get_contents(STDIN);
    if ($data !== false && trim($data) !== '') {
      $this->io()->writeln($data);
    }
    return self::EXIT_SUCCESS;
  }
}
