<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'backend-parse',
    description: 'Parse backend command output'
)]
final class BackendParseCommand extends Command {
  use ProvisionAutowireTrait;

  public function __construct() {
    parent::__construct();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $data = stream_get_contents(STDIN);
    if ($data !== false && trim($data) !== '') {
      $output->writeln($data);
    }
    return Command::SUCCESS;
  }
}
