<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'provision-delete',
    description: 'Delete a context'
)]
final class ProvisionDeleteCommand extends Command {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addArgument('context', InputArgument::REQUIRED, 'Context name');
    $this->addOption('delete-files', null, InputOption::VALUE_NONE, 'Remove site files on disk');
    $this->addOption('delete-db', null, InputOption::VALUE_NONE, 'Drop site database and db user');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->manager->delete(
      (string) $input->getArgument('context'),
      (bool) $input->getOption('delete-files'),
      (bool) $input->getOption('delete-db')
    );
    return Command::SUCCESS;
  }
}
