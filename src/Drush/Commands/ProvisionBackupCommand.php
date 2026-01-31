<?php

declare(strict_types=1);

namespace Aegir\Provision\Drush\Commands;

use Aegir\Provision\ProvisionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'provision-backup',
    description: 'Create a site backup'
)]
final class ProvisionBackupCommand extends Command {
  use ProvisionAutowireTrait;

  public function __construct(
    private readonly ProvisionManager $manager
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addArgument('context', InputArgument::REQUIRED, 'Site context name');
    $this->addArgument('backup-file', InputArgument::OPTIONAL, 'Optional backup file path');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $file = $this->manager->backup(
      (string) $input->getArgument('context'),
      $input->getArgument('backup-file')
    );
    $output->writeln($file);
    return Command::SUCCESS;
  }
}
