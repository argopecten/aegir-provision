<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Backend parse commands.
 */
#[CLI\Bootstrap(level: 0)]
final class BackendParseCommands extends DrushCommands {
  use ProvisionAutowireTrait;

  public function __construct() {
    parent::__construct();
  }

  /**
   * Parse backend command output.
   */
  #[CLI\Command(name: 'backend:parse')]
  public function parse(): int {
    $data = stream_get_contents(STDIN);
    if ($data !== false && trim($data) !== '') {
      $this->io()->writeln($data);
    }
    return self::EXIT_SUCCESS;
  }
}
